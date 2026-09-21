# Subagents and budgets

A mode may declare subagents, which the parent can call as tools.

::: language-group
```php
'subagents' => [
    new Subagent('verify_claim', 'Check a claim.', 'verifier', new RunBudget(maxSteps: 5)),
],
```

```ts
const verifyClaim = new Subagent(
  'verify_claim',
  'Check a claim.',
  'verifier',
  new RunBudget(5),
);
```

```python
verify_claim = Subagent(
    "verify_claim",
    "Check a claim.",
    "verifier",
    RunBudget(max_steps=5),
)
```
:::

A subagent runs with its own mode and a budget drawn from its parent. See
[Modes](/docs/harness/modes) for tool allowlists and permissions.

## Shared budgets

A child's effective budget is the smaller of its declared limit and the
remaining parent budget. Usage is recorded in a shared ledger, so child runs
reduce the budget available to the parent and other subagents.

## Run-specific limits

A mode supplies the default step limit. Applications can provide a
run-specific limit within the operator's configured ceiling:

::: language-group
```php
$session->usingMaxSteps(20);
```

```ts
const context = RunContext.root(runId, new RunBudget(20));
const response = await runtime.send(session, prompt, context);
```

```python
context = RunContext.root(run_id, RunBudget(max_steps=20))
response = runtime.send(session, prompt, context)
```
:::

In PHP, `agent.max_steps_ceiling` bounds the caller's override. TypeScript and
Python pass the run budget through `RunContext`, as shown above. Keep the
instructions given to the model consistent with the effective budget.

## Detecting a step limit

Inspect the run's finish reason and remaining steps. Pending tool calls with no
remaining steps indicate an interrupted run. Reaching the final allowed step
does not by itself mean the run was interrupted; the model may have completed
its response on that step.
