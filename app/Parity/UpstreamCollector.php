<?php

declare(strict_types=1);

namespace App\Parity;

/**
 * Our fork against upstream prism-php/prism, and the PRs we opened there.
 *
 * The same reads the Lab's /lab/drift tracker makes (compare, releases, a
 * public author search), collected on the schedule instead of per view. A
 * figure that could not be read is null and shows as unknown. It is never
 * turned into zero, because "0 behind" reads as "no drift".
 *
 * Only public pull request data is read: state, dates, the review verdicts and
 * the CI runs of the PR's head commit. Never a PR or issue body, and never
 * anything from a security advisory. A PR whose title or labels name security
 * is left off the page entirely (see PublicAllowRule::upstreamPull).
 */
final class UpstreamCollector
{
    private const REPO = '#^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*$#';

    private const BRANCH = '#^[A-Za-z0-9][A-Za-z0-9._/-]*$#';

    /** A GitHub login: alphanumerics and single hyphens, at most 39 characters. */
    private const LOGIN = '/\A[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}\z/';

    private const SEARCH_PAGE = 100;

    public function __construct(private readonly PublicSources $sources) {}

    /** @return array<string, mixed> */
    public function upstream(string $at): array
    {
        $repo = (string) config('parity.upstream.repo');
        $fork = (string) config('parity.upstream.fork');
        $branch = (string) config('parity.upstream.branch');
        if (preg_match(self::REPO, $repo) !== 1 || preg_match(self::REPO, $fork) !== 1 || preg_match(self::BRANCH, $branch) !== 1) {
            return $this->section('failed', $at, [], null);
        }
        [$forkOwner, $forkName] = explode('/', $fork, 2);

        // base = upstream, head = our fork: ahead is ours-not-upstream, behind is upstream-not-ours.
        $compare = $this->sources->get(PublicSources::API."/repos/{$repo}/compare/{$branch}...{$forkOwner}:{$forkName}:{$branch}?per_page=1");
        $upstreamReleases = $this->sources->get(PublicSources::API."/repos/{$repo}/releases?per_page=30");
        $forkRelease = $this->sources->get(PublicSources::API."/repos/{$fork}/releases/latest");
        $watch = $this->sources->get(PublicSources::RAW.'/Particle-Academy/prism-provider-watch/main/state/upstream.json');

        $reads = [
            $this->read("Fork vs upstream ({$repo} {$branch}...{$fork})", $compare),
            $this->read("Upstream releases ({$repo})", $upstreamReleases),
            $this->read("Our latest release ({$fork})", $forkRelease),
            $this->read('Watcher upstream baseline (prism-provider-watch state/upstream.json)', $watch),
        ];

        $ahead = is_int($compare['json']['ahead_by'] ?? null) ? $compare['json']['ahead_by'] : null;
        $behind = is_int($compare['json']['behind_by'] ?? null) ? $compare['json']['behind_by'] : null;
        $base = $compare['json']['merge_base_commit'] ?? null;
        $baseSha = is_array($base) && is_string($base['sha'] ?? null) && preg_match('/^[0-9a-f]{40}$/', $base['sha']) === 1 ? $base['sha'] : null;
        $baseDate = is_array($base) && is_string($base['commit']['committer']['date'] ?? null) ? $base['commit']['committer']['date'] : null;

        $releases = [];
        foreach (is_array($upstreamReleases['json']) && array_is_list($upstreamReleases['json']) ? $upstreamReleases['json'] : [] as $release) {
            $tag = PublicAllowRule::releaseTag($release);
            if ($tag !== null && is_string($release['published_at'] ?? null)) {
                $releases[] = ['tag' => $tag, 'published_at' => $release['published_at'], 'prerelease' => ($release['prerelease'] ?? false) === true];
            }
        }
        usort($releases, fn (array $a, array $b): int => strcmp($b['published_at'], $a['published_at']));
        $latestUpstream = array_values(array_filter($releases, fn (array $r): bool => ! $r['prerelease']))[0] ?? null;
        // A release published after the merge base is new to us, unless the fork
        // is 0 behind: then every upstream commit is already in the fork, and a
        // release tagged after the merge-base commit (v0.103.0 was tagged 18
        // minutes after its commit) brings nothing we lack.
        $sinceCatchUp = match (true) {
            $behind === 0 => [],
            $baseDate === null => null,
            default => array_values(array_filter($releases, fn (array $r): bool => strcmp($r['published_at'], $baseDate) > 0)),
        };

        // Issues and pulls opened upstream since the merge base, by public search.
        $since = null;
        if ($baseDate !== null) {
            $day = substr($baseDate, 0, 10);
            $since = [];
            foreach (['issue' => 'issues', 'pr' => 'pulls'] as $kind => $key) {
                $search = $this->sources->get(PublicSources::API."/search/issues?q=repo:{$repo}+is:{$kind}+created:%3E%3D{$day}&per_page=1");
                $reads[] = $this->read("Upstream {$key} opened since {$day}", $search);
                $since[$key] = is_int($search['json']['total_count'] ?? null) ? $search['json']['total_count'] : null;
            }
        }

        // What the provider watcher last saw open upstream (its committed baseline).
        $watched = [];
        foreach (['issues', 'pulls'] as $source) {
            $entry = $watch['json']['sources'][$source] ?? null;
            $entries = is_array($entry) && ($entry['ok'] ?? null) === true && is_array($entry['data']['entries'] ?? null) ? $entry['data']['entries'] : null;
            $watched[$source] = $entries === null ? null : [
                'count' => count($entries),
                // The watcher reads one page of the newest items, and its issue
                // source drops PRs from a page shared with them: a lower bound.
                'lower_bound' => $source === 'issues' || count($entries) >= 30,
            ];
        }

        $forkTag = PublicAllowRule::releaseTag($forkRelease['json']);
        $complete = $ahead !== null && $behind !== null && $baseDate !== null && $upstreamReleases['ok'] && $forkTag !== null
            && $since !== null && ! in_array(null, $since, true) && $watch['ok'];
        $any = $ahead !== null || $upstreamReleases['ok'] || $forkTag !== null;

        return $this->section($complete ? 'ok' : ($any ? 'partial' : 'failed'), $at, $reads, $any ? [
            'repo' => $repo,
            'fork' => $fork,
            'ahead' => $ahead,
            'behind' => $behind,
            'merge_base' => $baseSha === null ? null : ['sha' => $baseSha, 'date' => $baseDate],
            'upstream_release' => $latestUpstream,
            'fork_release' => $forkTag === null ? null : ['tag' => $forkTag, 'published_at' => is_string($forkRelease['json']['published_at'] ?? null) ? $forkRelease['json']['published_at'] : null],
            'releases_since' => $sinceCatchUp,
            'opened_since' => $since,
            'watched_open' => $watched,
        ] : null);
    }

    /** @return array<string, mixed> */
    public function pulls(string $at): array
    {
        $repo = (string) config('parity.upstream.repo');
        // A login that is not a login is dropped rather than spliced into the
        // search query, where it could widen the search to someone else's PRs.
        $authors = array_values(array_unique(array_filter((array) config('parity.upstream.pr_authors', []),
            fn (mixed $login): bool => is_string($login) && preg_match(self::LOGIN, $login) === 1)));
        if ($authors === [] || preg_match(self::REPO, $repo) !== 1) {
            return $this->section('failed', $at, [], null);
        }

        $reads = [];
        $items = [];
        $lowerBound = false;
        foreach ($authors as $login) {
            $search = $this->sources->get(PublicSources::API."/search/issues?q=repo:{$repo}+is:pr+author:{$login}&per_page=".self::SEARCH_PAGE);
            $reads[] = $this->read("Our pull requests on {$repo}", $search);
            // If ANY author's search fails the whole card is unknown: a partial
            // count would read as fewer open PRs than there are.
            if (! $search['ok'] || ! is_array($search['json']['items'] ?? null) || ! is_int($search['json']['total_count'] ?? null)) {
                return $this->section('failed', $at, $reads, null);
            }
            $lowerBound = $lowerBound || $search['json']['total_count'] > self::SEARCH_PAGE || ($search['json']['incomplete_results'] ?? false) === true;
            foreach ($search['json']['items'] as $item) {
                $pull = PublicAllowRule::upstreamPull($item, $repo);
                if ($pull !== null) {
                    $items[$pull['number']] = $pull;
                }
            }
        }
        krsort($items);

        $limit = max(0, (int) config('parity.upstream.pr_detail_limit', 12));
        $detailed = 0;
        $complete = true;
        foreach ($items as $number => $pull) {
            $items[$number]['review'] = null;
            $items[$number]['ci'] = null;
            $items[$number]['draft'] = null;
            if ($pull['state'] !== 'open') {
                continue;
            }
            if ($detailed >= $limit) {
                $complete = false;

                continue;
            }
            $detailed++;

            $detail = $this->sources->get(PublicSources::API."/repos/{$repo}/pulls/{$number}");
            $sha = is_string($detail['json']['head']['sha'] ?? null) && preg_match('/^[0-9a-f]{40}$/', $detail['json']['head']['sha']) === 1 ? $detail['json']['head']['sha'] : null;
            $items[$number]['draft'] = is_bool($detail['json']['draft'] ?? null) ? $detail['json']['draft'] : null;

            $reviews = $this->sources->get(PublicSources::API."/repos/{$repo}/pulls/{$number}/reviews?per_page=100");
            $items[$number]['review'] = $reviews['ok'] && is_array($reviews['json']) && array_is_list($reviews['json']) ? $this->reviewState($reviews['json']) : null;

            if ($sha !== null) {
                $runs = $this->sources->get(PublicSources::API."/repos/{$repo}/actions/runs?head_sha={$sha}&per_page=50");
                $items[$number]['ci'] = $runs['ok'] && is_array($runs['json']['workflow_runs'] ?? null) ? $this->ciState($runs['json']['workflow_runs']) : null;
            }

            $ok = $detail['ok'] && $sha !== null && $items[$number]['review'] !== null && $items[$number]['ci'] !== null;
            $complete = $complete && $ok;
            if (! $ok) {
                $reads[] = ['label' => "Review and CI state of #{$number}", 'url' => "https://github.com/{$repo}/pull/{$number}", 'ok' => false, 'error' => $detail['error'] ?? 'incomplete answer'];
            }
        }
        if ($detailed > 0 && $complete) {
            $reads[] = ['label' => "Review and CI state of {$detailed} open pull request(s)", 'url' => "https://github.com/{$repo}/pulls", 'ok' => true, 'error' => null];
        }

        return $this->section($complete ? 'ok' : 'partial', $at, $reads, [
            'repo' => $repo,
            'lower_bound' => $lowerBound,
            'stale_days' => (int) config('parity.upstream.pr_stale_days', 30),
            'pulls' => array_values($items),
        ]);
    }

    /**
     * The review verdict as GitHub would summarise it: each reviewer's latest
     * decisive review counts; a comment never overrides a decision.
     *
     * @param  list<mixed>  $reviews
     */
    private function reviewState(array $reviews): string
    {
        $latest = [];
        $commented = false;
        foreach ($reviews as $review) {
            if (! is_array($review) || ! is_string($review['state'] ?? null)) {
                continue;
            }
            $who = is_string($review['user']['login'] ?? null) ? $review['user']['login'] : '?';
            if (in_array($review['state'], ['APPROVED', 'CHANGES_REQUESTED', 'DISMISSED'], true)) {
                $latest[$who] = $review['state'];
            } elseif ($review['state'] === 'COMMENTED') {
                $commented = true;
            }
        }

        return match (true) {
            in_array('CHANGES_REQUESTED', $latest, true) => 'changes-requested',
            in_array('APPROVED', $latest, true) => 'approved',
            $commented || $latest !== [] => 'commented',
            default => 'none',
        };
    }

    /** @param  list<mixed>|array<mixed>  $runs */
    private function ciState(array $runs): string
    {
        $states = [];
        foreach ($runs as $run) {
            if (! is_array($run)) {
                continue;
            }
            $status = $run['status'] ?? null;
            $conclusion = $run['conclusion'] ?? null;
            $states[] = match (true) {
                $conclusion === 'action_required' || $status === 'action_required' || $status === 'waiting' => 'awaiting-approval',
                $status !== 'completed' => 'running',
                in_array($conclusion, ['failure', 'timed_out', 'startup_failure'], true) => 'failing',
                in_array($conclusion, ['success', 'skipped', 'neutral'], true) => 'passing',
                default => 'other',
            };
        }

        foreach (['failing', 'awaiting-approval', 'running', 'other'] as $worst) {
            if (in_array($worst, $states, true)) {
                return $worst === 'other' ? 'unknown' : $worst;
            }
        }

        return $states === [] ? 'none' : 'passing';
    }

    /**
     * @param  list<array<string, mixed>>  $reads
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private function section(string $status, string $at, array $reads, ?array $data): array
    {
        return ['status' => $status, 'fetched_at' => $at, 'last_ok_at' => $status === 'ok' ? $at : null, 'reads' => $reads, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{label: string, url: string, ok: bool, error: string|null}
     */
    private function read(string $label, array $result): array
    {
        return ['label' => $label, 'url' => (string) ($result['url'] ?? ''), 'ok' => (bool) ($result['ok'] ?? false), 'error' => is_string($result['error'] ?? null) ? $result['error'] : null];
    }
}
