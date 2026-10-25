<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    public function __construct(protected DocsRepository $docs) {}

    public function index(): InertiaResponse
    {
        return $this->show('getting-started/introduction');
    }

    public function show(string $path): InertiaResponse
    {
        abort_unless($this->docs->exists($path), 404);

        return Inertia::render('Docs/Show', [
            'page' => $this->docs->page($path),
            'nav' => $this->docs->nav(),
            'path' => $path,
            'version' => $this->docs->version(),
        ]);
    }

    public function searchIndex(): JsonResponse
    {
        return response()->json($this->docs->searchIndex())
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function asset(string $path): BinaryFileResponse
    {
        $file = $this->docs->assetFile($path);

        abort_unless($file !== '', 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=86400']);
    }
}
