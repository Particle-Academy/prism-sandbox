# Memory

`particle-academy/prism-memory`

Prism Memory stores what was said in a conversation and retrieves the records
that bear on a new question, ranked by meaning and, optionally, by how recent
they are. Use it with a Harness thread or on its own to select context without
sending the complete conversation on every turn.

The PHP package is a complete memory layer: it embeds through Prism, stores in
your database, queues the embedding work and fits recall to a token budget. The
TypeScript and Python packages provide the storage contract, vectors, ranking
and an in-memory store. They do not embed text: you supply the vectors from
your own embeddings call.

## Installation

::: language-group
```bash [language=php]
composer require particle-academy/prism-memory
php artisan migrate
```

```bash [language=typescript]
npm install @particle-academy/prism-memory
```

```bash [language=python]
pip install prism-ai-memory
```
:::

In PHP the `memory_vectors` migration is loaded automatically, so `migrate` is
the only setup step. No Redis, vector database or queue worker is required.

## Configuration (PHP)

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag=memory-config
```

| Key | Env | Default | Meaning |
|---|---|---|---|
| `default_scope` | `MEMORY_DEFAULT_SCOPE` | `default` | Scope used when `for()` is called without one. |
| `remember_tool_results` | `MEMORY_REMEMBER_TOOL_RESULTS` | `true` | Store tool results verbatim alongside user and assistant text. |
| `embeddings.provider` | `MEMORY_EMBEDDINGS_PROVIDER` | `openai` | Prism provider used to embed. |
| `embeddings.model` | `MEMORY_EMBEDDINGS_MODEL` | `text-embedding-3-small` | Embedding model. |
| `embeddings.batch` | `MEMORY_EMBEDDINGS_BATCH` | `96` | Memories per embeddings call. |
| `recall.limit` | `MEMORY_RECALL_LIMIT` | `8` | Results returned when no `limit` or `budget` is given. |
| `recall.overfetch` | `MEMORY_RECALL_OVERFETCH` | `8` | Candidates fetched per result before weighting reorders them. |
| `recall.min_score` | `MEMORY_RECALL_MIN_SCORE` | `null` | Drop results scoring below this. |
| `recall.relevance` | `MEMORY_RECALL_RELEVANCE` | `1.0` | Weight on similarity. |
| `recall.recency` | `MEMORY_RECALL_RECENCY` | `0.0` | Weight on recency. Raise it when newer means truer. |
| `recall.half_life` | `MEMORY_RECALL_HALF_LIFE` | `604800` (7 days) | Seconds for a memory's recency weight to halve. |
| `recall.query_cache` | `MEMORY_RECALL_QUERY_CACHE` | `300` | Seconds an identical query's embedding is reused. `0` disables. |
| `retention` | `MEMORY_RETENTION` | `null` | Seconds a memory lives. `null` keeps it. Expiry is enforced on read. |
| `store` | `MEMORY_STORE` | `database` | Which entry under `drivers` to use. |
| `require_durable` | `MEMORY_REQUIRE_DURABLE` | `true` | Refuse a store that reports itself volatile. |
| `drivers.database.connection` | `MEMORY_DB_CONNECTION` | app default | Database connection. Table `memory_vectors`. |
| `drivers.database.index.strategy` | `MEMORY_INDEX_STRATEGY` | `signature` | `signature` (fingerprint pre-ranking, exact rescoring) or `exact` (reads every vector). |
| `drivers.database.index.bits` | `MEMORY_INDEX_BITS` | `256` | Signature width. |
| `drivers.database.index.candidates` | `MEMORY_INDEX_CANDIDATES` | `256` | Candidates read in full and scored exactly. |
| `drivers.database.index.seed` | `MEMORY_INDEX_SEED` | `prism-memory` | Signature seed. |
| `drivers.database.index.exact_scan_limit` | `MEMORY_INDEX_EXACT_SCAN_LIMIT` | `2000` | Exact-scan ceiling. |
| `drivers.pgvector.connection` | `MEMORY_PGVECTOR_CONNECTION` | app default | Postgres connection. Table `memory_vectors_pgvector`. |
| `drivers.pgvector.dimensions` | `MEMORY_PGVECTOR_DIMENSIONS` | `1536` | Must match the embedding model (3072 for `text-embedding-3-large`). |
| `drivers.pgvector.ef_search` | `MEMORY_PGVECTOR_EF_SEARCH` | `100` | HNSW candidate list size: higher finds more true neighbours, more slowly. |

::: warning Changing the model, bits or seed
Vectors from two embedding models are not comparable, and a search across them
throws `EmbeddingSpaceMismatch` instead of returning results. Re-embed or use a
new scope after changing `embeddings.model`. Changing `index.bits` or
`index.seed` invalidates every stored signature; change them only with a
re-index.
:::

### Postgres and pgvector

The `database` driver is linear in the size of a collection. Past roughly twenty
thousand memories in one collection, switch to pgvector:

```bash
php artisan vendor:publish --tag=memory-pgvector-migrations
php artisan migrate
```

Then set `MEMORY_STORE=pgvector`. The migration runs
`CREATE EXTENSION IF NOT EXISTS vector`, which needs a database role allowed to
install extensions. Any other vector database can be registered with
`VectorStoreManager::extend()`:

```php
use Prism\Memory\Facades\PrismMemory;

// In a service provider's boot(). $config is memory.drivers.qdrant, or [] when absent.
PrismMemory::stores()->extend('qdrant', fn (array $config, $app) => new QdrantVectorStore($config));
```

Set `MEMORY_STORE=qdrant` to use it. The factory must return an implementation
of `Prism\Memory\Contracts\VectorStore`.

## Remember and recall

::: language-group
```php
use Prism\Memory\Facades\PrismMemory;
use Prism\Prism\Facades\Prism;

$memory = PrismMemory::for($user, scope: 'support');

// The whole exchange, including tool steps. Returns the ids written.
$ids = $memory->remember($response->messages);

$recollection = $memory->recall('billing address', budget: 1500);

Prism::text()
    ->using('anthropic', 'claude-sonnet-4-5')
    ->withSystemPrompt($instructions)
    ->withSystemPrompt($recollection->asContext())
    ->withPrompt($question)
    ->asText();
```

```ts
import {
  InMemoryVectorStore,
  RecallSettings,
  Vector,
  Weighting,
  recall,
} from '@particle-academy/prism-memory';

// embedding and queryEmbedding are number[] from your embeddings provider.
const store = new InMemoryVectorStore();

await store.upsert([{
  id: 'm-1',
  collection: 'user:7',
  content: 'Billing address is 12 Harbour Road.',
  kind: 'observation',
  vector: Vector.of(embedding),
  embeddingModel: 'text-embedding-3-small',
  metadata: {},
  provenance: { source: 'support', author: 'user:7', observedAt: 1_789_000_000 },
  createdAt: 1_789_000_000,
}]);

const relevant = await recall(
  store,
  { collections: ['user:7'], vector: Vector.of(queryEmbedding) },
  new RecallSettings(5, 8, null, new Weighting(0.8, 0.2)),
);

for (const memory of relevant.memories) {
  console.log(memory.score, memory.record.content);
}
```

```python
import time

from prism_memory import (
    InMemoryVectorStore,
    Provenance,
    RecallSettings,
    Vector,
    VectorRecord,
    Weighting,
    recall,
)

# embedding and query_embedding are lists of floats from your embeddings provider.
store = InMemoryVectorStore()
store.upsert([
    VectorRecord(
        id="m-1",
        collection="user:7",
        content="Billing address is 12 Harbour Road.",
        created_at=1_789_000_000,
        vector=Vector.of(embedding),
        embedding_model="text-embedding-3-small",
        provenance=Provenance(source="support", author="user:7", observed_at=1_789_000_000),
    )
])

relevant = recall(
    store,
    ["user:7"],
    Vector.of(query_embedding),
    RecallSettings(limit=5, weighting=Weighting(relevance=0.8, recency=0.2)),
    now=int(time.time()),
)

for memory in relevant.memories:
    print(memory.score, memory.record.content)
```
:::

::: warning Python: pass `now`
Python's `recall()` takes the current time as `now` and defaults it to `0`.
With the default every memory counts as brand new, so a recency weight has no
effect. Pass `now=int(time.time())` whenever the weighting uses recency.
TypeScript defaults `now` to the current time.
:::

### `remember()` (PHP)

```php
remember(iterable|Message|string $subject, array $metadata = [], ?Carbon $occurredAt = null): array
```

- Accepts a string, one Prism message, or a list of messages and strings.
  User and assistant messages are stored with their role; tool results are
  stored verbatim unless `remember_tool_results` is off; system messages are
  skipped. Any other value throws `UnstorableMemory`.
- `$metadata` is merged into every record. Values must be scalars or `null`,
  and keys cannot contain `->`, `[` or `"`. The keys `kind`, `role` and
  `source_*` are reserved.
- `$occurredAt` sets when the remembered thing happened (default now). Recency
  and `forgetBefore()` use this time.
- Ids are derived from the content, role and tool name, so remembering the
  same text twice replaces one record.

### `recall()` (PHP)

```php
recall(
    string $query,
    ?int $limit = null,
    ?int $budget = null,
    ?Weighting $weighting = null,
    ?float $minScore = null,
    array $filter = [],
): Recollection
```

- `budget` fills up to that many estimated tokens instead of taking a fixed
  `limit`. Memories are taken best first; one that does not fit is skipped and
  smaller ones behind it can still be taken.
- `weighting` overrides the configured mix, for example
  `new Weighting(relevance: 0.7, recency: 0.3)`, `Weighting::balanced()` or
  `Weighting::relevanceOnly()`.
- `filter` is equality on metadata; an array value means "any of". For
  example `filter: ['kind' => 'tool_result']`.
- An empty query returns an empty `Recollection`.

TypeScript and Python have no token budget: `RecallSettings` sets `limit`,
`overfetch`, `minScore` and the `Weighting`, and `recall()` returns at most
`limit` results. Filters and a store-side minimum similarity are passed with
the query (`filter`, `minSimilarity` in TypeScript; `filter=`,
`min_similarity=` in Python).

### Embedding runs on the queue (PHP)

`remember()` writes the records immediately and dispatches an `EmbedMemories`
job to embed them. A record cannot be recalled until its vector exists. On
Laravel's default `sync` queue connection the job runs inline, so this is
invisible. With a queue worker, a `recall()` straight after `remember()` does
not find the new records until the queue has processed the job.

```php
// Embed inline for a caller that must read its own write.
$memory->synchronously()->remember('Prefers email over phone.');

$memory->pending();      // stored but not yet searchable
$memory->embedPending(); // embed one batch of whatever is waiting
```

`synchronously()` returns a copy, so it does not change the shared instance.
A `pending()` count that stays above zero means the queue is not draining.

## Forgetting

::: language-group
```php
$memory->forget($ids);         // only these memories
$memory->forget();             // EVERY memory for this owner and scope
$memory->forgetBefore($date);  // everything that occurred before $date
$memory->count();
```

```ts
await store.forget('user:7', ['m-1']);
await store.purge('user:7');
await store.purgeObservedBefore('user:7', 1_789_000_000);
await store.count('user:7');
```

```python
store.forget("user:7", ["m-1"])
store.purge("user:7")
store.purge_observed_before("user:7", 1_789_000_000)
store.count("user:7")
```
:::

::: danger `forget()` with no ids deletes the whole scope
In PHP, `forget()` called without ids removes every memory for that owner and
scope. Pass the ids returned by `remember()` to remove specific memories.
:::

Deletes are hard deletes, and each call returns the number of rows removed.
`forgetBefore()` cuts on when the remembered thing occurred, not on when the
row was written.

## Data shapes

### PHP: `Recollection`

`recall()` returns a `Recollection`, which is countable and iterable over
`Recalled` items.

| Method | Returns |
|---|---|
| `all()` | Memories in chronological order, oldest first. |
| `ranked()` | Memories best score first. For inspection, not for building a prompt. |
| `asContext()` | One block of text, one line per memory: `[2026-10-25T09:00:00Z] user: ...`. Empty string when nothing was recalled. |
| `asSystemMessage()` | The same text as one `SystemMessage`. |
| `asMessages()` | `[]` or a list holding that one system message. |
| `digest()` | SHA-256 of `asContext()`. |
| `isEmpty()` / `count()` | Emptiness and size. |
| `estimatedTokens` | Estimated token size of the recalled lines (property). |

The prompt helpers are chronological so the recalled block stays byte-stable
between turns and provider prompt caches keep hitting.

::: warning Do not pair `asMessages()` with `withPrompt()`
Prism refuses a request that has both an explicit message list and a prompt.
Pass `asContext()` to `withSystemPrompt()` as shown above, and use
`asMessages()` only when you build the whole message list yourself.
:::

Each `Recalled` has `id`, `content`, `kind` (`MemoryKind::Observation` or
`MemoryKind::ToolResult`), `role`, `similarity` (raw cosine), `recency`
(decay factor 0 to 1), `score` (what the ranking used), `occurredAt` and
`metadata`.

### TypeScript and Python

| Type | Fields |
|---|---|
| `VectorRecord` | `id`, `collection`, `content`, `kind` (`'observation'`), `vector` (null until embedded), `embeddingModel` / `embedding_model`, `metadata`, `provenance`, `createdAt` / `created_at` |
| `Provenance` | `source`, `author`, `observedAt` / `observed_at` (unix seconds) |
| `Recollection` | `memories` (best score first), `candidates` (how many the store returned before reranking) |
| `Recalled` | `record`, `similarity`, `score` |

Recency is measured from `provenance.observedAt` when set, otherwise from
`createdAt`. Vectors serialise with `toStorage()` / `to_storage()` as base64 of
little-endian float64 values, the same format PHP stores.

### Stores

`InMemoryVectorStore` keeps records in process memory. It reports itself as
`volatile` and loses everything when the process exits; use it for tests and
single-process tools. For durable storage, implement the `VectorStore`
contract:

| Method (TypeScript) | Python |
|---|---|
| `upsert(records)` | `upsert(records)` |
| `search(query)` | `search(query)` |
| `forget(collection, recordIds)` | `forget(collection, record_ids)` |
| `purge(collection)` | `purge(collection)` |
| `purgeObservedBefore(collection, before)` | `purge_observed_before(collection, before)` |
| `count(collection, embeddedOnly?)` | `count(collection, embedded_only=False)` |
| `unembedded(collection, limit?)` | `unembedded(collection, limit=100)` |
| `durability()` | `durability()` |

TypeScript methods return promises, except `durability()`. A `search` must
skip records with no vector and must refuse to mix vectors from different
embedding models in one collection.

## With Harness (PHP)

Prism Memory can act as Harness's eviction sink and context recall. Turns that
compaction pushes out of the context window are stored in memory, and the
agent gets a `recall_context` tool that searches them by meaning within the
token budget Harness passes.

```php
use Prism\Harness\Contracts\ContextRecall;
use Prism\Harness\Contracts\EvictionSink;
use Prism\Memory\Harness\MemoryContextRecall;
use Prism\Memory\Harness\MemoryEvictionSink;
use Prism\Memory\PrismMemory;

// In a service provider. Both packages installed.
$this->app->singleton(EvictionSink::class, fn () => new MemoryEvictionSink(
    app(PrismMemory::class),
    $owner,
));

$this->app->singleton(ContextRecall::class, fn () => new MemoryContextRecall(
    app(PrismMemory::class),
    $owner,
));
```

`$owner` is who the memories are filed under, usually the participant whose
conversation it is. The scope is the Harness thread key. Evicted turns are
stored with the metadata `evicted => true`; pass `evictedOnly: true` to
`MemoryContextRecall` to search only those. A message that cannot be stored is
reported and skipped without dropping the rest of the turn.

Harness is optional: nothing in Prism Memory loads these classes unless you
bind them.

## Errors

PHP throws a dedicated exception class. TypeScript and Python throw
`MemoryError` with a `code`; the codes are in the TypeScript `MemoryErrorCode`
type and the Python `ErrorCode` enum.

| PHP exception | Code | When |
|---|---|---|
| `InvalidVector` | `invalid_vector` | An empty, non-numeric, NaN, infinite or zero vector, or a corrupt stored payload. TypeScript and Python also use this code for invalid `Weighting` and `RecallSettings` arguments. |
| `InvalidVector` | `dimension_mismatch` | Two vectors of different lengths were compared. |
| `EmbeddingSpaceMismatch` | `embedding_space_mismatch` | A collection embedded by one model was written or searched with another, or a stored vector's width does not match its recorded model. |
| `UnsafeMemoryConfiguration` | `unsafe_memory_configuration` | The configured store is volatile while `require_durable` is on, or `store` names an unknown driver. |
| `UnstorableMemory` | `unstorable_memory` | Blank content, a value `remember()` cannot store, non-scalar metadata, or a metadata key containing `->`, `[` or `"`. |
| `EmbeddingFailed` | — | The embeddings provider returned a different number of vectors than inputs. The whole batch is refused. PHP only. |

## Security notes

- Memory is addressed by owner and scope. Recall in one scope never returns
  another scope's memories, so keep unrelated conversations in separate scopes.
- Recalled text is what users and tools said earlier. Treat it as untrusted
  input in the prompt, the same as the original messages.
- Tool results are stored verbatim by default, including any personal data
  they contain. Turn off `remember_tool_results` where tool output is
  disposable, and set `retention` if records must expire.
- Deletes are hard deletes and return a count, so an erasure request can be
  evidenced.

- [Source](https://github.com/Particle-Academy/prism-memory)
