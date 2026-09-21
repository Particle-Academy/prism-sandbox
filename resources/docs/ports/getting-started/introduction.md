# Introduction

Prism provides a fluent API for calling language models from PHP, TypeScript and Python. Core handles provider requests and responses; the [Harness](/packages/harness) adds durable sessions, tools and agent workflows.

## First request

Set `OPENAI_API_KEY` in your server environment, then send a prompt. TypeScript returns a promise; Python returns the response synchronously.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withSystemPrompt('Explain things clearly and briefly.')
  .withPrompt('Explain quantum computing to a five-year-old.')
  .asText();

console.log(response.text);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_system_prompt("Explain things clearly and briefly.")
    .with_prompt("Explain quantum computing to a five-year-old.")
    .as_text()
)

print(response.text)
```
:::

## Capabilities and providers

The ports expose text, structured output, streaming, embeddings, images, moderation, audio, files, batches and fill-in-the-middle. The built-in providers are OpenAI, Anthropic and Mistral. Provider coverage varies by capability; [Language ports](/packages/language-ports) describes the limits. PHP-specific providers and Laravel integrations are identified separately.

Continue with [Installation](/getting-started/installation), [Configuration](/getting-started/configuration) and [Text generation](/core-concepts/text-generation).
