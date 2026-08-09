<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
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
        $file = $this->docs->basePath().'/public/'.trim(str_replace(['..', '\\'], '', $path), '/');

        abort_unless(is_file($file), 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function sitemap(): Response
    {
        $urls = collect($this->docs->pages())
            ->map(fn (array $item): string => '<url><loc>'.url('/docs'.$item['link']).'</loc></url>')
            ->prepend('<url><loc>'.url('/').'</loc></url>')
            ->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function llms(): Response
    {
        $lines = [
            '# Prism',
            '',
            '> Prism is a Laravel package for integrating Large Language Models (LLMs) into applications — a unified, fluent API across '.count($this->docs->pages()).' documented topics and 16 providers.',
            '> This is the actively maintained Particle Academy fork, published as `particle-academy/prism` (drop-in for `prism-php/prism`). Installed version: '.$this->docs->version().'.',
            '',
        ];

        foreach ($this->docs->nav() as $group) {
            $lines[] = '## '.$group['text'];
            $lines[] = '';

            foreach ($group['items'] as $item) {
                // Companion-package entries link straight out to GitHub; only
                // real pages live under /docs.
                $lines[] = '- ['.$item['text'].']('.($item['external'] ? $item['link'] : url('/docs'.$item['link'])).')';
            }

            $lines[] = '';
        }

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
