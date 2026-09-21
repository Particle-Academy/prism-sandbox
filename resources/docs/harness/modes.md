# Modes, tools and skills

A mode defines the instructions, tools and limits used for an agent run.

::: language-group
```php
$session->usingMode('research');
```

```ts
await session.usingMode('research');
```

```python
session.using_mode("research")
```
:::

Each mode declares its own system prompt, the tools it may call, the skills it
may read, and a default step ceiling:

::: language-group
```php
'research' => [
    'system_prompt' => PromptFile::content('research'),
    'tools' => ['search_web', 'fact_check'],
    'skills' => [],
    'max_steps' => 6,
],
```

```ts
const modes = new ModeRegistry({
  modes: {
    research: {
      system_prompt: researchPrompt,
      tools: ['search_web', 'fact_check'],
      skills: [],
      max_steps: 6,
    },
  },
});
```

```python
modes = ModeRegistry({
    "modes": {
        "research": {
            "system_prompt": research_prompt,
            "tools": ["search_web", "fact_check"],
            "skills": [],
            "max_steps": 6,
        }
    }
})
```
:::

## Tool allowlists

Include only the tools the mode needs. An empty allowlist is valid and creates
a mode with no tools. Separate modes can give research, editing and verification
runs different permissions.

## Tool authorization is off by default

The mode's allowlist determines which tools are available. Enable tool
authorization to apply additional session-specific access checks:

::: language-group
```php
'authorize_tools' => env('HARNESS_AUTHORIZE_TOOLS', false),
```

```ts
const authorizer = new ToolAuthorizer({ enabled: false });
```

```python
authorizer = ToolAuthorizer(enabled=False)
```
:::

Configure an authorization policy before enabling these checks. PHP's
`harness:doctor` reports its resolved configuration.
TypeScript and Python configure the authorizer directly; Laravel Gate and
Artisan are not required by those packages.

## Approvals

A mode can require a human before specific tools run:

::: language-group
```php
'requires_approval' => ['workspace_delete'],
```

```ts
const modes = new ModeRegistry({
  modes: { research: { system_prompt: researchPrompt, requires_approval: ['workspace_delete'] } },
});
```

```python
modes = ModeRegistry({
    "modes": {
        "research": {
            "system_prompt": research_prompt,
            "requires_approval": ["workspace_delete"],
        }
    }
})
```
:::

Tools requiring approval are denied when no approval decision is available.

For shared human/agent interfaces, see [Human+](/docs/packages/human-plus).
Its confirmation tools are reserved for human participants.

## Skills

Skills provide reusable instructions to the agent. Keep skill sources separate
from agent-writable workspace files so a run cannot modify its own instructions.
