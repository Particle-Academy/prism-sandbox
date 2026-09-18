# Sessions and threads

A session is how a conversation survives the request that started it.

::: language-group
```php
$session = PrismHarness::for($user)->session('support');

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

A Laravel request boots, serves and dies. So a session lives in a store, rather
than being an object kept
in memory the way a long-lived Node process can keep one — every call rebuilds
it from a store.

That is the constraint the whole package is shaped around, and it is why the
four verbs below exist rather than a `new Agent(...)` you hold onto:

| | |
|---|---|
| **Resolve** | address a session by participant *and* scope, so one user can hold several unrelated conversations that never merge |
| **Rehydrate** | rebuild its state from the store on every request, in whichever worker picks it up |
| **Lock** | run work exactly once, even if the user double-submits or a queue retries |
| **Continue** | append to the thread rather than replaying it, so the conversation grows without the prompt growing with it |

## Scope is part of the address

A session is keyed by participant **and** scope. Without the scope, a user's
support conversation and their billing conversation are the same thread, and
the model answers one with the context of the other — the kind of bug that
stays quiet in testing and is very loud in production.

## Locking

::: language-group
```php
$session->lock(function (Session $session) {
    // the work that runs once
});
```

```ts
await session.lock(async (live) => {
  // the work that runs once
});
```

```python
def advance(live):
    # the work that runs once
    pass

session.lock(advance)
```
:::

An agent turn is expensive and frequently side-effectful. Two of them running
concurrently against one session is the failure this prevents — a double-submit,
a queue retrying a job whose first attempt is still in flight, two browser tabs.

## Threads

The thread is the durable record of the exchange. It is appended to, and read
back selectively — see [Memory](/docs/packages/memory) for retrieving only the
parts that matter now, rather than replaying the whole thing into every prompt.
