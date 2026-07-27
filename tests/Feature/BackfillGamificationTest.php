<?php

namespace Tests\Feature;

use App\Models\Contributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillGamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gamification.ignored_users', ['wishborn']);
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
                $this->ghUser('wishborn', 1),                 // ignored → skipped
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
        $this->assertDatabaseMissing('contributors', ['github_username' => 'wishborn']);
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
}
