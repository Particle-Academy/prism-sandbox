<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Docs\DocsRepository;
use App\Http\Controllers\Controller;
use App\Lab\ProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Step;
use Prism\Prism\Tool;
use Throwable;

final class ChatController extends Controller
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function show(DocsRepository $docs): Response
    {
        return Inertia::render('Lab/Chat', [
            'version' => $docs->version(),
            'providers' => $this->providers->all(),
            'undescribed' => $this->providers->undescribed(),
            'phoenixUrl' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $input = $request->validate([
            // Every text provider is accepted, configured or not, so an
            // unconfigured pick gets the setup hint below instead of a bare
            // validation error that explains nothing.
            'provider' => ['required', Rule::in($this->providers->textProviderKeys())],
            'model' => ['required', 'string', 'max:120'],
            'feature' => ['required', 'in:text,tools'],
            'prompt' => ['required', 'string', 'max:12000'],
        ]);

        if (! $this->providers->isConfigured($input['provider'])) {
            return response()->json([
                'message' => $this->providers->setupHint($input['provider']),
                'missing_key' => true,
            ], 422);
        }

        $started = hrtime(true);

        try {
            $generation = Prism::text()
                ->using($input['provider'], $input['model'])
                ->withTelemetryMetadata(userId: 'prism-lab-user', sessionId: $request->session()->getId())
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
                    'provider_reported_cost' => $response->usage->cost,
                    'cost_source' => $response->usage->cost === null ? 'Derived in Phoenix after export' : 'Provider response',
                    'steps' => $response->steps->count(),
                    // Sum across steps, not $response->toolCalls: that holds only the
                    // FINAL step's calls, which is empty once the loop ends on a text
                    // answer — so a working tool run always reported zero.
                    'tool_calls' => $response->steps->sum(fn (Step $step): int => count($step->toolCalls)),
                    'finish_reason' => $response->finishReason->value,
                ],
                'phoenix_url' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Generation failed. Check the application log for details.',
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
}
