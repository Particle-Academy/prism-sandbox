<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Site-owned nav sections
    |--------------------------------------------------------------------------
    |
    | Almost all of this site's nav is parsed from the VitePress sidebar that
    | ships inside `particle-academy/prism`, so a new release publishes its own
    | docs without this repo being touched. These sections are appended after
    | it, and their pages are markdown files under `resources/docs`.
    |
    | They exist because Prism core is a provider API shuttle and stays one.
    | Documentation for the packages *around* Prism is not documentation of
    | Prism, and pushing it upstream would put satellite release notes in the
    | core package's tree — so the site owns it instead.
    |
    | Each `link` is the path under `resources/docs` without the `.md`, and is
    | served at `/docs<link>`. An absolute URL is allowed and renders as an
    | outbound link, the same way the upstream sidebar's package links do.
    |
    | Every entry here must resolve to a file. `SiteDocsTest` walks the whole
    | section and fails on one that does not, because a nav entry silently
    | dropped at render time is indistinguishable from a page nobody wrote.
    |
    */

    'sections' => [

        [
            'text' => 'Companion packages',
            'items' => [
                ['text' => 'Overview', 'link' => '/packages/overview'],
                ['text' => 'Harness', 'link' => '/packages/harness'],
                ['text' => 'MCP', 'link' => '/packages/mcp'],
                ['text' => 'Perplexity', 'link' => '/packages/perplexity'],
                ['text' => 'OpenTelemetry', 'link' => '/packages/opentelemetry'],
            ],
        ],

    ],

];
