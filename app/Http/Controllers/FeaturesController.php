<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The features index, one page per feature, and the packages catalog.
 *
 * The pages render from resources/js/data/ecosystem.ts; the server only
 * decides which slugs exist (config/features.php), so an unknown slug is a 404
 * rather than an empty page.
 */
final class FeaturesController extends Controller
{
    public function __construct(private readonly DocsRepository $docs) {}

    public function index(): Response
    {
        return Inertia::render('Features/Index', [
            'version' => $this->docs->version(),
        ]);
    }

    public function show(string $slug): Response
    {
        abort_unless(array_key_exists($slug, (array) config('features.pages', [])), 404);

        return Inertia::render('Features/Show', [
            'version' => $this->docs->version(),
            'slug' => $slug,
        ]);
    }

    public function packages(): Response
    {
        return Inertia::render('Packages', [
            'version' => $this->docs->version(),
        ]);
    }
}
