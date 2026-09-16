# Prism Harness

Durable agent sessions for Laravel — threads, reconstructed session state,
persisted provider/model/mode selection, exclusive locking, and a bounded agent
runtime built on top of Prism, with nested subagents that carry their own
budgets.

::: warning Pre-1.0 — the API can still change in a minor version
`prism-harness` is at **`v0.11.1`**. Use it where you can follow a moving
package.

Two things to know before you start: **tool authorization is off by default**,
and `php artisan harness:doctor` reports what your configuration does, resolved
value by resolved value.
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

The migrations are **loaded for you**, so threads work the moment you run
`migrate`. They create three tables:
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

`$response->messages` carries every step of a tool loop alongside the final
answer, so recording a turn is one call — and a run interrupted mid-tool resumes
from exactly where it stopped.

### Addressed by participant *and* scope

`forParticipant($user, 'support')` and `forParticipant($user, 'coding')` resolve
**different** conversations. One user holds several unrelated threads at once,
and a support chat bleeding into a coding session is a bug you find in
production, so the scope is part of the address rather than a label hung off it.

Resolution uses `firstOrCreate`: a fresh worker asking for the same address
lands on the same thread.

### Threads store a format built for replay

The package owns its storage format, so a thread rebuilds exactly as it was
recorded while Prism's `toArray()` stays free to change for telemetry and debug
output. Two consequences worth knowing:

- Content parts are stored with their concrete class, so an image comes back an
  image and a document comes back a document.
- Content that would lose fidelity on the round trip throws `UnmappableContent`
  at write time, where you can act on it — a thread is replayed to the model as
  context, and a gap there surfaces much later as a model that has forgotten
  something.

::: danger Thread rows are trusted storage
Rebuilding an attachment resolves whatever locator was recorded, so a row
carrying a `local_path` or a `url` becomes a file read or an outbound fetch at
replay time. None of this is reachable without write access to your database —
but it sets where the boundary is. **Never let request input write directly into
`harness_thread_messages`.**
:::

## Sessions

A session is one participant's live runtime: the active mode, the selected
model, and the thread they are bound to. It is **resolved per request**.

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

::: tip Resolve it from the container
`PrismHarness` is bound as a singleton — inject it or resolve it, as above. It
ships without a facade, so reach for the container rather than
`PrismHarness::for(...)`.
:::

A Laravel request boots, serves and dies, so every call rebuilds the session
from a store — which is precisely what makes a fresh queue worker see the same
mode, model and conversation as the web request that set them.

### Two slots, because the halves differ

| Slot | Holds | Losing it means |
| --- | --- | --- |
| `ephemeral` | active mode, selected model, run bookkeeping | falls back to a default |
| `durable` | threads, pending tool approvals | work is gone |

Both default to `database`, so the package works the moment you install it.
Redis is the better home for live session state — point the ephemeral half at it
when you have one:

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
entitled to flush it. Only you know whether yours is persistent, so `redis`
reports itself volatile by default, and pointing the durable slot at it throws
`UnsafeStateConfiguration` with both ways out named.

What that protects is concrete: a pending tool approval is a half-executed
action a human was asked to authorise, and it has to survive a deploy. If your
Redis really is durable, say so, and the slot opens:

```php
'drivers' => [
    'redis' => [
        // An assertion about your infrastructure, made deliberately.
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
    // the work that runs once
});
```

`lock()` **throws `SessionLocked`** on timeout, so the caller learns that
someone else holds the session. Locks carry an expiry, so a worker that dies
mid-run releases it. State is re-read inside the lock, so you act on the values
as they are at the moment you hold it.

## The context window

A thread that only ever grows eventually stops fitting. Compaction changes
**which of its messages the model sees**, and storage keeps everything.

**Compaction is opt-in.** You choose the strategy and the harness provides the
seam, so the shape of your context stays a decision you made.

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

**Try `keep_recent` first.** It is free, deterministic, and leaves every stored
turn exactly as written. `SummarisingCompaction` bills a model call on every
turn that fires, and a second one when the summary comes back over budget.

::: danger A summary is a lossy view — bind a sink with it
["Governance Decay"](https://arxiv.org/pdf/2606.22528) puts summarisation-based
compaction above 40% safety violations, against 25–30% for truncation, because
constraints stated early are progressively lost, quietly. Bind an
`EvictionSink` alongside summarisation and the original turns stay recoverable
from the moment they leave the window.
:::

### Ask for a word budget, enforce it with a strategy

`summary_words` reaches the model as *"in at most N words"* inside a prompt, so
it is a request. Measured live, a stated 15 words came back at 92 and at 346,
and a default of 60 came back at 205 — the 346 having re-stated every exchange
one by one, which is the unbounded growth that rewriting the summary exists to
prevent.

So enforcement is a choice you bind, separate from the size you ask for:

```php
$this->app->bind(SummaryBudget::class, fn () => new TruncateTo);
```

`RetryOnce` is the default and asks once more when over — it took that 60-word
budget from 205 words to 61, and it is allowed to miss. `TruncateTo` guarantees
the bound and can cut mid-thought. `AskOnly` sends the request and takes what
comes back.

## What is in the package

| Concept | What it does |
| --- | --- |
| Thread | The durable record of an exchange, appended to as it grows |
| Context window | Compaction, an eviction sink, and budgeted recall — the view shrinks while storage keeps everything |
| Session | Resolved per request, keyed on participant **and** scope so each conversation keeps its own address |
| State stores | Redis and database behind one contract, with the durable slot guarded |
| Agent runtime | `Session::send()`, durable run bookkeeping, full-message recording |
| Modes | A system prompt, a tool allowlist and a step ceiling, defined in config |
| Permissions | Mode allowlists, plus optional `harness.tool` Gate filtering |
| Approvals | Tools that require a human before they run, denied by default when unanswered |
| Subagents | A nested run with a narrowed toolset and its own budget |
| Event bus | Laravel events over Reverb, separate from Prism telemetry |

A scoped filesystem for an agent to work in lives in
[Workspace](/docs/packages/workspace), one `composer require` away.

Read next: [Sessions and threads](/docs/harness/sessions),
[Modes, tools and skills](/docs/harness/modes), and
[Subagents and budgets](/docs/harness/subagents).

Source, issues and design notes:
[Particle-Academy/prism-harness](https://github.com/Particle-Academy/prism-harness).
