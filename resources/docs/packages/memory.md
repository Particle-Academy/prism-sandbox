# Memory

`particle-academy/prism-memory`

An agent's useful context outgrows its context window. `prism-harness` gives a
conversation a thread, and a thread replayed whole is the crudest possible
memory: it grows without bound, costs tokens linearly, and eventually stops
fitting.

This stores what was said and retrieves **only the parts that matter now**.

```php
use Prism\Memory\Facades\PrismMemory;

$memory = PrismMemory::for($user, scope: 'support');

$memory->remember($response->messages);

$relevant = $memory->recall('billing address', budget: 1500);
```

## Recall takes a budget, not a count

`recall()` is given a token budget rather than "give me the top five". A caller
building a prompt knows how much room it has left; it does not know how many
memories will fit in that room, and a fixed count answers the wrong question —
five long memories and five short ones are not interchangeable.

## What it does not do

Recall is **retrieval, not summarisation** — it returns stored material, it does
not run a model to condense it. If you need a summary, build one from what
recall gives you.

Forgetting removes a memory. It does not trace and remove anything that was
derived from it.

- [Source](https://github.com/Particle-Academy/prism-memory)
- TypeScript and Python ports exist. See
  [Language ports](/docs/packages/language-ports) for what a port currently
  guarantees.
