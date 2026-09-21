# Prism MCP

A Model Context Protocol client for PHP, TypeScript and Python. Discover and
call server tools with explicit trust policies, definition pinning and bounded results.

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

::: tip Transport support
The PHP client supports MCP revision `2026-07-28` over Streamable HTTP.
Consult the selected language package's README for its transport and protocol support.
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

The PHP package requires PHP 8.2+, Laravel 12.61+ or 13.12+, and
`particle-academy/prism` 0.114 or newer. TypeScript and Python configure the
client and transport directly and do not require Laravel.

The PHP package replaces `prism-php/relay` and cannot be installed alongside it.

## Trust policy

Tool names, descriptions, input schemas, annotations and results are supplied
by the server. Treat them as untrusted input: they can contain prompt injection
and can change between calls. Limit the tools available to each run and review
definitions before allowing them. A compromised server can influence the model's
use of other tools in the same run.

## Configure allowed tools

Declare a trust policy before connecting. A missing trust policy causes an
error before any request is sent.

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

In PHP, `'tools' => '*'` allows every tool the server publishes, including future
additions. Use it only when that level of trust is appropriate. An empty
allowlist is rejected as a configuration error.

## Pin tool definitions

Record the digest of each reviewed tool definition:

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
model will read — with recursively sorted keys. Reordering JSON keys does not
change the digest. A definition that no longer matches its pin is rejected.

## Result limits and authorization

PHP's `max_result_bytes` defaults to 256 KB. Oversized results are rejected
rather than truncated. Results identify their source server and use per-call
delimiters. The same controls apply to successful and error results.

Trust controls which tools are exposed; authorization controls which tools an
actor may execute. The PHP package includes optional Laravel Gate and Fancy
feature-management adapters.

## Ad hoc connections

Ad hoc connections also require an explicit trust policy:

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
server failure propagates as an exception rather than returning a partial tool list.

::: danger A server URL must never come from user input
`PrismMcp::client($url)` fetches whatever you point it at, and this package does
**not** block private or loopback addresses — `http://127.0.0.1:3000/mcp` is how
you run a local MCP server. The SSRF boundary is therefore yours: server URLs
belong in config or an operator-controlled setting, never in a request parameter.
:::

## Security limitations

Result framing does not eliminate prompt injection. Server annotations such as
`readOnlyHint: true` are descriptive claims, not enforced permissions. Apply
network restrictions and process isolation where required, and keep sensitive
tools out of runs that do not need them.

Source and the full trust-boundary write-up:
[Particle-Academy/prism-mcp](https://github.com/Particle-Academy/prism-mcp).
