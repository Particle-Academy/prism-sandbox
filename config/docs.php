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

    /*
    |--------------------------------------------------------------------------
    | Upstream sections this site supersedes
    |--------------------------------------------------------------------------
    |
    | The package's own sidebar carries a "Companion Packages" group, and so did
    | this site — so Harness, MCP, Perplexity and OpenTelemetry each appeared
    | TWICE in one nav at two different URLs, while Memory and Workspace
    | appeared only upstream. Nothing told a reader which was canonical.
    |
    | The site owns the ecosystem story, for the reason stated above: core is a
    | provider API shuttle, and documenting the packages around it is not
    | documenting it. Titles are matched case-insensitively; upstream links move
    | between releases, its group titles do not.
    |
    */

    'superseded_package_sections' => [
        'Companion Packages',
        'Other Packages',
    ],

    'sections' => [

        [
            'text' => 'Build with Prism',
            'items' => [
                ['text' => 'Building with PHP', 'link' => '/languages/php'],
                ['text' => 'Building with TypeScript', 'link' => '/languages/typescript'],
                ['text' => 'Building with Python', 'link' => '/languages/python'],
            ],
        ],

        /*
         * The Harness gets a section of its own, not a bullet under
         * "companion".
         *
         * It is the package that makes this an agent platform rather than a
         * provider shuttle — durable sessions, modes, tools, subagents,
         * budgets — and filing it beside six integrations understated it to the
         * point where the whole site read as "just Prism". It is the second
         * thing a reader should meet, after choosing a language.
         */
        [
            'text' => 'Harness',
            'items' => [
                ['text' => 'What the Harness is', 'link' => '/packages/harness'],
                ['text' => 'Sessions and threads', 'link' => '/harness/sessions'],
                ['text' => 'Modes, tools and skills', 'link' => '/harness/modes'],
                ['text' => 'Subagents and budgets', 'link' => '/harness/subagents'],
            ],
        ],

        [
            'text' => 'The ecosystem',
            'items' => [
                ['text' => 'Overview', 'link' => '/packages/overview'],
                ['text' => 'Language ports', 'link' => '/packages/language-ports'],
                ['text' => 'Browser', 'link' => '/packages/browser'],
                ['text' => 'Human+', 'link' => '/packages/human-plus'],
                ['text' => 'MCP', 'link' => '/packages/mcp'],
                ['text' => 'Memory', 'link' => '/packages/memory'],
                ['text' => 'Workspace', 'link' => '/packages/workspace'],
                ['text' => 'Perplexity', 'link' => '/packages/perplexity'],
                ['text' => 'OpenTelemetry', 'link' => '/packages/opentelemetry'],
            ],
        ],

    ],

];
