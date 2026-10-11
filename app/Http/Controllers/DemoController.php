<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use App\Support\DemoRequestBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * A small public demonstration of Prism.
 *
 * The Lab that used to live here moved to prism-labs, because a testbed
 * holding sixteen provider credentials had no business sharing a deployment
 * with a public site. What is left is the smallest honest thing: one provider,
 * one model, bounded hard.
 *
 * The per-client route throttle is backed by a provider-wide application
 * request budget. These application limits are configured independently of
 * the provider's plan, since other applications may also use the same key.
 */
final class DemoController extends Controller
{
    /**
     * One model, not a picker. A demo that lets a stranger choose the model is
     * a demo that lets a stranger choose the most expensive one — and on a
     * shared free quota, spend is denial of service for everyone after them.
     */
    private const MODEL = 'llama-3.3-70b-versatile';

    private const MAX_TOKENS = 400;

    public function show(DocsRepository $docs): Response
    {
        return Inertia::render('Demo', [
            'version' => $docs->version(),
            'model' => self::MODEL,
            'configured' => $this->configured(),
        ]);
    }

    public function run(Request $request, DemoRequestBudget $budget): JsonResponse
    {
        // Validated by hand rather than via $request->validate(). This route
        // sits in the web group, so Inertia's middleware turns a validation
        // failure into a 302 back to the page — correct for a form post, wrong
        // for this, which is a JSON endpoint the page calls with fetch(). The
        // client would follow the redirect and try to parse HTML.
        $validator = Validator::make($request->all(), [
            'prompt' => ['required', 'string', 'max:600'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first('prompt'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $input = $validator->validated();

        if (! $this->configured()) {
            return response()->json([
                'message' => 'This demo is not configured. Set GROQ_API_KEY to enable it.',
            ], 503);
        }

        try {
            $retryAfter = $budget->reserve(Provider::Groq->value);
        } catch (Throwable $e) {
            // Storage failures cannot turn a shared budget into unlimited spend.
            Log::warning('Demo request budget unavailable', [
                'provider' => Provider::Groq->value, 'status' => 503,
                'error_class' => $e::class, 'correlation_id' => (string) Str::uuid(),
            ]);

            return response()->json(['message' => 'The demo is temporarily unavailable. Try again shortly.'], 503)
                ->header('Retry-After', '60');
        }
        if ($retryAfter > 0) {
            return response()->json(['message' => 'Demo request budget reached. Try again later.'], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        try {
            $response = Prism::text()
                ->using(Provider::Groq, self::MODEL)
                ->withMaxTokens(self::MAX_TOKENS)
                ->withPrompt($input['prompt'])
                ->asText();
        } catch (PrismException $e) {
            // Allowlist diagnostics only: never log the exception, its message,
            // previous exception, response body, headers, or the user's prompt.
            $status = $e->httpStatus ?? $e->getCode();
            Log::warning('Demo provider request failed', [
                'provider' => Provider::Groq->value,
                'status' => $status >= 100 && $status <= 599 ? $status : null,
                'error_class' => $e::class,
                'correlation_id' => (string) Str::uuid(),
            ]);

            return response()->json([
                'message' => 'The provider could not complete that request. Try again shortly.',
            ], 502);
        }

        return response()->json([
            'text' => $response->text,
            'model' => $response->meta->model,
            'tokens' => [
                'prompt' => $response->usage->promptTokens,
                'completion' => $response->usage->completionTokens,
            ],
        ]);
    }

    private function configured(): bool
    {
        return filled(config('prism.providers.groq.api_key'));
    }
}
