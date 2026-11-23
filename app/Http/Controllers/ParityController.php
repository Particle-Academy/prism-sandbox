<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use App\Parity\DashboardPresenter;
use App\Parity\SnapshotStore;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public parity dashboard: language parity, provider capability, provider
 * API drift and protocol coverage.
 *
 * Reads the snapshot `parity:snapshot` wrote. It never fetches anything itself,
 * so a page view cannot spend the sources' rate limit, and a missing snapshot
 * renders as "unknown" everywhere rather than as an empty board.
 */
final class ParityController extends Controller
{
    public function __invoke(DocsRepository $docs, SnapshotStore $store, DashboardPresenter $presenter): Response
    {
        return Inertia::render('Parity', [
            'version' => $docs->version(),
            'dashboard' => $presenter->present($store->read(), now()->toDateTimeImmutable()),
        ]);
    }
}
