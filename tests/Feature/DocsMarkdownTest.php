<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Docs\DocsRepository;
use Tests\TestCase;

/**
 * The VitePress dialect this site has to render.
 *
 * The docs are authored for VitePress and rendered here by CommonMark, so
 * container blocks (`::: tip`, `::: code-group`) have to be translated first.
 * Every case below is one that shipped broken to the live site.
 */
class DocsMarkdownTest extends TestCase
{
    public function test_a_callout_body_is_parsed_as_markdown(): void
    {
        $html = $this->render(<<<'MD'
            ::: warning
            Approval responses are trusted input from **your application**.
            :::
            MD);

        // Regression: the generated title <p> sat directly against the body, so
        // CommonMark read the body as a continuation of that HTML block and
        // emitted it verbatim — every callout on the site rendered its emphasis
        // as literal asterisks.
        $this->assertStringContainsString('<strong>your application</strong>', $html);
        $this->assertStringNotContainsString('**your application**', $html);
    }

    public function test_content_after_a_callout_is_not_swallowed_by_it(): void
    {
        $html = $this->render(<<<'MD'
            ::: tip
            Pin your installation.
            :::

            ## Streaming
            MD);

        // Regression: `</div>` was emitted with no blank line after it, so the
        // next block stayed inside the HTML block. `## Streaming` reached the
        // page as literal text on core-concepts/human-in-the-loop.
        $this->assertStringContainsString('<h2>Streaming</h2>', $html);
        $this->assertStringNotContainsString('## Streaming', $html);
    }

    public function test_a_callout_title_falls_back_to_the_container_name(): void
    {
        $this->assertStringContainsString(
            '<p class="doc-callout-title">TIP</p>',
            $this->render("::: tip\nBody.\n:::"),
        );

        $this->assertStringContainsString(
            '<p class="doc-callout-title">Read this first</p>',
            $this->render("::: tip Read this first\nBody.\n:::"),
        );
    }

    public function test_a_code_group_does_not_leave_an_unclosed_div_behind(): void
    {
        $html = $this->render(<<<'MD'
            ::: code-group
            ```php [Anthropic]
            $x = 1;
            ```
            :::
            MD);

        // Regression: the code-group opener was dropped but its closing `:::`
        // was rewritten to `</div>` like any other, so every page with a code
        // group — the introduction included — shipped one stray closing tag.
        $this->assertSame(substr_count($html, '<div '), substr_count($html, '</div>'));
        $this->assertStringContainsString('<div class="doc-codelabel">Anthropic</div>', $html);
    }

    public function test_a_container_token_inside_a_fence_is_left_alone(): void
    {
        // A page documenting the container syntax should get the syntax, not a
        // callout — and, more to the point, not a stray `</div>` in a <pre>.
        $html = $this->render("```\n::: tip\nBody.\n:::\n```");

        $this->assertStringNotContainsString('doc-callout', $html);
        $this->assertStringNotContainsString('</div>', $html);
        $this->assertStringContainsString('::: tip', $html);
    }

    public function test_multibyte_content_survives_preprocessing(): void
    {
        // Regression: lines were split on `\R`, which outside UTF mode also
        // matches the single byte 0x85 — a continuation byte in ✅ (E2 9C 85)
        // among others. The provider support matrix came back cut mid-character
        // and CommonMark rejected the whole page.
        $html = $this->render("Supported: ✅ — and 🟡.\n");

        $this->assertStringContainsString('✅', $html);
        $this->assertStringContainsString('🟡', $html);
    }

    public function test_the_provider_matrix_page_still_renders(): void
    {
        $this->get('/docs/getting-started/introduction')
            ->assertOk()
            ->assertSee('Introduction');
    }

    private function render(string $markdown): string
    {
        $docs = app(DocsRepository::class);

        $processed = (fn (): string => $this->preprocess($markdown))->call($docs);

        return (fn (): string => $this->converter()->convert($processed)->getContent())->call($docs);
    }
}
