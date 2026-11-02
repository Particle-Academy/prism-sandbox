<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use App\ProviderSetup\ProviderGuides;
use Inertia\Inertia;
use Inertia\Response;

final class ProviderSetupController extends Controller
{
    public function index(ProviderGuides $guides, DocsRepository $docs): Response
    {
        return Inertia::render('Providers/Index', ['providers' => array_values($guides->all()), 'version' => $docs->version()]);
    }

    public function show(string $id, ProviderGuides $guides, DocsRepository $docs): Response
    {
        $provider = $guides->all()[$id] ?? null;
        abort_if($provider === null, 404);

        return Inertia::render('Providers/Show', ['provider' => $provider, 'version' => $docs->version()]);
    }
}
