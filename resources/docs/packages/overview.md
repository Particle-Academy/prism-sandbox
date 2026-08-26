# Companion packages

Prism core is a **provider API shuttle**. It speaks to sixteen providers through
one fluent interface, and that is the whole job — text, structured output,
streaming, embeddings, tools, images, audio, moderation. Everything it does is
in service of getting a request to a provider and an answer back.

Which means a lot of what people want to build *around* a model — a
conversation that survives the request, tools borrowed from a server you do not
own, traces in your observability stack — is deliberately **not** in core. Put
it there and core stops being a shuttle and starts being a framework, and every
provider fix has to be shipped past an agent runtime that has nothing to do
with it.

So it lives out here instead, one package at a time, each installable on its
own.

::: tip These are separate packages
Nothing on this page is bundled with `particle-academy/prism`. You install what
you need and nothing else, and a package you never install costs you nothing —
not a dependency, not a service provider, not a config file.
:::

## What exists today

| Package | For | Version | Status |
| --- | --- | --- | --- |
| [`prism-harness`](/packages/harness) | Threads, sessions, tool state | `v0.1.2` | **Release candidate — in testing** |
| [`prism-mcp`](/packages/mcp) | Model Context Protocol client | `v0.1.0` | First slice, published |
| [`prism-perplexity`](/packages/perplexity) | Perplexity search + embeddings | `v0.1.0` | Published |
| [`prism-opentelemetry`](/packages/opentelemetry) | Telemetry bridge | `v0.1.1` | Published |

Every one of them is **pre-1.0**, and the version numbers say so. They follow
the same rule Prism itself does at this stage: a minor bump can change an API.
Pin what you install and read the release notes before you move.

## Still being built

Two more are written and working, and **not yet published to Packagist** — you
cannot `composer require` them today:

- **`prism-memory`** — persistent context and semantic recall: a vector store, a
  `remember`/`recall` pair, and retrieval that fits a token budget. The first
  slice works end to end. Three questions are still deliberately open, including
  what forgetting means for a memory derived from another one.
  ([repo](https://github.com/Particle-Academy/prism-memory))
- **`prism-workspace`** — a sandboxed, session-scoped place for an agent to keep
  files: a scoped Laravel `Storage` disk behind a path guard, shipped with the
  134-path adversarial corpus that proves the boundary holds. Files only; code
  execution is deferred on purpose.
  ([repo](https://github.com/Particle-Academy/prism-workspace))

A third, **`prism-rag`** (document ingestion, chunking, retrieval), is
[specified](https://github.com/Particle-Academy/prism-parity/blob/main/specs/prism-rag.md)
and **not started**. It is listed here so nobody builds it twice, not because it
is close.

## What is deliberately not being built

**A workflow engine.** A graph runtime with durable runs, checkpoints and
human-approval pauses is app plumbing, not AI — and one already exists in the
sibling Fancy ecosystem, with an agent node in it. Building a second would put
Prism on the wrong side of the boundary it just drew, and split the story across
two brands. The work that is actually needed is the *seam*: making that agent
node drive a harness session well. That is
[recorded in the specs](https://github.com/Particle-Academy/prism-parity/blob/main/specs/README.md),
because the gap is real and somebody will propose filling it again.

## Where the shared rules live

These packages are not independent projects that happen to share a prefix. The
conventions they all follow — versioning, error codes, when a package is allowed
to stay silent instead of throwing, how a pending-request builder behaves — are
written once in
[`prism-parity`](https://github.com/Particle-Academy/prism-parity), and each
package links there rather than restating them. Restated documentation drifts
exactly like restated code, and nothing tests prose.

The rule that matters most when reading any of them: **a package that wants
something from core files an issue against core and works around it in the
meantime.** Core does not grow a feature because a satellite would like one.

## Relay and Bedrock

The two entries in the upstream **Packages** section of this sidebar
([Relay](https://github.com/prism-php/relay),
[Bedrock](https://github.com/prism-php/bedrock)) come from `prism-php`, the
project this fork descends from. They still work.

`prism-mcp` is the successor to Relay and declares
`replace: prism-php/relay`, so Composer will not let you install both. If you
are already on Relay, [read the MCP page](/packages/mcp) before switching — the
trust model is genuinely different, and it will refuse a server that Relay would
have connected to.
