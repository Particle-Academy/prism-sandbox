<?php

declare(strict_types=1);

namespace App\Providers;

use App\Docs\DocsRepository;
use FancySeo\FancySeo;
use FancySeo\JsonLd;
use FancySeo\SitemapBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * What crawlers, link scrapers and LLMs see: the per-route head rendered into
 * the first byte by `<x-fancy-seo::head />`, plus sitemap.xml, robots.txt,
 * llms.txt and a Markdown variant of every docs page.
 *
 * The same resolved payload is shared with the client (HandleInertiaRequests),
 * so in-app navigation updates the head from this one definition rather than
 * from a second copy kept in each page.
 */
class SeoServiceProvider extends ServiceProvider
{
    private const REPOSITORY = 'https://github.com/Particle-Academy/prism';

    public function boot(FancySeo $seo, DocsRepository $docs): void
    {
        $base = $seo->baseUrl();
        $intro = 'getting-started/introduction';

        $seo->defaults([
            'title' => 'Prism — one provider interface, and a harness for agents',
            'jsonLd' => [
                JsonLd::website('Prism', $base, config('fancy-seo.description')),
                JsonLd::organization('Particle Academy', 'https://particle.academy', null, ['https://github.com/Particle-Academy']),
            ],
        ]);

        $seo->route('home', fn (): array => [
            'jsonLd' => [JsonLd::softwareSourceCode('Prism', $base, self::REPOSITORY, ['programmingLanguage' => 'PHP'])],
        ]);

        // /docs renders the introduction, so it names that page as canonical
        // instead of competing with it as a duplicate.
        $seo->route('docs.index', fn (): array => $this->docsPage($docs, $intro, $base));
        $seo->route('docs.show', fn (array $params): array => $this->docsPage($docs, (string) ($params['path'] ?? ''), $base));

        $seo->route('features.index', fn (): array => [
            'title' => 'Features — Prism',
            'description' => 'What the Prism ecosystem does, feature by feature: providers, the agent harness, Human+, browser, memory, workspace, MCP, research, observability, ACP and the language ports.',
            'canonical' => $base.'/features',
        ]);
        $seo->route('features.show', fn (array $params): array => $this->featurePage((string) ($params['slug'] ?? ''), $base));

        $seo->route('packages', fn (): array => [
            'title' => 'Packages — Prism',
            'description' => 'Every Prism package, by family and language, with its Packagist, npm or PyPI name and its docs.',
            'canonical' => $base.'/packages',
        ]);

        $seo->route('leaderboard', fn (): array => [
            'title' => 'Contributor Leaderboard — Prism',
            'description' => 'Top community contributors to Prism, ranked by contribution XP.',
        ]);

        $seo->route('demo', fn (): array => [
            'title' => 'Try Prism — live demo',
            'description' => 'Send a prompt through Prism to a live model and see the response it returns.',
        ]);

        $seo->noindexRoutes(['login', 'register', 'password.*', 'verification.*', 'two-factor.*']);

        $seo->sitemap(function (SitemapBuilder $map) use ($docs): void {
            $map->add('/', '1.0', 'weekly');

            foreach ($docs->pages() as $page) {
                $map->add('/docs'.$page['link'], '0.8', 'weekly');
            }

            $map->add('/features', '0.9', 'weekly');

            foreach (array_keys((array) config('features.pages', [])) as $slug) {
                $map->add('/features/'.$slug, '0.8', 'weekly');
            }

            $map->add('/packages', '0.8', 'weekly');
            $map->add('/leaderboard', '0.4', 'daily');

            // The demo page only works when a key is configured; otherwise it
            // shows a disabled notice, which is not worth advertising.
            if (filled(config('prism.providers.groq.api_key'))) {
                $map->add('/demo', '0.5', 'monthly');
            }
        });

        $seo->llms(fn (): string => $this->llms($docs, $base));

        // /docs/<page>.md: the page as Markdown, through the HTML route's guard.
        $seo->markdownUsing(function (string $path) use ($docs): ?string {
            $path = ltrim($path, '/');

            return str_starts_with($path, 'docs/') ? $docs->markdown(substr($path, 5)) : null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function docsPage(DocsRepository $docs, string $path, string $base): array
    {
        if (! $docs->exists($path)) {
            return [];
        }

        $page = $docs->page($path);
        $url = $base.'/docs/'.$path;
        $description = $docs->description($path)
            ?: $page['title'].': Prism documentation for PHP, TypeScript and Python.';

        $crumbs = [['name' => 'Docs', 'url' => $base.'/docs/getting-started/introduction']];

        if (filled($page['section'])) {
            $crumbs[] = ['name' => (string) $page['section'], 'url' => $url];
        }

        $crumbs[] = ['name' => $page['title'], 'url' => $url];

        return [
            'title' => $page['title'].' — Prism docs',
            'description' => $description,
            'canonical' => $url,
            'type' => 'article',
            'jsonLd' => [
                JsonLd::article($page['title'], $url, ['description' => $description]),
                JsonLd::breadcrumbList($crumbs),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function featurePage(string $slug, string $base): array
    {
        $page = config('features.pages.'.$slug);

        if (! is_array($page)) {
            return [];
        }

        $url = $base.'/features/'.$slug;

        return [
            'title' => $page['title'].' — Prism features',
            'description' => $page['description'],
            'canonical' => $url,
            'jsonLd' => [
                JsonLd::breadcrumbList([
                    ['name' => 'Features', 'url' => $base.'/features'],
                    ['name' => $page['title'], 'url' => $url],
                ]),
            ],
        ];
    }

    private function llms(DocsRepository $docs, string $base): string
    {
        $lines = [
            '# Prism',
            '',
            '> Prism is a cross-language provider API ecosystem for integrating Large Language Models into applications: a mature PHP/Laravel reference, a durable agent harness, and focused TypeScript and Python ports. These docs cover '.count($docs->pages()).' topics, including '.$docs->providerGuideCount().' provider guides.',
            '> This is the actively maintained Particle Academy fork, published as `particle-academy/prism` (a drop-in for `prism-php/prism`). Documented version: '.$docs->version().'. Every docs page is also available as Markdown by appending `.md` to its URL.',
            '',
        ];

        foreach ($docs->nav() as $group) {
            $lines[] = '## '.$group['text'];
            $lines[] = '';

            foreach ($group['items'] as $item) {
                // Companion-package entries link straight out to GitHub; only
                // real pages live under /docs.
                $href = $docs->isExternal($item) ? $item['link'] : $base.'/docs'.$item['link'];

                $lines[] = '- ['.$item['text'].']('.$href.')';
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
