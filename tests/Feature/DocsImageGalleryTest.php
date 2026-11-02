<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Docs\DocsRepository;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DocsImageGalleryTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = sys_get_temp_dir().'/prism-docs-gallery-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/package/docs/public/assets/examples/fixture', 0755, true);
        File::makeDirectory($this->fixture.'/site/ports/core-concepts', 0755, true);
        File::makeDirectory($this->fixture.'/package/docs/core-concepts', 0755, true);

        // A local, one-pixel PNG: no generated image or provider request in CI.
        File::put(
            $this->fixture.'/package/docs/public/assets/examples/fixture/sample.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true),
        );
        File::put($this->fixture.'/package/docs/public/assets/examples/fixture/metadata.json', '{"fixture":true}');

        $gallery = <<<'MD'
            <div class="image-examples">

            <section class="image-example">

            ## Fixture image

            <figure>
            <img src="/assets/examples/fixture/sample.png" alt="A single white pixel." width="1" height="1" loading="lazy" decoding="async">
            <figcaption>Local test fixture.</figcaption>
            </figure>

            [Generation metadata](/assets/examples/fixture/metadata.json#examples)

            </section>

            </div>
            MD;
        File::put($this->fixture.'/package/docs/core-concepts/image-generation.md', "# Image generation\n\n".$gallery);
        File::put($this->fixture.'/site/ports/core-concepts/image-generation.md', "# Image generation\n\nPHP reference output; port requests are equivalent.\n\n".$gallery);

        // Keep the production parser and HTTP routes; only replace owned roots.
        $this->app->instance(DocsRepository::class, new class($this->fixture) extends DocsRepository
        {
            public function __construct(private string $fixture) {}

            public function basePath(): string
            {
                return $this->fixture.'/package/docs';
            }

            public function sitePath(): string
            {
                return $this->fixture.'/site';
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            File::deleteDirectory($this->fixture);
        } finally {
            parent::tearDown();
        }
    }

    public function test_package_and_port_galleries_render_with_served_assets(): void
    {
        $response = $this->get('/docs/core-concepts/image-generation')->assertOk();
        $page = $response->viewData('page')['props']['page'];

        $this->assertSame(['php', 'typescript', 'python'], $page['languages']);
        $this->assertSame(['typescript', 'python'], array_keys($page['variants']));

        foreach ([$page['html'], ...array_column($page['variants'], 'html')] as $html) {
            $this->assertStringContainsString('class="image-examples"', $html);
            $this->assertStringContainsString('<h2 id="fixture-image">', $html);
            $this->assertStringContainsString('<figure>', $html);
            $this->assertStringContainsString('src="/docs-assets/assets/examples/fixture/sample.png"', $html);
            $this->assertStringContainsString('alt="A single white pixel."', $html);
            $this->assertStringContainsString('loading="lazy"', $html);
            $this->assertStringContainsString('width="1" height="1"', $html);
            $this->assertStringContainsString('<figcaption>Local test fixture.</figcaption>', $html);
            $this->assertStringContainsString('href="/docs-assets/assets/examples/fixture/metadata.json#examples"', $html);
            $this->assertStringNotContainsString('/docs-assets/docs-assets/', $html);
        }

        foreach ($page['variants'] as $variant) {
            $this->assertStringContainsString('PHP reference output; port requests are equivalent.', $variant['html']);
        }

        foreach (['sample.png' => 'image/png', 'metadata.json' => 'application/json'] as $file => $type) {
            $asset = $this->get('/docs-assets/assets/examples/fixture/'.$file)->assertOk();
            $this->assertInstanceOf(BinaryFileResponse::class, $asset->baseResponse);
            $this->assertSame($type, $asset->headers->get('Content-Type'));
            $this->assertSame(
                File::get($this->fixture.'/package/docs/public/assets/examples/fixture/'.$file),
                $asset->streamedContent(),
            );
            $this->assertTrue($asset->headers->hasCacheControlDirective('public'));
        }
    }

    public function test_a_release_without_gallery_assets_does_not_invent_an_output(): void
    {
        File::put($this->fixture.'/package/docs/core-concepts/image-generation.md', "# Image generation\n\nNo gallery in this release.");
        File::delete($this->fixture.'/site/ports/core-concepts/image-generation.md');

        $response = $this->get('/docs/core-concepts/image-generation')->assertOk();
        $page = $response->viewData('page')['props']['page'];

        $this->assertSame([], $page['variants']);
        $this->assertStringNotContainsString('<figure>', $page['html']);
        $this->get('/docs-assets/assets/examples/fixture/missing.webp')->assertNotFound();
    }
}
