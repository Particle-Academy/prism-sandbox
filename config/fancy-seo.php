<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Site defaults
    |--------------------------------------------------------------------------
    | The baseline applied to every page. Per-route resolvers + a controller's
    | `FancySeo::for([...])` override these. `url` falls back to config('app.url').
    */
    'site_name' => env('FANCY_SEO_SITE_NAME', 'Prism'),
    'url' => env('FANCY_SEO_URL', 'https://ai.particle.academy'),
    'title' => null,            // default <title>; null → site_name
    'description' => 'Prism is a cross-language provider API ecosystem: a mature PHP/Laravel reference across 18 providers, a durable agent harness, and focused TypeScript and Python ports.',
    'image' => '/og/prism.png',
    'image_alt' => 'Prism: one provider interface, and a harness for agents',
    'image_width' => 1200,
    'image_height' => 630,
    'image_type' => 'image/png',
    'locale' => 'en_US',
    'type' => 'website',        // default og:type
    'twitter_site' => null,     // @handle for twitter:site
    'theme_color' => '#07070b',

    /*
    |--------------------------------------------------------------------------
    | Indexing
    |--------------------------------------------------------------------------
    | `robots` is the default <meta name="robots"> for indexable pages.
    | `noindex_routes` are route-name patterns ('admin.*') always set noindex.
    */
    'robots' => 'index, follow, max-image-preview:large',
    'noindex_robots' => 'noindex, nofollow',
    'noindex_routes' => [],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    | Optional nonce applied to the inline JSON-LD <script> tags so a strict
    | `script-src 'nonce-…'` CSP doesn't drop your structured data. Leave null
    | when you don't run a CSP. For a per-request nonce, pass it to the head
    | component instead: <x-fancy-seo::head :nonce="$cspNonce" />.
    */
    'csp_nonce' => null,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | Auto-register the discovery routes (sitemap.xml, robots.txt, llms.txt,
    | llms-full.txt, .well-known/security.txt, humans.txt). Disable any you
    | serve yourself. `markdown` enables the per-page `{path}.md` route.
    */
    'routes' => [
        'enabled' => true,
        'sitemap' => true,
        'robots' => true,
        'llms' => true,
        'security' => false,
        'humans' => true,
        'markdown' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    | Paths every user-agent (incl. the welcomed AI bots) must not crawl, and
    | the AI/LLM crawler user-agents explicitly allowed (we WANT to be ingested).
    */
    'robots_txt' => [
        'disallow' => ['/admin', '/login', '/logout', '/webhooks'],
        'ai_bots' => [
            'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web',
            'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended',
            'CCBot', 'Amazonbot', 'Meta-ExternalAgent', 'cohere-ai',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | well-known
    |--------------------------------------------------------------------------
    */
    'security_txt' => [
        'contact' => env('FANCY_SEO_SECURITY_CONTACT'),   // e.g. mailto:security@example.com
        'languages' => 'en',
    ],
];
