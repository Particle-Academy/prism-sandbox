# Language ports

Prism is no longer PHP-only. TypeScript and Python ports exist today, and the
ecosystem is open to more languages rather than fixed to a closed list.

::: warning Every capability, three providers
Both ports implement every capability the PHP reference has, with three of its
eighteen providers: Anthropic, Mistral and OpenAI. Neither runs the multi-step
tool execution loop.
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

That proves agreement only where a case exists and a port implements the
surface. It does not imply that TypeScript or Python has the tool execution
loop or the PHP provider estate.
Those absences are recorded deliberately, so a planned gap reads differently
from an accident.

A fourth language would join the same corpus rather than needing a parallel one,
so the three listed here are where Prism is today, not a ceiling.
