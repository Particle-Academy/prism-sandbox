<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * What a crawler, link scraper or LLM receives on the FIRST BYTE, with no
 * JavaScript run: the per-route head rendered by <x-fancy-seo::head /> from
 * SeoServiceProvider, and the discovery files. Every assertion reads the raw
 * HTTP response, the way a crawler does.
 */
class SeoTest extends TestCase
{
    private const BASE = 'https://ai.particle.academy';

    public function test_the_home_page_head_is_complete_in_the_first_byte(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<title'), 'Exactly one <title> in the first byte.');
        $this->assertStringContainsString('<title inertia>Prism — one provider interface, and a harness for agents</title>', $html);
        $this->assertMatchesRegularExpression('#<meta head-key="description" name="description" content="[^"]{40,}">#', $html);
        $this->assertStringContainsString('<link head-key="canonical" rel="canonical" href="'.self::BASE.'/">', $html);
        $this->assertStringContainsString('property="og:image" content="'.self::BASE.'/og/prism.png"', $html);
        $this->assertStringContainsString('property="og:image:width" content="1200"', $html);
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);

        $types = $this->jsonLdTypes($html);
        $this->assertContains('WebSite', $types);
        $this->assertContains('Organization', $types);
        $this->assertContains('SoftwareSourceCode', $types);
    }

    public function test_a_docs_page_gets_its_own_title_description_canonical_and_article_markup(): void
    {
        $html = (string) $this->get('/docs/core-concepts/text-generation')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<title'));
        $this->assertMatchesRegularExpression('#<title inertia>[^<]+ — Prism docs</title>#', $html);
        $this->assertStringContainsString('<link head-key="canonical" rel="canonical" href="'.self::BASE.'/docs/core-concepts/text-generation">', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);

        // The page's own first paragraph, not the old one-size template.
        preg_match('#<meta head-key="description" name="description" content="([^"]*)">#', $html, $m);
        $this->assertNotEmpty($m[1] ?? '');
        $this->assertStringNotContainsString('Prism documentation (', $m[1]);

        $types = $this->jsonLdTypes($html);
        $this->assertContains('Article', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    public function test_docs_index_names_the_introduction_as_canonical(): void
    {
        // /docs renders the introduction; it must not compete with it as a duplicate.
        $this->get('/docs')->assertOk()
            ->assertSee('<link head-key="canonical" rel="canonical" href="'.self::BASE.'/docs/getting-started/introduction">', false);
    }

    public function test_robots_txt_points_at_the_sitemap_and_keeps_webhooks_out(): void
    {
        $body = (string) $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Sitemap: '.self::BASE.'/sitemap.xml', $body);
        $this->assertStringContainsString('Disallow: /webhooks', $body);
    }

    public function test_the_sitemap_lists_docs_and_omits_the_unconfigured_demo(): void
    {
        config(['prism.providers.groq.api_key' => null]);

        $body = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.self::BASE.'/docs/getting-started/introduction</loc>', $body);
        $this->assertStringNotContainsString('/demo</loc>', $body);
    }

    public function test_llms_txt_counts_provider_guides_instead_of_stating_a_stale_number(): void
    {
        $body = (string) $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringNotContainsString('16 providers', $body);
        $this->assertMatchesRegularExpression('/including \d+ provider guides/', $body);
        $this->assertStringContainsString(self::BASE.'/docs/getting-started/introduction', $body);
    }

    public function test_docs_pages_are_available_as_markdown_and_nothing_else_is(): void
    {
        $this->get('/docs/getting-started/introduction.md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

        // The same path guard as the HTML page: no traversal, no invented pages.
        $this->get('/docs/does-not-exist.md')->assertNotFound();
        $this->get('/docs/../.env.md')->assertNotFound();
        $this->get('/docs/..%2F..%2F.env.md')->assertNotFound();
        $this->get('/composer.md')->assertNotFound();
    }

    /** @return list<string> */
    private function jsonLdTypes(string $html): array
    {
        preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);

        return array_map(fn (string $json): string => (string) (json_decode($json, true)['@type'] ?? ''), $m[1]);
    }
}
