<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use App\Events\HitlDemoEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Streaming\Events\ToolApprovalRequestEvent;
use Prism\Prism\Tool as PrismTool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolApprovalRequest;
use Prism\Prism\ValueObjects\ToolApprovalResponse;
use Prism\Prism\ValueObjects\ToolCall;

class HitlDemoController extends Controller
{
    protected const MODEL = 'claude-haiku-4-5-20251001';

    protected const PROMPT = 'A customer named Acme Corp reports they were double-charged $49 for their subscription this month. Investigate and make it right.';

    public function show(DocsRepository $docs): InertiaResponse
    {
        return Inertia::render('DemoHitl', [
            'version' => $docs->version(),
            'hasApiKey' => config('prism.providers.anthropic.api_key') !== '',
            'prompt' => self::PROMPT,
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate(['run_id' => ['required', 'regex:/^[A-Za-z0-9-]{8,64}$/']]);
        $runId = $validated['run_id'];

        if (config('prism.providers.anthropic.api_key') === '') {
            return response()->json(['error' => 'ANTHROPIC_API_KEY is not configured.'], 422);
        }

        $stream = Prism::text()
            ->using('anthropic', self::MODEL)
            ->withSystemPrompt('You are a billing support agent. Use the issue_refund tool to refund double charges. Be concise.')
            ->withTools([$this->refundTool()])
            ->withMaxSteps(3)
            ->withPrompt(self::PROMPT)
            ->asStream();

        $this->broadcastStream($runId, $stream);

        return response()->json(['ok' => true]);
    }

    public function resume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'run_id' => ['required', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'approved' => ['required', 'boolean'],
        ]);

        $pending = Cache::pull('hitl-demo:'.$validated['run_id']);

        if ($pending === null) {
            return response()->json(['error' => 'No pending approval for this run (it may have expired).'], 404);
        }

        $toolCall = new ToolCall(
            id: $pending['tool_call']['id'],
            name: $pending['tool_call']['name'],
            arguments: $pending['tool_call']['arguments'],
        );

        $stream = Prism::text()
            ->using('anthropic', self::MODEL)
            ->withSystemPrompt('You are a billing support agent. Use the issue_refund tool to refund double charges. Be concise.')
            ->withTools([$this->refundTool()])
            ->withMaxSteps(3)
            ->withMessages([
                new UserMessage(self::PROMPT),
                new AssistantMessage(
                    $pending['assistant_text'],
                    [$toolCall],
                    toolApprovalRequests: [new ToolApprovalRequest($pending['approval_id'], $toolCall->id)],
                ),
                new ToolResultMessage([], [
                    new ToolApprovalResponse(
                        $pending['approval_id'],
                        approved: $validated['approved'],
                        reason: $validated['approved'] ? null : 'Denied by the demo user',
                    ),
                ]),
            ])
            ->asStream();

        $this->broadcastStream($validated['run_id'], $stream);

        return response()->json(['ok' => true]);
    }

    protected function refundTool(): PrismTool
    {
        return Tool::as('issue_refund')
            ->for('Issue a refund to a customer. Requires human approval.')
            ->withStringParameter('customer', 'The customer name')
            ->withNumberParameter('amount', 'The refund amount in dollars')
            ->using(fn (string $customer, int|float $amount): string => "Refund of \${$amount} issued to {$customer} (transaction rf_demo_1234).")
            ->requiresApproval();
    }

    /**
     * @param  iterable<\Prism\Prism\Streaming\Events\StreamEvent>  $stream
     */
    protected function broadcastStream(string $runId, iterable $stream): void
    {
        $assistantText = '';

        foreach ($stream as $event) {
            $payload = $event->toArray();

            if ($event->type()->value === 'text_delta') {
                $assistantText .= $payload['delta'] ?? ($payload['text'] ?? '');
            }

            if ($event instanceof ToolApprovalRequestEvent) {
                Cache::put('hitl-demo:'.$runId, [
                    'approval_id' => $event->approvalId,
                    'assistant_text' => $assistantText,
                    'tool_call' => [
                        'id' => $event->toolCall->id,
                        'name' => $event->toolCall->name,
                        'arguments' => $event->toolCall->arguments(),
                    ],
                ], now()->addMinutes(15));
            }

            event(new HitlDemoEvent($runId, $event->type()->value, $payload));
        }
    }
}
