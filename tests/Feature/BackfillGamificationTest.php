<?php

namespace Tests\Feature;

use App\Models\Contributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillGamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gamification.ignored_users', ['ignored-user']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function ghUser(string $login = 'octocat', int $id = 42, string $type = 'User', array $extra = []): array
    {
        return array_merge(['id' => $id, 'login' => $login, 'type' => $type, 'avatar_url' => "https://avatars/$login"], $extra);
    }

    /**
     * Fake the GitHub REST surface the command walks. Specific patterns come
     * before general ones so `issues/{n}/comments` isn't captured by `issues*`.
     */
    protected function fakeGitHub(): void
    {
        $repo = 'https://api.github.com/repos/Particle-Academy/prism';

        Http::fake([
            "{$repo}/issues/*/comments*" => Http::response([
                ['id' => 900, 'html_url' => 'http://c/900', 'user' => $this->ghUser()],
            ]),
            "{$repo}/issues*" => Http::response([
                ['node_id' => 'I_1', 'html_url' => 'http://i/1', 'user' => $this->ghUser()],
                // A PR surfaced by the issues endpoint — must be skipped here.
                ['node_id' => 'PR_asIssue', 'pull_request' => ['url' => 'x'], 'html_url' => 'http://i/2', 'user' => $this->ghUser()],
                // A bot — must be skipped.
                ['node_id' => 'I_bot', 'html_url' => 'http://i/3', 'user' => $this->ghUser('dependabot', 99, 'Bot')],
            ]),
            "{$repo}/pulls/*/reviews*" => Http::response([
                ['id' => 555, 'html_url' => 'http://r/555', 'user' => $this->ghUser()],
            ]),
            "{$repo}/pulls/comments*" => Http::response([
                ['id' => 700, 'html_url' => 'http://rc/700', 'user' => $this->ghUser()],
            ]),
            "{$repo}/pulls*" => Http::response([
                ['node_id' => 'PR_1', 'number' => 1, 'html_url' => 'http://pr/1', 'merged_at' => '2026-01-01T00:00:00Z', 'user' => $this->ghUser()],
            ]),
            "{$repo}/stargazers*" => Http::response([
                $this->ghUser(),                              // counts
                $this->ghUser('ignored-user', 1),             // ignored → skipped
            ]),
        ]);
    }

    protected function xp(string $metric): int
    {
        return (int) config("gamification.metrics.$metric.xp");
    }

    public function test_it_backfills_every_contribution_type(): void
    {
        $this->fakeGitHub();

        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])
            ->assertSuccessful();

        $c = Contributor::where('github_username', 'octocat')->firstOrFail();

        $this->assertSame($this->xp('issue-xp'), $c->getXpFor('issue-xp'));
        $this->assertSame($this->xp('pr-submitted-xp'), $c->getXpFor('pr-submitted-xp'));
        $this->assertSame($this->xp('pr-accepted-xp'), $c->getXpFor('pr-accepted-xp'));
        $this->assertSame($this->xp('pr-review-xp'), $c->getXpFor('pr-review-xp'));
        $this->assertSame($this->xp('star-xp'), $c->getXpFor('star-xp'));
        // Two distinct comment kinds (issue comment + inline review comment).
        $this->assertSame(2 * $this->xp('pr-comment-xp'), $c->getXpFor('pr-comment-xp'));
        $this->assertTrue($c->hasAchievement('first-pr'));

        // Bots and ignored/maintainer logins never become contributors.
        $this->assertDatabaseMissing('contributors', ['github_username' => 'dependabot']);
        $this->assertDatabaseMissing('contributors', ['github_username' => 'ignored-user']);
    }

    public function test_it_is_idempotent_across_runs(): void
    {
        $this->fakeGitHub();

        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])->assertSuccessful();
        $before = Contributor::where('github_username', 'octocat')->firstOrFail()->getTotalXp();

        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])->assertSuccessful();
        $after = Contributor::where('github_username', 'octocat')->firstOrFail()->getTotalXp();

        $this->assertSame($before, $after, 'A second backfill must not re-award anything.');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakeGitHub();

        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism'], '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('contributors', 0);
    }

    /**
     * Upstream: one author's commits, each landed on a different day, plus a
     * stargazer. Upstream is not a fork, so its whole history is walked.
     */
    protected function fakeUpstream(int $commits = 25): void
    {
        $repo = 'https://api.github.com/repos/prism-php/prism';

        $history = [];
        foreach (range(1, $commits) as $i) {
            $history[] = [
                'sha' => sprintf('%040d', $i),
                'html_url' => "http://commit/{$i}",
                'author' => $this->ghUser('sixlive', 7),
                'commit' => ['committer' => ['date' => sprintf('2024-01-%02dT12:00:00Z', $i)]],
            ];
        }

        Http::preventStrayRequests();
        Http::fake([
            "{$repo}/stargazers*" => Http::response([$this->ghUser('stargazer', 8)]),
            "{$repo}/commits*" => Http::response($history),
            "{$repo}/issues*" => Http::response([]),
            "{$repo}/pulls*" => Http::response([]),
            $repo => Http::response(['full_name' => 'prism-php/prism', 'default_branch' => 'main']),
        ]);
    }

    public function test_upstream_commits_are_capped_by_the_day_they_landed(): void
    {
        config()->set('gamification.upstream', ['prism-php/prism']);
        $this->fakeUpstream(25);

        $this->artisan('gamification:backfill', ['repos' => ['prism-php/prism']])->assertSuccessful();

        // Capped by the processing day, a single run would credit 20 of these
        // and reject the other 5 permanently.
        $sixlive = Contributor::where('github_username', 'sixlive')->firstOrFail();
        $this->assertSame(25 * $this->xp('commit-xp'), $sixlive->getXpFor('commit-xp'));

        // Every claim records that it was earned upstream, with its XP.
        $this->assertSame(25, DB::table('xp_award_claims')->where('repo', 'prism-php/prism')->where('xp', $this->xp('commit-xp'))->count());
    }

    public function test_upstream_stars_are_not_contributions(): void
    {
        config()->set('gamification.upstream', ['prism-php/prism']);
        $this->fakeUpstream(1);

        $this->artisan('gamification:backfill', ['repos' => ['prism-php/prism']])->assertSuccessful();

        $this->assertDatabaseMissing('contributors', ['github_username' => 'stargazer']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'prism-php/prism/stargazers'));
    }

    /**
     * A fork of prism-php/prism that is ahead by the given commits, and an
     * upstream whose history and merged pull requests are given.
     *
     * @param  list<array<string, mixed>>  $forkCommits
     * @param  list<array<string, mixed>>  $upstreamCommits
     * @param  array<int, list<array<string, mixed>>>  $upstreamPrCommits  PR number => its commits
     */
    protected function fakeForkAndUpstream(array $forkCommits, array $upstreamCommits, array $upstreamPrCommits = []): void
    {
        $fork = 'https://api.github.com/repos/Particle-Academy/prism';
        $up = 'https://api.github.com/repos/prism-php/prism';

        $pulls = [];
        $fakes = [];
        foreach ($upstreamPrCommits as $number => $commits) {
            $pulls[] = ['node_id' => "UP_PR_{$number}", 'number' => $number, 'html_url' => "http://up/pr/{$number}", 'merged_at' => '2026-03-01T00:00:00Z', 'user' => $this->ghUser('author', 50)];
            $fakes["{$up}/pulls/{$number}/commits*"] = Http::response($commits);
        }

        Http::preventStrayRequests();
        Http::fake($fakes + [
            "{$up}/pulls/*/reviews*" => Http::response([]),
            "{$up}/pulls/comments*" => Http::response([]),
            "{$up}/pulls*" => Http::response($pulls),
            "{$up}/issues*" => Http::response([]),
            "{$up}/commits*" => Http::response($upstreamCommits),
            $up => Http::response(['full_name' => 'prism-php/prism', 'default_branch' => 'main']),
            "{$fork}/compare/*" => Http::response(['commits' => $forkCommits, 'total_commits' => count($forkCommits)]),
            "{$fork}/*" => Http::response([]),
            $fork => Http::response(['full_name' => 'Particle-Academy/prism', 'default_branch' => 'main', 'parent' => ['full_name' => 'prism-php/prism']]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function commit(string $sha, string $authoredAt, string $title, string $committer = 'wishborn'): array
    {
        return [
            'sha' => $sha,
            'html_url' => "http://commit/{$sha}",
            'author' => $this->ghUser('author', 50),
            'committer' => ['login' => $committer],
            'commit' => [
                'message' => $title."\n\nbody",
                'author' => ['date' => $authoredAt],
                'committer' => ['date' => '2026-03-01T00:00:00Z'],
            ],
        ];
    }

    public function test_a_cherry_picked_commit_is_credited_once_across_repos(): void
    {
        config()->set('gamification.upstream', ['prism-php/prism']);

        // The fork absorbed the upstream commit: new SHA, same authored date.
        $this->fakeForkAndUpstream(
            [$this->commit('forksha', '2026-02-01T10:00:00Z', 'fix: a thing')],
            [$this->commit('upsha', '2026-02-01T10:00:00Z', 'fix: a thing', 'sixlive')],
        );

        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism', 'prism-php/prism']])->assertSuccessful();

        $author = Contributor::where('github_username', 'author')->firstOrFail();
        $this->assertSame($this->xp('commit-xp'), $author->getXpFor('commit-xp'));
        // The fork was walked first, so the fork holds the credit.
        $this->assertSame(1, DB::table('xp_award_claims')->where('metric', 'commit-xp')->where('repo', 'particle-academy/prism')->count());
        $this->assertSame(0, DB::table('xp_award_claims')->where('metric', 'commit-xp')->where('repo', 'prism-php/prism')->count());
    }

    public function test_a_squash_merge_is_credited_once_with_the_commits_absorbed_from_its_pr(): void
    {
        config()->set('gamification.upstream', ['prism-php/prism']);

        // Upstream squash-merged PR #7, whose own commits the fork had cherry-picked.
        $this->fakeForkAndUpstream(
            [
                $this->commit('fork1', '2026-02-01T10:00:00Z', 'feat: part one'),
                $this->commit('fork2', '2026-02-02T10:00:00Z', 'feat: part two'),
            ],
            [$this->commit('squash', '2026-03-01T00:00:00Z', 'feat: the whole thing (#7)', 'web-flow')],
            [7 => [
                ['sha' => 'pr1', 'commit' => ['author' => ['date' => '2026-02-01T10:00:00Z']]],
                ['sha' => 'pr2', 'commit' => ['author' => ['date' => '2026-02-02T10:00:00Z']]],
            ]],
        );

        // Upstream first this time: the squash takes the credit, and the
        // absorbed commits are recognised as the same work.
        $this->artisan('gamification:backfill', ['repos' => ['prism-php/prism', 'Particle-Academy/prism']])->assertSuccessful();

        $author = Contributor::where('github_username', 'author')->firstOrFail();
        $this->assertSame($this->xp('commit-xp'), $author->getXpFor('commit-xp'));
        $this->assertSame(1, DB::table('xp_award_claims')->where('metric', 'commit-xp')->where('repo', 'prism-php/prism')->count());
    }

    public function test_a_default_run_includes_upstream(): void
    {
        config()->set('gamification.upstream', ['prism-php/prism']);
        config()->set('services.github.repos', ['Particle-Academy/prism']);
        $this->fakeUpstream(1);
        Http::fake(['https://api.github.com/repos/Particle-Academy/prism*' => Http::response([])]);

        $this->artisan('gamification:backfill')
            ->expectsOutputToContain('Backfilling: Particle-Academy/prism, prism-php/prism')
            ->assertSuccessful();

        $this->assertDatabaseHas('contributors', ['github_username' => 'sixlive']);
    }
}
