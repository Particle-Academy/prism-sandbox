# Subagents and budgets

A mode may declare subagents, which the parent can call as tools.

```php
'subagents' => [
    new Subagent('verify_claim', 'Check a claim.', 'verifier', new RunBudget(maxSteps: 5)),
],
```

A subagent is a narrowed agent, not a fresh one — see
[Modes](/docs/harness/modes) for why the narrowing is the point.

## Budgets nest; they do not reset

This is the rule the whole subagent surface rests on, and it only has one
defensible answer.

A parent limited to eight steps that may spawn subagents each entitled to a
fresh eight has **no bound at all**. It has a bound per node, in a tree whose
width it also controls — which is unbounded spend wearing a limit's clothing.

So a child gets the **smaller** of what it declares and what the tree has left.
A child may ask for less than it is offered. It may never ask for more than
remains.

One ledger is shared by the whole tree, passed by reference rather than copied,
so a child spending is what makes the parent's remaining budget shrink.

## The caller may set the ceiling, within the operator's

A mode's `max_steps` is a sensible *default* and a poor universal. A caller
holding a frozen, approved budget knows the size of the job better than a config
constant does:

```php
$session->usingMaxSteps(20);
```

When those two disagree the agent is told one number and cut off at another — it
plans for the promise, spends the promise, and has its work discarded at the
smaller limit. That is not a bounded run; it is a wasted one.

The override is itself bounded. `agent.max_steps_ceiling` is the operator's
word and outranks the caller, because a limit the thing being limited can raise
without bound is a limit in name only — the same argument the nesting rule makes
about children.

## Truncation is not completion

When a run stops at its ceiling the loop returns normally. A caller that reads
only "the agent returned" cannot tell a finished job from one cut off mid-work,
and will file the second as the first.

The tell is the pair: the model still wanted to call a tool **and** no steps
remained. A run that stopped on the last step having said it was done is a
genuine finish.
