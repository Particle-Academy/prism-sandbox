# Memory

`particle-academy/prism-memory`

An agent's useful context outgrows its context window. `prism-harness` gives a
conversation a thread, and a thread replayed whole is the crudest possible
memory: every turn costs tokens again, and the thread eventually outgrows the
window.

This stores what was said and retrieves **only the parts that matter now**.

```php
use Prism\Memory\Facades\PrismMemory;

$memory = PrismMemory::for($user, scope: 'support');

$memory->remember($response->messages);

$relevant = $memory->recall('billing address', budget: 1500);
```

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
