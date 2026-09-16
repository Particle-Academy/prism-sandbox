# Modes, tools and skills

A **mode** is what an agent is allowed to be, for a stretch of a conversation.

```php
$session->usingMode('research');
```

Each mode declares its own system prompt, the tools it may call, the skills it
may read, and a default step ceiling:

```php
'research' => [
    'system_prompt' => PromptFile::content('research'),
    'tools' => ['search_web', 'fact_check'],
    'skills' => [],
    'max_steps' => 6,
],
```

## A mode narrows what a run can reach

A mode earns its keep through the reach it withholds. A verifier that can file
its own finding becomes a second author; a judge that can reach the workspace it
is scoring can look past the evidence at the artifact itself. Each stays a check
for as long as its mode stays narrow.

So the tool list is an allow-list, and an empty one is a legitimate and common
answer.

## Tool authorization is off by default

Declaring a tool in a mode makes it *offerable*. Whether a given session may
actually call it is a second question, and the answer is opt-in:

```php
'authorize_tools' => env('HARNESS_AUTHORIZE_TOOLS', false),
```

Off by default because turning it on without an authorizer would refuse
everything, and a package that breaks on install teaches people to skip the
security feature. `harness:doctor` reports what your configuration is actually
doing, resolved value by resolved value.

## Approvals

A mode can require a human before specific tools run:

```php
'requires_approval' => ['workspace_delete'],
```

Prism denies by default when no approval answer is found, so an unattended run
cannot slip past the gate by simply not being asked.

Where a human is genuinely part of the surface — approving, confirming,
participating — see [Human+](/docs/packages/human-plus), which reserves
confirmation tools for the person and not the agent.

## Skills

Skills are instructions offered to the agent without copying them into its
workspace. The agent reads a skill; it does not receive a folder it can then
modify and re-read as though it were fact.
