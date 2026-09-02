# Prism Human+

Participant presence for Prism agents in live Fancy surfaces. An agent joins
the same controlled, JSON-serializable surface a human sees instead of pulling
backend levers or impersonating a person through the DOM.

::: warning First vertical slice — source only
The implementation exists and is tested, but it has no tagged release and is
not on Packagist. Do not present the install command as available yet.
:::

## What the surface owns

The Fancy application owns the running surface, stable handles, staged writes,
undo, presence, cursors, and the MCP tools that express its actions. This
package lets a PHP agent enter through an explicit invitation and relay
transport as a named participant.

It ships participant and surface value objects, attachment lifecycle states,
explicit tool trust and definition pinning, bounded result framing, activity
notifications, an isolated MCP `2025-06-18` initialization state machine, and a
client-scoped POST + SSE relay transport.

## Human+ means coexistence

The participant identity, activity target, priority, and correlation remain
visible to the host surface. An agent can call bridge tools and submit staged
proposals, but the package reserves human confirmation callbacks: it does not
turn an agent proposal into its own approval.

Fancy currently supplies the primitives for activity, presence, staged writes,
and undo. This package does **not** claim generic conflict resolution for two
concurrent committed edits; that contract remains surface-specific.

## Trust boundary

Trust must be declared before tool discovery reaches the surface. Tool
definitions can be allowlisted and pinned, and both successful and error
results are bounded and provenance-framed before a model reads them.

The relay uses a nonempty client identifier so replies are scoped to the joining
participant. A gone surface and an unauthorized attachment are distinct terminal
states rather than retry aliases.

## Relationship to Harness and Browser

A Human+ attachment may bind to the same durable Harness session as other
capabilities, but Harness is a suggested dependency rather than a hidden
requirement. Browser remains separate: joining an application-owned surface is
not open-web automation.

Source: [Particle-Academy/prism-human-plus](https://github.com/Particle-Academy/prism-human-plus).

