<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Docs\DocsRepository;
use App\Http\Controllers\Controller;
use App\Lab\BenchmarkStore;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final class BenchmarkController extends Controller
{
    public function show(DocsRepository $docs, BenchmarkStore $benchmarks): Response
    {
        return Inertia::render('Lab/Benchmarks', [
            'version' => $docs->version(),
            'benchmarks' => $benchmarks->aggregate(),
            'totalRuns' => $benchmarks->all()->count(),
            'phoenixUrl' => rtrim((string) env('PHOENIX_UI_URL', 'http://localhost:6006'), '/'),
        ]);
    }

    /**
     * Publishable artifact — the aggregated comparison plus its provenance.
     */
    public function export(BenchmarkStore $benchmarks): JsonResponse
    {
        return response()->json($benchmarks->export(), 200, [
            'Content-Disposition' => 'attachment; filename="prism-benchmarks.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
