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

    public function test_the_companion_packages_section_is_appended_to_the_package_nav(): void
    {
        $nav = app(DocsRepository::class)->nav();

        $sections = array_column($nav, 'text');

        $this->assertContains('Getting Started', $sections, 'The package nav should still lead.');
        $this->assertContains('Companion packages', $sections);
        $this->assertGreaterThan(
            array_search('Getting Started', $sections, true),
            array_search('Companion packages', $sections, true),
            'Site sections belong after everything the release documents about itself.',
        );
    }

    public function test_a_site_owned_page_renders(): void
    {
        $response = $this->get('/docs/packages/harness')->assertOk();

        $page = $response->viewData('page')['props']['page'];

        $this->assertSame('Prism Harness', $page['title']);
        $this->assertSame('Companion packages', $page['section']);
        $this->assertStringContainsString('composer require particle-academy/prism-harness', $page['html']);
    }

    public function test_site_pages_join_the_sitemap_search_index_and_llms_txt(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/docs/packages/overview'), false);

        $this->getJson('/docs-index.json')
            ->assertOk()
            ->assertJsonFragment(['link' => '/docs/packages/harness', 'section' => 'Companion packages', 'title' => 'Harness']);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('## Companion packages')
            ->assertSee('[Harness]('.url('/docs/packages/harness').')', false);
    }

    public function test_prev_and_next_cross_from_the_package_docs_into_the_site_section(): void
    {
        $page = $this->get('/docs/packages/overview')->assertOk()
            ->viewData('page')['props']['page'];

        // The last package page hands off to the first site page and back, so
        // the two sources read as one book rather than two.
        $this->assertSame('/docs/advanced/telemetry', $page['prev']['link']);
        $this->assertSame('/docs/packages/harness', $page['next']['link']);
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
