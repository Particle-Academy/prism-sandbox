# Language ports

Prism has PHP, TypeScript and Python implementations of core and all eight
companion package families, including Harness.

::: tip Every capability, three providers
Both ports implement every capability the PHP reference has, with three of its
eighteen providers: Anthropic, Mistral and OpenAI. The multi-step tool execution
loop in core is PHP-specific; unsupported tool-loop responses in core produce
a coded error. The separate TypeScript and Python Harness packages provide
agent runtimes with tool execution and durable approvals.
:::

## What exists

| Port | Runtime | Install | Current scope |
| --- | --- | --- | --- |
| [TypeScript](https://github.com/Particle-Academy/prism-ts) | Node 20+ | `npm install @particle-academy/prism` | Every capability; Anthropic, Mistral and OpenAI; native promises and strict types |
| [Python](https://github.com/Particle-Academy/prism-py) | Python 3.10+ | `pip install prism-ai-core` | Every capability; Anthropic, Mistral and OpenAI; zero runtime dependencies |

## What parity means here

[`prism-parity`](https://github.com/Particle-Academy/prism-parity) owns a
set of shared conformance fixtures that run through all three languages and compare
their results. They cover request
construction, response parsing, value-object round trips, coded errors and JSON
container identity, alongside a security corpus for each package family.

Conformance results establish agreement for the tested cases. They do not imply
complete provider coverage or identical framework integrations. Each guide
identifies language-specific APIs and unsupported operations.
