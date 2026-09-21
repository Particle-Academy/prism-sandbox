# Sessions and threads

A session is how a conversation survives the request that started it, in PHP,
TypeScript or Python. PHP integrates with Laravel; the other packages do not
require it.

::: language-group
```php
use Prism\Harness\PrismHarness;

$session = app(PrismHarness::class)->for($user)->session('support');

$session->usingMode('plan')->usingModel('claude-sonnet-5');

$response = $session->send('Where did we get to?');
```

```ts
const session = harness.for({ type: 'User', id: user.id }).session('support');

await session.usingMode('plan');
await session.usingModel('claude-sonnet-5');

const response = await runtime.send(session, 'Where did we get to?');
```

```python
from prism_harness import Participant

session = harness.for_(Participant("User", user.id)).session("support")
session.using_mode("plan").using_model("claude-sonnet-5")

response = runtime.send(session, "Where did we get to?")
```
:::

## Resolved on every request

Requests can reach different workers, and even a long-lived Node.js or Python
process eventually restarts. Session state lives in a store so the next worker
can continue the conversation. This applies across all three languages and
does not depend on a particular web framework:

| | |
|---|---|
| **Resolve** | address a session by participant *and* scope, so one user can hold several unrelated conversations that never merge |
| **Rehydrate** | rebuild its state from the store on every request, in whichever worker picks it up |
| **Lock** | serialize concurrent changes to the session; use application-level idempotency for repeated jobs and side effects |
| **Continue** | append the new exchange to the stored thread and supply the relevant history to the next run |

## Scope is part of the address

A session is keyed by participant and scope. Use separate scopes for unrelated
conversations, such as support and billing, to keep their histories separate.

## Locking

::: language-group
```php
$session->lock(function (Session $session) {
    // update session state while holding the lock
});
```

```ts
await session.lock(async (live) => {
  // update session state while holding the lock
});
```

```python
def advance(live):
    # update session state while holding the lock
    pass

session.lock(advance)
```
:::

Locking serializes session changes from concurrent workers. It does not prevent
sequential retries from repeating an action; use idempotency controls for
external side effects.

## Threads

The thread is the durable record of the exchange. It is appended to, and read
back selectively — see [Memory](/docs/packages/memory) for retrieving only the
parts that matter now, rather than replaying the whole thing into every prompt.
