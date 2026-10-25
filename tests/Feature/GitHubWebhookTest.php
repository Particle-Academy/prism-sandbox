<?php

namespace Tests\Feature;

use App\Models\Contributor;
use App\Support\CommitCredits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.github.webhook_secret', $this->secret);
        config()->set('services.github.org', 'Particle-Academy');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function deliver(string $event, array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload);
        $signature ??= 'sha256='.hash_hmac('sha256', $body, $this->secret);

        return $this->call('POST', '/webhooks/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function actor(string $login = 'octocat', int $id = 42, string $type = 'User'): array
    {
        return ['id' => $id, 'login' => $login, 'type' => $type, 'avatar_url' => "https://avatars/$login"];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function repo(array $extra = []): array
    {
        return array_merge(['repository' => ['full_name' => 'Particle-Academy/prism']], $extra);
    }

    protected function xpFor(string $login, string $metric): int
    {
        return Contributor::where('github_username', $login)->firstOrFail()->getXpFor($metric);
    }

    public function test_it_rejects_an_invalid_signature(): void
    {
        $this->deliver('issues', $this->repo(['action' => 'opened']), 'sha256=deadbeef')
            ->assertStatus(403);
    }

    public function test_it_rejects_when_no_secret_is_configured(): void
    {
        config()->set('services.github.webhook_secret', '');

        $this->deliver('issues', $this->repo(['action' => 'opened']))
            ->assertStatus(503);
    }

    public function test_it_ignores_repositories_outside_the_org(): void
    {
        $this->deliver('issues', [
            'action' => 'opened',
            'repository' => ['full_name' => 'someone-else/prism'],
            'issue' => ['node_id' => 'I_x', 'html_url' => 'http://x', 'user' => $this->actor()],
        ])->assertOk();

        $this->assertDatabaseCount('contributors', 0);
    }

    public function test_it_awards_issue_xp_and_creates_the_contributor(): void
    {
        $this->deliver('issues', $this->repo([
            'action' => 'opened',
            'issue' => ['node_id' => 'I_1', 'html_url' => 'http://x/1', 'user' => $this->actor()],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertDatabaseHas('contributors', ['github_username' => 'octocat', 'github_id' => '42']);
        $this->assertSame(config('gamification.metrics.issue-xp.xp'), $this->xpFor('octocat', 'issue-xp'));
    }

    public function test_it_awards_pr_comment_xp_only_for_comments_on_prs(): void
    {
        // Comment on a plain issue → not rewarded.
        $this->deliver('issue_comment', $this->repo([
            'action' => 'created',
            'issue' => ['number' => 1],
            'comment' => ['id' => 11, 'html_url' => 'http://x', 'user' => $this->actor()],
        ]))->assertOk()->assertJson(['awarded' => false]);

        // Comment on a PR → rewarded.
        $this->deliver('issue_comment', $this->repo([
            'action' => 'created',
            'issue' => ['number' => 2, 'pull_request' => ['url' => 'http://pr']],
            'comment' => ['id' => 12, 'html_url' => 'http://x', 'user' => $this->actor()],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertSame(config('gamification.metrics.pr-comment-xp.xp'), $this->xpFor('octocat', 'pr-comment-xp'));
    }

    public function test_it_awards_submitted_and_accepted_pr_xp_with_first_pr_achievement(): void
    {
        $this->deliver('pull_request', $this->repo([
            'action' => 'opened',
            'pull_request' => ['node_id' => 'PR_1', 'html_url' => 'http://pr/1', 'user' => $this->actor()],
        ]))->assertJson(['awarded' => true]);

        $this->deliver('pull_request', $this->repo([
            'action' => 'closed',
            'pull_request' => ['node_id' => 'PR_1', 'merged' => true, 'html_url' => 'http://pr/1', 'user' => $this->actor()],
        ]))->assertJson(['awarded' => true]);

        $contributor = Contributor::where('github_username', 'octocat')->firstOrFail();
        $this->assertSame(config('gamification.metrics.pr-submitted-xp.xp'), $contributor->getXpFor('pr-submitted-xp'));
        $this->assertSame(config('gamification.metrics.pr-accepted-xp.xp'), $contributor->getXpFor('pr-accepted-xp'));
        $this->assertTrue($contributor->hasAchievement('first-pr'));
    }

    public function test_it_is_idempotent_on_redelivery(): void
    {
        $payload = $this->repo([
            'action' => 'opened',
            'pull_request' => ['node_id' => 'PR_dup', 'html_url' => 'http://pr', 'user' => $this->actor()],
        ]);

        $this->deliver('pull_request', $payload)->assertJson(['awarded' => true]);
        $this->deliver('pull_request', $payload)->assertJson(['awarded' => false]);

        $this->assertSame(config('gamification.metrics.pr-submitted-xp.xp'), $this->xpFor('octocat', 'pr-submitted-xp'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $commits
     * @return array<string, mixed>
     */
    protected function pushPayload(array $commits, string $ref = 'refs/heads/main'): array
    {
        return [
            'ref' => $ref,
            'repository' => ['full_name' => 'Particle-Academy/prism', 'default_branch' => 'main'],
            'commits' => $commits,
        ];
    }

    public function test_it_awards_commit_xp_to_the_author_not_the_pusher(): void
    {
        // The absorption case: a contributor already known from a backfill,
        // whose commit is replayed here by the maintainer.
        Contributor::create(['github_id' => '777', 'github_username' => 'lintume', 'avatar_url' => 'https://avatars/lintume']);

        $this->deliver('push', $this->pushPayload([
            ['id' => 'abc1234def', 'author' => ['username' => 'lintume', 'name' => 'lialia.sakhno']],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertSame(config('gamification.metrics.commit-xp.xp'), $this->xpFor('lintume', 'commit-xp'));
    }

    public function test_an_absorbed_commit_already_credited_upstream_is_not_credited_again(): void
    {
        $author = Contributor::create(['github_id' => '777', 'github_username' => 'lintume', 'avatar_url' => 'https://avatars/lintume']);

        // The upstream original, already credited by the backfill.
        CommitCredits::remember($author, CommitCredits::identities(['2026-02-01T10:00:00Z']), 'commit:upstreamsha');

        // The same work replayed into the fork: new SHA, same authored date.
        $this->deliver('push', $this->pushPayload([
            ['id' => 'forksha1234', 'timestamp' => '2026-02-01T11:00:00+01:00', 'author' => ['username' => 'lintume']],
        ]))->assertOk()->assertJson(['awarded' => false]);

        $this->assertSame(0, $this->xpFor('lintume', 'commit-xp'));
    }

    public function test_commit_xp_is_idempotent_on_redelivery(): void
    {
        Contributor::create(['github_id' => '777', 'github_username' => 'lintume', 'avatar_url' => 'https://avatars/lintume']);

        $payload = $this->pushPayload([['id' => 'abc1234def', 'author' => ['username' => 'lintume']]]);

        $this->deliver('push', $payload)->assertJson(['awarded' => true]);
        $this->deliver('push', $payload)->assertJson(['awarded' => false]);

        $this->assertSame(config('gamification.metrics.commit-xp.xp'), $this->xpFor('lintume', 'commit-xp'));
    }

    public function test_it_ignores_pushes_to_branches_other_than_the_default(): void
    {
        Contributor::create(['github_id' => '777', 'github_username' => 'lintume', 'avatar_url' => 'https://avatars/lintume']);

        $this->deliver('push', $this->pushPayload(
            [['id' => 'abc1234def', 'author' => ['username' => 'lintume']]],
            'refs/heads/some-feature',
        ))->assertOk()->assertJson(['awarded' => false]);

        $this->assertSame(0, $this->xpFor('lintume', 'commit-xp'));
    }

    public function test_a_push_never_creates_a_duplicate_contributor(): void
    {
        // Push payloads carry a username but no numeric id, and contributors are
        // keyed on github_id — creating from a login would mint a second row
        // alongside the backfill's. Unknown authors wait for the backfill.
        $this->deliver('push', $this->pushPayload([
            ['id' => 'abc1234def', 'author' => ['username' => 'brand-new-person']],
        ]))->assertOk()->assertJson(['awarded' => false]);

        $this->assertDatabaseCount('contributors', 0);
    }

    public function test_it_never_awards_commit_xp_to_an_ignored_user(): void
    {
        config()->set('gamification.ignored_users', ['ignored-user']);
        Contributor::create(['github_id' => '1', 'github_username' => 'ignored-user', 'avatar_url' => 'https://avatars/ignored-user']);

        $this->deliver('push', $this->pushPayload([
            ['id' => 'abc1234def', 'author' => ['username' => 'ignored-user']],
        ]))->assertOk()->assertJson(['awarded' => false]);

        $this->assertSame(0, $this->xpFor('ignored-user', 'commit-xp'));
    }

    public function test_maintainers_earn_xp_like_everyone_else(): void
    {
        Contributor::create(['github_id' => '1', 'github_username' => 'wishborn', 'avatar_url' => 'https://avatars/wishborn']);

        $this->deliver('push', $this->pushPayload([
            ['id' => 'abc1234def', 'author' => ['username' => 'wishborn']],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertSame(config('gamification.metrics.commit-xp.xp'), $this->xpFor('wishborn', 'commit-xp'));
    }

    public function test_it_ignores_bot_contributors(): void
    {
        $this->deliver('issues', $this->repo([
            'action' => 'opened',
            'issue' => ['node_id' => 'I_bot', 'html_url' => 'http://x', 'user' => $this->actor('dependabot', 99, 'Bot')],
        ]))->assertOk()->assertJson(['awarded' => false]);

        $this->assertDatabaseCount('contributors', 0);
    }

    public function test_it_awards_review_xp_for_a_submitted_review(): void
    {
        $this->deliver('pull_request_review', $this->repo([
            'action' => 'submitted',
            'review' => ['id' => 555, 'html_url' => 'http://pr#review', 'user' => $this->actor()],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertSame(config('gamification.metrics.pr-review-xp.xp'), $this->xpFor('octocat', 'pr-review-xp'));
    }

    public function test_it_awards_star_xp_once_per_user(): void
    {
        $payload = $this->repo([
            'action' => 'created',
            'sender' => $this->actor(),
        ]);

        $this->deliver('star', $payload)->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('star', $payload)->assertOk()->assertJson(['awarded' => false]); // re-star can't farm

        $this->assertSame(config('gamification.metrics.star-xp.xp'), $this->xpFor('octocat', 'star-xp'));
    }

    public function test_it_honours_the_repo_allowlist(): void
    {
        config()->set('services.github.repos', ['Particle-Academy/prism']);

        $this->deliver('issues', [
            'action' => 'opened',
            'repository' => ['full_name' => 'Particle-Academy/prism'],
            'issue' => ['node_id' => 'I_ok', 'html_url' => 'http://x', 'user' => $this->actor()],
        ])->assertJson(['awarded' => true]);

        // Another org repo not on the allowlist → ignored.
        $this->deliver('issues', [
            'action' => 'opened',
            'repository' => ['full_name' => 'Particle-Academy/prism-sandbox'],
            'issue' => ['node_id' => 'I_no', 'html_url' => 'http://x', 'user' => $this->actor('someone', 77)],
        ])->assertOk();

        $this->assertDatabaseMissing('contributors', ['github_username' => 'someone']);
    }

    public function test_it_ignores_users_on_the_ignore_list(): void
    {
        config()->set('gamification.ignored_users', ['ignored-user']);

        // Case-insensitive: "Ignored-User" is still ignored.
        $this->deliver('star', $this->repo([
            'action' => 'created',
            'sender' => $this->actor('Ignored-User', 1),
        ]))->assertOk()->assertJson(['awarded' => false]);

        $this->assertDatabaseCount('contributors', 0);
    }
}
