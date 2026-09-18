# Prism MCP

A **Model Context Protocol client** for Prism. Tools published by a third-party
server, handed to your model as ordinary Prism tools — across a trust boundary
you can actually see.

::: language-group
```php
use Prism\Mcp\Facades\PrismMcp;
use Prism\Prism\Facades\Prism;

$tools = PrismMcp::server('github')->tools();

Prism::text()
    ->using('anthropic', 'claude-sonnet-4-5')
    ->withTools($tools)
    ->withPrompt('Which of our open PRs touch the billing module?')
    ->asText();
```

```ts
import { Client, TrustPolicy } from '@particle-academy/prism-mcp';

const github = new Client({
  server: 'github',
  transport,
  trust: TrustPolicy.allowing(['search_repositories']),
});

const [search] = await github.listTools();
const result = await github.callTool(search, { query: 'billing module' });
```

```python
from prism_mcp import Client, TrustPolicy

github = Client(
    "github",
    transport,
    trust=TrustPolicy.allowing(["search_repositories"]),
)

search = github.list_tools()[0]
result = github.call_tool(search, {"query": "billing module"})
```
:::

::: tip Status: first slice, published
`v0.2.0` on Packagist. The client speaks MCP revision `2026-07-28` over
Streamable HTTP. stdio transport, OAuth, prompts and resources are on the
roadmap in the package README, each with the reasoning behind its place in the
queue.
:::

## Installation

::: language-group
```bash [language=php]
composer require particle-academy/prism-mcp
php artisan vendor:publish --tag=prism-mcp-config
```

```bash [language=typescript]
npm install @particle-academy/prism-mcp
```

```bash [language=python]
pip install prism-ai-mcp
```
:::

Requires PHP 8.2+, Laravel 12.61+ or 13.12+, and `particle-academy/prism` 0.114
or newer.

This package declares `replace: prism-php/relay`, so Composer will refuse to
install it alongside Relay. That is deliberate — it is Relay's successor.

## The part to read: what you are trusting

Connecting to an MCP server hands a third party a pen. Its text becomes part of
your model's **instructions**, and your model then **acts on what comes back**.

The tool's name, its description, every parameter description, the enum values in
its input schema, its annotations, and every result it returns are all authored by
whoever operates that server — and all of them reach the model as text it treats
as authoritative.

Two things make this unlike other untrusted input a Laravel app already handles
well:

- **A model reads it as instruction.** You escape input before it reaches HTML
  and bind it before it reaches SQL. Text that reaches a language model shares
  one channel with its instructions, so the equivalent escape does not exist.
- **It can change under you.** A tool your team wrote changes when somebody
  deploys. A tool a third party publishes changes whenever they like, between two
  calls. A server can publish a benign `search`, wait to be adopted, then rewrite
  its description — a rug pull, and a one-time review covers only the moment you
  read it.

An injected instruction reaches past that server, too. Once the model is
following it, every **other** tool in the same run is in range: your database
tools, your mail tools. A compromised MCP server is a foothold in the whole tool
set.

## What the package does about it

**You name what the model may see.** A server with no `trust` block refuses —
*before any request is sent*, so a misconfigured app keeps its silence toward
that server.

::: language-group
```php
// config/prism-mcp.php
'servers' => [
    'github' => [
        'transport' => 'http',
        'url' => env('MCP_GITHUB_URL'),
        'headers' => [
            'Authorization' => 'Bearer '.env('MCP_GITHUB_TOKEN', ''),
        ],
        'trust' => [
            'tools' => ['search_repositories', 'get_file_contents'],
        ],
    ],
],
```

```ts
const github = new Client({
  server: 'github',
  transport,
  trust: TrustPolicy.allowing(['search_repositories', 'get_file_contents']),
});
```

```python
github = Client(
    "github",
    transport,
    trust=TrustPolicy.allowing(["search_repositories", "get_file_contents"]),
)
```
:::

Or, when you genuinely accept whatever that server publishes today and next week
— reasonable for one you operate yourself — `'tools' => '*'`.

An **empty** allowlist gets its own, louder refusal: it looks configured while
handing the model zero tools, and a toolless run reads as *the model choosing
not to use any*.

**Pin a definition and only that definition runs.** Record the digest of a
definition you have actually read, per tool:

::: language-group
```php
'trust' => [
    'tools' => ['search_repositories'],
    'pins' => ['search_repositories' => 'sha256:…'],
],
```

```ts
TrustPolicy.allowing(
  ['search_repositories'],
  { search_repositories: 'sha256:…' },
);
```

```python
TrustPolicy.allowing(
    ["search_repositories"],
    pins={"search_repositories": "sha256:…"},
)
```
:::

The digest covers name, title, description and input schema — everything the
model will read — and sorts keys recursively, so a server reordering its JSON
does not read as a rewritten tool. It is the defence against a rug pull that
holds up, because it turns on noticing change — something a check can do
reliably.

**Results are bounded, framed, and filterable.** Over `max_result_bytes`
(256 KB by default) a call is **refused, not truncated** — a silently cut result
reads to a model as a complete one. Results are wrapped in a delimiter naming the
source server, with a per-call random nonce so a server that learns the delimiter
cannot close it early. The error path gets identical treatment, because
`isError: true` is the same channel from an attacker's point of view.

**A gate decides who may run what.** Trust decides which tools the model is *told
about*; a gate decides whether a given actor may *run* one. Adapters ship for
Laravel's `Gate` and for Fancy's feature management, each optional.

## Ad hoc connections

The exploratory form asks for trust just as firmly, because exploring is
exactly where an exemption would hurt:

::: language-group
```php
$tools = PrismMcp::client('https://mcp.example.com/mcp')
    ->withToken(config('services.example.token'))
    ->trusting(['search'])
    ->withTimeout(10)
    ->tools();
```

```ts
const client = new Client({
  server: 'example',
  transport, // your HTTP transport for https://mcp.example.com/mcp
  trust: TrustPolicy.allowing(['search']),
});

const tools = await client.listTools();
```

```python
client = Client(
    "example",
    transport,  # your HTTP transport for https://mcp.example.com/mcp
    trust=TrustPolicy.allowing(["search"]),
)

tools = client.list_tools()
```
:::

`PrismMcp::tools()` returns the tools of every configured server at once. A
server that refuses **surfaces its exception**, so the tool list your model gets
is the tool list your application configured.

::: danger A server URL must never come from user input
`PrismMcp::client($url)` fetches whatever you point it at, and this package does
**not** block private or loopback addresses — `http://127.0.0.1:3000/mcp` is how
you run a local MCP server. The SSRF boundary is therefore yours: server URLs
belong in config or an operator-controlled setting, never in a request parameter.
:::

## What the guarantees rest on

Every guarantee here traces back to something you declared: the servers you
trusted, the tools you allowed, the digests you pinned, the size cap you set and
the gate you bound. That is the property worth having — the trust is a decision
you made, a definition changes in front of you, and the blast radius is bounded
in size, in permission, and in which tools were ever offered.

Two things stay outside those bounds, and are worth saying plainly. Results are
framed rather than filtered: a determined server can still put an instruction in
a result that the model follows, and pattern-matching for injection would ship a
promise that fails quietly. Annotations such as `readOnlyHint: true` are claims
by the server, so trusting a server's `search` tool stops at the tool — what
`search` returns is still third-party text.

For a genuinely adversarial server, network controls and process isolation are
the answer, and they always were.

Source and the full trust-boundary write-up:
[Particle-Academy/prism-mcp](https://github.com/Particle-Academy/prism-mcp).
