# Prism Harness

Durable agent sessions for PHP, TypeScript and Python — threads, reconstructed
session state, persisted provider/model/mode selection, exclusive locking,
human approvals, and a bounded agent runtime with nested subagents that carry
their own budgets.

## When to use Harness

Use Harness for agents that need persistent conversations, configurable modes,
tool execution and human approvals across requests. For individual model
requests without session state, use Prism core directly.

## Installation

Choose the package for your runtime. PHP integrates with Laravel; TypeScript
and Python run independently of any framework.

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

Runtime requirements:

| Package | Requires |
| --- | --- |
| `particle-academy/prism-harness` | PHP 8.2+, Laravel `^12.69` or `^13.30`, `particle-academy/prism` 0.135 or newer |
| `@particle-academy/prism-harness` | Node.js 22+, no runtime dependencies |
| `prism-ai-harness` | Python 3.10+, no runtime dependencies |

In PHP, the migrations are **loaded for you**. Running `migrate` creates the
`harness_threads`, `harness_thread_messages` and `harness_session_state`
tables. Publish the config file when you need to change it, and the migrations
only if you need to change the schema:

```bash
php artisan vendor:publish --tag=prism-harness-config
php artisan vendor:publish --tag=prism-harness-migrations
```

The config file is `config/prism-harness.php`. A published `config/harness.php`
from an older release is refused at boot with a message telling you to rename
it, rather than being silently ignored.

## Setting up

PHP builds everything from config and the Laravel container. TypeScript and
Python take their pieces in constructors: a `PrismHarness` for state, a
`ModeRegistry` and `ToolRegistry` for what a run may do, and an `AgentRuntime`
that drives your model through an `LlmClient` function you supply.

::: language-group
```php
use Prism\Harness\Facades\PrismHarness;
use Prism\Harness\Tools\ToolRegistry;
use Prism\Prism\Facades\Tool;

// In a service provider: make a tool available to modes that list it.
app(ToolRegistry::class)->register(
    Tool::as('lookup_order')
        ->for('Look up an order by its id.')
        ->withStringParameter('id', 'The order id')
        ->using(fn (string $id): string => json_encode(Order::findOrFail($id)->only('id', 'status')))
);

// Anywhere: resolve a session through the facade, or inject Prism\Harness\PrismHarness.
$session = PrismHarness::for($user)->session('support');
```

```ts
import {
  AgentRuntime,
  FileSessionStore,
  MemorySessionStore,
  ModeRegistry,
  PrismHarness,
  ToolRegistry,
  type LlmClient,
} from '@particle-academy/prism-harness';

const harness = new PrismHarness({
  drivers: {
    memory: () => new MemorySessionStore(),
    files: () => new FileSessionStore('./storage/harness'),
  },
  stores: { ephemeral: 'memory', durable: 'files' },
});

const modes = new ModeRegistry({
  default: 'support',
  modes: {
    support: { system_prompt: 'You help customers with their orders.', tools: ['lookup_order'], max_steps: 8 },
  },
});

const tools = new ToolRegistry().register({
  name: 'lookup_order',
  description: 'Look up an order by its id.',
  handle: async (args) => JSON.stringify(await orders.find(String(args.id))),
});

// Your model call. It receives the system prompt, the thread as messages, the
// offered tools, provider, model and provider options, and returns one step.
const client: LlmClient = async (request) => {
  const reply = await callYourModel(request);
  return { text: reply.text, finishReason: reply.finishReason, toolCalls: reply.toolCalls, costUsd: reply.costUsd };
};

const runtime = new AgentRuntime({ client, modes, tools });

const session = harness.for({ type: 'User', id: user.id }).session('support');
```

```python
import json

from prism_harness import (
    AgentRuntime,
    FileSessionStore,
    LlmRequest,
    LlmResponse,
    MemorySessionStore,
    ModeRegistry,
    Participant,
    PrismHarness,
    ToolRegistry,
)

harness = PrismHarness(
    drivers={
        "memory": MemorySessionStore,
        "files": lambda: FileSessionStore("./storage/harness"),
    },
    stores={"ephemeral": "memory", "durable": "files"},
)

modes = ModeRegistry({
    "default": "support",
    "modes": {
        "support": {
            "system_prompt": "You help customers with their orders.",
            "tools": ["lookup_order"],
            "max_steps": 8,
        }
    },
})


class LookupOrder:
    name = "lookup_order"

    def handle(self, args):
        return json.dumps(orders.find(str(args["id"])))


tools = ToolRegistry().register(LookupOrder())


# Your model call. It receives the system prompt, the thread as messages, the
# offered tools, provider, model and provider options, and returns one step.
def client(request: LlmRequest) -> LlmResponse:
    reply = call_your_model(request)
    return LlmResponse(text=reply.text, finish_reason=reply.finish_reason, cost_usd=reply.cost_usd)


runtime = AgentRuntime(client=client, modes=modes, tools=tools)

session = harness.for_(Participant("User", user.id)).session("support")
```
:::

In TypeScript and Python the runtime calls your client once per step. It
passes the mode's `providerOptions` unchanged and records whatever the client
returns. A tool there is any object with a `name` and a `handle(args)` method;
describe its parameters to your provider inside the client. Return
`costUsd` / `cost_usd` when you know it, because cost budgets need it.

## Threads

A thread is a stored conversation, addressed by participant and scope. The
Harness reads its history for a run and records the resulting exchange,
including tool calls, tool results and approval decisions.

::: language-group
```php
use Prism\Harness\Models\Thread;
use Prism\Prism\Facades\Prism;

$thread = Thread::forParticipant($user, 'support');

$response = Prism::text()
    ->using('anthropic', 'claude-sonnet-4-5')
    ->withThread($thread)               // everything said so far
    ->withPrompt('And after that?')     // the turn being taken now
    ->asText();

$thread->record($response->messages);   // the full exchange, tool steps included
```

```ts
const response = await runtime.send(session, 'And after that?');

// runtime.send records the full exchange, including tool steps.
const messages = await session.thread().messages();
```

```python
response = runtime.send(session, "And after that?")

# runtime.send records the full exchange, including tool steps.
messages = session.thread().messages()
```
:::

PHP's Eloquent thread also implements Prism's `Thread` contract, so it can be
passed straight to `withThread()`. When you call Prism directly like this,
record the returned messages yourself; the Harness runtime records them for you
when it sends a turn.

The Harness owns its stored message format, separate from provider wire formats.
In PHP, content parts are stored with their concrete class, so an image comes
back an image, and content that cannot be stored without losing information
throws `UnmappableContent` at write time.

::: danger Thread rows are trusted storage
In PHP, rebuilding an attachment can resolve a recorded locator, so a row
carrying a `local_path` or a `url` becomes a file read or an outbound fetch at
replay time. **Never let request input write directly into
`harness_thread_messages`.** The runtimes in all three languages refuse
attachments built from a URL or a path when a turn is sent; provide embedded
bytes, provider file ids or document chunks.
:::

See [Sessions and threads](/docs/harness/sessions) for starting a new
conversation, reading state and locking.

## Sessions

A session is one participant's live runtime: the active mode, provider and
model, and the thread it is bound to. It is **resolved per request** from the
store, so any worker can continue it.

::: language-group
```php
$session = PrismHarness::for($user)->session('support');

$session->usingProvider('anthropic')->usingModel('claude-sonnet-4-5')->usingMode('support');
```

```ts
await session.usingProvider('anthropic');
await session.usingModel('claude-sonnet-4-5');
await session.usingMode('support');
```

```python
session.using_provider("anthropic").using_model("claude-sonnet-4-5").using_mode("support")
```
:::

## Running a turn

`send()` resolves the session's mode, provider, model, system prompt and tool
allowlist, runs the model with the mode's step limit, records every message to
the thread, and leaves run bookkeeping on success and on failure.

::: language-group
```php
$result = $session->send('Help with my order');

echo $result->text();
$result->runId;              // the run that produced it
$result->awaitingApproval(); // true when a tool is waiting for a person
$session->run();             // id, status, mode, provider, model, finish_reason, tool_calls
```

```ts
const result = await runtime.send(session, 'Help with my order');

console.log(result.text);
result.runId;            // the run that produced it
result.finishReason;     // 'awaiting_approval' when a tool is waiting for a person
result.stoppedBecause;   // why a budget stopped the run, or null
await session.run();     // id, status, mode, provider, model, finish_reason, tool_calls
```

```python
result = runtime.send(session, "Help with my order")

print(result.text)
result.run_id           # the run that produced it
result.finish_reason    # "awaiting_approval" when a tool is waiting for a person
result.stopped_because  # why a budget stopped the run, or None
session.run()           # id, status, mode, provider, model, finish_reason, tool_calls
```
:::

The second argument narrows the tools for one turn, and media can ride along
with the prompt:

| Argument | PHP | TypeScript / Python |
| --- | --- | --- |
| tool names | `send($prompt, ['lookup_order'])` — intersected with the mode's list, so it can only narrow | `send(session, prompt, ['lookup_order'])` — used **instead of** the mode's list, so pass only names that mode should allow |
| attachments | `send($prompt, null, [Image::fromBase64($b64, 'image/png')])` | fifth argument, `additionalContent` / `additional_content` |
| run context | `send($prompt, context: $run)` | fourth argument, a `RunContext` |

Attachments must be an image, document, audio or video carrying bytes, a
provider file id or document chunks. Anything else is refused before a run
starts with `attachment_by_reference`, `attachment_not_media`,
`attachment_empty` or `attachment_without_prompt`.

In PHP, `send()` holds the session lock for the whole run (see
[Locking](/docs/harness/sessions#locking)). The TypeScript and Python runtimes
do not lock around a run; [Locking](/docs/harness/sessions#locking) shows how to
serialize turns when two workers can send to the same session.

### Structured turns

When the answer is a document rather than prose, hand the turn a schema. It is
the same run as `send()`: same mode, tools, budget and events.

::: language-group
```php
use Prism\Harness\Exceptions\StructuredSchemaViolation;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema('plan', 'A proposed plan', [
    new StringSchema('title', 'What the plan is called'),
    new ArraySchema('steps', 'What to do', new StringSchema('step', 'One step')),
], requiredFields: ['title', 'steps']);

try {
    $plan = $session->sendStructured('Plan the release', $schema)->structured();
} catch (StructuredSchemaViolation $violation) {
    $violation->code();      // structured_schema_violation or structured_unreadable
    $violation->problems();  // every way it missed
    $violation->document();  // what the model actually said
}
```

```ts
import { HarnessError } from '@particle-academy/prism-harness';

const schema = {
  name: 'plan',
  type: 'object',
  properties: { title: { type: 'string' }, steps: { type: 'array', items: { type: 'string' } } },
  required: ['title', 'steps'],
};

try {
  const plan = (await runtime.sendStructured(session, 'Plan the release', schema)).structured;
} catch (error) {
  if (error instanceof HarnessError) {
    error.code;     // structured_schema_violation or structured_unreadable
    error.problems; // every way it missed
    error.document; // what the model actually said
  }
}
```

```python
from prism_harness import HarnessError

schema = {
    "name": "plan",
    "type": "object",
    "properties": {"title": {"type": "string"}, "steps": {"type": "array", "items": {"type": "string"}}},
    "required": ["title", "steps"],
}

try:
    plan = runtime.send_structured(session, "Plan the release", schema).structured
except HarnessError as error:
    error.code      # structured_schema_violation or structured_unreadable
    error.problems  # every way it missed
    error.document  # what the model actually said
```
:::

In TypeScript and Python your client receives the schema as `request.schema`
and returns the parsed document as `structured`. A document that misses the
schema is refused, never coerced or returned empty. The exchange is still
recorded and the run is marked failed. The check covers declared types,
required keys, enum members, array items and undeclared keys on closed objects;
it does not evaluate `$ref`, `allOf`, `oneOf` or numeric and string facets.

### Streaming

PHP can deliver the same turn as it happens. `stream()` yields Prism's stream
events unchanged and records the same transcript `send()` would when the stream
ends. TypeScript and Python have no streaming turn.

::: language-group
```php
foreach ($session->stream('Refactor the billing job') as $event) {
    // Prism's stream events — render them as you already do
}
// the turn is recorded and the run closed out when the stream ends
```

```ts
// The TypeScript runtime has no streaming turn. Use runtime.send().
```

```python
# The Python runtime has no streaming turn. Use runtime.send().
```
:::

The lock is held for the whole iteration. If the consumer stops reading early,
the run is still closed and the partial turn recorded.

## Approvals

A mode names the tools a person must approve with `requires_approval`. A turn
that calls one stops and returns its pending approvals; your application
records a decision for each and the run continues. Unanswered approvals are
**denied**, never run.

::: language-group
```php
use Prism\Prism\ValueObjects\ToolApprovalRequest;
use Prism\Prism\ValueObjects\ToolApprovalResponse;

$response = $session->send('Clean up the failed run');

if ($response->awaitingApproval()) {
    $response = $session->decide(array_map(
        fn (ToolApprovalRequest $pending) => new ToolApprovalResponse($pending->approvalId, approved: true),
        $response->pendingApprovals(),
    ));
}
```

```ts
import { recordApproval } from '@particle-academy/prism-harness';

let response = await runtime.send(session, 'Clean up the failed run');

if (response.finishReason === 'awaiting_approval') {
  for (const pending of response.pendingApprovals) {
    await recordApproval(session, pending.id, true); // or false, 'not on production'
  }
  response = await runtime.send(session, '');
}
```

```python
from prism_harness import record_approval

response = runtime.send(session, "Clean up the failed run")

if response.finish_reason == "awaiting_approval":
    for pending in response.pending_approvals:
        record_approval(session, pending.id, True)  # or False, "not on production"
    response = runtime.send(session, "")
```
:::

Decide who may approve before recording a decision. [Approvals](/docs/harness/modes#approvals)
covers the full flow in each language, including denying with a reason.

## Events

Each run emits a started event and then either a finished or a failed event,
with the run id and the session key. A failed run carries only the exception
class (PHP) or an error code or message (TypeScript, Python), never prompt
content.

::: language-group
```php
use Illuminate\Support\Facades\Event;
use Prism\Harness\Events\RunFailed;
use Prism\Harness\Events\RunFinished;
use Prism\Harness\Events\RunStarted;

Event::listen(function (RunFinished $event): void {
    $event->runId;
    $event->finishReason;
    $event->steps;
    $event->awaitingApproval;   // the one end state that needs a person to act
    $event->correlation();      // session_key, run_id, parent_run_id, root_run_id
});
```

```ts
import { AgentRuntime, HarnessEvents } from '@particle-academy/prism-harness';

const events = new HarnessEvents();

const stop = events.listen((event) => {
  if (event.type === 'run.finished') {
    console.log(event.runId, event.finishReason, event.steps, event.costUsd, event.toolCalls);
  }
});

const runtime = new AgentRuntime({ client, modes, tools, events });
```

```python
from prism_harness import AgentRuntime, HarnessEvents, RunFinished

events = HarnessEvents()


def on_event(event):
    if isinstance(event, RunFinished):
        print(event.run_id, event.finish_reason, event.steps, event.cost_usd, event.tool_calls)


stop = events.listen(on_event)

runtime = AgentRuntime(client=client, modes=modes, tools=tools, events=events)
```
:::

| Event | PHP class | TypeScript / Python `type` | Fields |
| --- | --- | --- | --- |
| started | `RunStarted` | `run.started` | run id, session key, mode, provider, model; PHP adds parent and root run ids, TS/Py add root run id and depth |
| finished | `RunFinished` | `run.finished` | finish reason, steps; PHP adds `awaitingApproval`, TS/Py add tool names and `costUsd` / `cost_usd` |
| failed | `RunFailed` | `run.failed` | PHP: `exception` (class name); TS/Py: `failure` and steps |

PHP events are ordinary Laravel events, so they can be queued or broadcast. In
TypeScript and Python, `listen()` returns a function that removes the listener,
and a listener that throws is reported as a warning rather than breaking the run.

## State stores

| Slot | Holds | Losing it means |
| --- | --- | --- |
| `ephemeral` | active mode, provider, model, run bookkeeping | falls back to a default |
| `durable` | threads, capability state, pending approvals, task lists | conversation history, approvals and work are lost |

PHP defaults both slots to `database`. TypeScript and Python default to a
volatile memory store, which is **refused for the durable slot**, so configure a
durable driver before opening a session.

::: language-group
```php
// config/prism-harness.php
'stores' => [
    'ephemeral' => 'redis',      // recommended in production
    'durable'   => 'database',
],
```

```ts
const harness = new PrismHarness({
  drivers: {
    memory: () => new MemorySessionStore(),
    files: () => new FileSessionStore('./storage/harness'),
  },
  stores: { ephemeral: 'memory', durable: 'files' },
});
```

```python
harness = PrismHarness(
    drivers={
        "memory": MemorySessionStore,
        "files": lambda: FileSessionStore("./storage/harness"),
    },
    stores={"ephemeral": "memory", "durable": "files"},
)
```
:::

| Driver | Language | Durability |
| --- | --- | --- |
| `database` (`DatabaseSessionStore`) | PHP | durable |
| `redis` (`RedisSessionStore`) | PHP | volatile unless `drivers.redis.durable` is `true` |
| `MemorySessionStore` | TS, Python | volatile, process-local lock |
| `FileSessionStore` | TS, Python | durable; atomic writes and a cross-process lock on one machine, not across a network filesystem |

### Durable store validation

A store that reports itself as volatile is rejected for durable state with
`UnsafeStateConfiguration` (PHP) or `unsafe_state_configuration` (TypeScript,
Python). PHP's Redis driver is volatile by default. Set `durable` to `true` only
if that Redis instance persists its data and nothing flushes it.

TypeScript and Python stores declare durability through the `SessionStore`
contract. For your own driver, return `'durable'` from `durability()` only when
it is true of the infrastructure:

::: language-group
```php
'drivers' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => env('HARNESS_REDIS_CONNECTION', 'default'),
        'durable' => true,   // an assertion about your infrastructure, made deliberately
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

## The context window

Compaction limits the conversation history sent to the model without deleting
the stored thread. Built-in compaction is PHP-only: TypeScript and Python send
the complete thread, and you can trim `request.messages` inside your client.

In PHP, compaction is off by default:

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
// No built-in compaction. The full thread reaches your client as request.messages.
```

```python
# No built-in compaction. The full thread reaches your client as request.messages.
```
:::

| Piece | What it does |
| --- | --- |
| `CompactionStrategy` | Decides what the model sees. `NoCompaction` (default), `KeepRecentTurns`, `SummarisingCompaction` — or your own |
| `EvictionSink` | Receives evicted messages so they can be retrieved later. The default discards them |
| `ContextRecall` | Backs a `recall_context` tool, offered to the model only when you bind an implementation |
| `SummaryBudget` | How a summary's word budget is enforced — `RetryOnce` (default), `AskOnly`, `TruncateTo` |

`keep_recent` counts messages, not turns, and a tool call and its result are
always kept or dropped together. `SummarisingCompaction` makes a model call
whenever compaction runs, and may make another if the summary is over budget.
It cannot be combined with a hard cost cap; a capped run refuses it with
`budget_compaction_unmetered`.

::: warning Summary information loss
Summaries can omit details and constraints. Bind an `EvictionSink` to keep the
original messages reachable, and keep required application policies in the
system prompt rather than in summarized history.
:::

`summary_words` asks for a word limit in the summarization prompt; a model may
not honour it. `RetryOnce` asks once more when the summary is over the limit,
`TruncateTo` cuts the result to the limit, and `AskOnly` accepts what came back:

::: language-group
```php
use Prism\Harness\Context\Budget\TruncateTo;
use Prism\Harness\Contracts\SummaryBudget;

$this->app->bind(SummaryBudget::class, fn () => new TruncateTo);
```

```ts
// SummaryBudget is PHP-only.
```

```python
# SummaryBudget is PHP-only.
```
:::

## Voice

PHP's `VoiceExchange` takes one spoken utterance, transcribes it, sends the
transcript as an ordinary turn on the session, and speaks the answer back. The
thread stores the transcript, not the audio. TypeScript and Python have no voice
helper.

::: language-group
```php
use Prism\Harness\Voice\VoiceExchange;
use Prism\Prism\ValueObjects\Media\Audio;

$reply = (new VoiceExchange)->exchange($session, Audio::fromBase64($base64, 'audio/webm'));

$reply->heard;   // the transcript — what the model was asked
$reply->text;    // the answer
$reply->audio;   // the answer spoken (a GeneratedAudio), or null when there was no prose
$reply->empty;   // true when nothing was heard; no turn was spent
```

```ts
// No voice helper. Transcribe with prism-ts, then runtime.send() the transcript.
```

```python
# No voice helper. Transcribe with prism-ai-core, then runtime.send() the transcript.
```
:::

The constructor takes `transcribeModel` (default `whisper-1`), `speakModel`
(`tts-1`), `voice` (`alloy`), `transcribeProvider` and `speakProvider` (both
OpenAI). Audio built from a URL or a file path is refused with
`UnsafeAudioSource` (`unsafe_audio_source`); pass
`allowReferencedAudio: true` only for recordings your own application wrote.

::: warning Audio in stack traces
When a provider call fails, the exception's stack frames hold the `Audio`
argument. If `zend.exception_ignore_args` is off, an error reporter can copy a
voice recording into your logs. Set `zend.exception_ignore_args=1`, or scrub
`Prism\Prism\ValueObjects\Media\Audio` in your error reporter.
:::

## Configuration

PHP reads `config/prism-harness.php`. TypeScript and Python take the equivalent
values in constructors: stores and drivers on `PrismHarness`, modes on
`ModeRegistry`, and budgets on `RunBudget`.

| Key | Default | Env | Meaning |
| --- | --- | --- | --- |
| `default_scope` | `default` | `HARNESS_DEFAULT_SCOPE` | Scope used when `session()` is called without one |
| `ephemeral_ttl` | `86400` | `HARNESS_EPHEMERAL_TTL` | Seconds the ephemeral half lives untouched; `null` keeps it until forgotten. Only an integer is applied, so set it in the config file rather than from the environment, which delivers a string |
| `context.keep_recent` | `null` | `HARNESS_KEEP_RECENT` | Messages kept by `KeepRecentTurns`; `null` turns compaction off |
| `context.recall_budget` | `1000` | `HARNESS_RECALL_BUDGET` | Token ceiling on what `recall_context` returns |
| `context.summarise_with` | `null` | `HARNESS_SUMMARISE_WITH` | Model for `SummarisingCompaction`; setting it turns that strategy on |
| `context.summary_words` | `200` | `HARNESS_SUMMARY_WORDS` | Requested summary length |
| `stores.ephemeral` | `database` | `HARNESS_EPHEMERAL_STORE` | Driver for the ephemeral slot |
| `stores.durable` | `database` | `HARNESS_DURABLE_STORE` | Driver for the durable slot |
| `drivers.redis.connection` | `default` | `HARNESS_REDIS_CONNECTION` | Redis connection |
| `drivers.redis.prefix` | `harness:` | `HARNESS_REDIS_PREFIX` | Key prefix |
| `drivers.redis.durable` | `false` | `HARNESS_REDIS_DURABLE` | Allow Redis in the durable slot |
| `drivers.database.connection` | `null` | `HARNESS_DB_CONNECTION` | Database connection; `null` is the default connection |
| `drivers.database.table` | `harness_session_state` | — | State table |
| `tasks.lease_seconds` | `300` | `HARNESS_TASK_LEASE_SECONDS` | Task claim lease; must be a whole positive number |
| `tasks.lock_wait` | `5` | `HARNESS_TASK_LOCK_WAIT` | Seconds a task-list operation waits for the list lock |
| `pricing` | `[]` | — | Trusted prices per provider and model, required by hard cost caps ([Subagents and budgets](/docs/harness/subagents#hard-cost-caps)) |
| `cost_observer` | `null` | — | Container-resolved `CallCostObserver` class for capped runs |
| `agent.provider` | `anthropic` | `HARNESS_PROVIDER` | Provider when the session has none |
| `agent.model` | `claude-sonnet-4-5` | `HARNESS_MODEL` | Model when the session has none |
| `agent.lock_ttl` | `300` | `HARNESS_RUN_LOCK_TTL` | Seconds a run's session lock lasts |
| `agent.lock_wait` | `0` | `HARNESS_RUN_LOCK_WAIT` | Seconds a run waits for a busy session before `SessionLocked` |
| `agent.authorize_tools` | `false` | `HARNESS_AUTHORIZE_TOOLS` | Turns on the `harness.tool` and `harness.tool.call` Gate checks |
| `agent.max_steps_ceiling` | `40` | `HARNESS_MAX_STEPS_CEILING` | Upper bound on `usingMaxSteps()`; `null` removes it |
| `agent.max_output_tokens_per_call` | `null` | — | Output token ceiling per model call; required by hard cost caps |
| `agent.max_cost_usd` | not set | — | Hard cost cap for a root run |
| `agent.max_seconds` | not set | — | Wall-clock limit for a root run |
| `agent.default` | `chat` | — | Mode used when the session has none |
| `agent.modes` | a `chat` mode | — | Mode definitions; see [Modes, tools and skills](/docs/harness/modes) |

## Errors

PHP throws a class per failure. Most implement `code()` with a stable string.
TypeScript and Python throw one `HarnessError` whose `code` carries the same
strings.

| PHP exception | Code | When |
| --- | --- | --- |
| `SessionLocked` | — | The session lock was not acquired within the wait |
| `UnsafeStateConfiguration` | `unsafe_state_configuration` | A volatile store in the durable slot or behind a task list, or an unknown driver |
| `UnsafeAuthorizationConfiguration` | — | A `harness.tool` Gate ability is defined while `authorize_tools` is off |
| `RunNotPermitted` | `run_not_permitted`, or a budget code | The run tree's budget is exhausted or a capped call cannot be admitted |
| `CallRefused` | your code | Thrown by your `CallCostObserver` to refuse a call |
| `StructuredSchemaViolation` | `structured_schema_violation`, `structured_unreadable` | A structured answer missed the schema or was unreadable |
| `UnacceptableAttachment` | `attachment_by_reference`, `attachment_not_media`, `attachment_empty`, `attachment_without_prompt` | A turn's media was refused |
| `UnsafeAudioSource` | `unsafe_audio_source` | `VoiceExchange` was given audio by URL or path |
| `UnmappableContent` | — | A message cannot be stored or rebuilt without loss |
| `ToolNotAvailable` | — | The fancy-flow integration asked for a tool it was not given |
| `InvalidTaskIdentifier` | `task_identifier_blank`, `duplicate_task_id` | A blank worker or task id, or a reused task id |
| `InvalidTaskLease` | `task_lease_invalid` | A lease that is not a whole positive number of seconds |
| `InvalidTaskOutcome` | `task_outcome_invalid` | An outcome other than `done` or `failed` |
| `TaskNotReleasable` | `task_not_found`, `task_already_terminal`, `task_lease_not_held` | `release()` by someone other than the current holder, or of a finished task |
| `LeaseNotExtendable` | `task_not_found`, `task_lease_not_held`, `run_not_permitted` | `extendLease()` refused |
| `UnmappableTask` | — | A task model or stored record cannot be read as a task |

The TypeScript and Python codes are: `session_locked`,
`unsafe_state_configuration`, `unknown_store_driver`, `tool_not_available`,
`run_not_permitted`, `unmappable_content`, `no_agent_runtime`,
`mode_not_configured`, `mode_malformed`, `unsafe_authorization_configuration`,
`call_not_authorized`, `skill_path_refused`, `duplicate_task_id`,
`task_identifier_blank`, `task_not_found`, `task_already_terminal`,
`task_lease_not_held`, `task_outcome_invalid`, `task_lease_invalid`, the four
`attachment_*` codes, `structured_unreadable` and
`structured_schema_violation`. In PHP an unknown or malformed mode throws
`InvalidArgumentException`.

## Checking your configuration

Resolve every mode, store and authorization setting up front, instead of
discovering a broken mode when a user first switches to it.

::: language-group
```php
// php artisan harness:doctor
//
//   stores ............................................ durable is durable
//   authorization ..... off — every registered tool is offered to every run
//   mode chat ...................... 0 tool(s), 0 subagent(s), 8 step(s)
//
//   INFO  Harness configuration is consistent.
```

```ts
import { SessionStoreManager, ToolAuthorizer, diagnose } from '@particle-academy/prism-harness';

const report = diagnose({
  modes,
  tools,
  stores: new SessionStoreManager({ drivers: { files: () => new FileSessionStore('./storage/harness') }, stores: { ephemeral: 'files', durable: 'files' } }),
  authorizer: new ToolAuthorizer(),
});

console.log(report.summary());
process.exitCode = report.ok ? 0 : 1;
```

```python
from prism_harness import FileSessionStore, SessionStoreManager, ToolAuthorizer, diagnose

report = diagnose(
    modes=modes,
    tools=tools,
    stores=SessionStoreManager(
        drivers={"files": lambda: FileSessionStore("./storage/harness")},
        stores={"ephemeral": "files", "durable": "files"},
    ),
    authorizer=ToolAuthorizer(),
)

print(report.summary())
```
:::

The doctor reports each mode's tool count (a `['*']` mode is reported as all
registered tools), subagents, step limit and approval list, and fails a mode
whose `requires_approval` names a tool the mode does not offer. `harness:doctor`
exits non-zero when it finds a problem.

## Package concepts

| Concept | What it does |
| --- | --- |
| Thread | The durable record of an exchange, appended to as it grows |
| Session | Resolved per request, keyed on participant **and** scope |
| State stores | Pluggable drivers in separate ephemeral and durable slots; volatile durable storage is refused |
| Agent runtime | Runs a mode's tools and step limit, records messages and run bookkeeping |
| Modes | A system prompt, a tool allowlist, approvals, skills, subagents and a step limit |
| Permissions | Mode allowlists plus optional offer-time and call-time authorization |
| Approvals | Tools that wait for a person, denied when unanswered |
| Subagents | A nested run with its own mode, session and a budget drawn from the parent |
| Task lists | Durable, leased work items an agent claims across runs |
| Events | Run started, finished and failed |

A scoped filesystem for an agent to work in lives in
[Workspace](/docs/packages/workspace).

Read next: [Sessions and threads](/docs/harness/sessions),
[Modes, tools and skills](/docs/harness/modes),
[Subagents and budgets](/docs/harness/subagents) and
[Task lists](/docs/harness/tasks).

Source and issue trackers:
[PHP](https://github.com/Particle-Academy/prism-harness),
[TypeScript](https://github.com/Particle-Academy/prism-harness-ts), and
[Python](https://github.com/Particle-Academy/prism-harness-py).
