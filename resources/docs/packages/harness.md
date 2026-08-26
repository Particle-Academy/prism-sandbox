# Prism Harness

Durable agent sessions for Laravel — threads, session state and, eventually,
modes, tool permissions and subagents, built on top of Prism.

::: warning Release candidate — still in testing
`prism-harness` is at **`v0.1.2`** and is a **release candidate, not a stable
release**. Threads and session state are implemented, tested and in use; the
rest of the surface described below as *planned* is design, not code. The API
can still change in a minor version, and two known defects are
[listed at the bottom of this page](#known-defects).

Use it where you can follow a moving package. Do not pin your production
roadmap to it yet.
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

## What is shipped and what is planned

The package documents this per row rather than in a banner, because a status
line four paragraphs above a table does not travel with the row somebody quotes.

| Concept | Status | What it maps to |
| --- | --- | --- |
| Thread | **shipped** | Eloquent models here; contract in Prism 0.113 |
| Session | **shipped** | Resolved per request, keyed on participant + scope |
| State stores | **shipped** | Redis and database behind one contract, durable slot guarded |
| Controller | *planned* | A container singleton, config plus mode classes |
| Modes | *planned* | One class per mode, container-resolved so they are testable |
| Permissions | *planned* | Gates and Policies — "may this tool run" is an authorization question |
| Subagents | *planned* | A Prism tool wrapping a nested run with a narrowed toolset |
| Event bus | *planned* | Laravel events over Reverb, separate from Prism telemetry |
| Workspace | *elsewhere* | A scoped filesystem disk — that is `prism-workspace` |

Two design questions are still genuinely open: whether **mode** is owned by the
session or by the thread, and whether subagent **step budgets** nest or reset.

## Known defects

Both are open against the package and neither is fixed in `v0.1.2`:

- [**#2**](https://github.com/Particle-Academy/prism-harness/issues/2) —
  `record()` computes the next position with `max('position')` outside a
  transaction, so two turns landing on one thread concurrently can collide.
  Serialise writes to a thread (the session lock above does this) until it is
  fixed.
- [**#5**](https://github.com/Particle-Academy/prism-harness/issues/5) — the
  published config is `harness.php`; ecosystem convention says it should be
  `prism-harness.php`. Fixing it will be a breaking change to the config key.

Source and full design notes:
[Particle-Academy/prism-harness](https://github.com/Particle-Academy/prism-harness).
