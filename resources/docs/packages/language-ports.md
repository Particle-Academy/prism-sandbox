# Language ports

Prism is no longer PHP-only. TypeScript and Python ports exist today, and the
parity manifest discovers language implementations rather than fixing the
ecosystem to a closed list.

::: warning Focused ports, not full PHP parity
Both ports are version `0.1.0` and are marked **unpublished** in the current
`prism-parity` manifest. Their shared implemented slice is Anthropic and OpenAI
text, not the PHP reference's eighteen-provider capability set.
:::

## What exists

| Port | Runtime | Package name | Current scope |
| --- | --- | --- | --- |
| [TypeScript](https://github.com/Particle-Academy/prism-ts) | Node 20+ | `@particle-academy/prism` | Text builder, Anthropic and OpenAI providers, message value objects; native promises and strict types |
| [Python](https://github.com/Particle-Academy/prism-py) | Python 3.10+ | `prism-ai` | Text builder, Anthropic and OpenAI providers, message value objects; zero runtime dependencies |

The repositories document install commands for their eventual registry
artifacts, but the parity manifest is authoritative about publication state and
currently marks both false. Use source checkouts for development until that
state changes.

## What parity means here

[`prism-parity`](https://github.com/Particle-Academy/prism-parity) owns a
language-independent conformance corpus. Corpus `0.1.0` currently contains five
suites and 49 cases covering the shared text slice: OpenAI request construction,
response parsing, value-object round trips, coded errors, and JSON container
identity.

That proves agreement only where a case exists and a port implements the
surface. It does not imply that TypeScript or Python has streaming, structured
output, embeddings, media, the tool execution loop, or the PHP provider estate.
The manifest records those absences explicitly so a planned gap can be told
apart from unnoticed drift.

The manifest and parity checker discover implementations by language. Adding a
future port extends the same registry, existence checks, loaders, and
cross-runner contract instead of requiring this page's three-language list to
become an architectural limit.
