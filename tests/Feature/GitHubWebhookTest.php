<?php

namespace Tests\Feature;

use App\Models\User;
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

    protected function contributor(string $login = 'octocat'): User
    {
        return User::create([
            'name' => 'Octo Cat',
            'email' => $login.'@example.com',
            'password' => 'secret',
            'github_username' => $login,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function repo(array $extra = []): array
    {
        return array_merge(['repository' => ['full_name' => 'Particle-Academy/prism']], $extra);
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
        $user = $this->contributor();

        $this->deliver('issues', [
            'action' => 'opened',
            'repository' => ['full_name' => 'someone-else/prism'],
            'issue' => ['node_id' => 'I_x', 'html_url' => 'http://x', 'user' => ['login' => 'octocat']],
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->getTotalXp());
    }

    public function test_it_awards_issue_xp_for_a_filed_issue(): void
    {
        $user = $this->contributor();

        $this->deliver('issues', $this->repo([
            'action' => 'opened',
            'issue' => ['node_id' => 'I_1', 'html_url' => 'http://x/1', 'user' => ['login' => 'octocat']],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertSame(config('gamification.metrics.issue-xp.xp'), $user->fresh()->getXpFor('issue-xp'));
    }

    public function test_it_awards_pr_comment_xp_only_for_comments_on_prs(): void
    {
        $user = $this->contributor();

        // Comment on a plain issue → not rewarded.
        $this->deliver('issue_comment', $this->repo([
            'action' => 'created',
            'issue' => ['number' => 1],
            'comment' => ['id' => 11, 'html_url' => 'http://x', 'user' => ['login' => 'octocat']],
        ]))->assertOk()->assertJson(['awarded' => false]);

        // Comment on a PR → rewarded.
        $this->deliver('issue_comment', $this->repo([
            'action' => 'created',
            'issue' => ['number' => 2, 'pull_request' => ['url' => 'http://pr']],
            'comment' => ['id' => 12, 'html_url' => 'http://x', 'user' => ['login' => 'octocat']],
        ]))->assertOk()->assertJson(['awarded' => true]);

        $this->assertSame(config('gamification.metrics.pr-comment-xp.xp'), $user->fresh()->getXpFor('pr-comment-xp'));
    }

    public function test_it_awards_submitted_and_accepted_pr_xp(): void
    {
        $user = $this->contributor();

        $this->deliver('pull_request', $this->repo([
            'action' => 'opened',
            'pull_request' => ['node_id' => 'PR_1', 'html_url' => 'http://pr/1', 'user' => ['login' => 'octocat']],
        ]))->assertJson(['awarded' => true]);

        $this->deliver('pull_request', $this->repo([
            'action' => 'closed',
            'pull_request' => ['node_id' => 'PR_1', 'merged' => true, 'html_url' => 'http://pr/1', 'user' => ['login' => 'octocat']],
        ]))->assertJson(['awarded' => true]);

        $fresh = $user->fresh();
        $this->assertSame(config('gamification.metrics.pr-submitted-xp.xp'), $fresh->getXpFor('pr-submitted-xp'));
        $this->assertSame(config('gamification.metrics.pr-accepted-xp.xp'), $fresh->getXpFor('pr-accepted-xp'));
        $this->assertTrue($fresh->hasAchievement('first-pr'));
    }

    public function test_it_is_idempotent_on_redelivery(): void
    {
        $user = $this->contributor();

        $payload = $this->repo([
            'action' => 'opened',
            'pull_request' => ['node_id' => 'PR_dup', 'html_url' => 'http://pr', 'user' => ['login' => 'octocat']],
        ]);

        $this->deliver('pull_request', $payload)->assertJson(['awarded' => true]);
        $this->deliver('pull_request', $payload)->assertJson(['awarded' => false]);

        $this->assertSame(config('gamification.metrics.pr-submitted-xp.xp'), $user->fresh()->getXpFor('pr-submitted-xp'));
    }

    public function test_it_skips_contributors_without_a_linked_account(): void
    {
        $this->deliver('issues', $this->repo([
            'action' => 'opened',
            'issue' => ['node_id' => 'I_2', 'html_url' => 'http://x', 'user' => ['login' => 'a-stranger']],
        ]))->assertOk()->assertJson(['awarded' => false]);
    }
}
