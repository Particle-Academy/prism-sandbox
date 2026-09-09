# Prism Harness

Durable agent sessions for Laravel — threads, reconstructed session state,
persisted provider/model/mode selection, exclusive locking, and a bounded agent
runtime built on top of Prism, with nested subagents that carry their own
budgets.

::: warning Pre-1.0 — the API can still change in a minor version
`prism-harness` is at **`v0.7.1`**. Use it where you can follow a moving
package.

Two things to know before you start: **tool authorization is off by default**,
and `php artisan harness:doctor` reports what your configuration actually does
rather than what it looks like it does.
:::

## What it is for

Applications where **the agent is the product** and the conversation outlives
the request — an interactive coding assistant, a support console, an operations
agent.

That is a different thing from an AI *feature* inside an app, which Prism
already serves well on its own: prompt, respond, maybe stream, done. You need a
harness when the conversation has to survive a deploy, the agent switches
between ways of working, and a tool call has to stop and wait for a human to say
yes.

## Installation

```bash
composer require particle-academy/prism-harness
php artisan migrate
```

The migrations are **loaded, not merely publishable**, so threads work
immediately after `migrate` with nothing to publish. They create three tables:
`harness_threads`, `harness_thread_messages` and `harness_session_state`.

Publish the config only when you need to change something in it:

```bash
php artisan vendor:publish --tag=harness-config
```

Requires PHP 8.2+, Laravel 12.61+ or 13.12+, and `particle-academy/prism` 0.113
or newer.

## Threads

A thread is a stored conversation. Prism 0.113 added a `Thread` contract — an
interface Prism reads history from — and this package provides the Eloquent
implementation, so `withThread()` reads the conversation out of your database
instead of you rebuilding a message array on every request.

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

`$response->messages` carries every step of a tool loop, not just the final
answer, so recording a turn is one call — and a run interrupted mid-tool resumes
from exactly where it stopped.

### Addressed by participant *and* scope

`forParticipant($user, 'support')` and `forParticipant($user, 'coding')` resolve
**different** conversations. One user holds several unrelated threads at once,
and a support chat bleeding into a coding session is a bug you find in
production, so the scope is part of the address rather than a label hung off it.

Resolution uses `firstOrCreate`: a fresh worker asking for the same address
lands on the same thread instead of starting a new one.

### The storage format is the package's, not Prism's

Threads do not persist Prism's `toArray()` output. That method exists to feed
telemetry and debug output and is free to change for presentational reasons;
storage cannot be. Two consequences worth knowing:

- Content parts are stored with their concrete class. Prism's `Media::toArray()`
  records *where* a file lives but not *what it is* — an image and a document
  serialise identically — so without this, every attachment would come back as
  whatever type the reader guessed.
- Anything that cannot be stored and rebuilt faithfully throws
  `UnmappableContent` rather than being dropped. A thread is replayed to the
  model as context, so a silent omission never surfaces as an error; it surfaces
  much later as a model that has forgotten something.

::: danger Thread rows are trusted storage
Rebuilding an attachment resolves whatever locator was recorded, so a row
carrying a `local_path` or a `url` becomes a file read or an outbound fetch at
replay time. None of this is reachable without write access to your database —
but it sets where the boundary is. **Never let request input write directly into
`harness_thread_messages`.**
:::

## Sessions

A session is one participant's live runtime: the active mode, the selected
model, and the thread they are bound to. It is **resolved, never held**.

```php
use Prism\Harness\PrismHarness;

$session = app(PrismHarness::class)->for($user)->session('support');

$session->usingMode('plan')->usingModel('claude-sonnet-4-5');

$session->thread();   // the durable conversation for this address
$session->mode();     // 'plan' — read back from the store, in any worker
```

### Running the durable agent

`send()` resolves the session's configured mode, provider, model, system
prompt, and explicit tool allowlist, then performs the Prism run while holding
the session lock. It records every returned message and leaves durable run
bookkeeping on both success and failure.

```php
$result = $session
    ->usingProvider('anthropic')
    ->usingModel('claude-sonnet-4-5')
    ->usingMode('support')
    ->send('Help with my order');

echo $result->text();
$session->run(); // run id, state, provider, model, finish reason
```

Modes are config-defined in this first runtime slice. Tool names are selected
by the active mode; applications can additionally enable the `harness.tool`
Laravel Gate. Identity-dependent capabilities can register a session-bound
tool factory, so callbacks resolve against the locked owner session rather than
process-global state.

::: tip There is no facade
Resolve `PrismHarness` from the container, as above. The package binds it as a
singleton and does not register a facade, so `PrismHarness::for(...)` written as
a static call will not work.
:::

A Laravel request boots, serves and dies, so a session cannot be an object kept
in memory the way a long-lived Node process can hold one. Every call rebuilds
one from a store — which is precisely what makes a fresh queue worker see the
same mode, model and conversation as the web request that set them.

### Two slots, because the halves differ

| Slot | Holds | Losing it means |
| --- | --- | --- |
| `ephemeral` | active mode, selected model, run bookkeeping | falls back to a default |
| `durable` | threads, pending tool approvals | work is gone |

Both default to `database`, so the package works on install with nothing to set
up. Redis is the better home for live session state — point the ephemeral half
at it when you have one:

```php
// config/harness.php
'stores' => [
    'ephemeral' => 'redis',      // recommended in production
    'durable'   => 'database',
],
```

### Why the durable slot is guarded

**A store that reports itself volatile is refused for durable state, loudly, at
resolve time.**

The `redis` connection in a typical Laravel app is a *cache* — something is
entitled to flush it. The package cannot tell from the inside whether yours is
persistent, so `redis` reports itself volatile by default, and pointing the
durable slot at it throws `UnsafeStateConfiguration` with both ways out named.

That is not defensive theatre. Quietly accepting a cache for state that must
survive a deploy is how a pending tool approval — a half-executed action a human
was asked to authorise — disappears with nothing in the logs. If your Redis
really is durable, say so, and it is allowed:

```php
'drivers' => [
    'redis' => [
        // An assertion about your infrastructure, not a preference.
        'durable' => true,
    ],
],
```

### Concurrency

Two workers can hold the same session at once — a queued job finishing a run
while the user sends another message is ordinary. Advance a run inside a lock:

```php
use Prism\Harness\Sessions\Session;

$session->lock(function (Session $session): void {
    // whatever must not happen twice
});
```

`lock()` **throws `SessionLocked` rather than running anyway** on timeout, since
running anyway would defeat the only thing it is for. Locks carry an expiry, so
a worker that dies mid-run does not hold the session shut forever. State is
re-read inside the lock, because acting on a value fetched before you held it is
the exact race the lock exists to prevent.

## The context window

A thread that only ever grows eventually stops fitting. Compaction changes
**which of its messages the model sees** — never what is stored.

**Nothing compacts by default.** Installing a newer version of this package does
not start dropping turns out from under you; you choose a strategy, and the
harness provides the seam rather than the policy.

```php
// config/prism-harness.php
'context' => [
    'keep_recent' => 20,          // a bounded window: free, deterministic
    // 'summarise_with' => 'claude-haiku-4-5-20251001',
    // 'summary_words'  => 200,
],
```

| Piece | What it does |
| --- | --- |
| `CompactionStrategy` | Decides what the model sees. `NoCompaction` (default), `KeepRecentTurns`, `SummarisingCompaction` — or your own |
| `EvictionSink` | Where evicted turns GO. Handed them before the turn is sent, so the detail is recoverable by the very turn that lost it |
| `ContextRecall` | Backs a `recall_context` tool, offered to the model only when something can actually answer it |
| `SummaryBudget` | How a summary's word budget is enforced — `RetryOnce` (default), `AskOnly`, `TruncateTo` |

**Try `keep_recent` first.** It costs nothing and cannot rewrite anything.
`SummarisingCompaction` bills a model call on every turn that fires, and a
second one when the summary comes back over budget.

::: danger A summary is a lossy view — bind a sink with it
["Governance Decay"](https://arxiv.org/pdf/2606.22528) puts summarisation-based
compaction above 40% safety violations, against 25–30% for truncation, because
constraints stated early are progressively lost with nothing reporting it.
Summarising **without** an `EvictionSink` makes that permanent: the summary
becomes the only record, and a summary cannot be un-summarised.
:::

### The word budget is a request, not a bound

`summary_words` reaches the model as *"in at most N words"* inside a prompt, and
a model need not grant a request. Measured live with nothing checking, a stated
15 words came back at 92 and at 346, and a default of 60 came back at 205 — the
346 having re-stated every exchange one by one, which is exactly the unbounded
growth that rewriting the summary is meant to prevent.

So enforcement is a choice you bind, separate from the size you ask for:

```php
$this->app->bind(SummaryBudget::class, fn () => new TruncateTo);
```

`RetryOnce` is the default and asks once more when over — it took that 60-word
budget from 205 words to 61, and it is allowed to miss. `TruncateTo` guarantees
the bound and can cut mid-thought. `AskOnly` enforces nothing.

## What is in the package

| Concept | What it does |
| --- | --- |
| Thread | The durable record of an exchange, appended to rather than replayed |
| Context window | Compaction, an eviction sink, and budgeted recall — the view shrinks, the storage does not |
| Session | Resolved per request, keyed on participant **and** scope so conversations never merge |
| State stores | Redis and database behind one contract, with the durable slot guarded |
| Agent runtime | `Session::send()`, durable run bookkeeping, full-message recording |
| Modes | A system prompt, a tool allowlist and a step ceiling, defined in config |
| Permissions | Mode allowlists, plus optional `harness.tool` Gate filtering |
| Approvals | Tools that require a human before they run, denied by default when unanswered |
| Subagents | A nested run with a narrowed toolset and its own budget |
| Event bus | Laravel events over Reverb, separate from Prism telemetry |

A scoped filesystem for an agent to work in is **not** here — that is
[Workspace](/docs/packages/workspace).

Read next: [Sessions and threads](/docs/harness/sessions),
[Modes, tools and skills](/docs/harness/modes), and
[Subagents and budgets](/docs/harness/subagents).

Source, issues and design notes:
[Particle-Academy/prism-harness](https://github.com/Particle-Academy/prism-harness).
