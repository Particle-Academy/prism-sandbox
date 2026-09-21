# Provider interoperability

The fluent request API is shared, but provider capabilities, model names and message formats still differ. Both ports ship OpenAI, Anthropic and Mistral adapters.

## Switch a text request's provider

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const requests = [
  Prism.text().using('openai', 'gpt-4o').withPrompt('Hello!').toRequest(),
  Prism.text().using('anthropic', 'claude-sonnet-4-20250514').withPrompt('Hello!').toRequest(),
  Prism.text().using('mistral', 'mistral-small-latest').withPrompt('Hello!').toRequest(),
];
console.log(requests);
```

```python
from prism import Prism

requests = [
    Prism.text().using("openai", "gpt-4o").with_prompt("Hello!").to_request(),
    Prism.text().using("anthropic", "claude-sonnet-4-20250514").with_prompt("Hello!").to_request(),
    Prism.text().using("mistral", "mistral-small-latest").with_prompt("Hello!").to_request(),
]
print(requests)
```
:::

These examples only build requests. Supply credentials for each provider and execute the appropriate terminal method when sending them.

## Capability boundaries

Text, structured output and streams work across all three adapters. Embeddings and transcription work with OpenAI and Mistral. Images, moderation, speech generation, files and batches currently use OpenAI. Fill-in-the-middle uses Mistral.

Media IDs, options and tool behavior are provider-specific. See [Language ports](/packages/language-ports) for the parity boundary and individual provider pages for examples.
