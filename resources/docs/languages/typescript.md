# Building with TypeScript

`@particle-academy/prism` is the native TypeScript port of Prism, written in
TypeScript's own idiom: promises, discriminated unions and strict null
checking.

For the shared contract and parity limits, read
[Language ports](/packages/language-ports). This page covers only the
TypeScript implementation.

## Install

```bash
npm install @particle-academy/prism
```

Node 20 or newer is required.

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

Failures use `PrismError` with a stable `code` — branch on the code, and read
the English `message` for humans. Consumers can narrow codes such as `unsupported_provider_action` and
`tool_loop_not_supported` without matching prose.

## Exact current scope

The port implements every capability the PHP reference has: text, structured
output, streaming, embeddings, images, moderation, text to speech, speech to
text, files, batches and fill-in-the-middle. It ships three providers:
Anthropic, Mistral and OpenAI.

Not every capability reaches every provider. Images, moderation, text to speech,
files and batches work with OpenAI only, and fill-in-the-middle works with
Mistral only.

The multi-step tool execution loop and the reference's other fifteen providers
stay with PHP for now. Anything outside this surface fails with a coded error,
so your code learns exactly where it stands.

