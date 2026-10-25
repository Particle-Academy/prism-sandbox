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

    public function test_a_language_group_labels_each_variant_without_changing_its_highlighter(): void
    {
        $html = $this->render(<<<'MD'
            ::: language-group
            ```bash [language=php]
            composer require particle-academy/prism
            ```

            ```ts
            await Prism.text().asText();
            ```

            ```python
            Prism.text().as_text()
            ```
            :::
            MD);

        $this->assertStringContainsString('data-doc-language="php"', $html);
        $this->assertStringContainsString('data-doc-language="typescript"', $html);
        $this->assertStringContainsString('data-doc-language="python"', $html);
        $this->assertStringContainsString('data-lang="bash"', $html);
        $this->assertStringNotContainsString('[language=php]', $html);
        $this->assertSame(3, substr_count($html, 'class="doc-language-code"'));
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

    public function test_a_github_alert_renders_as_a_callout(): void
    {
        $html = $this->render(<<<'MD'
            Before.

            > [!WARNING]
            > Never expose the engine to the model.
            > It bypasses **the policy**.

            After.
            MD);

        // Regression: the marker reached the page as a literal "[!WARNING]" quote.
        $this->assertStringNotContainsString('[!WARNING]', $html);
        $this->assertStringNotContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<p class="doc-callout-title">Warning</p>', $html);
        $this->assertStringContainsString('<strong>the policy</strong>', $html);
        $this->assertStringContainsString('<p>After.</p>', $html);
    }

    public function test_a_fence_that_ends_an_alert_does_not_swallow_the_alerts_after_it(): void
    {
        $html = $this->render(<<<'MD'
            > [!TIP]
            > Validate it first:
            ```php
            $ok = true;
            ```

            > [!NOTE]
            > Only the final step contains structured data.
            MD);

        // Regression: the fence ending the first alert skipped the fence toggle,
        // so the rest of core-concepts/structured-output was read as code and
        // every later alert reached the page as a literal "[!NOTE]" quote.
        $this->assertStringNotContainsString('[!NOTE]', $html);
        $this->assertStringContainsString('<p class="doc-callout-title">Note</p>', $html);
        $this->assertStringContainsString('$ok', $html);
    }

    public function test_two_alerts_in_one_quote_become_two_callouts(): void
    {
        $html = $this->render(<<<'MD'
            > [!NOTE]
            > Keep-alive comments are safe to ignore.
            >
            > [!WARNING]
            > Mid-stream failures arrive as ordinary payloads.
            MD);

        // Regression: providers/openrouter showed "[!WARNING]" as literal text.
        $this->assertStringNotContainsString('[!WARNING]', $html);
        $this->assertStringContainsString('<p class="doc-callout-title">Note</p>', $html);
        $this->assertStringContainsString('<p class="doc-callout-title">Warning</p>', $html);
    }

    public function test_an_alert_inside_a_code_fence_is_left_alone(): void
    {
        $html = $this->render("```md\n> [!NOTE]\n> literal\n```");

        $this->assertStringContainsString('[!NOTE]', $html);
        $this->assertStringNotContainsString('doc-callout', $html);
    }

    public function test_an_ordinary_quote_stays_a_quote(): void
    {
        $this->assertStringContainsString('<blockquote>', $this->render('> Just a quote.'));
    }

    public function test_the_exception_support_component_renders_a_table(): void
    {
        $html = $this->render('<ExceptionSupport />');

        // Regression: the component rendered nothing at all on this site.
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('Rate Limited', $html);
        $this->assertStringContainsString('<strong>Anthropic</strong>', $html);
    }

    public function test_a_custom_heading_id_is_used_and_not_shown(): void
    {
        $docs = app(DocsRepository::class);
        $html = $this->render("## What isn't built {#what-isn-t-built}");

        [$html, $toc] = (fn (): array => $this->anchorHeadings($html))->call($docs);

        $this->assertStringContainsString('id="what-isn-t-built"', $html);
        $this->assertStringNotContainsString('{#', $html);
        $this->assertSame("What isn't built", html_entity_decode($toc[0]['text'], ENT_QUOTES));
    }

    public function test_contributor_docs_are_not_pages(): void
    {
        $docs = app(DocsRepository::class);

        $this->assertFalse($docs->exists('README'));
        $this->assertFalse($docs->exists('documentation-style-guide'));
        $this->get('/docs/README')->assertNotFound();
    }

    private function render(string $markdown): string
    {
        $docs = app(DocsRepository::class);

        $processed = (fn (): string => $this->preprocess($markdown))->call($docs);

        return (fn (): string => $this->converter()->convert($processed)->getContent())->call($docs);
    }
}
