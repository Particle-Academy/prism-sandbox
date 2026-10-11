<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contributor;
use Fiber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use LaravelFunLab\Models\EventLog;
use Tests\TestCase;

class ContributionLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'cap-fixture-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.webhook_secret' => self::SECRET, 'services.github.org' => 'Particle-Academy']);
        Http::preventStrayRequests();
    }

    public function test_the_shipped_daily_cap_bounds_distinct_signed_events(): void
    {
        $limit = (int) config('gamification.limits.per_contributor_day');
        $this->assertGreaterThan(0, $limit);
        for ($id = 1; $id <= $limit; $id++) {
            $this->deliver('issues', $this->issue($id))->assertOk()->assertJson(['awarded' => true]);
        }
        $this->deliver('issues', $this->issue($limit + 1))->assertOk()->assertJson(['awarded' => false]);
        $this->assertState('caps-fixture', $limit * 5, $limit);
    }

    public function test_the_daily_cap_combines_metrics_but_isolates_contributors(): void
    {
        config(['gamification.limits.per_contributor_day' => 2]);
        $this->deliver('issues', $this->issue(1))->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('star', ['action' => 'created', 'sender' => $this->user()])->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('issues', $this->issue(2))->assertOk()->assertJson(['awarded' => false]);
        $this->deliver('issues', $this->issue(3, $this->user(2)))->assertOk()->assertJson(['awarded' => true]);

        $this->assertState('caps-fixture', 6, 2);
        $this->assertState('caps-fixture-2', 5, 1);
    }

    public function test_backfill_shares_daily_capacity_and_cannot_restore_a_refused_event_next_day(): void
    {
        config(['gamification.limits.per_contributor_day' => 2]);
        $this->deliver('issues', $this->issue(1))->assertOk()->assertJson(['awarded' => true]);
        $this->fakeIssues([1, 2, 3]);
        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])->assertSuccessful();
        $this->assertState('caps-fixture', 10, 2);
        $this->assertDatabaseHas('contribution_limit_decisions', [
            'event_hash' => hash('sha256', 'issue:CAP_3'), 'allowed' => false,
        ]);

        // No timestamp is supplied by this fixture: a naive processing-day cap
        // would award yesterday's refused event on this run.
        $this->travel(1)->days();
        $this->fakeIssues([3]);
        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])->assertSuccessful();
        $this->assertState('caps-fixture', 10, 2);
        $this->deliver('issues', $this->issue(4))->assertOk()->assertJson(['awarded' => true]);
        $this->assertState('caps-fixture', 15, 3);
    }

    public function test_pr_capacity_combines_comments_and_reviews_across_contributors(): void
    {
        config(['gamification.limits.per_pr' => 2]);
        $this->deliver('issue_comment', $this->comment(101, 17))->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('pull_request_review', [
            'action' => 'submitted', 'pull_request' => ['number' => 17],
            'review' => ['id' => 102, 'user' => $this->user(2), 'html_url' => 'https://github.com/Particle-Academy/prism/pull/17'],
        ])->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('issue_comment', $this->comment(103, 17, $this->user(3)))->assertOk()->assertJson(['awarded' => false]);
        $this->deliver('issue_comment', $this->comment(104, 18, $this->user(4)))->assertOk()->assertJson(['awarded' => true]);

        $this->assertState('caps-fixture', 2, 1);
        $this->assertState('caps-fixture-2', 8, 1);
        $this->assertState('caps-fixture-3', 0, 0);
        $this->assertState('caps-fixture-4', 2, 1);
    }

    public function test_backfill_and_webhook_use_the_same_pr_bucket(): void
    {
        config(['gamification.limits.per_pr' => 1]);
        $this->deliver('issue_comment', $this->comment(201, 17))->assertOk()->assertJson(['awarded' => true]);
        $base = 'api.github.com/repos/Particle-Academy/prism';
        Http::fake([
            $base.'/pulls/comments*' => Http::response([
                ['id' => 202, 'user' => $this->user(2), 'pull_request_url' => 'https://api.github.com/repos/Particle-Academy/prism/pulls/17'],
            ]),
            '*' => Http::response([]),
        ]);
        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])->assertSuccessful();
        $this->assertState('caps-fixture', 2, 1);
        $this->assertState('caps-fixture-2', 0, 0);
        $this->assertDatabaseHas('contribution_limit_decisions', [
            'event_hash' => hash('sha256', 'review-comment:202'), 'allowed' => false,
        ]);
    }

    public function test_interleaved_events_cannot_both_take_the_last_daily_slot(): void
    {
        config(['gamification.limits.per_contributor_day' => 1]);
        $paused = false;
        $armed = true;
        DB::listen(function ($query) use (&$paused, &$armed): void {
            if ($armed && ! $paused && Fiber::getCurrent() !== null
                && str_contains($query->sql, 'exists') && str_contains($query->sql, 'event_logs')) {
                $paused = true;
                Fiber::suspend();
            }
        });
        $first = null;
        $fiber = new Fiber(function () use (&$first): void {
            $first = $this->deliver('issues', $this->issue(301));
        });
        try {
            $fiber->start();
            $this->assertTrue($fiber->isSuspended());
            $this->deliver('issues', $this->issue(302))->assertOk()->assertJson(['awarded' => true]);
            $fiber->resume();
            $first->assertOk()->assertJson(['awarded' => false]);
        } finally {
            $armed = false;
        }
        $this->assertState('caps-fixture', 5, 1);
        $this->assertSame(1, (int) DB::table('contribution_limit_buckets')->sum('used'));
    }

    public function test_an_xp_audit_failure_does_not_consume_quota(): void
    {
        config(['gamification.limits.per_contributor_day' => 1]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::unprepared("CREATE TRIGGER reject_cap_audit BEFORE INSERT ON lfl_event_logs
            WHEN NEW.event_type = 'xp_awarded' BEGIN SELECT RAISE(ABORT, 'fixture write failure'); END");
        try {
            $this->deliver('issues', $this->issue(401))->assertStatus(503);
            $this->assertState('caps-fixture', 0, 0);
            $this->assertDatabaseCount('contribution_limit_decisions', 0);
            $this->assertSame(0, (int) DB::table('contribution_limit_buckets')->sum('used'));
        } finally {
            DB::unprepared('DROP TRIGGER reject_cap_audit');
        }
        $this->deliver('issues', $this->issue(401))->assertOk()->assertJson(['awarded' => true]);
        $this->assertState('caps-fixture', 5, 1);
    }

    public function test_a_quota_store_error_fails_closed(): void
    {
        Schema::rename('contribution_limit_decisions', 'contribution_limit_decisions_unavailable');
        try {
            $this->deliver('issues', $this->issue(501))->assertStatus(503);
            $this->assertState('caps-fixture', 0, 0);
            $this->assertDatabaseCount('xp_award_claims', 0);
        } finally {
            Schema::rename('contribution_limit_decisions_unavailable', 'contribution_limit_decisions');
        }
    }

    public function test_event_dates_are_grouped_by_utc_day(): void
    {
        config(['gamification.limits.per_contributor_day' => 1]);
        $first = $this->issue(601);
        $first['issue']['created_at'] = '2026-10-10T23:30:00-05:00';
        $second = $this->issue(602);
        $second['issue']['created_at'] = '2026-10-11T12:00:00Z';
        $this->deliver('issues', $first)->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('issues', $second)->assertOk()->assertJson(['awarded' => false]);
        $this->assertState('caps-fixture', 5, 1);
        $this->assertDatabaseHas('contribution_limit_decisions', ['event_day' => '2026-10-11', 'allowed' => true]);
    }

    public function test_author_controlled_commit_dates_cannot_evade_the_daily_cap_in_either_path(): void
    {
        config(['gamification.limits.per_contributor_day' => 2]);
        $this->deliver('issues', $this->issue(701))->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('push', ['ref' => 'refs/heads/main', 'commits' => [[
            'id' => 'cap-commit-1', 'timestamp' => '2001-01-01T00:00:00Z',
            'author' => ['username' => 'caps-fixture'],
        ]]])->assertOk()->assertJson(['awarded' => true]);
        $this->deliver('push', ['ref' => 'refs/heads/main', 'commits' => [[
            'id' => 'cap-commit-2', 'timestamp' => '2099-01-01T00:00:00Z',
            'author' => ['username' => 'caps-fixture'],
        ]]])->assertOk()->assertJson(['awarded' => false]);
        Http::fake([
            'api.github.com/repos/Particle-Academy/prism/commits*' => Http::response([[
                'sha' => 'cap-commit-3', 'author' => $this->user(),
                'commit' => ['committer' => ['date' => '2002-01-01T00:00:00Z']],
            ]]),
            '*' => Http::response([]),
        ]);
        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])->assertSuccessful();
        $this->assertState('caps-fixture', 15, 2);
        $this->assertSame(1, DB::table('contribution_limit_decisions')->distinct()->count('event_day'));
        $this->assertDatabaseHas('contribution_limit_decisions', [
            'event_hash' => hash('sha256', 'commit:cap-commit-3'), 'allowed' => false,
        ]);
    }

    private function user(int $id = 1): array
    {
        return ['id' => 82000 + $id, 'login' => $id === 1 ? 'caps-fixture' : 'caps-fixture-'.$id, 'type' => 'User'];
    }

    private function issue(int $id, ?array $user = null): array
    {
        return ['action' => 'opened', 'issue' => ['node_id' => 'CAP_'.$id, 'user' => $user ?? $this->user()]];
    }

    private function comment(int $id, int $pr, ?array $user = null): array
    {
        return ['action' => 'created', 'issue' => ['number' => $pr, 'pull_request' => ['url' => 'fixture']],
            'comment' => ['id' => $id, 'user' => $user ?? $this->user(), 'html_url' => 'https://github.com/Particle-Academy/prism/pull/'.$pr]];
    }

    private function deliver(string $event, array $payload): TestResponse
    {
        $payload['repository'] = ['full_name' => 'Particle-Academy/prism'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function fakeIssues(array $ids): void
    {
        Http::fake([
            'api.github.com/repos/Particle-Academy/prism/issues*' => Http::response(array_map(fn ($id) => $this->issue($id)['issue'], $ids)),
            '*' => Http::response([]),
        ]);
    }

    private function assertState(string $login, int $xp, int $events): void
    {
        $contributor = Contributor::where('github_username', $login)->firstOrFail();
        $this->assertSame($xp, $contributor->getTotalXp());
        $this->assertSame($events, EventLog::where('event_type', 'xp_awarded')->where('awardable_id', $contributor->id)->count());
    }
}
