# Prism Perplexity

Prism Perplexity provides access to Perplexity embeddings, web search and Agent
API operations. Packages are available for PHP, TypeScript and Python.

::: tip Versioning
The language packages are versioned independently. Review the selected
package's release notes for endpoint support before upgrading.
:::

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

In PHP, the package extends Prism's existing `perplexity` provider registration.
TypeScript and Python expose functions that take a configured HTTP transport;
they do not register a Perplexity provider in core.

The PHP package requires PHP 8.2+, Laravel 12.61+ or 13.12+, and
`particle-academy/prism` 0.113 or newer. Configure `PERPLEXITY_API_KEY` in your
server environment.

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

### Contextualized embeddings

Contextualized embeddings represent chunks from one document using the
surrounding document context. Enable this option when the supplied chunks
belong to the same document:

::: language-group
```php
Prism::embeddings()
    ->using('perplexity', 'text-embedding-v1')
    ->fromArray($chunks)
    ->withProviderOptions(['contextualized' => true])
    ->asEmbeddings();
```

```ts
await embeddings(http, {
  model: 'text-embedding-v1',
  inputs: chunks,
  contextualized: true,
});
```

```python
embeddings(
    http,
    "text-embedding-v1",
    chunks,
    contextualized=True,
)
```
:::

Use the response index to associate each vector with its input; response
arrival order may differ from input order.

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

`search()` also takes an array of queries to run together. Results are plain
arrays, so every field Perplexity sends reaches you — including the ones it adds
after this page was written.

**An empty result list is an answer.** It comes back as `[]`.

## PHP integration

| | Where |
| --- | --- |
| Text, structured output, streaming | Prism core |
| Slug → preset translation, Agent API transport | Prism core |
| Embeddings, contextualized embeddings | here |
| Search API | here |
| Async deep research | planned |

This table describes the PHP integration. For TypeScript and Python, use the
satellite's exported functions for supported Perplexity operations.

## Errors

The client checks response bodies for API failures, including failures returned
with HTTP 200. Handle these exceptions separately from a valid empty search result.

Source: [Particle-Academy/prism-perplexity](https://github.com/Particle-Academy/prism-perplexity).
