# Prism Human+

Participant presence for Prism agents in live Fancy surfaces. An agent joins
the same controlled, JSON-serializable surface a human sees, and works through
the handles that surface already exposes.

::: language-group
```bash [language=php]
composer require particle-academy/prism-human-plus
```

```bash [language=typescript]
npm install @particle-academy/prism-human-plus
```

```bash [language=python]
pip install prism-ai-human-plus
```
:::

::: warning Versioning
The language packages are versioned independently. Before 1.0, minor releases
may include API changes. Pin your dependency and review release notes before upgrading.
:::

## What the surface owns

The Fancy application owns the running surface, stable handles, staged writes,
undo, presence, cursors, and the MCP tools that express its actions. This
package lets an agent enter through an explicit invitation and relay
transport as a named participant.

It ships participant and surface value objects, attachment lifecycle states,
explicit tool trust and definition pinning, bounded result framing, activity
notifications, an isolated MCP `2025-06-18` initialization state machine, and a
client-scoped POST + SSE relay transport.

## Join a surface

::: language-group
```php
use Prism\HumanPlus\Data\Participant;
use Prism\HumanPlus\Data\SurfaceInvitation;
use Prism\HumanPlus\HumanPlusManager;
use Prism\HumanPlus\Security\TrustPolicy;
use Prism\HumanPlus\Stores\InMemoryAttachmentStore;
use Prism\HumanPlus\Security\ResultGuard;

$humanPlus = new HumanPlusManager(
    $transport,
    new InMemoryAttachmentStore,
    TrustPolicy::allowing(['sheet_read', 'sheet_propose_edit']),
    new ResultGuard,
);

$attachment = $humanPlus->attach(
    $session,
    new SurfaceInvitation($relayUrl, $sessionId, $token, $surfaceId, 'sheets'),
    new Participant('agent-1', 'Assistant', '#7c3aed'),
);
```

```ts
import {
  HumanPlusManager,
  InMemoryAttachmentStore,
  ResultGuard,
  SurfaceInvitation,
  TrustPolicy,
} from '@particle-academy/prism-human-plus';

const humanPlus = new HumanPlusManager(
  transport,
  new InMemoryAttachmentStore(),
  TrustPolicy.allowing(['sheet_read', 'sheet_propose_edit']),
  new ResultGuard(),
);

const attachment = await humanPlus.attach(
  session,
  new SurfaceInvitation({ relayBaseUrl, sessionId, token, surfaceId, application: 'sheets' }),
  { id: 'agent-1', name: 'Assistant', color: '#7c3aed' },
);
```

```python
from prism_human_plus import (
    HumanPlusManager,
    InMemoryAttachmentStore,
    Participant,
    SurfaceInvitation,
    TrustPolicy,
)

human_plus = HumanPlusManager(
    transport,
    InMemoryAttachmentStore(),
    TrustPolicy.allowing(["sheet_read", "sheet_propose_edit"]),
)

attachment = human_plus.attach(
    session,
    SurfaceInvitation(
        relay_base_url=relay_url,
        session_id=session_id,
        token=token,
        surface_id=surface_id,
        application="sheets",
    ),
    Participant(id="agent-1", name="Assistant", color="#7c3aed"),
)
```
:::

## Shared interaction

The participant identity, activity target, priority, and correlation remain
visible to the host surface. An agent can call bridge tools and submit staged
proposals. Human confirmation callbacks cannot be invoked by the agent.

Fancy currently supplies the primitives for activity, presence, staged writes,
and undo. Conflict resolution for two concurrent committed edits stays
with the host application.

## Trust boundary

Trust must be declared before tool discovery reaches the surface. Tool
definitions can be allowlisted and pinned, and both successful and error
results are bounded and provenance-framed before a model reads them.

The relay uses a nonempty client identifier so replies are scoped to the joining
participant. A gone surface and an unauthorized attachment each end in
their own terminal state, so your recovery path can tell them apart.

## Relationship to Harness and Browser

A Human+ attachment binds to the same durable Harness session as other
capabilities when you want it to; Harness stays a suggested dependency you opt
into. Browser covers the open web, Human+ covers an application-owned surface,
and the packages can be used independently.

Source: [Particle-Academy/prism-human-plus](https://github.com/Particle-Academy/prism-human-plus).
