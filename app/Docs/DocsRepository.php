<?php

declare(strict_types=1);

namespace App\Docs;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
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
     * Bump this whenever a cached structure changes.
     */
    private const SCHEMA = 2;

    public function basePath(): string
    {
        return base_path('vendor/particle-academy/prism/docs');
    }

    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('particle-academy/prism') ?? 'dev';
    }

    /**
     * Navigation tree parsed from the package's VitePress sidebar config, so
     * the site's nav always matches the installed release.
     *
     * @return array<int, array{text: string, items: array<int, array{text: string, link: string}>}>
     */
    public function nav(): array
    {
        return Cache::rememberForever($this->cacheKey('nav'), function (): array {
            $config = @file_get_contents($this->basePath().'/.vitepress/config.mts');

            if ($config !== false) {
                $literal = JsLiteralParser::extractArray($config, 'sidebar');
                $sidebar = $literal !== null ? JsLiteralParser::parse($literal) : null;

                if (is_array($sidebar)) {
                    $groups = $sidebar[0]['items'] ?? $sidebar;
                    $nav = $this->normalizeNav($groups);

                    if ($nav !== []) {
                        return $nav;
                    }
                }
            }

            return $this->navFromDirectories();
        });
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
        return is_file($this->pageFile($path));
    }

    /**
     * @return array{title: string, html: string, toc: array<int, array{id: string, text: string, level: int}>, prev: ?array{text: string, link: string}, next: ?array{text: string, link: string}, section: ?string}
     */
    public function page(string $path): array
    {
        return Cache::rememberForever($this->cacheKey("page:$path"), function () use ($path): array {
            $markdown = (string) file_get_contents($this->pageFile($path));
            $markdown = $this->preprocess($markdown);

            $html = $this->converter()->convert($markdown)->getContent();
            [$html, $toc] = $this->anchorHeadings($html);
            $html = $this->rewriteLinks($html);

            $title = $this->titleFor($path, $html);
            [$prev, $next, $section] = $this->siblings($path);

            return compact('title', 'html', 'toc', 'prev', 'next', 'section');
        });
    }

    /**
     * @return array<int, array{title: string, link: string, section: string, text: string}>
     */
    public function searchIndex(): array
    {
        return Cache::rememberForever($this->cacheKey('search'), function (): array {
            $index = [];

            foreach ($this->pages() as $item) {
                $file = $this->pageFile(ltrim($item['link'], '/'));

                if (! is_file($file)) {
                    continue;
                }

                $markdown = (string) file_get_contents($file);
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

    protected function pageFile(string $path): string
    {
        $path = trim(str_replace(['..', '\\'], '', $path), '/');

        return $this->basePath().'/'.$path.'.md';
    }

    protected function cacheKey(string $suffix): string
    {
        return 'docs:'.self::SCHEMA.':'.$this->version().':'.$suffix;
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

        // ::: code-group — unwrap; each fence keeps its [label] handled below.
        $markdown = (string) preg_replace('/^::: ?code-group\s*$/m', '', $markdown);

        // ::: tip|warning|danger|info|details [title]
        $markdown = (string) preg_replace_callback(
            '/^::: ?(tip|warning|danger|info|details)[ \t]*(.*)$/m',
            function (array $m): string {
                $title = $m[2] !== '' ? $m[2] : strtoupper($m[1]);

                return "<div class=\"doc-callout doc-callout--{$m[1]}\">\n\n<p class=\"doc-callout-title\">{$title}</p>";
            },
            $markdown
        );
        $markdown = (string) preg_replace('/^:::\s*$/m', "\n</div>", $markdown);

        // ```php [Label] fences → label chip above the fence.
        $markdown = (string) preg_replace_callback(
            '/^```([a-zA-Z0-9_-]*)[ \t]+\[([^\]]+)\][ \t]*$/m',
            fn (array $m): string => "<div class=\"doc-codelabel\">{$m[2]}</div>\n\n```{$m[1]}",
            $markdown
        );

        return $markdown;
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
                $text = trim(strip_tags($m[2]));
                $id = Str::slug($text) ?: 'section';

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

    protected function plainText(string $markdown): string
    {
        $text = $this->preprocess($markdown);
        $text = (string) preg_replace('/```.*?```/s', ' ', $text);
        $text = (string) preg_replace('/<[^>]+>/', ' ', $text);
        $text = (string) preg_replace('/[#>*_`\[\]()|-]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
