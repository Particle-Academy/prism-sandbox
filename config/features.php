<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Feature pages
    |--------------------------------------------------------------------------
    |
    | The allowlist for /features/{slug}: a slug not listed here is a 404, and
    | each entry titles and describes its page for crawlers. The page content
    | itself lives in resources/js/data/ecosystem.ts; keep the slugs, titles
    | and order of the two in step.
    |
    */

    'pages' => [
        'providers' => [
            'title' => 'One API, every provider',
            'description' => 'One request shape for text, structured output, streaming, embeddings, media, audio, files and batches, in PHP, TypeScript and Python.',
        ],
        'harness' => [
            'title' => 'Agent harness',
            'description' => 'Durable sessions, threads, modes, approvals and subagents with their own budgets, for agents that outlive a request.',
        ],
        'human-plus' => [
            'title' => 'Human+',
            'description' => 'An agent joins a live Fancy surface as a named participant, and confirmation stays with the human.',
        ],
        'browser' => [
            'title' => 'Guarded browser',
            'description' => 'Let an agent use a real browser within limits you set: hosts, actions, and how much of a page it may read.',
        ],
        'memory' => [
            'title' => 'Memory',
            'description' => 'Store what was said and recall the records that bear on a new question, sized to the token budget you have.',
        ],
        'workspace' => [
            'title' => 'Workspace',
            'description' => 'A directory of its own for an agent, where every path is checked before it touches the disk.',
        ],
        'mcp' => [
            'title' => 'MCP',
            'description' => 'Discover and call MCP server tools with explicit trust policies, definition pinning and bounded results.',
        ],
        'research' => [
            'title' => 'Research (Perplexity)',
            'description' => 'Perplexity embeddings, web search and the long-running Agent API.',
        ],
        'observability' => [
            'title' => 'Observability',
            'description' => 'Generations, steps and tool calls as GenAI-convention OpenTelemetry spans, exported over OTLP.',
        ],
        'acp' => [
            'title' => 'Agent Client Protocol',
            'description' => 'Speak ACP to Claude or Codex through the CLI the user has already signed in to, and get structured state back.',
        ],
        'languages' => [
            'title' => 'Three languages, one contract',
            'description' => 'Core and the companion families in PHP, TypeScript and Python, held together by one shared conformance corpus.',
        ],
    ],

];
