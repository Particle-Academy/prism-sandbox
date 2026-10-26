# Sessions and threads

A session is how a conversation survives the request that started it. It holds
the participant's active mode, provider and model, its run bookkeeping, any
capability state, and the thread of messages, all read from a store on every
request. This page covers the session API in depth; the
[Harness overview](/docs/packages/harness) shows how to set one up.

## Resolving a session

A session is addressed by a **participant** and a **scope**. The participant is
who the conversation belongs to; the scope separates that participant's
unrelated conversations, such as `support` and `billing`, so their histories
never merge.

::: language-group
```php
use Prism\Harness\Facades\PrismHarness;

$session = PrismHarness::for($user)->session('support');

$session = PrismHarness::session($user, 'support');   // the same address
$session = PrismHarness::for($user)->session();       // the configured default scope
```

```ts
const session = harness.for({ type: 'User', id: user.id }).session('support');
```

```python
from prism_harness import Participant

session = harness.for_(Participant("User", user.id)).session("support")
```
:::

In PHP the participant is any Eloquent model, and a missing scope falls back to
`default_scope` (`default`). In TypeScript and Python the participant is a type
and an id, and the scope is required.

Resolve a session on every request rather than holding one between requests.
A session object caches what it has read, so one kept across requests goes
stale as soon as another worker changes it.

### The session key

`key()` returns the address the session is stored under:

```
session:<first 12 hex characters of sha1(participant type)>:<participant id>:<scope>
```

The participant type is the Eloquent morph class in PHP and `type` in
TypeScript and Python. All three build the key the same way, so a PHP
application and a TypeScript or Python worker pointed at the same store resolve
the same session.

## Session state

Mode, provider and model live in the **ephemeral** slot. Losing them only means
the next run falls back to the defaults.

::: language-group
```php
$session->usingMode('plan')->usingProvider('anthropic')->usingModel('claude-sonnet-4-5');

$session->mode();       // 'plan', or null before one is chosen
$session->provider();
$session->model();
$session->state();      // the whole ephemeral array
$session->forget();     // drop the ephemeral half; the thread is untouched
```

```ts
await session.usingMode('plan');
await session.usingProvider('anthropic');
await session.usingModel('claude-sonnet-4-5');

await session.mode(); // 'plan', or null before one is chosen
await session.provider();
await session.model();
await session.state(); // the whole ephemeral object
await session.forget(); // drop the ephemeral half; the thread is untouched
```

```python
session.using_mode("plan").using_provider("anthropic").using_model("claude-sonnet-4-5")

session.mode()      # "plan", or None before one is chosen
session.provider()
session.model()
session.state()     # the whole ephemeral dict
session.forget()    # drop the ephemeral half; the thread is untouched
```
:::

In PHP the ephemeral half expires after `ephemeral_ttl` seconds untouched (a
day by default). TypeScript and Python take `ttlSeconds` / `ttl_seconds` on the
`PrismHarness` constructor; the default keeps it until forgotten.

PHP sessions can also ask for a step limit for their runs with
`usingMaxSteps(20)`, read back with `maxSteps()`. It is capped by
`agent.max_steps_ceiling`; see
[Run-specific limits](/docs/harness/subagents#run-specific-limits).

### Run bookkeeping

Each run started through the runtime records itself on the session. `run()`
returns the latest run, or null:

| Field | Value |
| --- | --- |
| `id` | the run id |
| `status` | `running`, `completed` or `failed` |
| `mode`, `provider`, `model` | what the run used |
| `started_at`, `finished_at` | ISO 8601 timestamps |
| `finish_reason` | on a completed run |
| `tool_calls` | on a completed run: the **names** of the tools it called, in order. Arguments are never stored here |
| `failure` | on a failed run: the exception class (PHP), the error message, or a code for structured failures (TypeScript, Python). A budget refusal in PHP records `budget` |

`beginRun()`, `completeRun()` and `failRun()` (`begin_run`, `complete_run`,
`fail_run` in Python) are what the runtime calls. Use them only if you drive
runs without the runtime.

## Capability state

Capability state is a small JSON map per name, kept in the **durable** slot
beside the thread. Use it for state that belongs to the conversation and must
survive a deploy, such as which external resource a session is attached to.
It is separate from the ephemeral state, so `forget()` does not clear it.

::: language-group
```php
$session->usingCapability('browser', ['attachment_id' => $attachment->id, 'opened_at' => now()->toIso8601String()]);

$session->capability('browser');        // ['attachment_id' => ..., 'opened_at' => ...], or null
$session->forgetCapability('browser');
```

```ts
await session.usingCapability('browser', { attachment_id: attachment.id, opened_at: new Date().toISOString() });

await session.capability('browser'); // { attachment_id, opened_at }, or null
await session.forgetCapability('browser');
```

```python
session.using_capability("browser", {"attachment_id": attachment.id, "opened_at": opened_at})

session.capability("browser")  # {"attachment_id": ..., "opened_at": ...}, or None
session.forget_capability("browser")
```
:::

Each call replaces the whole map for that name. Capability writes are not
locked; change them inside [`lock()`](#locking) when two workers can write the
same session.

## Threads

The thread is the durable record of the conversation. The runtime appends each
run's messages to it, tagged with the run id, and reads it back as the history
for the next run.

::: language-group
```php
$thread = $session->thread();

foreach ($thread->messages() as $message) {
    // Prism message objects: UserMessage, AssistantMessage, ToolResultMessage, ...
}

$thread->record($messages, $runId);   // append Prism messages yourself
```

```ts
const thread = session.thread();

for (const entry of await thread.messages()) {
  entry.position; // from 1
  entry.message; // the stored message object: { type: 'user' | 'assistant' | 'tool_result', ... }
  entry.runId; // the run that wrote it, or null
  entry.recordedAt;
}

await thread.count();
await thread.record([{ type: 'user', content: 'Hello' }], runId);
```

```python
thread = session.thread()

for entry in thread.messages():
    entry.position     # from 1
    entry.message      # the stored message dict: {"type": "user" | "assistant" | "tool_result", ...}
    entry.run_id       # the run that wrote it, or None
    entry.recorded_at

thread.count()
thread.record([{"type": "user", "content": "Hello"}], run_id)
```
:::

`record()` assigns positions inside the store lock, so two turns recorded at
the same moment never overwrite each other. In PHP, `messages()` applies the
configured [compaction](/docs/packages/harness#the-context-window); the stored
rows are never shortened. For retrieving only the relevant parts of a long
history, see [Memory](/docs/packages/memory).

### Starting a new conversation

In PHP, `newConversation()` retires the current thread and returns a fresh,
empty one at the same address. The retired thread keeps every message and stays
readable by id; the session's mode, provider, model and capability state are
unchanged.

::: language-group
```php
$old = $session->thread();
$thread = $session->newConversation();   // the next send() starts from an empty history

$old->fresh()->isRetired();              // true: the old thread is kept, not deleted
```

```ts
// No newConversation(). Start a new conversation under a new scope, or
// permanently delete this thread's messages:
await session.thread().clear();
```

```python
# No new_conversation(). Start a new conversation under a new scope, or
# permanently delete this thread's messages:
session.thread().clear()
```
:::

In TypeScript and Python, `clear()` **deletes** the history; it is not a
"new chat" that keeps the old one.

PHP also offers `Thread::shared($scope)` for a conversation addressed by scope
alone, with no participant. It makes no claim about who may read it; enforce
that in your application.

## Locking

`lock()` runs a callback while holding an exclusive lock on the session. Use it
for anything that reads session state and writes it back, so two workers
cannot both act on the same stale read.

::: language-group
```php
use Prism\Harness\Exceptions\SessionLocked;
use Prism\Harness\Sessions\Session;

try {
    $session->lock(function (Session $live): void {
        if ($live->capability('browser') === null) {
            $live->usingCapability('browser', ['attachment_id' => openAttachment()]);
        }
    }, ttlSeconds: 30, waitSeconds: 5);
} catch (SessionLocked) {
    // another worker held the session for longer than waitSeconds
}
```

```ts
import { HarnessError } from '@particle-academy/prism-harness';

try {
  await session.lock(
    async (live) => {
      if ((await live.capability('browser')) === null) {
        await live.usingCapability('browser', { attachment_id: await openAttachment() });
      }
    },
    30, // ttlSeconds
    5, // waitSeconds
  );
} catch (error) {
  if (error instanceof HarnessError && error.code === 'session_locked') {
    // another worker held the session for longer than waitSeconds
  }
}
```

```python
from prism_harness import HarnessError


def attach(live):
    if live.capability("browser") is None:
        live.using_capability("browser", {"attachment_id": open_attachment()})


try:
    session.lock(attach, ttl_seconds=30, wait_seconds=5)
except HarnessError as error:
    if error.code != "session_locked":
        raise
    # another worker held the session for longer than wait_seconds
```
:::

| Parameter | Default | Meaning |
| --- | --- | --- |
| `ttlSeconds` / `ttl_seconds` | `10` | How long the lock lasts if the holder never releases it, so a crashed worker cannot wedge the session |
| `waitSeconds` / `wait_seconds` | `5` | How long to wait for another holder before giving up with `SessionLocked` / `session_locked` |

State is re-read when the lock is acquired, so the callback sees what the
previous holder wrote. The lock is not reentrant: calling `lock()` again on the
same session inside the callback waits for itself and fails. Set the TTL longer
than the work inside it; if a lock expires mid-callback, another worker can
enter. Locking does not make anything exactly-once: a retried job can still
repeat an external side effect, so use idempotency keys for those.

### Locking a run

In PHP, `send()`, `sendStructured()` and `stream()` hold the session lock for
the whole run, using `agent.lock_ttl` (300 seconds) and `agent.lock_wait`
(0 seconds). With the default wait of 0, a second turn sent to a busy session
fails immediately with `SessionLocked`; raise `HARNESS_RUN_LOCK_WAIT` to make it
wait instead. Set `HARNESS_RUN_LOCK_TTL` longer than your slowest run.

The TypeScript and Python runtimes do not lock around a run. When two workers
can send to the same session, serialize turns with the store's lock under a key
of your own. Do not wrap `runtime.send()` in `session.lock()`: resuming an
approval takes the session lock internally, and the lock is not reentrant.

::: language-group
```php
// send() already holds the session lock for the run.
$session->send('Help with my order');
```

```ts
const response = await harness
  .ephemeralStore()
  .withLock(`${session.key()}:turn`, () => runtime.send(session, 'Help with my order'), 300, 30);
```

```python
response = harness.ephemeral_store().with_lock(
    f"{session.key()}:turn",
    lambda: runtime.send(session, "Help with my order"),
    300,
    30,
)
```
:::

The ephemeral store's lock covers every process that shares that store. A
`MemorySessionStore` lock covers one process only; a `FileSessionStore` lock
covers processes on one machine.

## Task lists

Each session has its own durable task list at `tasks()`. See
[Task lists](/docs/harness/tasks).
