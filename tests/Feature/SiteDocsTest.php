<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Docs\DocsRepository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The site-owned half of the docs source.
 *
 * Almost every page on this site ships inside `particle-academy/prism`. These
 * cover the second root — `resources/docs` — which exists so the site can
 * document the packages *around* Prism without a line of it landing in a
 * package whose entire job is talking to providers.
 */
class SiteDocsTest extends TestCase
{
    public function test_every_declared_nav_entry_resolves_to_a_page(): void
    {
        $docs = app(DocsRepository::class);
        $sections = config('docs.sections');

        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections, 'The site declares no sections of its own.');

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($docs->isExternal($item)) {
                    continue;
                }

                // A nav entry naming a page nobody wrote must fail here rather
                // than render as a link to a 404 — that is the whole reason
                // siteNav() does not filter entries it cannot resolve.
                $this->assertTrue(
                    $docs->exists(ltrim($item['link'], '/')),
                    "Nav entry {$item['text']} points at {$item['link']}, which has no file.",
                );
            }
        }
    }

    public function test_the_site_sections_are_appended_to_the_package_nav(): void
    {
        $nav = app(DocsRepository::class)->nav();

        $sections = array_column($nav, 'text');

        $this->assertContains('Getting Started', $sections, 'The package nav should still lead.');
        $this->assertContains('Harness', $sections);
        $this->assertContains('The ecosystem', $sections);
        $this->assertGreaterThan(
            array_search('Getting Started', $sections, true),
            array_search('Harness', $sections, true),
            'Site sections belong after everything the release documents about itself.',
        );
    }

    public function test_the_upstream_companion_section_is_suppressed_so_it_cannot_compete(): void
    {
        // The package ships its own "Companion Packages" group, and the site
        // owns one too — so Harness, MCP, Perplexity and OpenTelemetry each
        // appeared TWICE in one nav at two different URLs, and nothing told a
        // reader which was canonical. Core is a provider API shuttle, so
        // documenting the packages around it belongs here, not in its tree.
        $sections = array_column(app(DocsRepository::class)->nav(), 'text');

        $this->assertNotContains('Companion Packages', $sections);
        $this->assertNotContains('Other Packages', $sections);

        // The positive control: the upstream nav must still be arriving, or
        // this assertion passes because nothing was parsed at all.
        $this->assertContains('Core Concepts', $sections, 'The upstream sidebar must still be parsed.');
    }

    public function test_a_site_owned_page_renders(): void
    {
        $response = $this->get('/docs/packages/harness')->assertOk();

        $page = $response->viewData('page')['props']['page'];

        $this->assertSame('Prism Harness', $page['title']);
        $this->assertSame('Harness', $page['section'], 'The Harness has a section of its own, not a bullet under companion packages.');
        $this->assertStringContainsString('composer require particle-academy/prism-harness', $page['html']);
    }

    public function test_new_satellite_and_language_pages_are_first_class_docs(): void
    {
        $expected = [
            '/docs/packages/browser' => 'Prism Browser',
            '/docs/packages/human-plus' => 'Prism Human+',
            '/docs/packages/language-ports' => 'Language ports',
        ];

        foreach ($expected as $uri => $title) {
            $page = $this->get($uri)->assertOk()->viewData('page')['props']['page'];

            $this->assertSame($title, $page['title']);
            $this->assertSame('The ecosystem', $page['section']);
        }

        $index = $this->getJson('/docs-index.json')->assertOk()->json();
        $links = array_column($index, 'link');

        $this->assertContains('/docs/packages/browser', $links);
        $this->assertContains('/docs/packages/human-plus', $links);
        $this->assertContains('/docs/packages/language-ports', $links);
    }

    public function test_each_language_has_a_first_class_building_guide_and_footer_link(): void
    {
        $expected = [
            '/docs/languages/php' => 'Building with PHP',
            '/docs/languages/typescript' => 'Building with TypeScript',
            '/docs/languages/python' => 'Building with Python',
        ];

        foreach ($expected as $uri => $title) {
            $page = $this->get($uri)->assertOk()->viewData('page')['props']['page'];

            $this->assertSame($title, $page['title']);
            $this->assertSame('Build with Prism', $page['section']);
        }

        $footer = File::get(resource_path('js/components/kinetic.tsx'));

        foreach (array_keys($expected) as $uri) {
            $this->assertStringContainsString("href=\"{$uri}\"", $footer);
        }
    }

    public function test_homepage_keeps_core_and_satellite_boundaries_explicit(): void
    {
        $source = File::get(resource_path('js/pages/Landing.tsx'));

        $this->assertStringContainsString('Agents that survive', $source);
        $this->assertStringContainsString('Browser and Human+ can bind attachments', $source);
        $this->assertStringContainsString('Prism is no longer', $source);
        $this->assertStringContainsString('cross-language provider API ecosystem', $source);
        $this->assertStringContainsString('PHP and Laravel are the mature reference', $source);
        $this->assertStringContainsString('focused, growing ports', $source);
        $this->assertStringContainsString('Core remains the provider API shuttle', $source);
    }

    public function test_site_pages_join_the_sitemap_search_index_and_llms_txt(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/docs/packages/overview'), false);

        $this->getJson('/docs-index.json')
            ->assertOk()
            ->assertJsonFragment(['link' => '/docs/packages/harness', 'section' => 'Harness', 'title' => 'What the Harness is']);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('## The ecosystem')
            ->assertSee('## Harness')
            ->assertSee('[What the Harness is]('.url('/docs/packages/harness').')', false);
    }

    public function test_prev_and_next_cross_from_the_package_docs_into_the_site_section(): void
    {
        // The last page the PACKAGE documents hands off to the first page the
        // SITE owns, so the two sources read as one book rather than two. That
        // crossing is the property; which site page happens to be first is an
        // editorial decision that moves.
        $first = $this->get('/docs/languages/php')->assertOk()
            ->viewData('page')['props']['page'];

        $this->assertSame('/docs/advanced/telemetry', $first['prev']['link'], 'The first site page must follow the last package page.');

        // And the site sections chain into each other rather than each starting
        // over: the Harness section is entered from the language guides, and
        // the ecosystem is entered from the end of the Harness section.
        $harness = $this->get('/docs/packages/harness')->assertOk()
            ->viewData('page')['props']['page'];

        $this->assertSame('/docs/languages/python', $harness['prev']['link']);
        $this->assertSame('/docs/harness/sessions', $harness['next']['link']);
    }

    public function test_a_package_page_wins_over_a_site_page_at_the_same_path(): void
    {
        $docs = app(DocsRepository::class);

        $collision = $docs->sitePath().'/getting-started/installation.md';

        // Only tear down what this test created. Blindly removing the directory
        // would delete a real site section the day somebody adds one here.
        $created = ! File::isDirectory(dirname($collision));
        File::ensureDirectoryExists(dirname($collision));
        File::put($collision, "# Not the release's words\n");

        try {
            $this->get('/docs/getting-started/installation')
                ->assertOk()
                ->assertDontSee("Not the release's words");
        } finally {
            File::delete($collision);

            if ($created) {
                File::deleteDirectory(dirname($collision));
            }
        }
    }

    public function test_a_site_page_cache_key_moves_when_the_source_does(): void
    {
        $docs = app(DocsRepository::class);

        $before = $this->siteKeyFor($docs, 'nav');

        // A deploy of this repo edits site docs while the Prism release — the
        // only thing the plain key is namespaced by — stays exactly where it
        // is. Without the fingerprint, rememberForever would serve the previous
        // deploy's copy for as long as that release held.
        config(['docs.sections' => [['text' => 'Elsewhere', 'items' => [['text' => 'X', 'link' => '/x']]]]]);

        $this->assertNotSame($before, $this->siteKeyFor(app(DocsRepository::class), 'nav'));
    }

    public function test_traversal_out_of_the_site_root_is_still_refused(): void
    {
        $this->get('/docs/packages/../../../../composer')->assertNotFound();
        $this->get('/docs/nope/never')->assertNotFound();
    }

    private function siteKeyFor(DocsRepository $docs, string $suffix): string
    {
        return (fn (): string => $this->siteCacheKey($suffix))->call($docs);
    }
}
