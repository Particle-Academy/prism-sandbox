# Language ports

Prism speaks three languages. TypeScript and Python ports ship today, and the
ecosystem is built to take more.

::: tip Every capability, three providers
Both ports implement every capability the PHP reference has, with three of its
eighteen providers: Anthropic, Mistral and OpenAI. The multi-step tool execution
loop stays with the PHP reference, and a response that stops on tool calls says
so with a coded error.
:::

## What exists

| Port | Runtime | Install | Current scope |
| --- | --- | --- | --- |
| [TypeScript](https://github.com/Particle-Academy/prism-ts) | Node 20+ | `npm install @particle-academy/prism` | Every capability; Anthropic, Mistral and OpenAI; native promises and strict types |
| [Python](https://github.com/Particle-Academy/prism-py) | Python 3.10+ | `pip install prism-ai-core` | Every capability; Anthropic, Mistral and OpenAI; zero runtime dependencies |

## What parity means here

[`prism-parity`](https://github.com/Particle-Academy/prism-parity) owns a
language-independent conformance corpus: 20 suites and 393 cases that run one
fixture through all three languages and compare the bytes. They cover request
construction, response parsing, value-object round trips, coded errors and JSON
container identity, alongside a security corpus for each package family.

That proves agreement wherever a case exists and a port implements the surface.
The tool execution loop and the wider PHP provider estate stay with the
reference for now, and every such gap is recorded as a decision with a date on
it.

A fourth language joins the same corpus, so the three here are where Prism is
today and the list is open.
