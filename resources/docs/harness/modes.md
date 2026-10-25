# Modes, tools and skills

A mode defines the instructions, tools, approvals and limits used for an agent
run. A session selects its mode, and every run in that session uses it until
the mode changes.

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

## Defining modes

PHP reads modes from `agent.modes` in `config/prism-harness.php`, with
`agent.default` naming the mode a session uses before it selects one.
TypeScript and Python pass the same structure to `ModeRegistry`.

::: language-group
```php
// config/prism-harness.php
'agent' => [
    'default' => 'research',
    'modes' => [
        'research' => [
            'system_prompt' => 'You research questions and cite your sources.',
            'tools' => ['search_web', 'fact_check'],
            'skills' => [],
            'max_steps' => 6,
            'requires_approval' => [],
            'provider_options' => [],
        ],
    ],
],
```

```ts
import { ModeRegistry } from '@particle-academy/prism-harness';

const modes = new ModeRegistry({
  default: 'research',
  modes: {
    research: {
      system_prompt: 'You research questions and cite your sources.',
      tools: ['search_web', 'fact_check'],
      skills: [],
      max_steps: 6,
      requires_approval: [],
      provider_options: {},
    },
  },
});
```

```python
from prism_harness import ModeRegistry

modes = ModeRegistry({
    "default": "research",
    "modes": {
        "research": {
            "system_prompt": "You research questions and cite your sources.",
            "tools": ["search_web", "fact_check"],
            "skills": [],
            "max_steps": 6,
            "requires_approval": [],
            "provider_options": {},
        }
    },
})
```
:::

| Key | Default | Meaning |
| --- | --- | --- |
| `system_prompt` | `''` | The mode's instructions. A string; to keep a long prompt in a file, read it into this value yourself |
| `tools` | `[]` | Tool names the mode may offer. `['*']` offers every registered tool |
| `skills` | `[]` | Skill names whose instructions are added to the prompt (PHP; see [Skills](#skills)) |
| `max_steps` | `8` | Model steps per run; a positive integer |
| `requires_approval` | `[]` | Tool names that wait for a person. `['*']` gates every tool |
| `subagents` | `[]` | Nested agents the mode may call as tools, keyed by name ([Subagents and budgets](/docs/harness/subagents)) |
| `provider_options` | `[]` | Passed unchanged to the provider on every run in this mode |

A mode does not choose a provider or model. Those come from the session
(`usingProvider()`, `usingModel()`); in PHP, `agent.provider` and `agent.model`
are the fallback. In TypeScript and Python they are passed to your client
unchanged, as `'unknown'` when the session has none.

Resolving a mode that is not configured, or one with a malformed key, throws
`InvalidArgumentException` in PHP and `HarnessError` with `mode_not_configured`
or `mode_malformed` in TypeScript and Python. Run the
[configuration check](/docs/packages/harness#checking-your-configuration) to
find these before a user does.

### Provider options

`provider_options` must be a map of option names. It reaches Prism's
`withProviderOptions()` in PHP, and `request.providerOptions` /
`request.provider_options` in your TypeScript or Python client. For example,
Anthropic adaptive thinking:

::: language-group
```php
'overseer' => [
    'system_prompt' => 'You review the plan before it runs.',
    'provider_options' => [
        'thinking' => ['type' => 'adaptive'],
        'effort' => 'medium',
    ],
],
```

```ts
const modes = new ModeRegistry({
  modes: {
    overseer: {
      system_prompt: 'You review the plan before it runs.',
      provider_options: { thinking: { type: 'adaptive' }, effort: 'medium' },
    },
  },
});
```

```python
modes = ModeRegistry({
    "modes": {
        "overseer": {
            "system_prompt": "You review the plan before it runs.",
            "provider_options": {"thinking": {"type": "adaptive"}, "effort": "medium"},
        }
    }
})
```
:::

## Registering tools

A mode lists tool **names**; the tools themselves live in a `ToolRegistry`.
There are three ways to register one:

| Method | Use it for |
| --- | --- |
| `register(tool)` | A tool that is the same for every session |
| `registerFactory(name, fn (session) => tool)` | A tool bound to the session that runs it, such as one that reads the participant's own records |
| `registerProvider(fn (session) => tools)` | A set of tools decided per session |

::: language-group
```php
use Prism\Harness\Sessions\Session;
use Prism\Harness\Tools\ToolRegistry;
use Prism\Prism\Facades\Tool;

// In a service provider's boot() method.
app(ToolRegistry::class)->registerFactory('my_orders', fn (Session $session) => Tool::as('my_orders')
    ->for('List the current user\'s recent orders.')
    ->using(fn (): string => $session->participant()->orders()->latest()->limit(10)->get()->toJson()));
```

```ts
import { ToolRegistry, type Session } from '@particle-academy/prism-harness';

const tools = new ToolRegistry().registerFactory('my_orders', (session: Session) => ({
  name: 'my_orders',
  description: "List the current user's recent orders.",
  handle: async () => JSON.stringify(await orders.recentFor(String(session.participant.id))),
}));
```

```python
import json

from prism_harness import Session, ToolRegistry


class MyOrders:
    name = "my_orders"

    def __init__(self, session: Session) -> None:
        self._session = session

    def handle(self, args):
        return json.dumps(orders.recent_for(str(self._session.participant.id)))


tools = ToolRegistry().register_factory("my_orders", MyOrders)
```
:::

Bind identity-dependent tools to the session this way rather than reading the
user from global state, so a run can only act for the participant it belongs to.

## Tool allowlists

Include only the tools the mode needs. An empty list is valid and gives a mode
with no tools. Separate modes can give research, editing and verification runs
different authority.

A turn can narrow the list further by passing tool names to `send()`. In PHP
the names are intersected with the mode's list, so a caller can never widen a
mode. In TypeScript and Python the names passed **replace** the mode's list for
that turn; pass only names you have checked against the mode, and never names
taken from request input.

In TypeScript and Python, a name with no registered tool throws
`tool_not_available` when the run starts. PHP offers only the names it can
resolve.

## Tool authorization

The mode's allowlist decides which tools exist for a run. Tool authorization
adds two per-session questions on top, and is **off by default**:

| Check | PHP Gate ability | TypeScript / Python policy | Asked |
| --- | --- | --- | --- |
| May this tool be offered to this run? | `harness.tool` | `offer(session, tool)` | when the toolset is assembled |
| May this call proceed with these arguments? | `harness.tool.call` | `call(session, tool, args)` | before each call runs |

The offer check can only say whether a tool is present. Only the call check can
restrict how it is used, because arguments do not exist until the model calls.

::: language-group
```php
// .env: HARNESS_AUTHORIZE_TOOLS=true

use Illuminate\Support\Facades\Gate;
use Prism\Harness\Sessions\Session;
use Prism\Prism\Tool;

// In a service provider's boot() method. $user is the session's participant.
Gate::define('harness.tool', fn ($user, Session $session, Tool $tool): bool =>
    $tool->name() !== 'refund_order' || $user->is_staff);

Gate::define('harness.tool.call', function ($user, Session $session, Tool $tool, array $arguments): bool {
    if ($tool->name() !== 'lookup_order') {
        return true;
    }

    return Order::whereKey($arguments['id'] ?? null)->where('user_id', $user->getKey())->exists();
});
```

```ts
import { AgentRuntime, ToolAuthorizer } from '@particle-academy/prism-harness';

const authorizer = new ToolAuthorizer({
  enabled: true,
  offer: (session, tool) => tool.name !== 'refund_order' || staffIds.has(String(session.participant.id)),
  call: async (session, tool, args) =>
    tool.name !== 'lookup_order' || (await orders.ownedBy(String(args.id), String(session.participant.id))),
});

const runtime = new AgentRuntime({ client, modes, tools, authorizer });
```

```python
from prism_harness import AgentRuntime, ToolAuthorizer

authorizer = ToolAuthorizer(
    enabled=True,
    offer=lambda session, tool: tool.name != "refund_order" or str(session.participant.id) in staff_ids,
    call=lambda session, tool, args: tool.name != "lookup_order"
    or orders.owned_by(str(args.get("id")), str(session.participant.id)),
)

runtime = AgentRuntime(client=client, modes=modes, tools=tools, authorizer=authorizer)
```
:::

A policy that exists while authorization is off is refused rather than ignored:
PHP throws `UnsafeAuthorizationConfiguration` when `harness.tool` is defined but
`agent.authorize_tools` is false, and TypeScript and Python throw
`unsafe_authorization_configuration` when you pass `offer` or `call` without
`enabled: true`. With authorization on and no call policy, every call of an
offered tool is allowed.

When a call is refused, PHP returns a framed refusal to the model as the tool's
result and the run continues. TypeScript and Python stop the run with
`call_not_authorized`.

## Approvals

A mode can require a person to approve specific tools before they run:

::: language-group
```php
'cleanup' => [
    'system_prompt' => 'You tidy up failed runs.',
    'tools' => ['workspace_read', 'workspace_delete'],
    'requires_approval' => ['workspace_delete'],   // '*' gates every tool
],
```

```ts
const modes = new ModeRegistry({
  modes: {
    cleanup: {
      system_prompt: 'You tidy up failed runs.',
      tools: ['workspace_read', 'workspace_delete'],
      requires_approval: ['workspace_delete'], // '*' gates every tool
    },
  },
});
```

```python
modes = ModeRegistry({
    "modes": {
        "cleanup": {
            "system_prompt": "You tidy up failed runs.",
            "tools": ["workspace_read", "workspace_delete"],
            "requires_approval": ["workspace_delete"],  # "*" gates every tool
        }
    }
})
```
:::

When the model calls a gated tool, the run stops and returns the pending
approvals. Calls in the same step that need no approval have already run. Your
application shows the pending calls to a person, records each decision, and
the run continues: approved calls run once, denied calls return their reason to
the model, and the model carries on from there.

### Answering in PHP

`send()` returns an `AgentResponse`. `awaitingApproval()` is true when the run
stopped for a person, and `pendingApprovals()` lists Prism
`ToolApprovalRequest` objects with an `approvalId` and the `toolCallId` they
gate. Each of these methods records the decision in the thread and **continues
the run**, returning the next `AgentResponse`:

| Method | Does |
| --- | --- |
| `approve($request, bool $approved = true, ?string $reason = null, ?RunContext $context = null)` | Answers one pending request (or its approval id) |
| `deny($request, ?string $reason = null, ?RunContext $context = null)` | Answers one with a refusal |
| `decide(array $decisions, ?RunContext $context = null)` | Answers several `ToolApprovalResponse` objects at once |

A run continues as soon as it has an answer, and any pending call still without
one is **denied**. So with more than one pending call, answer them all together
with `decide()`:

```php
use Prism\Prism\ValueObjects\ToolApprovalRequest;
use Prism\Prism\ValueObjects\ToolApprovalResponse;

$response = $session->send('Clean up the failed run');

if ($response->awaitingApproval()) {
    // What each request is for: match toolCallId to the step's tool calls.
    $calls = [];
    foreach ($response->response->steps as $step) {
        foreach ($step->toolCalls as $call) {
            $calls[$call->id] = $call;   // ->name, ->arguments()
        }
    }

    $response = $session->decide(array_map(
        fn (ToolApprovalRequest $pending) => new ToolApprovalResponse(
            $pending->approvalId,
            approved: $calls[$pending->toolCallId]->name !== 'workspace_delete' || $user->is_admin,
            reason: 'Only an administrator may delete.',
        ),
        $response->pendingApprovals(),
    ));
}
```

With a single pending call, `$session->approve($pending)` or
`$session->deny($pending, 'not on production')` is enough. If the run uses a
[run context](/docs/harness/subagents#run-specific-limits), pass the same one
to `approve()`, `deny()` or `decide()`.

### Answering in TypeScript and Python

The response's finish reason is `awaiting_approval`, and `pendingApprovals` /
`pending_approvals` lists `{ id, toolCallId, tool, arguments }`. Recording a
decision does **not** continue the run: record every decision first, then
resume with an empty prompt.

::: language-group
```php
// See "Answering in PHP" above.
```

```ts
import { recordApproval } from '@particle-academy/prism-harness';

let response = await runtime.send(session, 'Clean up the failed run');

if (response.finishReason === 'awaiting_approval') {
  for (const pending of response.pendingApprovals) {
    const allowed = pending.tool !== 'workspace_delete' || isAdmin(user);
    await recordApproval(session, pending.id, allowed, allowed ? null : 'Only an administrator may delete.');
  }

  response = await runtime.send(session, '');
}
```

```python
from prism_harness import record_approval

response = runtime.send(session, "Clean up the failed run")

if response.finish_reason == "awaiting_approval":
    for pending in response.pending_approvals:
        allowed = pending.tool != "workspace_delete" or is_admin(user)
        record_approval(session, pending.id, allowed, None if allowed else "Only an administrator may delete.")

    response = runtime.send(session, "")
```
:::

`recordApproval(session, approvalId, approved, reason?)` and
`record_approval(session, approval_id, approved, reason=None)` take the
approval's `id`, not the tool call id. On the next `send()`, any pending call
with no recorded decision is refused with "No approval response provided".

### What holds in every language

- **Decisions are stored in the thread.** The approval a person gives this
  morning can be resumed tonight by a different worker, after a deploy. This is
  why the durable store refuses volatile drivers.
- **Unanswered means denied.** A lost or unanswered approval fails closed.
- **A call that has a result never runs again**, and the model is not asked to
  repeat it.
- **Who may approve is your decision.** The session is scoped to its
  participant, but whether this person may approve this action is something
  only your application knows. Authorize before recording a decision, and never
  pass an approval id from request input without checking it belongs to a
  pending request you showed that person.
- **Waiting is not failure.** Do not retry a run that is awaiting approval;
  answer it.

For shared human/agent interfaces, see [Human+](/docs/packages/human-plus).
Its confirmation tools are reserved for human participants.

## Skills

A skill is a folder of instructions an agent can read: a `SKILL.md` and any
files it refers to. Keep skills separate from agent-writable workspace files so
a run cannot modify its own instructions.

In PHP, list skills on the mode. Each skill's `SKILL.md` is appended to the
system prompt inside a `<skill name="…">` block, and the run is offered a
`skill_read` tool (`skill`, `path`) that reads other files inside a skill, such
as `remotion-create/REFERENCE.md`. Paths that leave the skill folder are
refused.

Skills are read from the package's own `resources/skills` folder, which ships
the `remotion` skill. To serve your own folder instead, rebind `SkillRegistry`
in a service provider's `register()` method:

::: language-group
```php
use Prism\Harness\Skills\SkillRegistry;

// AppServiceProvider::register()
$this->app->singleton(SkillRegistry::class, fn () => new SkillRegistry(resource_path('skills')));

// config/prism-harness.php, under agent.modes:
//
//   'video' => [
//       'system_prompt' => 'You build short videos.',
//       'tools' => ['workspace_write'],
//       'skills' => ['remotion'],
//   ],
```

```ts
import { ModeRegistry, SkillRegistry, ToolRegistry } from '@particle-academy/prism-harness';

const skills = new SkillRegistry('./resources/skills');

const modes = new ModeRegistry({
  modes: {
    video: {
      system_prompt: skills.augmentPrompt('You build short videos.', ['remotion']),
      tools: ['workspace_write', 'skill_read'],
    },
  },
});

const tools = new ToolRegistry().register({
  name: 'skill_read',
  description: 'Read a referenced file from a skill.',
  handle: (args) => skills.read(String(args.skill), String(args.path)),
});
```

```python
from prism_harness import ModeRegistry, SkillRegistry, ToolRegistry

skills = SkillRegistry("./resources/skills")

modes = ModeRegistry({
    "modes": {
        "video": {
            "system_prompt": skills.augment_prompt("You build short videos.", ["remotion"]),
            "tools": ["workspace_write", "skill_read"],
        }
    }
})


class SkillRead:
    name = "skill_read"

    def handle(self, args):
        return skills.read(str(args["skill"]), str(args["path"]))


tools = ToolRegistry().register(SkillRead())
```
:::

The TypeScript and Python runtimes do not read a mode's `skills` key. Use
`SkillRegistry` directly, as above: `augmentPrompt` / `augment_prompt` builds
the same prompt PHP does, and `read()` applies the same path checks, failing
with `skill_path_refused`.

`skill_read` can read any skill under its root, not only the ones the mode
lists, so keep only skills you are willing to expose in that folder. With tool
authorization on, `skill_read` is checked like any other tool.
