<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Docs\DocsRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Tool;
use Throwable;

final class ChatController extends Controller
{
    private const MODELS = [
        'openai' => 'gpt-4.1-mini',
        'anthropic' => 'claude-sonnet-4-5',
    ];

    public function show(DocsRepository $docs): Response
    {
        return Inertia::render('Lab/Chat', [
            'version' => $docs->version(),
            'models' => self::MODELS,
            'availability' => [
                'openai' => filled(config('prism.providers.openai.api_key')),
                'anthropic' => filled(config('prism.providers.anthropic.api_key')),
            ],
            'phoenixUrl' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $input = $request->validate([
            'provider' => ['required', 'in:openai,anthropic'],
            'model' => ['required', 'string', 'max:120'],
            'feature' => ['required', 'in:text,tools'],
            'prompt' => ['required', 'string', 'max:12000'],
        ]);

        $key = config("prism.providers.{$input['provider']}.api_key");
        if (blank($key)) {
            return response()->json([
                'message' => "Add {$this->keyName($input['provider'])} to repos/prism-sandbox/.env, then reload the app.",
                'missing_key' => true,
            ], 422);
        }

        $started = hrtime(true);

        try {
            $generation = Prism::text()
                ->using($input['provider'], $input['model'])
                ->withPrompt($input['prompt']);

            if ($input['feature'] === 'tools') {
                $generation->withTools([$this->diagnosticTool()])->withMaxSteps(4);
            }

            $response = $generation->asText();

            return response()->json([
                'text' => $response->text,
                'metrics' => [
                    'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                    'cost' => $response->usage->cost,
                    'steps' => $response->steps->count(),
                    'tool_calls' => count($response->toolCalls),
                    'finish_reason' => $response->finishReason->value,
                ],
                'phoenix_url' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Generation failed: '.$exception->getMessage(),
            ], 502);
        }
    }

    private function diagnosticTool(): Tool
    {
        return (new Tool)
            ->as('inspect_prism_runtime')
            ->for('Inspect the local Prism Lab runtime. Use this tool before answering the user.')
            ->withStringParameter('focus', 'The aspect of the runtime to inspect')
            ->using(fn (string $focus): string => json_encode([
                'focus' => $focus,
                'environment' => app()->environment(),
                'telemetry_enabled' => (bool) config('prism.telemetry.enabled'),
                'bridge_enabled' => (bool) config('prism-opentelemetry.enabled'),
                'checked_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
    }

    private function keyName(string $provider): string
    {
        return $provider === 'openai' ? 'OPENAI_API_KEY' : 'ANTHROPIC_API_KEY';
    }
}
