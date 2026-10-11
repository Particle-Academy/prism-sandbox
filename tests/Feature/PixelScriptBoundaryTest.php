<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PixelScriptBoundaryTest extends TestCase
{
    private string $buildDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise real Vite tags without requiring a previous frontend build.
        $this->buildDirectory = 'pixel-test-'.bin2hex(random_bytes(8));
        File::makeDirectory(public_path($this->buildDirectory));
        $manifest = [];
        foreach (['resources/css/app.css', 'resources/js/app.tsx', 'resources/js/fancy-pixel.ts'] as $source) {
            $manifest[$source] = ['src' => $source, 'file' => basename($source).'.js', 'isEntry' => true];
        }
        File::put(public_path($this->buildDirectory.'/manifest.json'), json_encode($manifest));
        $this->app->instance(Vite::class, (new Vite)
            ->useBuildDirectory($this->buildDirectory)
            ->useHotFile(public_path($this->buildDirectory.'/unused-hot-file')));
    }

    protected function tearDown(): void
    {
        try {
            File::deleteDirectory(public_path($this->buildDirectory));
        } finally {
            parent::tearDown();
        }
    }

    public function test_production_html_loads_the_pixel_from_our_vite_build(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->get('/')->assertOk();
        $response->assertSee('/'.$this->buildDirectory.'/fancy-pixel.ts.js', false)
            ->assertDontSee('unpkg.com');

        preg_match_all('/<script[^>]*src="([^"]+)"/', $response->getContent(), $scripts);
        $this->assertNotEmpty($scripts[1]);
        foreach ($scripts[1] as $src) {
            $this->assertContains(parse_url($src, PHP_URL_HOST), [null, $response->baseRequest->getHost()]);
        }
    }

    public function test_the_pixel_is_not_loaded_outside_production(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('/'.$this->buildDirectory.'/app.tsx.js', false)
            ->assertDontSee('fancy-pixel.ts.js')
            ->assertDontSee('unpkg.com');
    }
}
