# Prism Perplexity

Prism Perplexity provides access to Perplexity embeddings, web search and the
long-running Agent API. Packages are available for PHP, TypeScript and Python.

## Installation

::: language-group
```bash [language=php]
composer require particle-academy/prism-perplexity
```

```bash [language=typescript]
npm install @particle-academy/prism-perplexity
```

```bash [language=python]
pip install prism-ai-perplexity
```
:::

In PHP, the package extends Prism's existing `perplexity` provider registration:
text, structured output and streaming keep working through Prism core, and
embeddings, search and the Agent API are added to the same provider.
TypeScript and Python expose functions that take an HTTP transport; they do not
register a Perplexity provider in core.

The PHP package requires PHP 8.2+, Laravel 12.61+ or 13.12+, and
`particle-academy/prism` 0.113 or newer. Configure `PERPLEXITY_API_KEY` in your
server environment.

## Transport (TypeScript and Python)

Every TypeScript and Python function takes an `HttpClient` as its first
argument. The package ships one built on the runtime's own HTTP client:

::: language-group
```ts
import { fetchTransport } from '@particle-academy/prism-perplexity';

const http = fetchTransport({
  apiKey: process.env.PERPLEXITY_API_KEY!,
  baseUrl: 'https://api.perplexity.ai', // the default
});
```

```python
import os
from prism_perplexity import urllib_transport

http = urllib_transport(
    os.environ["PERPLEXITY_API_KEY"],
    base_url="https://api.perplexity.ai",  # the default
    timeout=30.0,  # seconds, per request; the default
)
```
:::

`fetchTransport` has no timeout option. To set one, pass your own `fetch`; an
expired timeout rejects with the `fetch` abort error, not a `PerplexityError`:

```ts
const http = fetchTransport({
  apiKey: process.env.PERPLEXITY_API_KEY!,
  fetch: (input, init) => fetch(input, { ...init, signal: AbortSignal.timeout(30_000) }),
});
```

To use another HTTP library, write your own transport: a function that takes
an `HttpRequest` (`method`, `path` relative to the base URL, optional JSON
`body`) and returns an `HttpResponse` (`status` and the decoded `json`, or
`null` when the body was not JSON). In TypeScript it returns a promise; in
Python it is a plain function.

In PHP, the provider uses Laravel's HTTP client and the `perplexity` entry in
`config/prism.php`.

## Embeddings

Generate embeddings through the PHP provider extension or the port's
`embeddings` function:

::: language-group
```php
use Prism\Prism\Facades\Prism;

$response = Prism::embeddings()
    ->using('perplexity', 'text-embedding-v1')
    ->fromInput('The quick brown fox')
    ->asEmbeddings();

$response->embeddings[0]->embedding;   // float[]
```

```ts
import { embeddings, fetchTransport } from '@particle-academy/prism-perplexity';

const http = fetchTransport({ apiKey: process.env.PERPLEXITY_API_KEY! });
const response = await embeddings(http, {
  model: 'text-embedding-v1',
  inputs: ['The quick brown fox'],
});

response.embeddings[0]; // number[]
```

```python
import os
from prism_perplexity import embeddings, urllib_transport

http = urllib_transport(os.environ["PERPLEXITY_API_KEY"])
response = embeddings(http, "text-embedding-v1", ["The quick brown fox"])

response.embeddings[0]  # list[float]
```
:::

TypeScript and Python return `embeddings`, the provider's `usage` object and
the `model` that answered.

**Order.** PHP sorts the vectors by the `index` Perplexity returns with each
one, so `embeddings[i]` belongs to input `i`. TypeScript and Python return the
vectors in the order the response lists them and do not expose the index.

### Contextualized embeddings

Contextualized embeddings represent chunks from one document using the
surrounding document context. This calls a separate endpoint
(`/contextualized-embeddings`). Enable it when the supplied chunks belong to the
same document; `document_context` / `documentContext` optionally sends the
document context with them:

::: language-group
```php
Prism::embeddings()
    ->using('perplexity', 'text-embedding-v1')
    ->fromArray($chunks)
    ->withProviderOptions([
        'contextualized' => true,
        'document_context' => $documentText, // optional
    ])
    ->asEmbeddings();
```

```ts
await embeddings(http, {
  model: 'text-embedding-v1',
  inputs: chunks,
  contextualized: true,
  documentContext: documentText, // optional
});
```

```python
embeddings(
    http,
    "text-embedding-v1",
    chunks,
    contextualized=True,
    document_context=document_text,  # optional
)
```
:::

## Search

Retrieve web search results without requesting a generated answer.

::: language-group
```php
use Prism\Prism\PrismManager;

$provider = app(PrismManager::class)->resolve('perplexity');

$results = $provider->search('what is prism php', [
    'max_results' => 5,
    'search_domain_filter' => ['github.com'],
]);
```

```ts
import { search } from '@particle-academy/prism-perplexity';

const results = await search(http, 'what is prism php', {
  max_results: 5,
  search_domain_filter: ['github.com'],
});
```

```python
from prism_perplexity import search

results = search(
    http,
    "what is prism php",
    {"max_results": 5, "search_domain_filter": ["github.com"]},
)
```
:::

PHP exposes search as a provider method. TypeScript and Python expose a
standalone function.

`search()` also takes an array of queries to run together. The options are
sent to Perplexity's search endpoint as given; options set to `null` or to an
empty array are left out of the request. Results are plain arrays (objects in
TypeScript, dicts in Python), so every field Perplexity sends reaches you.

**An empty result list is an answer.** It comes back as `[]`.

## Agent API

The Agent API runs long research tasks in the background. `create()` starts a
run and returns at once; `retrieve()` reads its current state, `wait()` polls
until it finishes, and `cancel()` stops it.

::: language-group
```php
use Prism\Perplexity\Agent\AgentStatus;
use Prism\Prism\PrismManager;

$agent = app(PrismManager::class)->resolve('perplexity')->agent();

$queued = $agent->create('Research the Prism ecosystem', [
    'preset' => 'deep-research',
    'max_steps' => 8,
]);

$snapshot = $agent->retrieve($queued->id);
$finished = $agent->wait($queued->id, maxAttempts: 300, intervalMilliseconds: 1000);

if ($finished->isSuccessful()) {
    echo $finished->text();
} elseif ($finished->status === AgentStatus::Failed) {
    report($finished->error?->message);
}

$agent->cancel($queued->id);
```

```ts
import { AgentClient } from '@particle-academy/prism-perplexity';

const agent = new AgentClient(http);

const queued = await agent.create('Research the Prism ecosystem', {
  preset: 'deep-research',
  max_steps: 8,
});

const snapshot = await agent.retrieve(queued.id);
const finished = await agent.wait(queued.id, 300, 1000); // maxAttempts, intervalMs

if (finished.isSuccessful()) {
  console.log(finished.text());
} else if (finished.status === 'failed') {
  console.error(finished.error?.message);
}

await agent.cancel(queued.id);
```

```python
from prism_perplexity import AgentClient, AgentStatus

agent = AgentClient(http)

queued = agent.create(
    "Research the Prism ecosystem",
    {"preset": "deep-research", "max_steps": 8},
)

snapshot = agent.retrieve(queued.id)
finished = agent.wait(queued.id, max_attempts=300, interval_seconds=1.0)

if finished.is_successful():
    print(finished.text())
elif finished.status is AgentStatus.FAILED and finished.error is not None:
    print(finished.error.message)

agent.cancel(queued.id)
```
:::

`create()` takes the input (a string, or a list of input items) and an options
array that is sent to Perplexity as the request body. `background` defaults to
`true`; pass `'background' => false` to hold the request open until the run
completes.

### Responses

Every call returns an `AgentResponse`:

| Field | Contains |
| --- | --- |
| `id` | the response id — keep it to retrieve or cancel the run later |
| `status` | `queued`, `in_progress`, `completed`, `failed`, `incomplete` or `cancelled` |
| `model` | the model that ran, or null |
| `output` | the output items |
| `annotations` | every annotation (citations) from the output's content parts, flattened |
| `usage` | the provider's usage and cost object, unmodified |
| `error` | an `AgentError` (`message`, `code`, `type`) when the run reported one |
| `createdAt` / `created_at` | Unix timestamp, or null |
| `raw` | the unmodified response body |

`isTerminal()` / `is_terminal()` is true for `completed`, `failed`,
`incomplete` and `cancelled`; `isSuccessful()` / `is_successful()` only for
`completed`. `text()` joins every text part of the output. PHP's `status` is the
`Prism\Perplexity\Agent\AgentStatus` enum (`AgentStatus::Completed`, …), and
Python's is the `AgentStatus` enum (`AgentStatus.COMPLETED`, …); TypeScript
uses the string values. PHP responses also carry `protocol`
(`AgentProtocol::Agent`).

A run that ends `failed`, `incomplete` or `cancelled` is returned as a
response, not thrown. Check `status` before reading `text()`.

### Waiting

`wait()` retrieves the run up to `maxAttempts` times, sleeping the interval
between attempts, and returns as soon as the run is terminal. If it is still
running after the last attempt, `wait()` throws — it never returns an
unfinished run.

| | Defaults | Throws on timeout |
| --- | --- | --- |
| PHP | `maxAttempts: 60`, `intervalMilliseconds: 1000` | `Prism\Perplexity\Agent\AgentWaitTimedOut` (`$responseId`, `$attempts`) |
| TypeScript | `maxAttempts = 60`, `intervalMs = 1000` | `PerplexityError` with code `agent_wait_timed_out` |
| Python | `max_attempts=300`, `interval_seconds=1.0` | `PerplexityError` with code `agent_wait_timed_out` |

A deep-research run routinely takes about a minute, so the PHP and TypeScript
defaults of 60 one-second attempts can time out on a run that is working.
Pass a larger `maxAttempts` for those. In a queue worker, store the response id
and call `retrieve()` from a later job instead of waiting.

A `maxAttempts` below 1 or a negative interval is rejected
(`InvalidArgumentException` in PHP, `invalid_argument` in TypeScript and
Python).

The sleep between attempts can be replaced, for example in tests: the first
argument to `agent()` in PHP (a closure receiving milliseconds), and the second
constructor argument to `AgentClient` in TypeScript (returns a promise,
receives milliseconds) and Python (receives seconds).

Perplexity does not document an endpoint that lists Agent responses, so there
is no `list()`.

## Client options (PHP)

`search()` and `agent()` accept Laravel HTTP client options and retry settings;
embeddings use Prism's own `withClientOptions()` and `withClientRetry()`:

```php
use Prism\Prism\Facades\Prism;
use Prism\Prism\PrismManager;

$provider = app(PrismManager::class)->resolve('perplexity');

// search(query, options, clientOptions, clientRetry)
$results = $provider->search('prism php', ['max_results' => 5], ['timeout' => 10], [3, 500]);

// agent(sleeper, clientOptions, clientRetry)
$agent = $provider->agent(clientOptions: ['timeout' => 600], clientRetry: [2, 1000]);

$response = Prism::embeddings()
    ->using('perplexity', 'text-embedding-v1')
    ->fromInput('The quick brown fox')
    ->withClientOptions(['timeout' => 20])
    ->withClientRetry(3, 500)
    ->asEmbeddings();
```

`$clientRetry` is the argument list for Laravel's `retry()`: times, sleep in
milliseconds, an optional `when` callback and `throw`. The Agent API client
defaults to a 300-second timeout; a `timeout` in `$clientOptions` replaces it.

## Errors

Perplexity can report a failure inside an HTTP 200 response. Every call checks
the response body for an `error` object and throws when it finds one, so a
failure is never returned as an empty vector set or an empty result list. A
response that is not a JSON object also throws.

In PHP these are `Prism\Prism\Exceptions\PrismException`, with the message
`Perplexity Error [<status>]: <type> - <message>` and the HTTP status as the
exception code. The type is Perplexity's error type when it sent one, or
`unreadable_response`, `invalid_response`, `search_error`, `embeddings_error` or
`agent_request_error`.

In TypeScript and Python they are `PerplexityError`, with a stable `code`,
the provider's error type (`providerType` / `provider_type`) and the HTTP
`status` when known:

| Code | Meaning |
| --- | --- |
| `unreadable_response` | the endpoint did not return a JSON object |
| `provider_error` | the response carried an error, or the Agent API answered with a non-2xx status |
| `invalid_response` | an Agent API response is missing its id or a recognised status |
| `agent_wait_timed_out` | `wait()` ran out of attempts |
| `invalid_argument` | `wait()` was called with invalid arguments |

Python also exposes the codes as the `ErrorCode` enum.

::: language-group
```php
use Prism\Perplexity\Agent\AgentWaitTimedOut;
use Prism\Prism\Exceptions\PrismException;

try {
    $finished = $agent->wait($queued->id, maxAttempts: 5);
} catch (AgentWaitTimedOut $e) {
    // Still running: keep $e->responseId and retrieve it later.
} catch (PrismException $e) {
    report($e);
}
```

```ts
import { PerplexityError } from '@particle-academy/prism-perplexity';

try {
  await agent.wait(queued.id, 5, 1000);
} catch (error) {
  if (error instanceof PerplexityError && error.code === 'agent_wait_timed_out') {
    // Still running: keep queued.id and retrieve it later.
  } else if (error instanceof PerplexityError && error.code === 'provider_error') {
    console.error(error.status, error.providerType, error.message);
  } else {
    throw error;
  }
}
```

```python
from prism_perplexity import ErrorCode, PerplexityError

try:
    agent.wait(queued.id, max_attempts=5)
except PerplexityError as error:
    if error.code == ErrorCode.AGENT_WAIT_TIMED_OUT.value:
        pass  # Still running: keep queued.id and retrieve it later.
    elif error.code == ErrorCode.PROVIDER_ERROR.value:
        print(error.status, error.provider_type, error.message)
    else:
        raise
```
:::

Source: [Particle-Academy/prism-perplexity](https://github.com/Particle-Academy/prism-perplexity).
