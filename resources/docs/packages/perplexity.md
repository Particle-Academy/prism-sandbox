# Prism Perplexity

The rest of the Perplexity API — embeddings, direct web search, and the Agent
API, each reached the way Perplexity actually exposes it.

::: tip Status: published
`v0.2.0` on Packagist. Embeddings, search and the Agent API are tested and in
use. Async deep research is on the roadmap.
:::

## Installation

```bash
composer require particle-academy/prism-perplexity
```

That is the whole setup. The package registers under the **same provider key**
as Prism core, so `using('perplexity', …)` gains capabilities while every
call you already wrote keeps working — it extends Prism's Perplexity provider in
place.

Requires PHP 8.2+, Laravel 12.61+ or 13.12+, and `particle-academy/prism` 0.113
or newer. Your existing `PERPLEXITY_API_KEY` is the one it uses.

## Embeddings

Prism core throws `unsupportedProviderAction` for Perplexity embeddings. With
this installed:

```php
use Prism\Prism\Facades\Prism;

$response = Prism::embeddings()
    ->using('perplexity', 'text-embedding-v1')
    ->fromInput('The quick brown fox')
    ->asEmbeddings();

$response->embeddings[0]->embedding;   // float[]
```

### Contextualized embeddings

Perplexity has a second endpoint for chunks of **one document**, embedding each
in light of the others — which is what you want when you have split a document
for retrieval and want each chunk to carry the document around it.

Prism's embeddings abstraction is text-in, vector-out, so "these belong
together" is something you say explicitly:

```php
Prism::embeddings()
    ->using('perplexity', 'text-embedding-v1')
    ->fromArray($chunks)
    ->withProviderOptions(['contextualized' => true])
    ->asEmbeddings();
```

Vectors come back ordered by the `index` the API returns, so match on that index.
Lining vectors up with the inputs that produced them by position would be a
silent mismatch the first time a response arrived out of order.

## Search

Web results **straight from the index** — the sources a grounded answer would
have been built from, at the cost of the lookup alone.

```php
use Prism\Prism\PrismManager;

$provider = app(PrismManager::class)->resolve('perplexity');

$results = $provider->search('what is prism php', [
    'max_results' => 5,
    'search_domain_filter' => ['github.com'],
]);
```

It is a method on the provider rather than a `Prism::` verb, because the result
is a list of sources with structure worth keeping — every other Prism verb
answers with a model.

`search()` also takes an array of queries to run together. Results are plain
arrays, so every field Perplexity sends reaches you — including the ones it adds
after this page was written.

**An empty result list is an answer.** It comes back as `[]`.

## What lives here, and what lives in core

| | Where |
| --- | --- |
| Text, structured output, streaming | Prism core |
| Slug → preset translation, Agent API transport | Prism core |
| Embeddings, contextualized embeddings | here |
| Search API | here |
| Async deep research | planned |

The split follows the abstraction. Core carries what fits "prompt in, answer
out"; anything that is a genuinely different operation lives out here rather
than distorting core to fit it.

## Errors

Perplexity's agent surface returns failures inside an HTTP 200. This package
assumes the same is possible on every endpoint and branches on the response body
— an empty vector set or an empty result list read as a valid answer is worse
than an exception.

Source: [Particle-Academy/prism-perplexity](https://github.com/Particle-Academy/prism-perplexity).
