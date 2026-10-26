# Task lists

A task list tracks the work an agent still has to do across many runs. Tasks
and their claims live in the durable store, so another worker can continue
after a crash, a restart or a deploy.

## Getting a list

Every session has its own list, stored beside the session's state. A list that
several sessions share, or that outlives them, is opened by name.

::: language-group
```php
use Prism\Harness\Facades\PrismHarness;

$tasks = $session->tasks();                          // this session's list

$shared = PrismHarness::tasks('nightly-reconciliation');   // a named list
```

```ts
import { StoreTaskSource } from '@particle-academy/prism-harness';

const tasks = session.tasks(); // this session's list

const shared = new StoreTaskSource(harness.durableStore(), 'nightly-reconciliation:tasks'); // a named list
```

```python
from prism_harness import StoreTaskSource

tasks = session.tasks()  # this session's list

shared = StoreTaskSource(harness.durable_store(), "nightly-reconciliation:tasks")  # a named list
```
:::

A list always lives in durable storage. Building one over a volatile store is
refused with `UnsafeStateConfiguration` (PHP) or `unsafe_state_configuration`
(TypeScript, Python): a list that vanished on a deploy would look exactly like a
finished one.

## Adding tasks

`add()` appends a task in the `todo` state and returns it. Give it your own id,
or let the list generate one.

::: language-group
```php
$tasks->add('Draft the summary', 't-1');
$tasks->add('Check the figures', 't-2');
$tasks->add('Send the report');          // id generated, e.g. task_3f9c...
```

```ts
await tasks.add('Draft the summary', 't-1');
await tasks.add('Check the figures', 't-2');
await tasks.addMany(['Send the report', 'Archive the run']); // ids generated: t-1, t-2, ... skipping taken ones
```

```python
tasks.add("Draft the summary", "t-1")
tasks.add("Check the figures", "t-2")
tasks.add_many(["Send the report", "Archive the run"])  # ids generated
tasks.add_many(["Notify finance"], ["t-9"])             # or supplied, one per instruction
```
:::

A blank id is refused (`task_identifier_blank`), and so is an id already in the
list (`duplicate_task_id`). Ids are compared exactly; nothing is trimmed.

## Working through a list

::: language-group
```php
use Prism\Harness\Enums\TaskOutcome;

$worker = gethostname().':'.getmypid();

while ($tasks->pending() > 0) {
    $task = $tasks->claim($worker);          // one atomic call, or null

    if ($task === null) {
        break;                               // other workers hold what is left
    }

    $response = $session->send($task->instruction());

    $tasks->release($task, $worker, TaskOutcome::Done);   // or TaskOutcome::Failed
}
```

```ts
import { hostname } from 'node:os';

const worker = `${hostname()}:${process.pid}`;

while ((await tasks.pending()) > 0) {
  const task = await tasks.claim(worker); // one atomic call, or null

  if (task === null) break; // other workers hold what is left

  const response = await runtime.send(session, task.instruction());

  await tasks.release(task, worker, 'done'); // or 'failed'
}
```

```python
import os
import socket

from prism_harness import TaskOutcome

worker = f"{socket.gethostname()}:{os.getpid()}"

while tasks.pending() > 0:
    task = tasks.claim(worker)  # one atomic call, or None

    if task is None:
        break  # other workers hold what is left

    response = runtime.send(session, task.instruction)

    tasks.release(task, worker, TaskOutcome.DONE)  # or TaskOutcome.FAILED
```
:::

| Method | Does |
| --- | --- |
| `claim(worker, leaseSeconds?)` | Atomically takes the first `todo` task in insertion order and leases it to `worker`. Returns null when none is free |
| `release(task, worker, outcome)` | Records `done` or `failed`. Only the worker holding the current lease may release |
| `pending()` | How many tasks are `todo` (claimable), not how many are unfinished |
| `find(id)` | Resolves an id to a task, or null |
| `extendLease(...)` | Pushes the holder's lease out, within the run's budget |

A task's state is one of four:

| State | Meaning |
| --- | --- |
| `todo` | Waiting to be claimed |
| `claimed` | Leased to a worker until its lease expires |
| `done` | Finished; terminal |
| `failed` | Could not be finished; terminal |

Releasing a finished task again is refused (`task_already_terminal`). A failed
task stays failed; re-queueing it, for example by adding it again under a new
id, is your call.

## A claim carries a lease

Every claim expires. If a worker dies holding a task, the lease lapses and the
task returns to `todo`, never to `failed`: a worker dying is not the task
failing.

The lease defaults to 300 seconds. A lease must be a whole, positive number of
seconds; zero, negative and fractional values are refused with
`task_lease_invalid` rather than rounded.

::: language-group
```php
// The default comes from config/prism-harness.php:
//   'tasks' => ['lease_seconds' => 600, 'lock_wait' => 5]
// or HARNESS_TASK_LEASE_SECONDS and HARNESS_TASK_LOCK_WAIT.
// lock_wait is how long an operation waits for the list's lock.

$task = $tasks->claim($worker, leaseSeconds: 900);   // or per claim
```

```ts
const task = await tasks.claim(worker, 900); // per claim; the default is DEFAULT_LEASE_SECONDS (300)
```

```python
tasks = session.tasks(lease_seconds=600)  # the default for this list

task = tasks.claim(worker, lease_seconds=900)  # or per claim
```
:::

### Extending a lease

A worker may extend a lease it still holds. The extension is bounded by the
run's own [`RunBudget`](/docs/harness/subagents#shared-budgets): it is refused
once the run is cancelled or out of steps, money or time, and never granted
past the run's remaining wall-clock time. The new expiry is now plus what was
granted, even when that is shorter than the current lease.

::: language-group
```php
use Prism\Harness\Subagents\RunBudget;
use Prism\Harness\Subagents\RunContext;

$run = RunContext::root('run-'.$requestId, new RunBudget(maxSteps: 40, maxSeconds: 1800));

$task = $tasks->extendLease($task, $worker, $run->ledger, $run->budget, leaseSeconds: 600);
$task->claimedUntil();   // the new expiry, a Unix timestamp
```

```ts
import { RunBudget, RunContext } from '@particle-academy/prism-harness';

const run = RunContext.root(`run-${requestId}`, new RunBudget(40, null, 1800));

const expiresAt = await tasks.extendLease(task, worker, 600, run.ledger, run.budget); // a Unix timestamp
```

```python
from prism_harness import RunBudget, RunContext

run = RunContext.root(f"run-{request_id}", RunBudget(max_steps=40, max_seconds=1800))

task = tasks.extend_lease(task, worker, run.ledger, run.budget, lease_seconds=600)
task.claimed_until  # the new expiry, a Unix timestamp
```
:::

An extension is refused with `task_lease_not_held` when the worker no longer
holds the task, `task_not_found` when it is not in the list, and
`run_not_permitted` when the budget is spent. Then the task stays claimed until
its current lease runs out and returns to the queue.

## `release()` requires the worker

Only the worker holding the current lease may release a task. A release from a
different worker, or from one whose lease already lapsed and was reclaimed, is
refused with `task_lease_not_held`. Without this, a slow worker could finish
late and overwrite the claim of the worker that legitimately took the task over.

## Completion is the application's call

By default your application decides when a task is done, from evidence, and
calls `release()`. If the model could mark its own task done, "run until the
goal is met" would become "run until the model says it is met".

To let an agent close its own task, register the `complete_task` tool
yourself, list it in the mode's `tools`, and authorize it. Nothing registers it
for you. It requires an explicit `outcome` of `done` or `failed`, and refuses a
task its worker does not hold.

::: language-group
```php
use Illuminate\Support\Facades\Gate;
use Prism\Harness\Sessions\Session;
use Prism\Harness\Tools\TaskCompletionTool;
use Prism\Harness\Tools\ToolAuthorizer;
use Prism\Harness\Tools\ToolRegistry;
use Prism\Prism\Tool;

// In a service provider's boot() method, with HARNESS_AUTHORIZE_TOOLS=true.
app(ToolRegistry::class)->registerFactory(TaskCompletionTool::NAME, fn (Session $session) => TaskCompletionTool::for(
    $session->tasks(),
    $session,
    app(ToolAuthorizer::class),
    gethostname().':'.getmypid(),   // the same worker id that claimed the task
));

// Required: without a harness.tool.call policy the tool refuses every call.
Gate::define('harness.tool.call', fn ($user, Session $session, Tool $tool, array $arguments): bool =>
    $tool->name() !== TaskCompletionTool::NAME || $user->can('close-agent-tasks'));
```

```ts
import { agentCompletionTool, ToolAuthorizer } from '@particle-academy/prism-harness';

const authorizer = new ToolAuthorizer({
  enabled: true,
  call: (session, tool) => tool.name !== 'complete_task' || trustedAgents.has(String(session.participant.id)),
});

const task = await tasks.claim(worker);

if (task !== null) {
  // Bound to this one task; the model supplies only the outcome.
  tools.registerFactory('complete_task', agentCompletionTool({ source: tasks, task, worker, authorizer }));

  await runtime.send(session, task.instruction()); // a runtime built with the same authorizer
}
```

```python
from prism_harness import TaskCompletionTool, ToolAuthorizer

authorizer = ToolAuthorizer(
    enabled=True,
    call=lambda session, tool, args: tool.name != "complete_task"
    or str(session.participant.id) in trusted_agents,
)

# The model supplies task_id and outcome. Give the runtime the same authorizer.
tools.register(TaskCompletionTool(session.tasks(), worker))
```
:::

| | PHP | TypeScript | Python |
| --- | --- | --- | --- |
| Build | `TaskCompletionTool::for($source, $session, $authorizer, $worker)` | `agentCompletionTool({ source, task, worker, authorizer, name? })` returns a factory | `TaskCompletionTool(source, worker)` |
| Model arguments | `task_id`, `outcome` | `outcome` (the task is fixed when built) | `task_id`, `outcome` |
| Without authorization | Refuses every call unless authorization is on **and** a `harness.tool.call` policy exists | Throws when built with a disabled authorizer | Runs; gate it with a `ToolAuthorizer` call policy |
| Refusal | A framed refusal returned to the model | `HarnessError` (`task_outcome_invalid`, `task_lease_not_held`, ...) | `HarnessError` (`task_outcome_invalid`, `task_lease_not_held`, ...) |

## Using your own task model

The store-backed list needs no table or migration. If your application already
has a task model, map it onto the `AgentTask` contract: an id, an instruction
and one of the four states. To claim and release those tasks, implement
`AgentTaskSource` (`claim`, `release`, `pending`, `find`) over your table, with
the same lease and holder rules.

::: language-group
```php
use Illuminate\Database\Eloquent\Model;
use Prism\Harness\Concerns\IsAgentTask;
use Prism\Harness\Contracts\AgentTask;

class Chore extends Model implements AgentTask
{
    use IsAgentTask;

    protected function taskInstructionColumn(): string
    {
        return 'body';          // the default is `instruction`
    }
}
```

```ts
import type { AgentTask, TaskState } from '@particle-academy/prism-harness';

class ChoreTask implements AgentTask {
  constructor(private readonly chore: Chore) {}

  id() { return String(this.chore.id); }
  instruction() { return this.chore.body; }
  state(): TaskState { return this.chore.state; }
}
```

```python
from prism_harness import AgentTaskMixin, TaskState


class ChoreTask(AgentTaskMixin):
    def __init__(self, chore):
        self.chore = chore

    def agent_task_id(self) -> str:
        return str(self.chore.pk)

    def agent_task_instruction(self) -> str:
        return self.chore.body

    def agent_task_state(self) -> TaskState:
        return TaskState(self.chore.status)
```
:::

The PHP trait reads conventional columns, each with an override method:
`taskIdColumn()` (the primary key), `taskInstructionColumn()` (`instruction`),
`taskStateColumn()` (`state`), `taskClaimedByColumn()` (`claimed_by`) and
`taskClaimedUntilColumn()` (`claimed_until`). A missing column or an unknown
state throws `UnmappableTask`. In TypeScript, `asAgentTask(attributes)` and the
`withAgentTask()` mixin build the same contract from plain attributes. In
Python, `AgentTaskMixin` exposes `id`, `instruction` and `state` as properties
from the three methods you implement.

## Errors

| Code | PHP exception | When |
| --- | --- | --- |
| `task_identifier_blank` | `InvalidTaskIdentifier` | A blank worker id or task id |
| `duplicate_task_id` | `InvalidTaskIdentifier` | `add()` with an id already in the list |
| `task_lease_invalid` | `InvalidTaskLease` | A lease that is not a whole positive number of seconds |
| `task_outcome_invalid` | `InvalidTaskOutcome` | An outcome other than `done` or `failed` |
| `task_not_found` | `TaskNotReleasable`, `LeaseNotExtendable` | The task is not in this list |
| `task_already_terminal` | `TaskNotReleasable` | The task is already `done` or `failed` |
| `task_lease_not_held` | `TaskNotReleasable`, `LeaseNotExtendable` | This worker does not hold the task |
| `run_not_permitted` | `LeaseNotExtendable` | The run's budget is spent, so the lease cannot be extended |
| `unsafe_state_configuration` | `UnsafeStateConfiguration` | The list is backed by a volatile store |

In TypeScript and Python each of these is a `HarnessError` with that `code`.

## Ordering and run limits

Tasks are a flat list with next-item semantics, claimed in insertion order:
claim the next one, release it done or failed, repeat. There are no
dependencies between tasks. For dependency-ordered execution, checkpoints or
resume-from-pause, use [`fancy-flow`](https://github.com/Particle-Academy/fancy-flow-php),
which PHP Harness can drive.

How long an agent keeps working is the run's
[`RunBudget`](/docs/harness/subagents#shared-budgets): steps, cost and
wall-clock time. Set cost and time limits as well as steps when individual
steps can be expensive or slow.
