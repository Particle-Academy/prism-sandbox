# Task lists

An agent given a goal has to keep working across many requests until the goal is
met. It needs a list of what remains — and that list has to survive the request,
the worker, a crash and a deploy.

```php
$tasks = $session->tasks();

$task = $tasks->claim('worker-1');          // one atomic call, or null

if ($task !== null) {
    $response = $session->send($task->instruction());

    $tasks->release($task, 'worker-1', TaskOutcome::Done);
}
```

A Laravel request boots, serves and dies, so the list cannot live in memory. It
lives in the session's durable store, and a fresh worker resolving the same
session sees the same list at the same point.

## It does not decide what a task is

There is no task model, no schema and no migration. That part is yours.

**With no table at all**, `$session->tasks()` gives you a store-backed list that
works immediately.

**With your own model**, add the trait:

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

Conventional column names, one override method each.

## A claim carries a lease

`claim()` is a single call, not "read the next task" followed by "mark it mine".
Two workers claiming at once get different tasks, or one gets `null`. Never the
same task twice.

Every claim expires. If a worker dies holding a task, the lease lapses and the
task becomes claimable again — returning to `todo`, never to `failed`. **A worker
dying is not the task failing**, and marking it failed would burn a retry that
never ran.

A worker may extend its own lease while it still holds it, bounded by what the
run's wall-clock budget has left.

## `release()` requires the worker

Passing the worker is not bookkeeping. A release by anyone other than the current
holder is refused, and the failure it prevents needs no adversary:

Worker A's lease lapses mid-task. B legitimately reclaims it and starts. A
finishes and releases — overwriting B's live claim. The task reads `done` while B
is still working, B's work is discarded, and **B's own release then fails as
"already terminal"**, so the second worker is blamed for the first one's mistake.

## The agent cannot close its own task

By default, completion is the application's call, made from evidence.

If the model can set its own task to `done`, then "run until the goal is met"
quietly becomes "run until it decides it is met", and a run that has stalled ends
by declaring victory.

To let an agent close its own work, register `TaskCompletionTool` and authorize
it through the [tool authorizer](/docs/harness/modes) — the same mechanism that
gates every other tool. Nothing registers it for you.

```php
$tasks->pending();          // how many remain claimable
$tasks->find($id);          // resolve an id to a task
```

## What bounds the loop

The existing [`RunBudget`](/docs/harness/subagents) — cost, turns and wall-clock.
There is no second limit to configure, and budgets nest rather than reset, so a
task that spawns subagents draws from the same remaining allowance.

`maxSteps` alone is not a budget: it bounds iterations, and a handful of steps
each calling an expensive tool sits comfortably inside it.

## What it does not do

It is **not a workflow engine**. Tasks are a flat list with next-item semantics —
no dependency graph, no scheduling, no retry policy. A failed task stays failed;
re-queueing it is the application's decision.

If you need dependency-ordered execution, durable checkpoints or
resume-from-pause, that is
[`fancy-flow`](https://github.com/Particle-Academy/fancy-flow-php), which the
harness already bridges to.

The list is durable state, so a task source backed by a volatile store refuses to
start rather than losing your list on the next deploy. See
[Sessions](/docs/harness/sessions) for that distinction.

- TypeScript and Python ports carry the same contract. See
  [Language ports](/docs/packages/language-ports).
