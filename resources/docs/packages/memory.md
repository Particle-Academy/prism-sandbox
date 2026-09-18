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

An agent's useful context outgrows its context window. `prism-harness` gives a
conversation a thread, and a thread replayed whole is the crudest possible
memory: every turn costs tokens again, and the thread eventually outgrows the
window.

This stores what was said and retrieves **only the parts that matter now**.

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

## Recall takes a token budget

`recall()` is given the room you have left in the prompt, and fills it. That is
the number a caller actually knows — five long memories and five short ones fill
a prompt very differently, so a budget answers the question a fixed count only
approximates.

## What recall returns

Stored material, verbatim, ranked to fit the budget. Building a summary from it
is your call, with the model and the prompt you choose.

`forget()` removes the memory you name.

- [Source](https://github.com/Particle-Academy/prism-memory)
- TypeScript and Python ports exist. See
  [Language ports](/docs/packages/language-ports) for what a port currently
  guarantees.
