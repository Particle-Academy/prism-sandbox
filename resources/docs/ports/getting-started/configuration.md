# Configuration

The ports read provider credentials from the server environment. Explicit configuration passed to `using` takes precedence. Keep credentials on the server; TypeScript examples are Node.js code, not browser code.

## Environment configuration

Set the key for the provider you select: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` or `MISTRAL_API_KEY`. OpenAI also supports `OPENAI_URL`, `OPENAI_ORGANIZATION` and `OPENAI_PROJECT`. Load your environment with your application framework or process manager before building requests.

## Explicit provider configuration

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o', { apiKey: process.env.OPENAI_API_KEY })
  .withPrompt('Hello!')
  .asText();
```

```python
import os
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o", {"api_key": os.environ["OPENAI_API_KEY"]})
    .with_prompt("Hello!")
    .as_text()
)
```
:::

## Testing configuration

Provider configuration accepts an injected HTTP transport. Use it to assert the outgoing request and return a recorded response without network access; see [Testing](/core-concepts/testing).

The ports do not read `config/prism.php`, Laravel's `env()` helper or service-container bindings. Configure network deadlines in your transport rather than assuming PHP's Guzzle options apply to Node.js or Python.
