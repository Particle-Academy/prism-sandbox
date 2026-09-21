# OpenAI

Set `OPENAI_API_KEY` in your server environment. Both ports' OpenAI adapters use the Responses API for text and structured output.

## Text generation

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Explain retrieval-augmented generation in two sentences.')
  .asText();
console.log(response.text);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Explain retrieval-augmented generation in two sentences.")
    .as_text()
)
print(response.text)
```
:::

## Available capabilities

The adapter implements text, structured output, streaming, embeddings, images, moderation, speech generation, transcription, files and batches. Select the corresponding Core Concepts page for examples in your language.

## Configuration and limits

Explicit provider configuration uses `apiKey`, `url`, `organization` and `project` in TypeScript, and `api_key`, `url`, `organization` and `project` in Python. Environment configuration is documented in [Configuration](/getting-started/configuration).

The ports do not implement the OpenAI Chat Completions API format. An alternate base URL must implement the Responses API for this adapter to work. Core's automatic tool-execution loop is also unavailable; see [Tools](/core-concepts/tools-function-calling).
