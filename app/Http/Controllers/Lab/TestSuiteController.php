<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Docs\DocsRepository;
use App\Http\Controllers\Controller;
use App\Lab\PrismTestRegistry;
use App\Lab\PrismTestRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TestSuiteController extends Controller
{
    public function show(DocsRepository $docs, PrismTestRegistry $registry): Response
    {
        return Inertia::render('Lab/Tests', [
            'version' => $docs->version(),
            'cases' => $registry->all()->map->toArray()->values(),
            'availability' => [
                'openai' => filled(config('prism.providers.openai.api_key')),
                'anthropic' => filled(config('prism.providers.anthropic.api_key')),
            ],
            'phoenixUrl' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
        ]);
    }

    public function run(Request $request, PrismTestRegistry $registry, PrismTestRunner $runner): JsonResponse
    {
        $input = $request->validate(['cases' => ['required', 'array', 'min:1'], 'cases.*' => ['string']]);
        $cases = collect($input['cases'])->map(fn (string $id) => $registry->find($id));

        if ($cases->contains(null)) {
            return response()->json(['message' => 'One or more test cases are unknown.'], 422);
        }

        return response()->json(['results' => $cases->map(fn ($case) => $runner->run($case))->values()]);
    }
}
