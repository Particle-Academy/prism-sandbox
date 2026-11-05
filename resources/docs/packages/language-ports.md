# Language ports

Prism has PHP, TypeScript and Python implementations of core and all eight
companion package families, including Harness.

::: tip Native APIs, shared conformance fixtures
Both core ports provide fluent request APIs for Anthropic, Mistral and OpenAI.
Their supported operations and provider mappings are described in the guides.
The separate TypeScript and Python Harness packages provide agent runtimes
with tool execution and durable approvals.
:::

## What exists

| Port | Runtime | Install | Current scope |
| --- | --- | --- | --- |
| [TypeScript](https://github.com/Particle-Academy/prism-ts) | Node 22+ | `npm install @particle-academy/prism` | Anthropic, Mistral and OpenAI adapters; native promises and strict types |
| [Python](https://github.com/Particle-Academy/prism-py) | Python 3.10+ | `pip install prism-ai-core` | Anthropic, Mistral and OpenAI adapters; native modules and zero runtime dependencies |

## Current limits

Core ports expose a subset of the PHP reference's provider integrations and
operation depth. Check [provider interoperability](/docs/advanced/provider-interoperability),
[tool execution](/docs/core-concepts/tools-function-calling),
[core confirmations and Harness approvals](/docs/core-concepts/human-in-the-loop),
[schema types](/docs/core-concepts/structured-output), and
[payload projection](/docs/advanced/payload-projection) before choosing an API.
Image generation does not include the reference's image-edit API, and port
moderation accepts text inputs. The
[conformance ledger](https://github.com/Particle-Academy/prism-parity/blob/main/parity/ledger.json)
records the scope and known gaps of the shared comparisons.

## What parity means here

[`prism-parity`](https://github.com/Particle-Academy/prism-parity) owns a
set of shared conformance fixtures that run through all three languages and compare
their results. They cover request
construction, response parsing, value-object round trips, coded errors and JSON
container identity, alongside a security corpus for each package family.

Conformance results establish agreement for the tested cases. They do not imply
complete provider coverage or identical framework integrations. Each guide
identifies language-specific APIs and unsupported operations.
