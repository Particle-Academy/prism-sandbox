<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DemoRequestBudget;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DemoRequestBudgetTest extends TestCase
{
    use RefreshDatabase;

    private bool $providerFailure = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        $this->travelTo(CarbonImmutable::parse('2026-10-10T12:00:05Z'));
        config(['prism.providers.groq.api_key' => 'fixture-key',
            'demo.requests_per_minute' => 2, 'demo.requests_per_day' => 3]);
        Http::preventStrayRequests();
        $this->fakeProvider();
    }

    public function test_multiple_source_ips_share_one_provider_budget(): void
    {
        $this->request('198.51.100.1')->assertOk()->assertJsonPath('text', 'Budget fixture response');
        $this->request('198.51.100.2')->assertOk();
        $this->request('198.51.100.3')->assertStatus(429)->assertHeader('Retry-After', '55');
        Http::assertSentCount(2);
        $this->assertUsed(2, 2);
    }

    public function test_rotating_forwarded_headers_cannot_reset_the_shared_budget(): void
    {
        for ($ip = 1; $ip <= 2; $ip++) {
            $this->request('192.0.2.10', '203.0.113.'.$ip)->assertOk();
        }
        $this->request('192.0.2.10', '203.0.113.3')->assertStatus(429);
        Http::assertSentCount(2);
        $this->assertUsed(2, 2);
    }

    public function test_minute_rollover_does_not_reset_the_daily_budget(): void
    {
        $this->request('198.51.100.1')->assertOk();
        $this->request('198.51.100.2')->assertOk();
        $this->travel(1)->minutes();
        $this->request('198.51.100.3')->assertOk();
        $this->request('198.51.100.4')->assertStatus(429)->assertHeader('Retry-After', '43135');
        Http::assertSentCount(3);
        $this->assertUsed(1, 3);

        $this->travelTo(CarbonImmutable::parse('2026-10-11T00:00:05Z'));
        $this->request('198.51.100.5')->assertOk();
        $this->assertUsed(1, 1);
        $this->assertDatabaseCount('demo_request_budgets', 2);
    }

    public function test_a_cache_flush_and_another_budget_instance_do_not_restore_capacity(): void
    {
        config(['demo.requests_per_minute' => 1]);
        $this->request('198.51.100.1')->assertOk();
        Cache::flush();
        $this->assertSame(55, (new DemoRequestBudget)->reserve('groq'));
        $this->request('198.51.100.2')->assertStatus(429);
        Http::assertSentCount(1);
        $this->assertUsed(1, 1);
    }

    public function test_failed_provider_attempts_are_still_charged(): void
    {
        config(['demo.requests_per_minute' => 1]);
        $this->providerFailure = true;
        $this->request('198.51.100.1')->assertStatus(502)
            ->assertExactJson(['message' => 'The provider could not complete that request. Try again shortly.']);
        $this->request('198.51.100.2')->assertStatus(429);
        Http::assertSentCount(1);
        $this->assertUsed(1, 1);
    }

    public function test_unconfigured_and_invalid_requests_do_not_consume_provider_budget(): void
    {
        $this->request('198.51.100.1', prompt: '')->assertStatus(422);
        config(['prism.providers.groq.api_key' => '']);
        $this->request('198.51.100.2')->assertStatus(503);
        Http::assertNothingSent();
        $this->assertDatabaseCount('demo_request_budgets', 0);
    }

    public function test_an_unavailable_budget_store_fails_closed_without_contacting_the_provider(): void
    {
        Schema::rename('demo_request_budgets', 'demo_request_budgets_unavailable');
        try {
            $this->request('198.51.100.1')->assertStatus(503)->assertHeader('Retry-After', '60')
                ->assertExactJson(['message' => 'The demo is temporarily unavailable. Try again shortly.']);
            Http::assertNothingSent();
        } finally {
            Schema::rename('demo_request_budgets_unavailable', 'demo_request_budgets');
        }
        $this->assertDatabaseCount('demo_request_budgets', 0);
        $this->request('198.51.100.2')->assertOk();
        $this->assertUsed(1, 1);
    }

    public function test_a_counter_write_failure_rolls_back_both_windows_and_never_contacts_the_provider(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::unprepared("CREATE TRIGGER reject_budget_write BEFORE UPDATE ON demo_request_budgets
            WHEN NEW.window = 'day' AND NEW.used > OLD.used BEGIN SELECT RAISE(ABORT, 'fixture budget failure'); END");
        try {
            $this->request('198.51.100.1')->assertStatus(503);
            Http::assertNothingSent();
            $this->assertDatabaseCount('demo_request_budgets', 0);
        } finally {
            DB::unprepared('DROP TRIGGER reject_budget_write');
        }
        $this->request('198.51.100.2')->assertOk();
        $this->assertUsed(1, 1);
    }

    public function test_zero_budget_closes_the_demo(): void
    {
        config(['demo.requests_per_minute' => 0]);
        $this->request('198.51.100.1')->assertStatus(429);
        Http::assertNothingSent();
        $this->assertUsed(0, 0);
    }

    public function test_the_existing_per_client_throttle_remains_in_force(): void
    {
        config(['demo.requests_per_minute' => 10, 'demo.requests_per_day' => 100]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->request('198.51.100.1')->assertOk();
        }
        $this->withExceptionHandling();
        $this->request('198.51.100.1')->assertStatus(429);
        Http::assertSentCount(5);
        $this->assertUsed(5, 5);
    }

    private function request(string $source, ?string $forwarded = null, string $prompt = 'Budget fixture prompt'): TestResponse
    {
        return $this->call('POST', '/demo', [], [], [], [
            'REMOTE_ADDR' => $source, 'HTTP_X_FORWARDED_FOR' => $forwarded ?? $source,
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['prompt' => $prompt], JSON_THROW_ON_ERROR));
    }

    private function fakeProvider(): void
    {
        Http::fake(['api.groq.com/*' => fn () => $this->providerFailure
            ? Http::response(['error' => ['message' => 'fixture failure']], 422)
            : Http::response([
                'id' => 'budget-fixture', 'model' => 'llama-3.3-70b-versatile',
                'choices' => [['index' => 0, 'finish_reason' => 'stop',
                    'message' => ['role' => 'assistant', 'content' => 'Budget fixture response']]],
                'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 3, 'total_tokens' => 7],
            ])]);
    }

    private function assertUsed(int $minute, int $day): void
    {
        $this->assertDatabaseHas('demo_request_budgets', ['provider' => 'groq', 'window' => 'minute', 'used' => $minute]);
        $this->assertDatabaseHas('demo_request_budgets', ['provider' => 'groq', 'window' => 'day', 'used' => $day]);
    }
}
