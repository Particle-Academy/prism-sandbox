# Task lists

An agent given a goal has to keep working across many requests until the goal is
met. It needs a list of what remains — and that list has to survive the request,
the worker, a crash and a deploy.

::: language-group
```php
$tasks = $session->tasks();

$task = $tasks->claim('worker-1');          // one atomic call, or null

if ($task !== null) {
    $response = $session->send($task->instruction());

    $tasks->release($task, 'worker-1', TaskOutcome::Done);
}
```

```ts
const tasks = session.tasks();
const task = await tasks.claim('worker-1'); // one atomic call, or null

if (task !== null) {
  const response = await runtime.send(session, task.instruction());
  await tasks.release(task, 'worker-1', 'done');
}
```

```python
from prism_harness import TaskOutcome

tasks = session.tasks()
task = tasks.claim("worker-1")  # one atomic call, or None

if task is not None:
    response = runtime.send(session, task.instruction)
    tasks.release(task, "worker-1", TaskOutcome.DONE)
```
:::

A Laravel request boots, serves and dies, so the list cannot live in memory. It
lives in the session's durable store, and a fresh worker resolving the same
session sees the same list at the same point.

## You decide what a task is

Bring your own model, schema and migration — or bring none.

**With no table at all**, `$session->tasks()` gives you a store-backed list that
works immediately.

**With your own model**, add the trait:

::: language-group
```php
use Prism\Harness\Concerns\IsAgentTask;

class Chore extends Model
{
    use IsAgentTask;

    protected function taskInstructionColumn(): string
    {
        return 'body';          // if your column is not `instruction`
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
    def agent_task_id(self) -> str:
        return str(self.pk)

    def agent_task_instruction(self) -> str:
        return self.body

    def agent_task_state(self) -> TaskState:
        return TaskState(self.status)
```
:::

Conventional column names, one override method each.

## A claim carries a lease

`claim()` is a single call that reads the next task and marks it yours together.
Two workers claiming at once get different tasks, or one gets `null`. Never the
same task twice.

Every claim expires. If a worker dies holding a task, the lease lapses and the
task becomes claimable again — returning to `todo`, never to `failed`. **A worker
dying is not the task failing**, and marking it failed would burn a retry that
never ran.

A worker may extend its own lease while it still holds it, bounded by what the
run's wall-clock budget has left.

## `release()` requires the worker

Passing the worker is what makes a release safe. Only the current holder may
release a task, and the failure that prevents needs no adversary:

Worker A's lease lapses mid-task. B legitimately reclaims it and starts. A
finishes and releases — overwriting B's live claim. The task reads `done` while B
is still working, B's work is discarded, and **B's own release then fails as
"already terminal"**, so the second worker is blamed for the first one's mistake.

## Completion is the application's call

By default, a task closes on evidence your code has seen.

If the model can set its own task to `done`, then "run until the goal is met"
quietly becomes "run until it decides it is met", and a run that has stalled ends
by declaring victory.

To let an agent close its own work, register `TaskCompletionTool` and authorize
it through the [tool authorizer](/docs/harness/modes) — the same mechanism that
gates every other tool. Nothing registers it for you.

::: language-group
```php
$tasks->pending();          // how many remain claimable
$tasks->find($id);          // resolve an id to a task
```

```ts
await tasks.pending(); // how many remain claimable
await tasks.find(id);  // resolve an id to a task
```

```python
tasks.pending()  # how many remain claimable
tasks.find(id)   # resolve an id to a task
```
:::

## What bounds the loop

The existing [`RunBudget`](/docs/harness/subagents) — cost, turns and wall-clock.
There is no second limit to configure, and budgets nest rather than reset, so a
task that spawns subagents draws from the same remaining allowance.

`maxSteps` alone is not a budget: it bounds iterations, and a handful of steps
each calling an expensive tool sits comfortably inside it.

## The shape of the list

Tasks are a flat list with next-item semantics: claim the next one, release it
done or failed, repeat. A failed task stays failed, and re-queueing it is your
call.

For dependency-ordered execution, durable checkpoints or resume-from-pause,
reach for [`fancy-flow`](https://github.com/Particle-Academy/fancy-flow-php),
which the harness already bridges to.

The list is durable state, so a task source backed by a volatile store refuses
to start — your list survives the next deploy. See
[Sessions](/docs/harness/sessions) for that distinction.

- TypeScript and Python ports carry the same contract. See
  [Language ports](/docs/packages/language-ports).
