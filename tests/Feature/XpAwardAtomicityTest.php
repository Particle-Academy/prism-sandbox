<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contributor;
use Fiber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use LaravelFunLab\Facades\LFL;
use LaravelFunLab\Models\EventLog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class XpAwardAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private Contributor $contributor;

    private const SECRET = 'atomicity-fixture-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.webhook_secret' => self::SECRET, 'services.github.org' => 'Particle-Academy']);
        Http::preventStrayRequests();
        $this->contributor = Contributor::create(['github_id' => 81001, 'github_username' => 'atomicity-fixture']);
    }

    public function test_interleaved_signed_deliveries_award_exactly_once(): void
    {
        $paused = false;
        $armed = true;
        DB::listen(function ($query) use (&$paused, &$armed): void {
            if ($armed && ! $paused && Fiber::getCurrent() !== null
                && str_contains($query->sql, 'exists') && str_contains($query->sql, 'event_logs')) {
                $paused = true;
                Fiber::suspend(); // Pause after the real SELECT returned its stale result.
            }
        });

        $first = null;
        $fiber = new Fiber(function () use (&$first): void {
            $first = $this->deliver();
        });
        try {
            $fiber->start();
            $this->assertTrue($fiber->isSuspended(), 'The real durable lookup must be reached and paused.');
            $this->deliver()->assertOk()->assertJson(['awarded' => true]);
            $fiber->resume();
            $this->assertTrue($fiber->isTerminated());
            $first->assertOk()->assertJson(['awarded' => false]);
        } finally {
            $armed = false;
        }

        $this->assertState(5, 1, 1);
    }

    public function test_an_audit_insert_failure_rolls_back_xp_and_claim_and_can_retry(): void
    {
        $this->rejectAuditWrites();
        try {
            foreach ([1, 2] as $attempt) {
                $this->deliver()->assertStatus(503)->assertJson(['awarded' => false])->assertHeader('Retry-After', '60');
                $this->assertState(0, 0, 0);
                $this->assertFalse(Cache::has('xp:'.$this->contributor->id.':issue-xp:issue:F2_EVENT'));
            }
        } finally {
            DB::unprepared('DROP TRIGGER reject_xp_audit');
        }

        $this->deliver()->assertOk()->assertJson(['awarded' => true]);
        $this->assertState(5, 1, 1);
    }

    public function test_a_dedup_store_read_error_is_retryable_and_never_awards(): void
    {
        Schema::rename('lfl_event_logs', 'lfl_event_logs_unavailable');
        try {
            $this->deliver()->assertStatus(503)->assertJson(['awarded' => false]);
            $this->assertSame(0, $this->contributor->fresh()->getTotalXp());
            $this->assertDatabaseCount('xp_award_claims', 0);
        } finally {
            Schema::rename('lfl_event_logs_unavailable', 'lfl_event_logs');
        }
        $this->assertState(0, 0, 0);
    }

    public function test_a_claim_store_error_cannot_be_hidden_by_a_cache_hit(): void
    {
        Cache::put('xp:'.$this->contributor->id.':issue-xp:issue:F2_EVENT', true, 3600);
        Schema::rename('xp_award_claims', 'xp_award_claims_unavailable');
        try {
            $this->deliver()->assertStatus(503)->assertJson(['awarded' => false]);
            $this->assertSame(0, $this->contributor->fresh()->getTotalXp());
        } finally {
            Schema::rename('xp_award_claims_unavailable', 'xp_award_claims');
        }
        $this->assertState(0, 0, 0);
    }

    #[DataProvider('auditFlags')]
    public function test_disabling_the_audit_pipeline_cannot_commit_unaudited_xp(string $flag): void
    {
        config([$flag => false]);
        $this->deliver()->assertStatus(503)->assertJson(['awarded' => false]);
        $this->assertState(0, 0, 0);
    }

    public static function auditFlags(): array
    {
        return [['lfl.events.dispatch'], ['lfl.events.log_to_database']];
    }

    public function test_pre_migration_audit_history_is_not_re_awarded(): void
    {
        LFL::award('issue-xp')->to($this->contributor)->amount(5)->because('Legacy fixture')
            ->from('github')->withMeta(['dedup' => 'issue:F2_EVENT'])->save();
        $this->assertState(5, 1, 0);

        $this->deliver()->assertOk()->assertJson(['awarded' => false]);
        $this->assertState(5, 1, 1);
    }

    public function test_the_database_enforces_a_unique_claim(): void
    {
        $this->deliver()->assertOk()->assertJson(['awarded' => true]);
        $claim = (array) DB::table('xp_award_claims')->first();
        unset($claim['id']);
        try {
            DB::table('xp_award_claims')->insert($claim);
            $this->fail('The database accepted a duplicate contributor/metric/event claim.');
        } catch (UniqueConstraintViolationException) {
            $this->assertState(5, 1, 1);
        }
    }

    public function test_backfill_reports_award_storage_failure_and_rolls_back(): void
    {
        Http::fake([
            'api.github.com/repos/Particle-Academy/prism/issues*' => Http::response([$this->payload()['issue']]),
            '*' => Http::response([]),
        ]);
        $this->rejectAuditWrites();
        try {
            $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism']])
                ->expectsOutputToContain('award FAILED')
                ->assertExitCode(1);
            $this->assertState(0, 0, 0);
        } finally {
            DB::unprepared('DROP TRIGGER reject_xp_audit');
        }
    }

    private function rejectAuditWrites(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::unprepared("CREATE TRIGGER reject_xp_audit BEFORE INSERT ON lfl_event_logs
            WHEN NEW.event_type = 'xp_awarded' BEGIN SELECT RAISE(ABORT, 'fixture audit insert failure'); END");
    }

    private function deliver(): TestResponse
    {
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'issues',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function payload(): array
    {
        return ['action' => 'opened', 'repository' => ['full_name' => 'Particle-Academy/prism'],
            'issue' => ['node_id' => 'F2_EVENT', 'html_url' => 'https://example.test/issues/1',
                'user' => ['id' => 81001, 'login' => 'atomicity-fixture', 'type' => 'User']]];
    }

    private function assertState(int $xp, int $events, int $claims): void
    {
        $this->assertSame($xp, $this->contributor->fresh()->getTotalXp());
        $this->assertSame($events, EventLog::query()->where('event_type', 'xp_awarded')
            ->where('awardable_id', $this->contributor->id)->count());
        $this->assertDatabaseCount('xp_award_claims', $claims);
    }
}
