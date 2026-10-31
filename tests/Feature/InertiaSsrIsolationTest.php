<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Inertia\Ssr\BundleDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InertiaSsrIsolationTest extends TestCase
{
    #[DataProvider('inertiaPages')]
    public function test_page_rendering_does_not_contact_ssr_when_a_bundle_exists(string $path): void
    {
        $bundle = tempnam(sys_get_temp_dir(), 'sandbox-ssr-');
        $this->assertIsString($bundle);

        try {
            file_put_contents($bundle, '// Bundle detection fixture; never executed.');
            config([
                'inertia.ssr.bundle' => $bundle,
                'inertia.ssr.url' => 'http://127.0.0.1:13714',
            ]);
            $this->assertSame($bundle, app(BundleDetector::class)->detect());
            $this->assertTrue(Http::preventingStrayRequests());

            // A removed TestCase disable records the attempted SSR request and
            // fails assertNothingSent, without contacting a real renderer.
            Http::fake([
                'http://127.0.0.1:13714/render' => Http::response([
                    'head' => [],
                    'body' => '<div>Unexpected SSR fixture response</div>',
                ]),
            ]);

            $this->get($path)->assertOk();

            Http::assertNothingSent();
        } finally {
            unlink($bundle);
        }
    }

    public static function inertiaPages(): array
    {
        return [
            'home' => ['/'],
            'docs' => ['/docs/core-concepts/text-generation'],
        ];
    }
}
