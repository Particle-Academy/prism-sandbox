<?php

namespace App\Console\Commands;

use App\Models\Contributor;
use App\Support\ContributorResolver;
use App\Support\XpAwarder;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LaravelFunLab\Facades\LFL;
use RuntimeException;
use Throwable;

/**
 * Seeds and reconciles contribution XP from historical GitHub activity.
 *
 * The webhook only sees events from the moment it was registered; this command
 * replays everything that came before — and anything a webhook delivery dropped
 * — through the SAME award path ({@see XpAwarder} + {@see ContributorResolver}),
 * using the SAME metric names and dedup keys. Because awards are idempotent by
 * dedup key, it is safe to run repeatedly and safe to run alongside the live
 * webhook: no contribution is ever double-counted (given a persistent cache
 * store, which the dedup relies on across runs).
 */
class BackfillGamification extends Command
{
    protected $signature = 'gamification:backfill
        {repos?* : owner/repo names to process; defaults to GITHUB_REPOS, else all repos in GITHUB_ORG}
        {--token= : GitHub token; falls back to GITHUB_TOKEN / GH_TOKEN env}
        {--dry-run : Report what would be awarded without writing anything}';

    protected $description = 'Backfill/reconcile contribution XP from historical GitHub activity (idempotent).';

    /** Hard page cap per source so a runaway pagination can never loop forever. */
    private const MAX_PAGES = 50;

    private bool $dry = false;

    private int $awarded = 0;

    private int $skipped = 0;

    public function handle(ContributorResolver $resolver): int
    {
        $this->dry = (bool) $this->option('dry-run');
        // config() (not env()) so the token still resolves under config:cache.
        $token = (string) ($this->option('token') ?: config('services.github.token') ?: '');

        if ($token === '') {
            $this->warn('No GitHub token set — unauthenticated API is limited to 60 req/hr and will likely fail mid-run. Set GITHUB_TOKEN.');
        }

        $http = Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->when($token !== '', fn (PendingRequest $r) => $r->withToken($token));

        $repos = $this->resolveRepos($http, $token);

        if ($repos === []) {
            $this->error('No repositories to process. Pass owner/repo arguments, or set GITHUB_REPOS / GITHUB_ORG.');

            return self::FAILURE;
        }

        $this->info(($this->dry ? '[dry-run] ' : '').'Backfilling: '.implode(', ', $repos));

        foreach ($repos as $repo) {
            $this->line("· {$repo}");
            try {
                $this->backfillRepo($http, $resolver, $repo);
            } catch (Throwable $e) {
                // One bad repo (rate limit, 404, private) must not abort the rest.
                $this->error("  {$repo}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s%d contribution event(s); %d skipped/duplicate.',
            $this->dry ? '[dry-run] would award ' : 'awarded ',
            $this->awarded,
            $this->skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveRepos(PendingRequest $http, string $token): array
    {
        $args = array_values(array_filter(array_map('trim', (array) $this->argument('repos'))));
        if ($args !== []) {
            return $args;
        }

        /** @var list<string> $configured */
        $configured = (array) config('services.github.repos', []);
        if ($configured !== []) {
            return array_values($configured);
        }

        $org = (string) config('services.github.org', '');
        if ($org === '') {
            return [];
        }

        $repos = [];
        foreach ($this->paginate($http, "/orgs/{$org}/repos", ['type' => 'public']) as $repo) {
            $fullName = data_get($repo, 'full_name');
            if (is_string($fullName)) {
                $repos[] = $fullName;
            }
        }

        return $repos;
    }

    private function backfillRepo(PendingRequest $http, ContributorResolver $resolver, string $repo): void
    {
        if (! str_contains($repo, '/')) {
            $this->error("  {$repo}: expected owner/repo");

            return;
        }

        // Issues (opened) — the issues endpoint also returns PRs; skip those here.
        foreach ($this->paginate($http, "/repos/{$repo}/issues", ['state' => 'all']) as $issue) {
            if (data_get($issue, 'pull_request') !== null) {
                continue;
            }
            $this->award($resolver, data_get($issue, 'user'), 'issue-xp', 'issue', data_get($issue, 'node_id'), 'Opened issue '.data_get($issue, 'html_url'));
        }

        // Pull requests: opened (+ merged), plus each PR's reviews and comments.
        foreach ($this->paginate($http, "/repos/{$repo}/pulls", ['state' => 'all']) as $pr) {
            $user = data_get($pr, 'user');
            $node = data_get($pr, 'node_id');
            $url = data_get($pr, 'html_url');

            $this->award($resolver, $user, 'pr-submitted-xp', 'pr-opened', $node, 'Opened pull request '.$url);

            if (data_get($pr, 'merged_at') !== null) {
                $merged = $this->award($resolver, $user, 'pr-accepted-xp', 'pr-merged', $node, 'Merged pull request '.$url);
                if ($merged) {
                    $this->grantFirstPr($resolver, $user);
                }
            }

            $number = data_get($pr, 'number');
            if ($number === null) {
                continue;
            }

            foreach ($this->paginate($http, "/repos/{$repo}/pulls/{$number}/reviews") as $review) {
                $this->award($resolver, data_get($review, 'user'), 'pr-review-xp', 'review', data_get($review, 'id'), 'Reviewed a pull request '.data_get($review, 'html_url'));
            }

            // Conversation comments on the PR (issue-comment id space — matches the webhook's `comment:` key).
            foreach ($this->paginate($http, "/repos/{$repo}/issues/{$number}/comments") as $comment) {
                $this->award($resolver, data_get($comment, 'user'), 'pr-comment-xp', 'comment', data_get($comment, 'id'), 'Commented on a pull request '.data_get($comment, 'html_url'));
            }
        }

        // Inline review comments across the repo (review-comment id space).
        foreach ($this->paginate($http, "/repos/{$repo}/pulls/comments") as $comment) {
            $this->award($resolver, data_get($comment, 'user'), 'pr-comment-xp', 'review-comment', data_get($comment, 'id'), 'Commented on a pull request '.data_get($comment, 'html_url'));
        }

        // Stargazers (default response is user objects).
        foreach ($this->paginate($http, "/repos/{$repo}/stargazers") as $star) {
            $this->award($resolver, $star, 'star-xp', 'star', data_get($star, 'id'), 'Starred the repository');
        }

        $this->backfillCommits($http, $resolver, $repo);
    }

    /**
     * Commit authorship, scoped to commits that landed in THIS repository.
     *
     * On a fork that matters enormously: /repos/{repo}/contributors and
     * /repos/{repo}/commits both return the whole INHERITED history, so they
     * would award XP to every upstream author for work that predates the fork
     * — on prism that is 563 commits and 100+ people who never contributed
     * here. The compare endpoint returns only what this fork is ahead by, which
     * is exactly the set whose authors deserve credit.
     */
    private function backfillCommits(PendingRequest $http, ContributorResolver $resolver, string $repo): void
    {
        $parent = data_get($http->get("/repos/{$repo}")->json(), 'parent.full_name');

        if (is_string($parent) && $parent !== '') {
            [$parentOwner] = explode('/', $parent, 2);
            [$owner] = explode('/', $repo, 2);

            $base = data_get($http->get("/repos/{$repo}")->json(), 'default_branch', 'main');
            $response = $http->get("/repos/{$repo}/compare/{$parentOwner}:{$base}...{$owner}:{$base}");

            if (! $response->successful()) {
                $this->error("  {$repo}: could not compare against {$parent} — skipping commit XP");

                return;
            }

            $payload = $response->json();
            $commits = (array) data_get($payload, 'commits', []);
            $total = (int) data_get($payload, 'total_commits', count($commits));

            // The compare endpoint caps its commit list at 250. Say so rather
            // than silently crediting a prefix of the range.
            if ($total > count($commits)) {
                $this->warn(sprintf(
                    '  %s: %d commits ahead but the compare API returned %d — commit XP covers the returned set only.',
                    $repo, $total, count($commits),
                ));
            }
        } else {
            // Not a fork: every commit on the default branch is ours.
            $commits = iterator_to_array($this->paginate($http, "/repos/{$repo}/commits"), false);
        }

        foreach ($commits as $commit) {
            // `author` is the resolved GitHub ACCOUNT (matched by commit email);
            // `commit.author` is the raw git trailer and has no account to award.
            $this->award(
                $resolver,
                data_get($commit, 'author'),
                'commit-xp',
                'commit',
                data_get($commit, 'sha'),
                'Landed commit '.substr((string) data_get($commit, 'sha'), 0, 7).' '.data_get($commit, 'html_url'),
            );
        }
    }

    /**
     * Award one contribution, mirroring the webhook exactly. In dry-run it only
     * checks eligibility (no rows written); otherwise it delegates to the shared,
     * idempotent XpAwarder. A missing dedup subject (id/node_id) is skipped.
     */
    private function award(ContributorResolver $resolver, mixed $user, string $metric, string $keyPrefix, mixed $id, string $reason): bool
    {
        $id = is_scalar($id) ? (string) $id : '';
        if ($id === '') {
            $this->skipped++;

            return false;
        }

        if ($this->dry) {
            if ($resolver->isEligible($user)) {
                $this->awarded++;

                return true;
            }
            $this->skipped++;

            return false;
        }

        $ok = XpAwarder::award(
            $resolver->resolve($user),
            $metric,
            (int) config("gamification.metrics.$metric.xp", 0),
            $reason,
            "$keyPrefix:$id",
        );

        $ok ? $this->awarded++ : $this->skipped++;

        return $ok;
    }

    private function grantFirstPr(ContributorResolver $resolver, mixed $user): void
    {
        if ($this->dry) {
            return; // dry-run must not create/resolve any rows
        }

        $contributor = $resolver->resolve($user);

        if ($contributor instanceof Contributor && ! $contributor->hasAchievement('first-pr')) {
            try {
                LFL::grant('first-pr')->to($contributor)->because('First merged pull request')->save();
            } catch (Throwable) {
                // Best-effort, exactly like the webhook.
            }
        }
    }

    /**
     * Yield every item across a paginated GitHub list endpoint. Stops on the
     * first short page (or the hard cap). Never throws for 404 (treated as
     * "nothing here"); throws with a clear message on other API errors.
     *
     * @param  array<string, string>  $query
     * @return Generator<int, mixed>
     */
    private function paginate(PendingRequest $http, string $path, array $query = []): Generator
    {
        $page = 1;

        do {
            $response = $http->get($path, array_merge($query, ['per_page' => 100, 'page' => $page]));

            if ($response->status() === 404) {
                return;
            }

            if (! $response->successful()) {
                throw new RuntimeException("GET {$path} -> HTTP {$response->status()}");
            }

            $items = $response->json();
            if (! is_array($items) || $items === []) {
                return;
            }

            foreach ($items as $item) {
                yield $item;
            }

            $page++;
        } while (count($items) === 100 && $page <= self::MAX_PAGES);
    }
}
