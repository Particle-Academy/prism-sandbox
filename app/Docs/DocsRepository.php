<?php

declare(strict_types=1);

namespace App\Docs;

use Composer\InstalledVersions;
use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tempest\Highlight\CommonMark\HighlightExtension;

class DocsRepository
{
    /**
     * Shape version for everything this class caches forever.
     *
     * The cache key used to be namespaced by package version alone, so a
     * deploy that changed the *shape* of a cached structure (rather than the
     * Prism release) kept reading the old shape back — which took sitemap.xml
     * and llms.txt down with a 500 when `external` was added to nav entries.
     * Bump this whenever a cached structure changes, or the rendering of one
     * does (8: GitHub alerts, <ExceptionSupport />, custom heading ids).
     */
    private const SCHEMA = 8;

    private ?string $siteFingerprint = null;

    /**
     * The Prism package's own docs root.
     *
     * Everything the release documents about itself lives here, shipped inside
     * the composer package, which is why this site never has to be redeployed
     * to publish a new version's docs.
     */
    public function basePath(): string
    {
        return base_path('vendor/particle-academy/prism/docs');
    }

    /**
     * This repository's own docs root.
     *
     * Prism core is a provider API shuttle and stays that way — documentation
     * for the packages *around* it does not belong inside it. So the site
     * carries a second source, and pages that are about the ecosystem rather
     * than the release live here instead of being pushed upstream.
     */
    public function sitePath(): string
    {
        return resource_path('docs');
    }

    /**
     * Where a page can come from, in resolution order.
     *
     * The package first, always. A site-owned file can never shadow one the
     * release ships: if core ever documents a path this repo also has, core
     * wins and the site page becomes dead weight rather than a silent
     * override of the release's own words.
     *
     * @return array<int, string>
     */
    public function roots(): array
    {
        return [$this->basePath(), $this->sitePath()];
    }

    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('particle-academy/prism') ?? 'dev';
    }

    /**
     * The package's nav, then this site's own sections appended after it.
     *
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    public function nav(): array
    {
        return Cache::rememberForever($this->siteCacheKey('nav'), fn (): array => [
            ...$this->packageNav(),
            ...$this->siteNav(),
        ]);
    }

    /**
     * Navigation tree parsed from the package's VitePress sidebar config, so
     * the site's nav always matches the installed release.
     *
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    protected function packageNav(): array
    {
        $config = @file_get_contents($this->basePath().'/.vitepress/config.mts');

        if ($config !== false) {
            $literal = JsLiteralParser::extractArray($config, 'sidebar');
            $sidebar = $literal !== null ? JsLiteralParser::parse($literal) : null;

            if (is_array($sidebar)) {
                $groups = $sidebar[0]['items'] ?? $sidebar;
                $nav = $this->withoutSuperseded($this->normalizeNav($groups));

                if ($nav !== []) {
                    return $nav;
                }
            }
        }

        return $this->navFromDirectories();
    }

    /**
     * Drop the upstream sections this site now owns outright.
     *
     * The package's sidebar carries its own "Companion Packages" group, and so
     * does this site — so Harness, MCP, Perplexity and OpenTelemetry each
     * appeared TWICE in one nav, at two different URLs, while Memory and
     * Workspace appeared only in the upstream one. A reader could not tell
     * which was canonical, because nothing said.
     *
     * The site wins that argument for a reason already written into
     * `config/docs.php`: Prism core is a provider API shuttle and stays one, so
     * documentation for the packages AROUND core is not documentation of core.
     * Keeping the ecosystem story here means it can cover nine families in
     * three languages without any of that living in the core package's tree.
     *
     * Matched on title rather than on link, because the upstream group is
     * defined by what it is called; its links move between releases and its
     * title has not.
     *
     * @param  array<int, array{text: string, items: array<int, array{text: string, link: string}>}>  $nav
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    protected function withoutSuperseded(array $nav): array
    {
        $superseded = array_map(
            fn ($title): string => mb_strtolower(trim((string) $title)),
            (array) config('docs.superseded_package_sections', []),
        );

        return array_values(array_filter(
            $nav,
            fn (array $section): bool => ! in_array(mb_strtolower(trim($section['text'])), $superseded, true),
        ));
    }

    /**
     * The nav sections this site owns, declared in `config/docs.php`.
     *
     * Declared rather than globbed: order and titles are editorial decisions,
     * and a directory scan would sort them alphabetically and headline the
     * filenames. A section naming a page that does not exist is a typo CI
     * catches — `SiteDocsTest` walks every entry — not something this method
     * quietly drops, because a dropped nav entry looks exactly like a page
     * nobody wrote.
     *
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    protected function siteNav(): array
    {
        $sections = config('docs.sections', []);

        return $this->normalizeNav(is_array($sections) ? $sections : []);
    }

    /**
     * Every nav entry, including the absolute links to companion packages that
     * the upstream sidebar mixes in. Only the sidebar wants those — anything
     * that builds a URL from a nav entry wants pages() instead.
     *
     * @return array<int, array{text: string, link: string, external: bool, section: string}>
     */
    public function flatNav(): array
    {
        $flat = [];

        foreach ($this->nav() as $group) {
            foreach ($group['items'] as $item) {
                $flat[] = [...$item, 'section' => $group['text']];
            }
        }

        return $flat;
    }

    /**
     * Nav entries that are actually pages on this site — external links
     * dropped. Prefixing one of those with /docs yields a nonsense URL, so
     * sitemap, llms.txt, search, and prev/next all build from this.
     *
     * @return array<int, array{text: string, link: string, external: bool, section: string}>
     */
    public function pages(): array
    {
        return array_values(array_filter(
            $this->flatNav(),
            fn (array $item): bool => ! $this->isExternal($item),
        ));
    }

    /**
     * Whether a nav entry points off-site. Falls back to inspecting the link
     * so a nav array from any source — including a cache entry written by an
     * older deploy — is still classified correctly rather than fataling.
     *
     * @param  array{link: string, external?: bool}  $item
     */
    public function isExternal(array $item): bool
    {
        return $item['external'] ?? (bool) preg_match('#^https?://#i', $item['link']);
    }

    public function exists(string $path): bool
    {
        // Port sources are fragments of public guides, not separate pages.
        if (! $this->validRelativePath($path) || str_starts_with($path, 'ports/')) {
            return false;
        }

        // Contributor docs shipped in the package's docs/ folder, not user pages.
        if (in_array(strtolower($path), ['readme', 'documentation-style-guide'], true)) {
            return false;
        }

        return is_file($this->pageFile($path));
    }

    /**
     * @return array{title: string, html: string, toc: array<int, array{id: string, text: string, level: int}>, prev: ?array{text: string, link: string}, next: ?array{text: string, link: string}, section: ?string, languages: array<int, string>, defaultLanguage: ?string, variants: array<string, array{html: string, toc: array}>}
     */
    public function page(string $path): array
    {
        $file = $this->pageFile($path);
        // Package pages also carry site-owned port examples.
        $key = $this->siteCacheKey("page:$path");

        return Cache::rememberForever($key, function () use ($file, $path): array {
            $markdown = (string) file_get_contents($file);
            $markdown = $this->preprocess($markdown);

            $html = $this->converter()->convert($markdown)->getContent();
            [$html, $toc] = $this->anchorHeadings($html);
            $html = $this->rewriteLinks($html);

            preg_match_all('/data-doc-language="(php|typescript|python)"/', $html, $matches);
            $languages = array_values(array_intersect(
                ['php', 'typescript', 'python'],
                array_unique($matches[1] ?? []),
            ));
            $title = $this->titleFor($path, $html);
            [$prev, $next, $section] = $this->siblings($path);

            $portFile = $this->portFile($path);
            $variants = [];

            if (is_file($portFile)) {
                $portHtml = $this->converter()->convert($this->preprocess((string) file_get_contents($portFile)))->getContent();
                // Shared guides (schemas, unavailable providers) keep the page's identity.
                $portHtml = (string) preg_replace_callback('/<h1>.*?<\/h1>/s', fn (): string => '<h1>'.e($title).'</h1>', $portHtml, 1);
                [$portHtml, $portToc] = $this->anchorHeadings($portHtml);
                $portHtml = $this->rewriteLinks($portHtml);
                $variants = [
                    'typescript' => ['html' => $portHtml, 'toc' => $portToc],
                    'python' => ['html' => $portHtml, 'toc' => $portToc],
                ];
                $languages = ['php', 'typescript', 'python'];
            }
            $defaultLanguage = $this->defaultLanguage($path, $languages);

            return compact('title', 'html', 'toc', 'prev', 'next', 'section', 'languages', 'defaultLanguage', 'variants');
        });
    }

    /**
     * A page's meta description: its first real paragraph, as plain text, cut
     * on a word boundary near 160 characters. Empty when the page opens with
     * no prose, so the caller can fall back to a site default.
     */
    public function description(string $path): string
    {
        $html = $this->page($path)['html'];

        foreach (preg_split('#</p>#i', $html) ?: [] as $chunk) {
            $start = stripos($chunk, '<p');

            if ($start === false) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(substr($chunk, $start)), ENT_QUOTES | ENT_HTML5)));

            if (mb_strlen($text) >= 40) {
                return mb_strlen($text) <= 160 ? $text : rtrim(Str::limit($text, 157, ''), ' ,;:.').'…';
            }
        }

        return '';
    }

    /**
     * A page as Markdown for LLM crawlers (`/docs/<page>.md`), or null when no
     * such page exists. Goes through the same path guard as the HTML page.
     */
    public function markdown(string $path): ?string
    {
        if (! $this->exists($path)) {
            return null;
        }

        return $this->preprocess((string) file_get_contents($this->pageFile($path)));
    }

    /** How many provider guides the docs carry, counted from the nav rather than written down. */
    public function providerGuideCount(): int
    {
        foreach ($this->nav() as $group) {
            if ($group['text'] === 'Providers') {
                return count($group['items']);
            }
        }

        return 0;
    }

    /**
     * @return array<int, array{title: string, link: string, section: string, text: string}>
     */
    public function searchIndex(): array
    {
        return Cache::rememberForever($this->siteCacheKey('search'), function (): array {
            $index = [];

            foreach ($this->pages() as $item) {
                $file = $this->pageFile(ltrim($item['link'], '/'));

                if (! is_file($file)) {
                    continue;
                }

                $markdown = (string) file_get_contents($file);
                $portFile = $this->portFile(ltrim($item['link'], '/'));
                if (is_file($portFile)) {
                    $markdown .= "\n".(string) file_get_contents($portFile);
                }
                $text = $this->plainText($markdown);

                $index[] = [
                    'title' => $item['text'],
                    'link' => '/docs'.$item['link'],
                    'section' => $item['section'],
                    'text' => Str::limit($text, 600, ''),
                ];
            }

            return $index;
        });
    }

    public function assetFile(string $path): string
    {
        return $this->fileWithinRoot($this->basePath().'/public', $path);
    }

    protected function portFile(string $path): string
    {
        if (! $this->validRelativePath($path)) {
            return '';
        }

        $source = (string) config('docs.port_guides.'.$path, 'ports/'.$path);

        return $this->fileWithinRoot($this->sitePath(), $source, '.md');
    }

    protected function pageFile(string $path): string
    {
        if (! $this->validRelativePath($path)) {
            return '';
        }

        $sourcePath = (string) config("docs.page_aliases.$path", $path);

        foreach ($this->roots() as $root) {
            $file = $this->fileWithinRoot($root, $sourcePath, '.md');

            if ($file !== '') {
                return $file;
            }
        }

        return '';
    }

    protected function validRelativePath(string $path): bool
    {
        // Laravel has already URL-decoded the route parameter. Reject segments
        // as received: removing substrings can manufacture new traversal.
        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)
                || str_contains($segment, '\\') || str_contains($segment, "\0")) {
                return false;
            }
        }

        return true;
    }

    protected function fileWithinRoot(string $root, string $path, string $suffix = ''): string
    {
        if (! $this->validRelativePath($path)) {
            return '';
        }

        $root = realpath($root);

        if ($root === false) {
            return '';
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $file = realpath($prefix.$path.$suffix);

        // Canonical paths also prevent symlink escapes. The separator excludes
        // siblings such as public-evil from a root named public.
        if ($file === false || ! str_starts_with($file, $prefix) || ! is_file($file)) {
            return '';
        }

        return $file;
    }

    /**
     * @param  array<int, string>  $languages
     */
    protected function defaultLanguage(string $path, array $languages): ?string
    {
        if ($languages === []) {
            return null;
        }

        $preferred = config('docs.language_defaults.'.trim($path, '/'));

        return is_string($preferred) && in_array($preferred, $languages, true)
            ? $preferred
            : null;
    }

    protected function isSiteFile(string $file): bool
    {
        return str_starts_with($file, $this->sitePath().'/');
    }

    protected function cacheKey(string $suffix): string
    {
        return 'docs:'.self::SCHEMA.':'.$this->version().':'.$suffix;
    }

    /**
     * Cache key for anything whose content includes site-owned docs.
     *
     * The plain key is namespaced by the installed Prism release, which is
     * exactly right for package pages: they cannot change unless it does.
     * Site-owned pages can — a deploy of this repo edits them while Prism
     * stays put — and `rememberForever` would then keep serving the previous
     * deploy's copy for as long as the release held, which on a site that
     * tracks stable releases is weeks. So their keys carry a fingerprint of
     * the source instead.
     */
    protected function siteCacheKey(string $suffix): string
    {
        return $this->cacheKey($this->siteFingerprint().':'.$suffix);
    }

    /**
     * A short digest of every site-owned docs input.
     *
     * Covers all docs configuration as well as the files, so reordering a
     * section or changing a language alias/default without touching a page
     * still invalidates. Memoised because sitemap and llms.txt ask for several
     * cached structures in one request.
     */
    protected function siteFingerprint(): string
    {
        if ($this->siteFingerprint !== null) {
            return $this->siteFingerprint;
        }

        $parts = [(string) json_encode(config('docs', []))];

        foreach ($this->siteFiles() as $file) {
            $parts[] = $file.':'.(string) filemtime($file).':'.(string) filesize($file);
        }

        return $this->siteFingerprint = substr(sha1(implode('|', $parts)), 0, 12);
    }

    /**
     * Every site-owned markdown file, in a stable order.
     *
     * @return array<int, string>
     */
    protected function siteFiles(): array
    {
        if (! is_dir($this->sitePath())) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sitePath(), FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    protected function converter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new HighlightExtension);

        return new MarkdownConverter($environment);
    }

    protected function preprocess(string $markdown): string
    {
        // Frontmatter.
        $markdown = (string) preg_replace('/\A---\n.*?\n---\n/s', '', $markdown);

        // Vue script-setup blocks.
        $markdown = (string) preg_replace('/<script setup>.*?<\/script>\s*/s', '', $markdown);

        // The provider support matrix component becomes a static table.
        if (str_contains($markdown, '<ProviderSupport />')) {
            $markdown = str_replace('<ProviderSupport />', $this->providerSupportTable(), $markdown);
        }

        // ...and so does the exception mapping matrix.
        if (str_contains($markdown, '<ExceptionSupport />')) {
            $markdown = str_replace('<ExceptionSupport />', $this->exceptionSupportTable(), $markdown);
        }

        // GitHub-style `> [!NOTE]` alerts become the matching callout container.
        $markdown = $this->alerts($markdown);

        // ::: code-group / ::: tip|warning|… and the bare ::: that closes one.
        $markdown = $this->containers($markdown);

        // ```php [Label] fences → label chip above the fence.
        $markdown = (string) preg_replace_callback(
            '/^```([a-zA-Z0-9_-]*)[ \t]+\[([^\]]+)\][ \t]*$/m',
            fn (array $m): string => "<div class=\"doc-codelabel\">{$m[2]}</div>\n\n```{$m[1]}",
            $markdown
        );

        return $markdown;
    }

    /**
     * VitePress container blocks: `::: code-group`, `::: tip|warning|…`, and
     * the bare `:::` that closes whichever of them is open.
     *
     * Walked line by line rather than matched with three independent regexes,
     * because the closing token is identical for every container and a pattern
     * that rewrote every `:::` into `</div>` also emitted one for each
     * code-group — whose opener is dropped and which therefore has nothing to
     * close. That left an unbalanced `</div>` on every page using a code group,
     * the introduction included.
     *
     * Blank lines around the generated HTML are the other half of the fix.
     * CommonMark runs an HTML block to the next blank line, so a callout whose
     * `<p>` title was followed immediately by its body swallowed that body as
     * raw HTML — `**bold**` reached the page as asterisks — and a `</div>` sat
     * directly against whatever came next, eating the heading after it.
     *
     * Fenced code is copied through untouched, so a `:::` being *documented*
     * inside a fence is not mistaken for a container.
     */
    protected function containers(string $markdown): string
    {
        $out = [];
        $open = [];
        $fenced = false;
        $languageFence = null;

        // Split on the three line endings CommonMark recognises, spelled out.
        // `\R` looks like the tidier choice and is a trap: outside UTF mode it
        // also matches the single byte 0x85, which is a continuation byte in
        // plenty of UTF-8 sequences — ✅ among them — so the provider support
        // table came back cut mid-character and CommonMark rejected the input.
        foreach (preg_split("/\r\n|\n|\r/", $markdown) ?: [] as $line) {
            if (preg_match('/^ {0,3}(`{3,}|~{3,})([a-zA-Z0-9_-]*)/', $line, $fence) === 1) {
                if (! $fenced) {
                    $language = null;

                    if (end($open) === 'language-group') {
                        $language = $this->normalizeCodeLanguage($fence[2] ?? '');

                        // Shell, JSON and configuration examples still vary by
                        // package language. An explicit marker chooses the
                        // variant while leaving syntax highlighting truthful.
                        if (preg_match('/[ \t]+\[language=(php|typescript|python)\][ \t]*$/i', $line, $variant) === 1) {
                            $language = $this->normalizeCodeLanguage($variant[1]);
                            $line = (string) preg_replace('/[ \t]+\[language=(php|typescript|python)\][ \t]*$/i', '', $line);
                        }
                    }

                    if ($language !== null) {
                        $out[] = '<div class="doc-language-code" data-doc-language="'.$language.'">';
                        $out[] = '';
                        $languageFence = $language;
                    }

                    $fenced = true;
                    $out[] = $line;

                    continue;
                }

                $out[] = $line;

                $fenced = false;

                if ($languageFence !== null) {
                    $out[] = '';
                    $out[] = '</div>';
                    $out[] = '';
                    $languageFence = null;
                }

                continue;
            }

            if ($fenced) {
                $out[] = $line;

                continue;
            }

            if (preg_match('/^::: ?code-group[ \t]*$/', $line) === 1) {
                // Unwrapped: each fence inside keeps its own [label], which the
                // code-label pass turns into a chip.
                $open[] = 'code-group';

                continue;
            }

            if (preg_match('/^::: ?language-group[ \t]*$/', $line) === 1) {
                // Each fence becomes a data-labelled block. React owns one
                // page-level language value, so changing it updates every
                // example at once without duplicating the surrounding prose.
                $open[] = 'language-group';

                continue;
            }

            if (preg_match('/^::: ?(tip|warning|danger|info|details)[ \t]*(.*)$/', $line, $m) === 1) {
                $title = trim($m[2]) !== '' ? trim($m[2]) : strtoupper($m[1]);
                $open[] = 'callout';

                $out[] = '<div class="doc-callout doc-callout--'.$m[1].'">';
                $out[] = '';
                $out[] = '<p class="doc-callout-title">'.$title.'</p>';
                $out[] = '';

                continue;
            }

            if (preg_match('/^:::[ \t]*$/', $line) === 1) {
                if (array_pop($open) === 'callout') {
                    $out[] = '';
                    $out[] = '</div>';
                    $out[] = '';
                }

                continue;
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    protected function normalizeCodeLanguage(string $language): ?string
    {
        return match (strtolower($language)) {
            'php' => 'php',
            'ts', 'tsx', 'typescript' => 'typescript',
            'py', 'python' => 'python',
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: array<int, array{id: string, text: string, level: int}>}
     */
    protected function anchorHeadings(string $html): array
    {
        $toc = [];
        $seen = [];

        $html = (string) preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/s',
            function (array $m) use (&$toc, &$seen): string {
                // VitePress custom ids: `## Title {#custom-id}`. Other pages link
                // to that id, and the braces must not reach the reader.
                $customId = null;
                if (preg_match('/\s*\{#([A-Za-z0-9_-]+)\}\s*$/', $m[2], $custom) === 1) {
                    $customId = $custom[1];
                    $m[2] = substr($m[2], 0, -strlen($custom[0]));
                }

                $text = trim(strip_tags($m[2]));
                $id = $customId ?? (Str::slug($text) ?: 'section');

                while (isset($seen[$id])) {
                    $id .= '-1';
                }
                $seen[$id] = true;

                $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) $m[1]];

                return "<h{$m[1]} id=\"{$id}\">{$m[2]}<a class=\"doc-anchor\" href=\"#{$id}\" aria-label=\"Link to {$text}\">#</a></h{$m[1]}>";
            },
            $html
        );

        return [$html, $toc];
    }

    protected function rewriteLinks(string $html): string
    {
        // Internal doc links: absolute paths that aren't assets get the /docs prefix.
        $html = (string) preg_replace_callback(
            '/href="(\/[^"#]*?)(#[^"]*)?"/',
            function (array $m): string {
                $path = (string) preg_replace('/\.(md|html)$/', '', $m[1]);
                $hash = $m[2] ?? '';

                if (preg_match('/\.[a-z0-9]{2,4}$/i', $path) === 1 || str_starts_with($path, '/docs')) {
                    return "href=\"{$path}{$hash}\"";
                }

                return "href=\"/docs{$path}{$hash}\"";
            },
            $html
        );

        // Images served from the package's docs/public folder.
        return (string) preg_replace('/src="\/(?!docs-assets\/)([^"]+)"/', 'src="/docs-assets/$1"', $html);
    }

    protected function titleFor(string $path, string $html): string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $m) === 1) {
            return trim(strip_tags($m[1]));
        }

        foreach ($this->flatNav() as $item) {
            if (trim($item['link'], '/') === trim($path, '/')) {
                return $item['text'];
            }
        }

        return Str::headline(basename($path));
    }

    /**
     * @return array{0: ?array{text: string, link: string}, 1: ?array{text: string, link: string}, 2: ?string}
     */
    protected function siblings(string $path): array
    {
        $flat = $this->pages();
        $needle = '/'.trim($path, '/');

        foreach ($flat as $i => $item) {
            if ($item['link'] === $needle) {
                $prev = $flat[$i - 1] ?? null;
                $next = $flat[$i + 1] ?? null;

                return [
                    $prev ? ['text' => $prev['text'], 'link' => '/docs'.$prev['link']] : null,
                    $next ? ['text' => $next['text'], 'link' => '/docs'.$next['link']] : null,
                    $item['section'],
                ];
            }
        }

        return [null, null, null];
    }

    /**
     * @param  array<int, mixed>  $groups
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    protected function normalizeNav(array $groups): array
    {
        $nav = [];

        foreach ($groups as $group) {
            if (! is_array($group) || ! isset($group['text'])) {
                continue;
            }

            $items = [];

            foreach ($group['items'] ?? [] as $item) {
                if (isset($item['text'], $item['link'])) {
                    $link = rtrim($item['link'], '/');

                    $items[] = [
                        'text' => $item['text'],
                        'link' => $link,
                        'external' => (bool) preg_match('#^https?://#i', $link),
                    ];
                }
            }

            if ($items !== []) {
                $nav[] = ['text' => $group['text'], 'items' => $items];
            }
        }

        return $nav;
    }

    /**
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    protected function navFromDirectories(): array
    {
        $nav = [];

        foreach (glob($this->basePath().'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);

            if (in_array($name, ['.vitepress', 'components', 'public', 'node_modules'], true)) {
                continue;
            }

            $items = [];

            foreach (glob($dir.'/*.md') ?: [] as $file) {
                $slug = basename($file, '.md');
                $items[] = ['text' => Str::headline($slug), 'link' => "/$name/$slug", 'external' => false];
            }

            if ($items !== []) {
                $nav[] = ['text' => Str::headline($name), 'items' => $items];
            }
        }

        return $nav;
    }

    protected function providerSupportTable(): string
    {
        $vue = @file_get_contents($this->basePath().'/components/ProviderSupport.vue');

        if ($vue === false) {
            return '';
        }

        $featuresLiteral = JsLiteralParser::extractArray($vue, 'features');
        $providersLiteral = JsLiteralParser::extractArray($vue, 'providers');
        $features = $featuresLiteral !== null ? JsLiteralParser::parse($featuresLiteral) : null;
        $providers = $providersLiteral !== null ? JsLiteralParser::parse($providersLiteral) : null;

        if (! is_array($features) || ! is_array($providers)) {
            return '';
        }

        $marks = ['supported' => '✅', 'planned' => '🟡', 'adapted' => '⚙️'];

        $table = '| Provider | '.implode(' | ', $features)." |\n";
        $table .= '| --- '.str_repeat('| :---: ', count($features))."|\n";

        foreach ($providers as $provider) {
            $cells = array_map(
                fn (string $feature): string => $marks[strtolower((string) ($provider[strtolower($feature)] ?? ''))] ?? '—',
                $features
            );
            $table .= '| **'.($provider['name'] ?? '?').'** | '.implode(' | ', $cells)." |\n";
        }

        return $table."\n✅ supported · 🟡 planned · ⚙️ adapted · — not supported\n";
    }

    /**
     * The `<ExceptionSupport />` matrix: which HTTP failures each provider maps
     * to a typed exception, read from the component's own data so the two
     * cannot drift. Its values are JS constants (`Supported`), which the
     * literal parser returns as their names.
     */
    protected function exceptionSupportTable(): string
    {
        $vue = @file_get_contents($this->basePath().'/components/ExceptionSupport.vue');

        if ($vue === false) {
            return '';
        }

        $exceptionsLiteral = JsLiteralParser::extractArray($vue, 'exceptions');
        $providersLiteral = JsLiteralParser::extractArray($vue, 'providers');
        $exceptions = $exceptionsLiteral !== null ? JsLiteralParser::parse($exceptionsLiteral) : null;
        $providers = $providersLiteral !== null ? JsLiteralParser::parse($providersLiteral) : null;

        if (! is_array($exceptions) || ! is_array($providers)) {
            return '';
        }

        $table = '| Provider | '.implode(' | ', $exceptions)." |\n";
        $table .= '| --- '.str_repeat('| :---: ', count($exceptions))."|\n";

        foreach ($providers as $provider) {
            $cells = array_map(
                fn (string $exception): string => strtolower((string) ($provider[Str::camel($exception)] ?? '')) === 'supported' ? '✅' : '—',
                $exceptions
            );
            $table .= '| **'.($provider['name'] ?? '?').'** | '.implode(' | ', $cells)." |\n";
        }

        return $table."\n✅ mapped to a typed exception · — not mapped\n";
    }

    /**
     * GitHub-style alerts (`> [!NOTE]` followed by quoted lines) as callouts.
     * Unconverted, CommonMark renders the marker as literal text in a quote.
     * Fenced code is copied through untouched.
     */
    protected function alerts(string $markdown): string
    {
        $types = [
            'NOTE' => ['info', 'Note'],
            'TIP' => ['tip', 'Tip'],
            'IMPORTANT' => ['info', 'Important'],
            'WARNING' => ['warning', 'Warning'],
            'CAUTION' => ['danger', 'Caution'],
        ];

        $out = [];
        $fenced = false;
        $inAlert = false;

        foreach (preg_split("/\r\n|\n|\r/", $markdown) ?: [] as $line) {
            // An open alert ends at the first unquoted line, which is then read
            // like any other — INCLUDING as a fence. Checking for fences first
            // skipped the toggle when a fence directly ended an alert, and every
            // alert after it on the page was read as code and left raw.
            if ($inAlert) {
                if (preg_match('/^ {0,3}>[ ]?(.*)$/', $line, $m) === 1) {
                    // A second marker inside the same quote (`>` blank line,
                    // then `> [!WARNING]`) starts the next callout.
                    if (preg_match('/^[ \t]*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*$/i', $m[1], $next) === 1) {
                        [$container, $title] = $types[strtoupper($next[1])];
                        array_push($out, ':::', '', "::: {$container} {$title}");

                        continue;
                    }

                    $out[] = $m[1];

                    continue;
                }

                $out[] = ':::';
                $out[] = '';
                $inAlert = false;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line) === 1) {
                $fenced = ! $fenced;
            }

            if ($fenced || preg_match('/^ {0,3}(`{3,}|~{3,})/', $line) === 1) {
                $out[] = $line;

                continue;
            }

            if (preg_match('/^ {0,3}>[ \t]*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*$/i', $line, $m) === 1) {
                [$container, $title] = $types[strtoupper($m[1])];
                $out[] = '';
                $out[] = "::: {$container} {$title}";
                $inAlert = true;

                continue;
            }

            $out[] = $line;
        }

        if ($inAlert) {
            $out[] = ':::';
        }

        return implode("\n", $out);
    }

    protected function plainText(string $markdown): string
    {
        $text = $this->preprocess($markdown);
        $text = (string) preg_replace('/```.*?```/s', ' ', $text);
        $text = (string) preg_replace('/<[^>]+>/', ' ', $text);
        $text = (string) preg_replace('/[#>*_`\[\]()|-]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
