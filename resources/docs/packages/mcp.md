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
import { httpTransport } from './mcp-http'; // see "HTTP transport" below

const github = new Client({
  server: 'github',
  transport: httpTransport(process.env.MCP_GITHUB_URL!),
  trust: TrustPolicy.allowing(['search_repositories']),
});

const [search] = await github.listTools();
const result = await github.callTool(search, { query: 'billing module' });
```

```python
import os

from prism_mcp import Client, TrustPolicy

from mcp_http import http_transport  # see "HTTP transport" below

github = Client(
    "github",
    http_transport(os.environ["MCP_GITHUB_URL"]),
    trust=TrustPolicy.allowing(["search_repositories"]),
)

search = (await github.list_tools())[0]
result = await github.call_tool(search, {"query": "billing module"})
```
:::

::: tip Transport support
All three clients speak MCP revision `2026-07-28`, the stateless revision with
no `initialize` handshake; servers that only speak earlier revisions are not
supported. The PHP package includes a Streamable HTTP transport. The TypeScript
and Python packages ship **no transport**: you pass one in, and the
**HTTP transport** section below has a complete one to copy. None of the three supports stdio servers.
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
client and transport directly and do not require Laravel. The Python client is
asynchronous: `list_tools()` and `call_tool()` are awaited.

The PHP package replaces `prism-php/relay` and cannot be installed alongside it.

## Trust policy

Tool names, descriptions, input schemas, annotations and results are supplied
by the server. Treat them as untrusted input: they can contain prompt injection
and can change between calls. Limit the tools available to each run and review
definitions before allowing them. A compromised server can influence the model's
use of other tools in the same run.

## Configure allowed tools

Every server needs a trust policy. Without one, asking for its tools throws
`server_not_trusted` and no tool is offered.

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

To allow every tool the server publishes, including tools it adds later, use
`'tools' => '*'` in PHP config, `trustingEveryTool()` on a PHP connection,
`TrustPolicy.allowingEveryTool()` in TypeScript or
`TrustPolicy.allowing_every_tool()` in Python. Use it only for a server you
operate or trust to that degree.

The languages differ at the edges:

- **When an undeclared server is refused.** PHP refuses before any request is
  sent. TypeScript and Python send `tools/list` and refuse when the reply
  arrives, before any tool is returned.
- **An empty allowlist.** PHP rejects `'tools' => []` as a configuration error
  (`server_not_trusted`). In TypeScript and Python, `TrustPolicy.allowing([])`
  is valid and admits no tools.
- **A trusted tool the server does not offer.** PHP logs a warning naming it;
  TypeScript and Python return the tools that were offered.

### Tool names in PHP

In PHP, `tools()` returns Prism `Tool` objects named
`mcp__<server>__<tool>` — for example `mcp__github__search_repositories` — so
two servers can both publish `search`. Characters outside `A–Z a–z 0–9 _ -` are
replaced with `_`, and a name longer than 64 characters is shortened with a
digest in the middle. Each tool's `remoteName()` and `serverName()` return the
original names. TypeScript and Python return the server's `ToolDefinition`
objects unchanged; you call them through `callTool` / `call_tool`.

## Pin tool definitions

A pin records the digest of a tool definition you have reviewed. If the server
later changes that definition, the tool is refused with
`tool_definition_changed` instead of being offered to the model.

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
change it. Annotations are not covered. The same definition has the same digest
in PHP, TypeScript and Python, so a pin taken in one language holds in the
others.

### Getting a digest

In PHP, the `prism-mcp:pins` command reads a configured server's tools and
prints each digest, with a `trust` block ready to paste into the config. It does
not need a trust declaration, because you run it to decide what to trust:

```bash
php artisan prism-mcp:pins github
php artisan prism-mcp:pins github --tool=search_repositories --tool=get_file_contents
php artisan prism-mcp:pins github --json
```

`--tool` may be repeated to limit the output; `--json` prints a
`{"tool": "digest"}` object instead of a table.

In TypeScript and Python, read the digest from the definitions themselves.
Trust every tool for this one inspection, read each description, then pin:

::: language-group
```ts
const inspect = new Client({ server: 'github', transport, trust: TrustPolicy.allowingEveryTool() });

for (const tool of await inspect.listTools()) {
  console.log(tool.name, tool.digest(), tool.description);
}
```

```python
inspect = Client("github", transport, trust=TrustPolicy.allowing_every_tool())

for tool in await inspect.list_tools():
    print(tool.name, tool.digest(), tool.description)
```
:::

A digest records that a definition has not changed. It says nothing about
whether the definition was safe to begin with, so read it before pinning it.

## PHP configuration

`config/prism-mcp.php` (publish with
`php artisan vendor:publish --tag=prism-mcp-config`):

| Key | Default | Does |
| --- | --- | --- |
| `client.name` | `PRISM_MCP_CLIENT_NAME`, else `APP_NAME` | Sent to servers as `clientInfo`. Self-reported; not an identity |
| `client.version` | `0.1.0` | Sent with `client.name` |
| `defaults.timeout` | `PRISM_MCP_TIMEOUT`, `30` | Seconds before a request fails with `ServerTimedOut` |
| `defaults.cache_ttl` | `null` | Seconds to cache a server's tool list. `null` uses the `ttlMs` the server sends (300 s if it sends none); `0` disables caching |
| `defaults.max_result_bytes` | `262144` | Largest tool result handed to the model; larger results are refused |
| `defaults.frame_results` | `true` | Wrap results in an untrusted-output frame |
| `defaults.gate` | `PRISM_MCP_GATE`, `null` | Who may run a tool: `null` (allow), `deny`, `laravel`, `fms`, or a class name |
| `servers.<name>` | — | One entry per server: `transport` (`http`), `url`, `headers`, `trust`, plus any `defaults` key to override it |
| `gates.laravel.ability` | `mcp.call` | The Gate ability the `laravel` gate checks |
| `gates.fms.feature` | `mcp.tools` | The feature the `fms` gate checks |
| `gates.fms.per_server` | `true` | Check `mcp.tools.<server>` rather than `mcp.tools` |

`PrismMcp::tools()` returns the tools of every configured server at once. A
server failure propagates as an exception rather than returning a partial tool
list.

The cached tool list is keyed by server name, URL and credential headers, so
two users with different tokens never share a list.

## Connections (PHP)

`PrismMcp::server($name)` and `PrismMcp::client($url)` return a
`PendingConnection`. Every method returns the connection, and nothing is checked
until you call `tools()`:

| Method | Does |
| --- | --- |
| `trusting(array $tools)` | Allow these tools |
| `trustingEveryTool()` | Allow every tool the server publishes |
| `pinning(array $pins)` | Pin tool name => digest |
| `withHeaders(array $headers)` | Add request headers |
| `withToken(string $token)` | Add `Authorization: Bearer …` |
| `withTimeout(float $seconds)` | Request timeout |
| `usingGate(ToolGate $gate)` | Decide who may run each tool |
| `filteringResults(Closure $filter)` | `fn (string $text, string $server, string $tool): string`, applied to each result before framing |
| `withMaxResultBytes(int $bytes)` | Result size cap; `0` disables it |
| `withoutProvenanceFraming()` | Return results without the untrusted-output frame |
| `withoutCache()` | Fetch the tool list on every `tools()` call |
| `cachingFor(int $seconds, ?string $store = null)` | Cache the tool list for this long, optionally in a named cache store |
| `tools()` | Check trust and pins, then return the Prism tools |
| `client()` | The raw protocol client — **no trust checks**; `definitions()`, `callTool()`, and `forget()` to clear the cached tool list |

## Ad hoc connections

Ad hoc connections also require an explicit trust policy:

::: language-group
```php
$tools = PrismMcp::client('https://mcp.example.com/mcp', ['name' => 'example'])
    ->withToken(config('services.example.token'))
    ->trusting(['search'])
    ->withTimeout(10)
    ->tools();
```

```ts
const client = new Client({
  server: 'example',
  transport: httpTransport('https://mcp.example.com/mcp', {
    headers: { Authorization: `Bearer ${process.env.EXAMPLE_TOKEN}` },
    timeoutMs: 10_000,
  }),
  trust: TrustPolicy.allowing(['search']),
});

const tools = await client.listTools();
```

```python
client = Client(
    "example",
    http_transport(
        "https://mcp.example.com/mcp",
        headers={"Authorization": f"Bearer {os.environ['EXAMPLE_TOKEN']}"},
        timeout=10.0,
    ),
    trust=TrustPolicy.allowing(["search"]),
)

tools = await client.list_tools()
```
:::

In PHP, the second argument to `client()` takes any server config key
(`name`, `headers`, `trust`, `timeout`, `gate`, …); `name` defaults to the URL's
host and appears in errors and in the PHP tool names.

::: danger A server URL must never come from user input
`PrismMcp::client($url)` fetches whatever you point it at, and this package does
**not** block private or loopback addresses — `http://127.0.0.1:3000/mcp` is how
you run a local MCP server. The same is true of any transport you build for
TypeScript or Python. The SSRF boundary is therefore yours: server URLs belong in
config or an operator-controlled setting, never in a request parameter.
:::

## Results

Every tool result passes through a result guard before it reaches the model:

1. **Size.** A result larger than the cap is refused with `result_too_large`,
   not truncated. The cap is 256 KB in PHP (`max_result_bytes`) and **64 KB** in
   TypeScript and Python (`maxBytes` / `max_bytes` on `ResultGuard`). `0`
   disables it.
2. **Filter.** Your filter, if any, rewrites the text.
3. **Framing.** The text is wrapped in
   `<untrusted-tool-output source="mcp:<server>" tool="<tool>" id="<random>">`
   with a short instruction to treat it as data, and a matching closing tag. The
   id is random per result, so a server cannot forge the closing tag.

::: language-group
```php
$tools = PrismMcp::server('github')
    ->withMaxResultBytes(128 * 1024)
    ->filteringResults(fn (string $text, string $server, string $tool): string => trim($text))
    ->tools();
```

```ts
import { ResultGuard } from '@particle-academy/prism-mcp';

const github = new Client({
  server: 'github',
  transport,
  trust: TrustPolicy.allowing(['search_repositories']),
  guard: new ResultGuard({
    maxBytes: 128 * 1024,
    frameProvenance: true,
    filter: (server, tool, text) => text.trim(),
  }),
});
```

```python
from prism_mcp import ResultGuard

github = Client(
    "github",
    transport,
    trust=TrustPolicy.allowing(["search_repositories"]),
    guard=ResultGuard(
        max_bytes=128 * 1024,
        frame_provenance=True,
        filter=lambda server, tool, text: text.strip(),
    ),
)
```
:::

The filter's arguments are ordered differently: `($text, $server, $tool)` in
PHP, `(server, tool, text)` in TypeScript and Python.

**Errors reported by the tool.** When a server marks a result `isError`, PHP
throws `ToolCallFailed` with the guarded text. TypeScript and Python return it:
`callTool` resolves to `{ text, isError }` and `call_tool` to
`ToolResult(text, is_error)`, so check the flag. The same size cap and framing
apply to error text.

Only the text parts of a result's content reach the model. PHP replaces each
other part (an image, a resource) with a line such as
`[image content omitted: this release passes text through only]`; TypeScript and
Python leave them out.

## Authorization

Trust decides which tools the model is **told about**. A gate decides whether a
given caller may **run** one, and is checked before each call is sent. A denied
call fails with `tool_denied`.

In PHP, set `gate` in config (per server or under `defaults`) or call
`usingGate()`:

- **`laravel`** checks the Gate ability `gates.laravel.ability` (`mcp.call`)
  with the server name, the tool's original name and its arguments. One ability
  covers every remote tool, so a server adding a tool cannot introduce an
  ability your application has not defined:

  ```php
  use App\Models\User;
  use Illuminate\Support\Facades\Gate;

  Gate::define('mcp.call', function (?User $user, string $server, string $tool, array $arguments): bool {
      return $user?->is_admin === true || $tool === 'search_repositories';
  });
  ```

- **`fms`** asks Fancy's feature management whether the current subject is
  entitled to `mcp.tools.<server>` (or `mcp.tools` with `per_server` off). It
  requires `particle-academy/laravel-fms` (PHP 8.4, Laravel 13). Use it for
  which servers a plan may reach, not for who may run a destructive tool.
- **`deny`** refuses every call; `null` / `allow` permits every call.
- **A class name** resolves your own `Prism\Mcp\Contracts\ToolGate`, which
  implements `allows(string $server, string $tool, array $arguments): bool`
  and `name(): string`.

In TypeScript and Python, a gate is a function `(server, tool, args)`
returning a boolean, or a promise / awaitable of one. `allowAll` / `allow_all`
(the default) and `denyAll` / `deny_all` are exported:

::: language-group
```ts
import { type ToolGate } from '@particle-academy/prism-mcp';

const gate: ToolGate = (server, tool) => currentUser.can(`mcp:${server}:${tool}`);

const github = new Client({ server: 'github', transport, trust, gate });
```

```python
def gate(server: str, tool: str, args: dict[str, object]) -> bool:
    return current_user.can(f"mcp:{server}:{tool}")

github = Client("github", transport, trust=trust, gate=gate)
```
:::

## Mirrored parameters

A server can annotate a tool parameter with `x-mcp-header`, asking the client
to copy that argument into an `Mcp-Param-<Name>` request header so gateways can
route on it. All three clients validate the annotation: the header name must be
a legal HTTP token, the parameter must be a `string`, `integer` or `boolean`,
and a name may not appear twice. A tool that breaks these rules is excluded
from the tool list. In PHP, a tool you trusted **by name** throws
`mirrored_parameter_refused` instead of disappearing silently; under
"trust every tool", and always in TypeScript and Python, it is dropped.

Mirrored values are visible to every proxy between you and the server, so a
server should not annotate secrets for mirroring.

## HTTP transport (TypeScript and Python)

The TypeScript and Python packages contain the protocol rules — trust, pins,
gates, result guarding, mirrored parameters — but no network code. A transport
is one function: it receives a `TransportRequest` (`method`, `params`,
`headers`) and returns the JSON-RPC **result** of that request, or throws.

This is a complete Streamable HTTP transport for revision `2026-07-28`, with no
dependencies beyond the runtime. It adds the `_meta` block and the `MCP-*`
headers the revision requires on every request, accepts both JSON and
event-stream responses, caps the response size, and encodes header values that
are not plain ASCII.

::: language-group
```ts
// mcp-http.ts
import {
  LATEST_PROTOCOL_VERSION,
  McpError,
  isJsonObject,
  type JsonObject,
  type JsonValue,
  type Transport,
} from '@particle-academy/prism-mcp';

export interface HttpTransportOptions {
  headers?: Record<string, string>;
  timeoutMs?: number;
  maxResponseBytes?: number;
  clientInfo?: { name: string; version: string };
}

// Header values can carry model-supplied text. Anything that is not plain,
// trimmed, printable ASCII travels base64 in the sentinel the spec defines.
function headerValue(value: string): string {
  const plain = /^[\x20-\x7E]*$/.test(value) && value === value.trim() && !value.startsWith('=?');

  return plain ? value : `=?base64?${Buffer.from(value, 'utf8').toString('base64')}?=`;
}

async function readCapped(response: Response, limit: number): Promise<string> {
  const reader = response.body?.getReader();
  if (reader === undefined) return '';

  const chunks: Uint8Array[] = [];
  let size = 0;

  for (;;) {
    const { done, value } = await reader.read();
    if (done) break;
    size += value.byteLength;
    if (size > limit) {
      await reader.cancel();
      throw new McpError('protocol_failure', `The MCP server sent more than ${limit} bytes.`);
    }
    chunks.push(value);
  }

  return Buffer.concat(chunks).toString('utf8');
}

// A server may answer with an event stream; the LAST JSON-RPC frame is the result.
function lastFrame(body: string): string {
  const frames = body
    .split(/\r\n|\r|\n/)
    .filter((line) => line.startsWith('data:'))
    .map((line) => line.slice(5).trim())
    .filter((data) => data.includes('"jsonrpc"'));

  const last = frames.at(-1);
  if (last === undefined) throw new McpError('protocol_failure', 'The event stream carried no JSON-RPC frame.');

  return last;
}

export function httpTransport(url: string, options: HttpTransportOptions = {}): Transport {
  const clientInfo = options.clientInfo ?? { name: 'my-app', version: '1.0.0' };
  let nextId = 1;

  return async ({ method, params = {}, headers = {} }) => {
    const id = nextId++;
    const meta = isJsonObject(params._meta) ? params._meta : {};

    // 2026-07-28 is stateless: every request carries its own version, capabilities and client info.
    const body = {
      jsonrpc: '2.0',
      id,
      method,
      params: {
        ...params,
        _meta: {
          'io.modelcontextprotocol/protocolVersion': LATEST_PROTOCOL_VERSION,
          'io.modelcontextprotocol/clientCapabilities': {},
          'io.modelcontextprotocol/clientInfo': clientInfo,
          ...meta,
        },
      },
    };

    const requestHeaders: Record<string, string> = {
      ...options.headers,
      'Content-Type': 'application/json',
      Accept: 'application/json, text/event-stream',
      'MCP-Protocol-Version': LATEST_PROTOCOL_VERSION,
      'Mcp-Method': method,
    };

    if (typeof params.name === 'string') requestHeaders['Mcp-Name'] = headerValue(params.name);

    // The client passes the tool's mirrored parameters (`Mcp-Param-*`) here.
    for (const [name, value] of Object.entries(headers)) requestHeaders[name] = headerValue(value);

    const response = await fetch(url, {
      method: 'POST',
      headers: requestHeaders,
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(options.timeoutMs ?? 30_000),
    });

    if (!response.ok) {
      throw new McpError('protocol_failure', `The MCP server answered HTTP ${response.status}.`);
    }

    const text = await readCapped(response, options.maxResponseBytes ?? 8 * 1024 * 1024);
    const raw = (response.headers.get('content-type') ?? '').includes('text/event-stream') ? lastFrame(text) : text;

    let envelope: JsonValue;
    try {
      envelope = JSON.parse(raw) as JsonValue;
    } catch {
      throw new McpError('protocol_failure', 'The MCP server did not answer with JSON.');
    }

    if (!isJsonObject(envelope) || envelope.jsonrpc !== '2.0' || envelope.id !== id) {
      throw new McpError('protocol_failure', `The response is not the JSON-RPC answer to request ${id}.`);
    }

    if (isJsonObject(envelope.error)) {
      const error: JsonObject = envelope.error;
      throw new McpError(
        error.code === -32022 ? 'unsupported_protocol_version' : 'protocol_failure',
        `JSON-RPC error ${String(error.code)}: ${String(error.message)}`,
      );
    }

    return envelope.result ?? null;
  };
}
```

```python
# mcp_http.py
from __future__ import annotations

import asyncio
import base64
import itertools
import json
import re
import urllib.error
import urllib.request
from typing import Any

from prism_mcp import LATEST_PROTOCOL_VERSION, ErrorCode, McpError, Transport, TransportRequest

_PLAIN = re.compile(r"[\x20-\x7e]*")


def _header_value(value: str) -> str:
    # Header values can carry model-supplied text. Anything that is not plain,
    # trimmed, printable ASCII travels base64 in the sentinel the spec defines.
    if _PLAIN.fullmatch(value) and value == value.strip() and not value.startswith("=?"):
        return value
    return "=?base64?" + base64.b64encode(value.encode("utf-8")).decode("ascii") + "?="


def _last_frame(body: str) -> str:
    # A server may answer with an event stream; the LAST JSON-RPC frame is the result.
    frames = [
        line[5:].strip()
        for line in body.splitlines()
        if line.startswith("data:") and '"jsonrpc"' in line
    ]
    if not frames:
        raise McpError(ErrorCode.PROTOCOL_FAILURE, "The event stream carried no JSON-RPC frame.")
    return frames[-1]


def http_transport(
    url: str,
    headers: dict[str, str] | None = None,
    timeout: float = 30.0,
    max_response_bytes: int = 8 * 1024 * 1024,
    client_info: dict[str, str] | None = None,
) -> Transport:
    info = client_info or {"name": "my-app", "version": "1.0.0"}
    ids = itertools.count(1)

    def send(request: TransportRequest, request_id: int) -> Any:
        params = dict(request.params or {})
        existing = params.get("_meta")
        meta: dict[str, Any] = existing if isinstance(existing, dict) else {}
        # 2026-07-28 is stateless: every request carries its own version, capabilities and client info.
        params["_meta"] = {
            "io.modelcontextprotocol/protocolVersion": LATEST_PROTOCOL_VERSION,
            "io.modelcontextprotocol/clientCapabilities": {},
            "io.modelcontextprotocol/clientInfo": info,
            **meta,
        }
        body = {"jsonrpc": "2.0", "id": request_id, "method": request.method, "params": params}

        sent = {
            **(headers or {}),
            "Content-Type": "application/json",
            "Accept": "application/json, text/event-stream",
            "MCP-Protocol-Version": LATEST_PROTOCOL_VERSION,
            "Mcp-Method": request.method,
        }
        if isinstance(params.get("name"), str):
            sent["Mcp-Name"] = _header_value(params["name"])
        # The client passes the tool's mirrored parameters (Mcp-Param-*) here.
        for name, value in request.headers.items():
            sent[name] = _header_value(value)

        http_request = urllib.request.Request(
            url, data=json.dumps(body).encode("utf-8"), headers=sent, method="POST"
        )
        try:
            with urllib.request.urlopen(http_request, timeout=timeout) as response:
                raw_bytes = response.read(max_response_bytes + 1)
                content_type = response.headers.get("Content-Type", "")
        except urllib.error.HTTPError as failure:
            raise McpError(
                ErrorCode.PROTOCOL_FAILURE, f"The MCP server answered HTTP {failure.code}."
            ) from failure

        if len(raw_bytes) > max_response_bytes:
            raise McpError(
                ErrorCode.PROTOCOL_FAILURE,
                f"The MCP server sent more than {max_response_bytes} bytes.",
            )

        text = raw_bytes.decode("utf-8")
        raw = _last_frame(text) if "text/event-stream" in content_type else text

        try:
            envelope = json.loads(raw)
        except ValueError as failure:
            raise McpError(
                ErrorCode.PROTOCOL_FAILURE, "The MCP server did not answer with JSON."
            ) from failure

        if (
            not isinstance(envelope, dict)
            or envelope.get("jsonrpc") != "2.0"
            or envelope.get("id") != request_id
        ):
            raise McpError(
                ErrorCode.PROTOCOL_FAILURE,
                f"The response is not the JSON-RPC answer to request {request_id}.",
            )

        error = envelope.get("error")
        if isinstance(error, dict):
            code = (
                ErrorCode.UNSUPPORTED_PROTOCOL_VERSION
                if error.get("code") == -32022
                else ErrorCode.PROTOCOL_FAILURE
            )
            raise McpError(code, f"JSON-RPC error {error.get('code')}: {error.get('message')}")

        return envelope.get("result")

    async def transport(request: TransportRequest) -> Any:
        # urllib blocks, so the request runs in a worker thread.
        return await asyncio.to_thread(send, request, next(ids))

    return transport
```
:::

Use it with `httpTransport(url, { headers, timeoutMs })` /
`http_transport(url, headers=..., timeout=...)`. Network failures and timeouts
surface as the runtime's own errors (`fetch` rejections, `urllib.error.URLError`,
`TimeoutError`). The TypeScript and Python clients do not cache the tool list:
each `listTools()` / `list_tools()` is a request.

## Errors

Every failure carries a stable code. In PHP, each exception extends
`Prism\Mcp\Exceptions\McpException` and returns its code from `code()`; branch on
the class or the code, not the message. In TypeScript and Python, every failure
is an `McpError` with a `code` property (Python also has the `ErrorCode` enum).

| Code | PHP exception | Raised when |
| --- | --- | --- |
| `server_not_trusted` | `ServerNotTrusted` | no trust declaration, or (PHP) an empty allowlist |
| `server_not_configured` | `ServerNotConfigured` | no server under that name, no `url`, or an unknown gate |
| `tool_definition_changed` | `ToolDefinitionChanged` | a pinned tool's digest no longer matches |
| `tool_denied` | `ToolDenied` | the gate refused the call |
| `result_too_large` | `ResultTooLarge` | a result exceeded the size cap |
| `tool_call_failed` | `ToolCallFailed` | (PHP) the server reported the call as an error |
| `mirrored_parameter_refused` | `MirroredParameterRefused` | (PHP) a tool trusted by name has invalid `x-mcp-header` annotations |
| `unsupported_protocol_version` | `UnsupportedProtocolVersion` | the server does not speak `2026-07-28` |
| `protocol_failure` | `ProtocolFailure` | a malformed reply, a mismatched response id, or a JSON-RPC error |
| `server_timed_out` | `ServerTimedOut` | (PHP) the server did not answer within `timeout` |
| `transport_failure` | `TransportFailure` | (PHP) unreachable server, HTTP error status, or a response over 8 MB |
| `unsupported_transport` | `UnsupportedTransport` | (PHP) a server configured with `stdio` or another transport than `http` |

## Security limitations

Result framing does not eliminate prompt injection. Server annotations such as
`readOnlyHint: true` are descriptive claims, not enforced permissions. Apply
network restrictions and process isolation where required, and keep sensitive
tools out of runs that do not need them.

Source and the full trust-boundary write-up:
[Particle-Academy/prism-mcp](https://github.com/Particle-Academy/prism-mcp).
