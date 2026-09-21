# Task lists

Task lists track work across agent runs. Tasks and claims are stored durably
so another worker can continue after a restart or deployment.

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

In PHP, TypeScript and Python, the list lives in the session's durable store.
A fresh worker resolving the same session sees the same list at the same point,
including after a process restart or deployment.

## Task definitions

Use the built-in store-backed list or adapt your application's task model.

The session's `tasks()` method gives you a store-backed list without requiring
a separate task table.

For your own model, PHP offers an Eloquent trait, TypeScript an `AgentTask`
interface, and Python an `AgentTaskMixin`:

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

Map your model's identifier, instruction and state to the task contract. The
PHP trait supplies conventional column names with override methods.

## A claim carries a lease

`claim()` atomically selects and leases the next available task. Concurrent
claims return different tasks, or `null` when none is available. A task can be
claimed again after its lease expires.

Every claim expires. If a worker dies holding a task, the lease lapses and the
task becomes claimable again with state `todo`. A worker failure does not
automatically mark the task as failed.

A worker may extend its own lease while it still holds it, bounded by what the
run's wall-clock budget has left.

## `release()` requires the worker

Only the worker holding the current lease may release a task. Releases from
an expired or different holder are rejected, preventing a previous worker from
overwriting a newer claim.

## Completion is the application's call

By default, application code decides when a task is complete. Validate the
result before marking it done.

To let an agent close its own work, register `TaskCompletionTool` and authorize
it through the [tool authorizer](/docs/harness/modes). It is not registered
automatically.

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

## Run limits

Task execution uses [`RunBudget`](/docs/harness/subagents) for cost, turn and
wall-clock limits. Subagents draw from the parent run's remaining budget.

`maxSteps` limits iterations. Configure cost and wall-clock limits as well when
individual steps may be expensive or slow.

## Task ordering

Tasks are a flat list with next-item semantics: claim the next one, release it
done or failed, repeat. A failed task stays failed, and re-queueing it is your
call.

For dependency-ordered execution, durable checkpoints or resume-from-pause,
reach for [`fancy-flow`](https://github.com/Particle-Academy/fancy-flow-php),
through the PHP Harness integration. That integration is separate from the
task-list contract shared by all three languages.

Task sources require durable storage and reject volatile stores. See
[Sessions](/docs/harness/sessions) for that distinction.

- TypeScript and Python ports carry the same contract. See
  [Language ports](/docs/packages/language-ports).
