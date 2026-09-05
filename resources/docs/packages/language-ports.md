# Language ports

Prism is no longer PHP-only. TypeScript and Python ports exist today, and the
ecosystem is open to more languages rather than fixed to a closed list.

::: warning Focused ports, not full PHP parity
Both ports are version `0.1.0` and cover Anthropic and OpenAI text. That is
deliberately narrower than the PHP reference's eighteen providers.
:::

## What exists

| Port | Runtime | Package name | Current scope |
| --- | --- | --- | --- |
| [TypeScript](https://github.com/Particle-Academy/prism-ts) | Node 20+ | `@particle-academy/prism` | Text builder, Anthropic and OpenAI providers, message value objects; native promises and strict types |
| [Python](https://github.com/Particle-Academy/prism-py) | Python 3.10+ | `prism-ai` | Text builder, Anthropic and OpenAI providers, message value objects; zero runtime dependencies |

Neither port is published to a registry yet. Install from a source checkout;
each repository's README has the steps.

## What parity means here

[`prism-parity`](https://github.com/Particle-Academy/prism-parity) owns a
language-independent conformance corpus: 16 suites and 335 cases that run one
fixture through all three languages and compare the bytes. They cover request
construction, response parsing, value-object round trips, coded errors and JSON
container identity, alongside a security corpus for each package family.

That proves agreement only where a case exists and a port implements the
surface. It does not imply that TypeScript or Python has streaming, structured
output, embeddings, media, the tool execution loop, or the PHP provider estate.
Those absences are recorded deliberately, so a planned gap reads differently
from an accident.

A fourth language would join the same corpus rather than needing a parallel one,
so the three listed here are where Prism is today, not a ceiling.
