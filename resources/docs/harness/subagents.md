# Subagents and budgets

A subagent is a nested run that a parent agent calls like a tool. It runs in
its own mode, with authority it was **given** rather than inherited, in its own
session, and on a budget drawn from the parent's.

## Declaring subagents

A mode declares the subagents it may call, keyed by the tool name the parent
will see. A mode that declares none cannot spawn one.

::: language-group
```php
// config/prism-harness.php, under 'agent'
'modes' => [
    'researcher' => [
        'system_prompt' => 'You answer questions and verify every claim you make.',
        'tools' => ['search_web'],
        'max_steps' => 12,
        'subagents' => [
            'verify_claim' => [
                'description' => 'Check one factual claim and report whether it holds.',
                'mode' => 'verifier',        // the child's authority, not the parent's
                'max_steps' => 5,
                'max_cost_usd' => 0.25,
                'max_seconds' => 60,
                'max_output_tokens_per_call' => 2048,
            ],
        ],
    ],
    'verifier' => [
        'system_prompt' => 'You verify one claim and report what you found.',
        'tools' => ['fact_check'],           // deliberately not the researcher's tools
        'max_steps' => 5,
    ],
],
```

```ts
import { ModeRegistry } from '@particle-academy/prism-harness';

const modes = new ModeRegistry({
  modes: {
    researcher: {
      system_prompt: 'You answer questions and verify every claim you make.',
      tools: ['search_web', 'verify_claim'],
      max_steps: 12,
      subagents: {
        verify_claim: {
          description: 'Check one factual claim and report whether it holds.',
          mode: 'verifier',
          max_steps: 5,
          max_cost_usd: 0.25,
          max_seconds: 60,
        },
      },
    },
    verifier: {
      system_prompt: 'You verify one claim and report what you found.',
      tools: ['fact_check'],
      max_steps: 5,
    },
  },
});
```

```python
from prism_harness import ModeRegistry

modes = ModeRegistry({
    "modes": {
        "researcher": {
            "system_prompt": "You answer questions and verify every claim you make.",
            "tools": ["search_web", "verify_claim"],
            "max_steps": 12,
            "subagents": {
                "verify_claim": {
                    "description": "Check one factual claim and report whether it holds.",
                    "mode": "verifier",
                    "max_steps": 5,
                    "max_cost_usd": 0.25,
                    "max_seconds": 60,
                }
            },
        },
        "verifier": {
            "system_prompt": "You verify one claim and report what you found.",
            "tools": ["fact_check"],
            "max_steps": 5,
        },
    }
})
```
:::

| Key | Default | Meaning |
| --- | --- | --- |
| `description` | `Run the [name] subagent and return its result.` | What the parent model is told the tool does |
| `mode` | the subagent's name | The mode the child runs in; it must be configured |
| `max_steps` | `8` | The child's step allowance |
| `max_cost_usd` | none | The child's cost allowance |
| `max_seconds` | none | The child's wall-clock allowance |
| `max_output_tokens_per_call` | none | PHP only: an output token ceiling per model call, which can narrow but never raise the parent's |
| `scope` | the subagent's name | Suffix for the child's session scope |

A subagent whose `mode` is not configured makes the parent mode fail to
resolve, so the [configuration check](/docs/packages/harness#checking-your-configuration)
catches it.

## Offering a subagent to a run

In PHP, a mode's subagents are offered automatically as tools, each taking one
string argument, `task`. They pass through the same approval list and tool
authorization as any other tool.

The TypeScript and Python runtimes read the `subagents` key but do not offer
the tools themselves. Build each one with `SubagentRunner` for the run you are
about to start, list its name in the mode's `tools`, and pass the same
`RunContext` to `send()` so parent and child share one budget:

::: language-group
```php
// Nothing to wire up: the parent calls verify_claim like any other tool.
$response = $session->usingMode('researcher')->send('Did the Eiffel Tower open in 1889?');
```

```ts
import { RunBudget, RunContext, SubagentRunner } from '@particle-academy/prism-harness';

const runner = new SubagentRunner(harness, runtime);
const verifyClaim = modes.resolve('researcher').subagents.verify_claim!;

const context = RunContext.root(`run-${requestId}`, new RunBudget(20, 1.0, 300));
tools.registerFactory('verify_claim', (parent) => runner.tool(verifyClaim, parent, context, context.rootRunId));

await session.usingMode('researcher');
const response = await runtime.send(session, 'Did the Eiffel Tower open in 1889?', undefined, context);
```

```python
from prism_harness import RunBudget, RunContext, SubagentRunner

runner = SubagentRunner(harness, runtime)
verify_claim = modes.resolve("researcher").subagents["verify_claim"]

context = RunContext.root(f"run-{request_id}", RunBudget(max_steps=20, max_cost_usd=1.0, max_seconds=300))
tools.register_factory(
    "verify_claim", lambda parent: runner.tool(verify_claim, parent, context, context.root_run_id)
)

session.using_mode("researcher")
response = runtime.send(session, "Did the Eiffel Tower open in 1889?", context=context)
```
:::

`registerFactory` replaces any earlier factory with the same name. If one
process runs several parents at once, give each run its own `ToolRegistry` and
`AgentRuntime` so each subagent tool closes over its own context.

## What happens when a subagent runs

- **The child gets its own session and thread**, at the parent's scope plus
  `::sub::<name>` (or `::sub::<scope>`). The parent holds its own session for
  the whole run, so the child cannot share it. In PHP the child's thread is
  linked to the parent's thread.
- **The child runs in its own mode**, with that mode's tools and approvals. It
  does not see the parent's conversation, only the `task` text.
- **The child uses the parent's provider and model** in TypeScript and Python.
  In PHP it uses its session's own selection, falling back to `agent.provider`
  and `agent.model`.
- **Nesting is limited to 4 levels.** Deeper calls are refused with outcome
  `denied` rather than run.
- **A child failure does not abort the parent.** It comes back as the tool's
  result, so the parent model can decide what to do.

### The result

Every subagent call ends in one outcome:

| Outcome | Meaning |
| --- | --- |
| `completed` | The child finished |
| `exhausted` | The run tree's budget ran out, before the child started or while it ran |
| `cancelled` | The run tree was cancelled |
| `denied` | Refused, for example because nesting is too deep |
| `failed` | The child threw; PHP reports only the exception class to the model and logs the details |
| `awaiting_approval` | PHP enum only |

In PHP the tool result is a JSON envelope built from `SubagentResult`:
`subagent`, `run_id`, `parent_run_id`, `outcome`, `succeeded`, `retryable` (true
only for `failed`), `reason`, `usage` (`steps`, `cost_usd`) and `content`, with a
`_framing` note telling the parent model that `content` is data, not
instructions. A child's output is model-written text that may have read
untrusted input, so it is never spliced into the parent's instructions
unmarked.

In TypeScript and Python, `SubagentRunner.run()` returns a `SubagentResult` with
`subagent`, `runId` / `run_id`, `parentRunId` / `parent_run_id`, `outcome` and
`text`. The tool result given to the parent is the child's text, or a sentence
saying the child stopped early or did not run, and why.

## Shared budgets

A `RunBudget` sets the limits for a whole run tree. Parent and children share
one ledger, so what a child spends is gone from the parent's allowance.

| Limit | PHP | TypeScript | Python |
| --- | --- | --- | --- |
| model steps | `maxSteps` | `maxSteps` | `max_steps` |
| cost in USD | `maxCostUsd` | `maxCostUsd` | `max_cost_usd` |
| wall-clock seconds | `maxSeconds` | `maxSeconds` | `max_seconds` |
| output tokens per call | `maxOutputTokensPerCall` | — | — |

A child's effective budget is the smaller of what it declares (counted from
what the tree has already spent) and what the parent's budget allows. A child
can be narrowed; it can never be given more than the tree has left.

A cost limit is only enforceable when every step reports its cost. A step with
no cost makes the spend unknown, and the next check stops the tree rather than
guessing. In TypeScript and Python, return `costUsd` / `cost_usd` from your
client when you set `maxCostUsd`.

`RunLedger` holds the tree's account: `steps`, `costUsd` / `cost_usd`,
`elapsedSeconds()`, `remainingCost(budget)`, `remainingSeconds(budget)`,
`exhaustion(budget)` (the reason the budget is spent, or null) and
`cancel(reason)`. Cancellation is cooperative: a tool already running finishes,
and the next step is refused.

## Run-specific limits

A mode supplies the default step limit. An application can give one run a
different budget:

::: language-group
```php
use Prism\Harness\Subagents\RunBudget;
use Prism\Harness\Subagents\RunContext;

// A step limit for this session's runs, capped by agent.max_steps_ceiling.
$session->usingMaxSteps(20);

// Or a whole budget for one run.
$run = RunContext::root('run-'.$requestId, new RunBudget(maxSteps: 20, maxSeconds: 300));
$session->send($prompt, context: $run);
```

```ts
import { RunBudget, RunContext } from '@particle-academy/prism-harness';

const context = RunContext.root(`run-${requestId}`, new RunBudget(20, null, 300));
const response = await runtime.send(session, prompt, undefined, context);
```

```python
from prism_harness import RunBudget, RunContext

context = RunContext.root(f"run-{request_id}", RunBudget(max_steps=20, max_seconds=300))
response = runtime.send(session, prompt, context=context)
```
:::

In PHP, `usingMaxSteps()` is stored on the session and applies to every run
that does not pass a context; `agent.max_steps_ceiling` (40 by default) bounds
it, so a caller can never lift its own limit past what the operator allows. A
`RunContext` passed to a turn replaces the configured budget for that run. Keep
the limits you tell the model consistent with the budget it actually has.

## Detecting a step limit

A run that hits its limit still returns a response. Check for it explicitly
rather than treating the text as a finished answer.

::: language-group
```php
use Prism\Harness\Exceptions\RunNotPermitted;
use Prism\Prism\Enums\FinishReason;

try {
    $response = $session->send($prompt);
} catch (RunNotPermitted $refused) {
    // The tree's budget was already spent before this run could start.
    $refused->code();   // run_not_permitted, or a cost-cap code
}

$hitStepLimit = $response->response->finishReason === FinishReason::ToolCalls
    && ! $response->awaitingApproval();
```

```ts
const response = await runtime.send(session, prompt, undefined, context);

if (response.finishReason === 'budget_exhausted') {
  response.stoppedBecause; // e.g. 'step budget exhausted (20 of 20 used)'
}
```

```python
response = runtime.send(session, prompt, context=context)

if response.finish_reason == "budget_exhausted":
    response.stopped_because  # e.g. "step budget exhausted (20 of 20 used)"
```
:::

In PHP, the last step of a run cut off by its step limit has finish reason
`tool-calls` (`FinishReason::ToolCalls`) without pending approvals: the model
asked for more tools and was not given another step. That is also the
`finish_reason` recorded in `$session->run()`. A run that ends on its final
allowed step with an ordinary answer finishes with `stop` and was not cut off.

In TypeScript and Python, the runtime checks the budget before every step. A
stopped run has finish reason `budget_exhausted`, and `stoppedBecause` /
`stopped_because` says which limit was reached; it is `null` / `None` for every
other run. For a subagent, the same condition shows as outcome `exhausted`.

## Hard cost caps

In PHP, a run with a `maxCostUsd` (from `agent.max_cost_usd`, a subagent
declaration or a `RunContext`) is metered **per model call**: before each call
the Harness reserves its worst-case cost and refuses the call if it would cross
the cap. Calls already made stay in the ledger and the thread, and the run fails
with reason `budget`. TypeScript and Python check cost between steps, from the
`costUsd` / `cost_usd` your client reports.

A capped PHP run needs trusted prices and an output ceiling:

```php
// config/prism-harness.php
'pricing' => [
    'anthropic' => [
        'claude-sonnet-4-5' => [
            'inputPerMTok' => 3.0,        // USD per million input tokens
            'outputPerMTok' => 15.0,
            'cacheWritePerMTok' => 3.75,  // optional; defaults to the input price
            'cacheReadPerMTok' => 0.30,   // optional; defaults to the input price
            'toolOverheadTokens' => 346,  // required when the run offers tools
            'imageTokenBound' => 1600,    // required when turns carry images
        ],
    ],
],
'agent' => [
    'max_cost_usd' => 2.00,
    'max_output_tokens_per_call' => 4096,
    // ...
],
```

The prices above are placeholders; use your provider's current prices. The
package supplies none. A price entry may also carry a `multiplier` and an
ascending `tiers` list (each with `aboveInputTokens` and its own prices), and
OpenRouter entries use `'billing' => 'reported-complete'`. A `RunContext` can
override prices per run with its `pricing` argument.

The worst case counts every byte of the request (system prompt, history, tool
schemas and results) plus the output ceiling at the output price. Documents,
audio and video are refused under a cap, and model-based summarizing
compaction cannot be combined with one. Refusals are `RunNotPermitted` with
these codes:

| Code | Cause |
| --- | --- |
| `call_budget_exceeded` | The next call's worst case does not fit in what is left |
| `budget_unpriced` | No valid price for the provider and model |
| `budget_unbounded_output` | No positive `max_output_tokens_per_call` |
| `budget_input_unbounded` | Unsupported media, or a missing image or tool overhead bound |
| `budget_compaction_unmetered` | `SummarisingCompaction` is configured |
| `budget_observer_uncapped` | A cost observer was supplied for a run without a cap |
| `call_refused_by_host` | Your observer refused without a valid code of its own |

To keep a durable account across workers, implement
`Prism\Harness\Contracts\CallCostObserver`. `reserving(CallReservation $call,
RunContext $run)` is called before each admitted call with a unique `callId`,
`provider`, `model`, `worstCaseUsd` and `kind`; throw `CallRefused('your_code')`
to refuse it. `settled(CallSettlement $call, RunContext $run)` is called once
afterwards with `outcome` (`completed`, `failed`, `abandoned`), `chargedUsd` and
`usageKnown`. Pass the observer to `RunContext::root(..., observer: $observer)`
or name its class in `cost_observer`. A worker that dies cannot call `settled`,
so expire stale reservations in your own store.
