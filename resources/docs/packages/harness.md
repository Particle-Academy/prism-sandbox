# Prism Harness

Durable agent sessions for PHP, TypeScript and Python — threads, reconstructed session state,
persisted provider/model/mode selection, exclusive locking, and a bounded agent
runtime built on top of Prism, with nested subagents that carry their own
budgets.

::: warning Pre-1.0 — the API can still change in a minor version
Each language package is versioned independently. Pin the package you install
and review its release notes when upgrading.

**Tool authorization is off by default**; configure it explicitly for your
application. PHP additionally provides `php artisan harness:doctor` to inspect
its resolved configuration.
:::

## When to use Harness

Use Harness for agents that need persistent conversations, configurable modes,
tool execution and human approvals across requests. For individual model
requests without session state, use Prism core directly.

## Installation

Choose the package for your runtime. PHP integrates with Laravel; TypeScript
and Python run independently of Laravel.

::: language-group
```bash [language=php]
composer require particle-academy/prism-harness
php artisan migrate
```

```bash [language=typescript]
npm install @particle-academy/prism-harness
```

```bash [language=python]
pip install prism-ai-harness
```
:::

In PHP/Laravel, the migrations are **loaded for you**. Running `migrate` creates
three tables:
`harness_threads`, `harness_thread_messages` and `harness_session_state`.

PHP applications can publish the config with
`php artisan vendor:publish --tag=harness-config` when they need to change it.
TypeScript and Python take drivers and mode configuration in their
constructors. They do not require Artisan or Eloquent. Configure a durable
store before opening a session, as shown under **State stores** below.

Runtime requirements: PHP 8.2+ with Laravel 12.61+ or 13.12+ and
`particle-academy/prism` 0.113 or newer; Node.js 20+ for TypeScript;
Python 3.10+ for Python.

## Threads

A thread is a stored conversation, addressed by participant and scope. The
Harness reads its history for a run and records the resulting exchange.

PHP's Eloquent thread also implements Prism's `Thread` contract for direct
`withThread()` calls. TypeScript and Python use the thread supplied by their
configured session store. Their examples below assume configured `harness`
and `runtime` instances.

::: language-group
```php
use Prism\Harness\Models\Thread;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

$thread = Thread::forParticipant($user, 'support');

$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-sonnet-4-5')
    ->withThread($thread)               // everything said so far
    ->withPrompt('And after that?')     // the turn being taken now
    ->asText();

$thread->record($response->messages);   // the full exchange, tool steps included
```

```ts
const session = harness.for({ type: 'User', id: user.id }).session('support');

const response = await runtime.send(session, 'And after that?');

// runtime.send records the full exchange, including tool steps.
const messages = await session.thread().messages();
```

```python
from prism_harness import Participant

session = harness.for_(Participant("User", user.id)).session("support")

response = runtime.send(session, "And after that?")

# runtime.send records the full exchange, including tool steps.
messages = session.thread().messages()
```
:::

The recorded exchange includes tool calls and results alongside the final
answer. With PHP's direct Prism call, record the returned messages explicitly;
the Harness runtime records them when it sends a turn. Pending approvals are
stored so the application can collect a decision and resume the run.

### Addressed by participant *and* scope

The participant and scope identify a conversation. The same participant can
have separate `support` and `coding` threads without sharing history.

A fresh worker using the same store and address resolves the same thread.
PHP's Eloquent implementation uses `firstOrCreate` for this.

### Threads store a format built for replay

The Harness owns its stored message format separately from provider wire
formats and debug serialization. Each implementation preserves the message
and attachment information needed to replay the conversation.

In the PHP implementation:

- Content parts are stored with their concrete class, so an image comes back an
  image and a document comes back a document.
- Content that cannot be stored without losing information throws
  `UnmappableContent` at write time.

::: danger Thread rows are trusted storage
In PHP thread storage, rebuilding an attachment can resolve a recorded locator, so a row
carrying a `local_path` or a `url` becomes a file read or an outbound fetch at
replay time. This requires write access to your database. **Never let request input write directly into
`harness_thread_messages`.** TypeScript and Python runtime attachments reject
URL/local-path references; provide embedded bytes, provider file IDs or document
chunks. In every language, validate attachments before recording them.
:::

## Sessions

A session is one participant's live runtime: the active mode, the selected
model, and the thread they are bound to. It is **resolved per request**.

::: language-group
```php
use Prism\Harness\PrismHarness;

$session = app(PrismHarness::class)->for($user)->session('support');

$session->usingMode('plan')->usingModel('claude-sonnet-4-5');

$session->thread();   // the durable conversation for this address
$session->mode();     // 'plan' — read back from the store, in any worker
```

```ts
const session = harness.for({ type: 'User', id: user.id }).session('support');

await session.usingMode('plan');
await session.usingModel('claude-sonnet-4-5');

session.thread();     // the durable conversation for this address
await session.mode(); // 'plan' — read back from the store, in any worker
```

```python
from prism_harness import Participant

session = harness.for_(Participant("User", user.id)).session("support")

session.using_mode("plan").using_model("claude-sonnet-4-5")

session.thread()  # the durable conversation for this address
session.mode()    # "plan" — read back from the store, in any worker
```
:::

### Running the durable agent

`send()` resolves the session's configured mode, provider, model, system
prompt, and explicit tool allowlist, then performs the Prism run while holding
the session lock. It records every returned message and leaves durable run
bookkeeping on both success and failure.

::: language-group
```php
$result = $session
    ->usingProvider('anthropic')
    ->usingModel('claude-sonnet-4-5')
    ->usingMode('support')
    ->send('Help with my order');

echo $result->text();
$session->run(); // run id, state, provider, model, finish reason
```

```ts
await session.usingProvider('anthropic');
await session.usingModel('claude-sonnet-4-5');
await session.usingMode('support');

const result = await runtime.send(session, 'Help with my order');
console.log(result.text);
```

```python
session.using_provider("anthropic")
session.using_model("claude-sonnet-4-5")
session.using_mode("support")

result = runtime.send(session, "Help with my order")
print(result.text)
```
:::

Modes select the system prompt, tools and run limits. All three Harness
implementations have a runtime for tool execution and durable approvals. Automatic tool execution is provided by Harness, rather than by the
TypeScript and Python core request APIs.

PHP can filter tools through the `harness.tool` Laravel Gate. TypeScript and
Python configure their `ToolAuthorizer` in application code. Bind
identity-dependent tools to the session that owns the run.

::: tip PHP: resolve it from the Laravel container
The PHP `PrismHarness` is bound as a singleton — inject it or resolve it, as above. It
does not provide a facade; use the container instead of
`PrismHarness::for(...)`.
:::

Workers restart, requests reach different processes, and deployments replace
running applications. Resolve state from a shared store so the next worker
can continue the session, regardless of language or framework.

### State stores

| Slot | Holds | Losing it means |
| --- | --- | --- |
| `ephemeral` | active mode, selected model, run bookkeeping | falls back to a default |
| `durable` | threads, pending tool approvals | conversation history and approvals are lost |

PHP defaults both slots to `database`; its example below moves ephemeral state
to Redis. TypeScript and Python require an explicit durable driver: the default
memory store is volatile and is rejected for durable state. Their examples use
files for durable state and memory for ephemeral state. With multiple workers,
choose stores and locking that cover all participating processes.

::: language-group
```php
// config/harness.php
'stores' => [
    'ephemeral' => 'redis',      // recommended in production
    'durable'   => 'database',
],
```

```ts
import { FileSessionStore, MemorySessionStore, PrismHarness } from '@particle-academy/prism-harness';

const harness = new PrismHarness({
  drivers: {
    memory: () => new MemorySessionStore(),
    files: () => new FileSessionStore('./storage/harness'),
  },
  stores: { ephemeral: 'memory', durable: 'files' },
});
```

```python
from prism_harness import FileSessionStore, MemorySessionStore, PrismHarness

harness = PrismHarness(
    drivers={
        "memory": MemorySessionStore,
        "files": lambda: FileSessionStore("./storage/harness"),
    },
    stores={"ephemeral": "memory", "durable": "files"},
)
```
:::

### Durable store validation

Stores that report themselves as volatile are rejected for durable state.
PHP's Redis driver is volatile by default and raises `UnsafeStateConfiguration`
when assigned to the durable slot. Set `durable` to `true` only if the Redis
instance is configured to persist session data and is not subject to cache flushes.

TypeScript and Python stores declare durability through their store contract.
The Redis variables below represent application-supplied implementations:

::: language-group
```php
'drivers' => [
    'redis' => [
        // An assertion about your infrastructure, made deliberately.
        'durable' => true,
    ],
],
```

```ts
const harness = new PrismHarness({
  drivers: { redis: () => durableRedisStore },
  stores: { ephemeral: 'redis', durable: 'redis' },
});

// durableRedisStore.durability() must return 'durable'.
```

```python
harness = PrismHarness(
    drivers={"redis": lambda: durable_redis_store},
    stores={"ephemeral": "redis", "durable": "redis"},
)

# durable_redis_store.durability() must return "durable".
```
:::

### Concurrency

Use a session lock to serialize changes from concurrent requests or workers:

::: language-group
```php
use Prism\Harness\Sessions\Session;

$session->lock(function (Session $session): void {
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

`lock()` throws `SessionLocked` if it cannot acquire the lock before timeout.
State is re-read after acquisition. Locks expire, allowing recovery after a
worker stops; configure the expiry for the expected operation duration.
Locking does not guarantee exactly-once execution. Use idempotency controls
for retried jobs and external side effects.

## The context window

Compaction limits the conversation history sent to the model without deleting
the stored thread.

The compaction strategies below are currently PHP-specific. TypeScript and
Python retain the complete thread and can apply a context policy at their
model-client boundary. They do not expose PHP's `CompactionStrategy`,
`EvictionSink`, `ContextRecall` or `SummaryBudget` APIs.

In PHP, compaction is opt-in.

::: language-group
```php
// config/prism-harness.php
'context' => [
    'keep_recent' => 20,          // a bounded window: free, deterministic
    // 'summarise_with' => 'claude-haiku-4-5-20251001',
    // 'summary_words'  => 200,
],
```

```ts
// Context compaction is not yet exposed by @particle-academy/prism-harness.
// Keep the complete thread, or compact it in the model-client boundary.
```

```python
# Context compaction is not yet exposed by prism-ai-harness.
# Keep the complete thread, or compact it in the model-client boundary.
```
:::

| Piece | What it does |
| --- | --- |
| `CompactionStrategy` | Decides what the model sees. `NoCompaction` (default), `KeepRecentTurns`, `SummarisingCompaction` — or your own |
| `EvictionSink` | Receives evicted turns before the next request so they can be retrieved later |
| `ContextRecall` | Backs a `recall_context` tool, offered to the model only when something can actually answer it |
| `SummaryBudget` | How a summary's word budget is enforced — `RetryOnce` (default), `AskOnly`, `TruncateTo` |

`KeepRecentTurns` selects a bounded history without a model call.
`SummarisingCompaction` makes a model call when compaction runs and may make
another if the summary exceeds its budget.

::: warning Summary information loss
Summaries can omit details and constraints. Bind an `EvictionSink` to preserve
access to the original turns, and keep required application policies outside
summarized conversation history.
:::

### Summary word limits

`summary_words` requests a word limit in the summarization prompt.
The `SummaryBudget` strategy determines how an over-length response is handled:

::: language-group
```php
$this->app->bind(SummaryBudget::class, fn () => new TruncateTo);
```

```ts
// SummaryBudget is currently a PHP-only extension point.
```

```python
# SummaryBudget is currently a PHP-only extension point.
```
:::

`RetryOnce` (the default) requests one revision but does not guarantee the limit.
`TruncateTo` enforces the limit by truncating the result, which can remove part
of a sentence. `AskOnly` accepts the model's response without enforcement.

## Package concepts

| Concept | What it does |
| --- | --- |
| Thread | The durable record of an exchange, appended to as it grows |
| Context window | Stored history in every language; built-in compaction, eviction sinks and budgeted recall are currently PHP-specific |
| Session | Resolved per request, keyed on participant **and** scope so each conversation keeps its own address |
| State stores | Pluggable drivers and separate ephemeral/durable slots, with volatile durable storage refused |
| Agent runtime | Session sends in PHP; runtime sends in TypeScript/Python; tool execution, bookkeeping and message recording |
| Modes | A system prompt, a tool allowlist and a step ceiling, defined in config |
| Permissions | Mode allowlists and optional tool authorization; PHP integrates with Laravel Gate |
| Approvals | Tools that require a human before they run, denied by default when unanswered |
| Subagents | A nested run with a narrowed toolset and its own budget |
| Events | Runtime event hooks; Laravel event broadcasting is a PHP integration |

A scoped filesystem for an agent to work in lives in
[Workspace](/docs/packages/workspace), available for PHP, TypeScript and Python.

Read next: [Sessions and threads](/docs/harness/sessions),
[Modes, tools and skills](/docs/harness/modes), and
[Subagents and budgets](/docs/harness/subagents).

Source and issue trackers:
[PHP](https://github.com/Particle-Academy/prism-harness),
[TypeScript](https://github.com/Particle-Academy/prism-harness-ts), and
[Python](https://github.com/Particle-Academy/prism-harness-py).
