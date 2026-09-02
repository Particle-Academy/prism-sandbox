# Building with TypeScript

`prism-ts` is the native TypeScript port of Prism's focused text slice. It uses
promises, discriminated unions, and strict null checking rather than copying PHP
syntax into TypeScript.

For the shared contract and parity limits, read
[Language ports](/packages/language-ports). This page covers only the
TypeScript implementation.

::: warning Source build — not an npm release
The package manifest is version `0.1.0`, but the parity manifest marks
`@particle-academy/prism` unpublished. `npm install @particle-academy/prism` is
therefore not presented here as an available registry install.
:::

## Try it from source

```bash
git clone https://github.com/Particle-Academy/prism-ts.git
cd prism-ts
npm install
npm run build
```

Node 20 or newer is required. After building, another local project can install
the checkout by filesystem path; publishing and registry installation remain
unavailable until the manifest's publication state changes.

## First request

```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Explain why the sky is blue in one sentence.')
  .asText();

console.log(response.text);
```

Configuration is explicit first and environment second. `asText()` returns a
`Promise<TextResponse>`, and the HTTP transport is injectable, so tests can run
without a socket.

Failures use `PrismError` with a stable `code`; its English `message` is not the
contract. Consumers can narrow codes such as `unsupported_provider_action` and
`tool_loop_not_supported` without matching prose.

## Exact current scope

The source and parity manifest contain Anthropic and OpenAI text providers,
message value objects, provider-tool mapping, serialization, and rehydration.
The port does **not** currently ship streaming, structured output, embeddings,
images, audio, moderation, batches, files, or the multi-step tool execution and
approval loop. Unsupported work fails with a coded error instead of quietly
degrading.

