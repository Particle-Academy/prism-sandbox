# Memory

`particle-academy/prism-memory`

::: language-group
```bash [language=php]
composer require particle-academy/prism-memory
```

```bash [language=typescript]
npm install @particle-academy/prism-memory
```

```bash [language=python]
pip install prism-ai-memory
```
:::

Prism Memory stores context and retrieves relevant records for a model request.
Use it with a Harness thread or independently to select context without sending
the complete conversation on every turn.

::: language-group
```php
use Prism\Memory\Facades\PrismMemory;

$memory = PrismMemory::for($user, scope: 'support');

$memory->remember($response->messages);

$relevant = $memory->recall('billing address', budget: 1500);
```

```ts
import {
  InMemoryVectorStore,
  RecallSettings,
  Vector,
  Weighting,
  recall,
} from '@particle-academy/prism-memory';

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
```

```python
from prism_memory import InMemoryVectorStore, RecallSettings, Vector, VectorRecord, Weighting, recall

store = InMemoryVectorStore()
store.upsert([
    VectorRecord(
        id="m-1",
        collection="user:7",
        content="Billing address is 12 Harbour Road.",
        created_at=1_789_000_000,
        vector=Vector.of(embedding),
        embedding_model="text-embedding-3-small",
    )
])

relevant = recall(
    store,
    ["user:7"],
    Vector.of(query_embedding),
    RecallSettings(limit=5, weighting=Weighting(relevance=0.8, recency=0.2)),
)
```
:::

## Recall limits

The PHP example limits recall with a token budget. The TypeScript and Python
examples configure the result count and relevance/recency weighting through
`RecallSettings`. Account for the size of the selected records when assembling
the final prompt.

## What recall returns

Recall returns stored content ranked by the configured retrieval policy. It
does not summarize that content. Summarization, if required, is a separate
application step. In PHP, `forget()` removes the specified memory.

- [Source](https://github.com/Particle-Academy/prism-memory)
- TypeScript and Python ports exist. See
  [Language ports](/docs/packages/language-ports) for what a port currently
  guarantees.
