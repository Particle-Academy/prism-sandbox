<?php

namespace Tests\Feature;

use App\Models\Contributor;
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
}
