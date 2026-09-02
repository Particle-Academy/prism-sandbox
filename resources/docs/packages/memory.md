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

## What it deliberately has not settled

The package README keeps a short list of questions it has **not** answered, and
they are open on purpose rather than closed quietly:

- what is worth storing, as against everything that was said
- whether recall should ever run a model, or stay retrieval only
- what forgetting means for a memory something else was derived from

These are recorded because a memory layer that guesses at them silently is
harder to trust than one that says which parts are unfinished.

- [Source](https://github.com/Particle-Academy/prism-memory)
- TypeScript and Python ports exist. See
  [Language ports](/docs/packages/language-ports) for what "port" currently
  guarantees, and what the cross-language suites have found.
