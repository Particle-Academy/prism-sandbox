# Anthropic

Set `ANTHROPIC_API_KEY` in your server environment. The ports implement Anthropic text, structured output and streaming.

## Text generation

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('anthropic', 'claude-sonnet-4-20250514')
  .withMaxTokens(512)
  .withSystemPrompt('Answer concisely.')
  .withPrompt('How does a heat pump work?')
  .asText();
console.log(response.text);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-20250514")
    .with_max_tokens(512)
    .with_system_prompt("Answer concisely.")
    .with_prompt("How does a heat pump work?")
    .as_text()
)
print(response.text)
```
:::

## Other capabilities

Use [Structured output](/core-concepts/structured-output) or [Streaming](/core-concepts/streaming-output) with `anthropic` and a compatible model. Image and document content must follow Anthropic's supported media formats.

Anthropic file and batch operations are not implemented in the TypeScript or
Python adapters. Select PHP for those integration examples.
