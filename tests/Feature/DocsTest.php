<?php

namespace Tests\Feature;

use App\Docs\DocsRepository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_landing_page_renders(): void
    {
        $response = $this->get('/')->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame('Landing', $response->viewData('page')['component']);
        $this->assertNotEmpty($props['version']);
    }

    public function test_docs_index_shows_introduction(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Introduction');
    }

    public function test_a_docs_page_renders_with_nav_and_content(): void
    {
        $response = $this->get('/docs/getting-started/installation')->assertOk();

        $page = $response->viewData('page')['props'];

        $this->assertSame('installation', basename($page['path']));
        $this->assertNotEmpty($page['nav']);
        $this->assertStringContainsString('composer require', $page['page']['html']);
    }

    public function test_unknown_docs_page_is_404(): void
    {
        $this->get('/docs/nope/never')->assertNotFound();
    }

    public function test_search_index_is_served(): void
    {
        $this->getJson('/docs-index.json')
            ->assertOk()
            ->assertJsonStructure([['title', 'link', 'section', 'text']]);
    }

    public function test_sitemap_and_llms_txt(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('urlset', false);
        $this->get('/llms.txt')->assertOk()->assertSee('# Prism');
    }

    public function test_nav_marks_companion_package_links_as_external(): void
    {
        $nav = app(DocsRepository::class)->flatNav();

        $external = array_values(array_filter($nav, fn (array $i): bool => $i['external']));

        $this->assertNotEmpty($external, 'Upstream sidebar should still carry absolute package links.');

        foreach ($external as $item) {
            $this->assertMatchesRegularExpression('#^https?://#', $item['link']);
        }

        foreach (app(DocsRepository::class)->pages() as $item) {
            $this->assertFalse($item['external']);
            $this->assertStringStartsWith('/', $item['link']);
        }
    }

    public function test_sitemap_never_prefixes_an_absolute_link_with_docs(): void
    {
        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        // Regression: '/docs' . 'https://github.com/...' produced
        // '/docshttps://github.com/...' in the sitemap fed to search engines.
        $this->assertStringNotContainsString('/docshttp', (string) $body);
    }

    public function test_public_endpoints_survive_a_nav_cache_from_an_older_deploy(): void
    {
        // Reproduces the outage: the nav cache key was namespaced by package
        // version only, so a deploy that added `external` to nav entries read
        // back the old shape and 500'd sitemap.xml and llms.txt.
        $repo = app(DocsRepository::class);
        $stale = array_map(
            fn (array $g): array => [
                'text' => $g['text'],
                'items' => array_map(
                    fn (array $i): array => ['text' => $i['text'], 'link' => $i['link']],
                    $g['items'],
                ),
            ],
            $repo->nav(),
        );

        $key = (fn (): string => $this->cacheKey('nav'))->call($repo);
        Cache::forever($key, $stale);

        $this->get('/sitemap.xml')->assertOk();
        $this->get('/llms.txt')->assertOk();
        $this->get('/docs')->assertOk();

        $this->assertStringNotContainsString(
            '/docshttp',
            (string) $this->get('/sitemap.xml')->getContent(),
        );
    }

    public function test_llms_txt_links_companion_packages_straight_out(): void
    {
        $body = (string) $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringNotContainsString('/docshttp', $body);
        $this->assertStringContainsString('[Relay](https://github.com/prism-php/relay)', $body);
    }
}
