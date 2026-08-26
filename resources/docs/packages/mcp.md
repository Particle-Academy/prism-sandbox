# Prism MCP

A **Model Context Protocol client** for Prism. Tools published by a server you
do not own, handed to your model as ordinary Prism tools — across a trust
boundary you can actually see.

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

::: tip Status: first slice, published
`v0.1.0` on Packagist. The client speaks MCP revision `2026-07-28` over
Streamable HTTP. stdio transport, OAuth, prompts and resources are deferred on
purpose and listed in the package README — an absence there is a plan, not an
oversight.
:::

## Installation

```bash
composer require particle-academy/prism-mcp
php artisan vendor:publish --tag=prism-mcp-config
```

Requires PHP 8.2+, Laravel 12.61+ or 13.12+, and `particle-academy/prism` 0.114
or newer.

This package declares `replace: prism-php/relay`, so Composer will refuse to
install it alongside Relay. That is deliberate — it is Relay's successor.

## The part to read: what you are trusting

When you connect to an MCP server, you are not fetching data. You are letting a
third party **write into your model's instructions**, and then letting your model
**act on what they write back**.

The tool's name, its description, every parameter description, the enum values in
its input schema, its annotations, and every result it returns are all authored by
whoever operates that server — and all of them reach the model as text it treats
as authoritative.

Two things make this unlike other untrusted input a Laravel app already handles
well:

- **It is instructions, not data.** You escape input before it reaches HTML and
  bind it before it reaches SQL. There is no equivalent escape for text that
  reaches a language model, because instruction and data share one channel.
- **It can change under you.** A tool your team wrote changes when somebody
  deploys. A tool a third party publishes changes whenever they like, between two
  calls. A server can publish a benign `search`, wait to be adopted, then rewrite
  its description — a rug pull, and reviewing the tool once does not prevent it.

And an injected instruction is not confined to that server. Once the model is
following it, every **other** tool in the same run is reachable: your database
tools, your mail tools. A compromised MCP server is a foothold in the whole tool
set.

## What the package does about it

**Nothing reaches the model until you say so.** A server with no `trust` block
refuses — *before any request is sent*, so a misconfigured app never even tells
the server it has an audience.

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

Or, when you genuinely accept whatever that server publishes today and next week
— reasonable for one you operate yourself — `'tools' => '*'`.

An **empty** allowlist is a separate and louder refusal than a **missing** one.
An empty one looks configured and silently hands the model zero tools, and a run
with no tools reads as *the model choosing not to use any*.

**Pin a definition and a rewrite is refused, not obeyed.** Record the digest of a
definition you have actually read, per tool:

```php
'trust' => [
    'tools' => ['search_repositories'],
    'pins' => ['search_repositories' => 'sha256:…'],
],
```

The digest covers name, title, description and input schema — everything the
model will read — and sorts keys recursively, so a server reordering its JSON
does not read as a rewritten tool. This is the one defence against a rug pull
that actually holds, because it does not depend on recognising malice, only on
noticing change.

**Results are bounded, framed, and filterable.** Over `max_result_bytes`
(256 KB by default) a call is **refused, not truncated** — a silently cut result
reads to a model as a complete one. Results are wrapped in a delimiter naming the
source server, with a per-call random nonce so a server that learns the delimiter
cannot close it early. The error path gets identical treatment, because
`isError: true` is the same channel from an attacker's point of view.

**A gate decides who may run what.** Trust decides which tools the model is *told
about*; a gate decides whether a given actor may *run* one. Adapters ship for
Laravel's `Gate` and for Fancy's feature management, neither as a hard
dependency.

## Ad hoc connections

The exploratory form refuses just as firmly — exempting it would put the hole
exactly where the exploring happens:

```php
$tools = PrismMcp::client('https://mcp.example.com/mcp')
    ->withToken(config('services.example.token'))
    ->trusting(['search'])
    ->withTimeout(10)
    ->tools();
```

`PrismMcp::tools()` returns the tools of every configured server at once. A
server that refuses is **not skipped** — the exception surfaces, because a helper
that quietly dropped an unreachable server would hand the model a shorter tool
list than the application believes it configured.

::: danger A server URL must never come from user input
`PrismMcp::client($url)` fetches whatever you point it at, and this package does
**not** block private or loopback addresses — `http://127.0.0.1:3000/mcp` is how
you run a local MCP server. The SSRF boundary is therefore yours: server URLs
belong in config or an operator-controlled setting, never in a request parameter.
:::

## What it does not do

It does not scan results for injection — no regex, no heuristic, no suspicious
phrase list. Pattern-matching for prompt injection produces false confidence and
false positives, and shipping a check that does not hold is worse than shipping
none, because somebody will rely on it.

Framing is a mitigation, not a fix. Annotations like `readOnlyHint: true` are
claims by a party you do not control, and nothing here is pinned to them. Trust
is not transitive: trusting a server's `search` tool is not trusting what
`search` returns.

With every feature switched on, a malicious server can still emit an instruction
inside a result that the model follows. What the package buys you is that this is
a **decision you made** rather than a default you inherited, that a definition
cannot change underneath you unnoticed, and that the blast radius is bounded in
size, in permission, and in which tools were ever offered.

For a genuinely adversarial server, the answer is network controls and process
isolation, and it always was.

Source and the full trust-boundary write-up:
[Particle-Academy/prism-mcp](https://github.com/Particle-Academy/prism-mcp).
