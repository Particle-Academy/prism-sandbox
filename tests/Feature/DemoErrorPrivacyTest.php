<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Prism\Prism\Exceptions\PrismException;
use Tests\TestCase;

class DemoErrorPrivacyTest extends TestCase
{
    public function test_provider_error_details_reach_neither_the_response_nor_the_log(): void
    {
        $this->withoutExceptionHandling();
        $marker = 'SENSITIVE-PROMPT-ERROR-FIXTURE';
        config(['prism.providers.groq.api_key' => 'fixture-key']);
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/*' => Http::response([
            'error' => ['type' => 'validation_error', 'message' => $marker],
        ], 422)]);

        // Capture the actual log records, including any exception-handler logging.
        $handler = new TestHandler;
        Log::swap(new Logger(new MonologLogger('privacy-test', [$handler])));

        $this->postJson('/demo', ['prompt' => $marker])
            ->assertStatus(502)
            ->assertExactJson(['message' => 'The provider could not complete that request. Try again shortly.'])
            ->assertDontSee($marker);
        Http::assertSentCount(1);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $record = $records[0];
        $this->assertSame('Demo provider request failed', $record['message']);
        $this->assertSame('groq', $record['context']['provider']);
        $this->assertSame(422, $record['context']['status']);
        $this->assertSame(PrismException::class, $record['context']['error_class']);
        $this->assertTrue(Str::isUuid($record['context']['correlation_id']));
        $this->assertSame(['provider', 'status', 'error_class', 'correlation_id'], array_keys($record['context']));
        $this->assertStringNotContainsString($marker, json_encode($records, JSON_THROW_ON_ERROR));
    }
}
