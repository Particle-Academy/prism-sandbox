<?php

declare(strict_types=1);

namespace App\Lab;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Tool;
use Throwable;

final class PrismTestRunner
{
    /** @return array<string, mixed> */
    public function run(PrismTestCase $case): array
    {
        $started = hrtime(true);

        try {
            $metrics = match ($case->feature) {
                'text' => $this->text($case),
                'streaming' => $this->streaming($case),
                'tools' => $this->tools($case),
                'structured' => $this->structured($case),
                'embeddings' => $this->embeddings($case),
                'images' => $this->images($case),
            };

            return $this->result($case, true, $started, $metrics);
        } catch (Throwable $exception) {
            report($exception);

            return $this->result($case, false, $started, [], $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function text(PrismTestCase $case): array
    {
        $response = Prism::text()->using($case->provider, $case->model)
            ->withPrompt('Reply with exactly: Prism telemetry text check passed.')
            ->asText();

        return $this->usage($response->usage) + ['finish_reason' => $response->finishReason->value, 'steps' => $response->steps->count()];
    }

    /** @return array<string, mixed> */
    private function streaming(PrismTestCase $case): array
    {
        $events = 0;
        $end = null;
        foreach (Prism::text()->using($case->provider, $case->model)->withPrompt('Reply briefly: streaming telemetry passed.')->asStream() as $event) {
            $events++;
            if ($event instanceof StreamEndEvent) {
                $end = $event;
            }
        }

        return ($end?->usage ? $this->usage($end->usage) : []) + ['events' => $events, 'finish_reason' => $end?->finishReason->value];
    }

    /** @return array<string, mixed> */
    private function tools(PrismTestCase $case): array
    {
        $tool = (new Tool)->as('prism_lab_probe')->for('Read the Prism Lab probe value. You must call this tool before answering.')
            ->using(fn (): string => 'telemetry-tool-ok');
        $response = Prism::text()->using($case->provider, $case->model)->withPrompt('Call the probe tool, then report its value.')
            ->withTools([$tool])->withMaxSteps(4)->asText();

        return $this->usage($response->usage) + ['steps' => $response->steps->count(), 'tool_calls' => count($response->toolCalls), 'finish_reason' => $response->finishReason->value];
    }

    /** @return array<string, mixed> */
    private function structured(PrismTestCase $case): array
    {
        $schema = new ObjectSchema('telemetry_check', 'Prism telemetry check result', [
            new StringSchema('status', 'Must be the word passed'),
        ], ['status']);
        $response = Prism::structured()->using($case->provider, $case->model)->withSchema($schema)
            ->withPrompt('Return the telemetry check status as passed.')->asStructured();

        return $this->usage($response->usage) + ['status' => $response->structured['status'] ?? null, 'finish_reason' => $response->finishReason->value];
    }

    /** @return array<string, mixed> */
    private function embeddings(PrismTestCase $case): array
    {
        $response = Prism::embeddings()->using($case->provider, $case->model)->fromInput('Prism telemetry embedding check')->asEmbeddings();

        return ['embeddings' => count($response->embeddings), 'tokens' => $response->usage->tokens];
    }

    /** @return array<string, mixed> */
    private function images(PrismTestCase $case): array
    {
        $response = Prism::image()->using($case->provider, $case->model)
            ->withPrompt('A minimal black square containing one small cyan prism, flat vector style')->generate();

        return $this->usage($response->usage) + ['images' => $response->imageCount()];
    }

    /** @return array<string, int|float|null> */
    private function usage(object $usage): array
    {
        return [
            'prompt_tokens' => $usage->promptTokens ?? null,
            'completion_tokens' => $usage->completionTokens ?? null,
            'cost' => $usage->cost ?? null,
        ];
    }

    /** @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function result(PrismTestCase $case, bool $passed, int $started, array $metrics, ?string $error = null): array
    {
        return [
            'id' => $case->id, 'provider' => $case->provider, 'feature' => $case->feature,
            'passed' => $passed, 'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
            'metrics' => $metrics, 'error' => $error,
            'phoenix_url' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
        ];
    }
}
