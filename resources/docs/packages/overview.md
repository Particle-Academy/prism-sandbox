# Companion packages

Prism core is a **provider API shuttle**. It speaks to eighteen providers through
one fluent interface, and that is the whole job — text, structured output,
streaming, embeddings, tools, images, audio, moderation. Everything it does is
in service of getting a request to a provider and an answer back.

Everything you build *around* a model — a conversation that survives the
request, tools borrowed from a server you do not own, traces in your
observability stack — ships out here, one package at a time. Core keeps its
shape, so a provider fix reaches you the day it lands.

::: tip One require each
Every package on this page installs on its own: one `composer require`, one
version to pin, one config file. Take what your application needs and leave the
rest.
:::

## What you can install today

| Package | For | Version |
| --- | --- | --- |
| [`prism-harness`](/packages/harness) | Durable agent sessions: threads, modes, approvals, subagents | `v0.11.1` |
| [`prism-mcp`](/packages/mcp) | Model Context Protocol client, across a visible trust boundary | `v0.2.0` |
| [`prism-workspace`](/packages/workspace) | A sandboxed disk an agent can write to, behind a path guard | `v0.2.0` |
| [`prism-memory`](/packages/memory) | Persistent context and semantic recall on a token budget | `v0.2.0` |
| [`prism-human-plus`](/packages/human-plus) | An agent as a participant in a live Fancy surface | `v0.3.0` |
| [`prism-browser`](/packages/browser) | Guarded open-web automation | `v0.1.0` |
| [`prism-perplexity`](/packages/perplexity) | Perplexity search, embeddings and the Agent API | `v0.2.0` |
| [`prism-opentelemetry`](/packages/opentelemetry) | Agent runs as OpenTelemetry spans | `v0.3.0` |
| [TypeScript and Python ports](/packages/language-ports) | Every capability, in two more languages | npm · PyPI |

Every one of them is **pre-1.0**, and the version numbers say so. They follow
the same rule Prism itself does at this stage: a minor bump can change an API.
Pin what you install and read the release notes before you move.

## Next

**`prism-rag`** — document ingestion, chunking and retrieval — is
[specified](https://github.com/Particle-Academy/prism-parity/blob/main/specs/prism-rag.md)
and open. The spec is the place to start.

## Where workflows live

Durable orchestration — a graph runtime, run checkpointing, a human-approval
pause — is application plumbing, and the sibling Fancy ecosystem already ships
it with an agent node.

Drive a Harness session from that node, and each side keeps the job it is good
at: Fancy runs the graph, Prism runs the model.

## Where the shared rules live

One set of conventions covers every package here — versioning, error codes, when
a package stays silent and when it throws, how a pending-request builder behaves.
They are written once in
[`prism-parity`](https://github.com/Particle-Academy/prism-parity), and each
package links there, so the rule you read on one page is the rule every package
follows.

The rule that matters most when reading any of them: **a package that wants
something from core files an issue against core and works around it in the
meantime.** Core's surface follows the providers.

## Relay and Bedrock

The two entries in the upstream **Packages** section of this sidebar
([Relay](https://github.com/prism-php/relay),
[Bedrock](https://github.com/prism-php/bedrock)) come from `prism-php`, the
project this fork descends from. They still work.

`prism-mcp` is the successor to Relay and declares
`replace: prism-php/relay`, so Composer keeps you on one of the two. If you are
already on Relay, [read the MCP page](/packages/mcp) before switching — the
trust model is genuinely different, and it will refuse a server that Relay would
have connected to.
