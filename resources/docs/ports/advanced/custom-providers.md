# Custom providers

Register a provider factory under an application-defined key. The provider object must implement the port's provider contract; unsupported capabilities must raise an explicit error.

## Register a factory

This example registers an alias for the existing OpenAI adapter. Use the same factory signature for your own provider implementation.

::: language-group
```ts
import { OpenAI, Prism, registerProvider } from '@particle-academy/prism';

registerProvider('application-openai', (config) => new OpenAI(config));

const request = Prism.text()
  .using('application-openai', 'gpt-4o')
  .withPrompt('Hello!')
  .toRequest();
console.log(request);
```

```python
from prism import OpenAI, Prism, register_provider

register_provider("application-openai", lambda **config: OpenAI(**config))

request = (
    Prism.text()
    .using("application-openai", "gpt-4o")
    .with_prompt("Hello!")
    .to_request()
)
print(request)
```
:::

## Implement a new protocol

Extend the provider base class and implement the capabilities you expose, including serialization, response parsing, streaming and errors. Registration is process-wide; do it during application setup with trusted keys, not from a user-controlled provider name.

An endpoint advertising OpenAI compatibility may only implement Chat Completions. The ports' OpenAI adapter uses the Responses API, so changing its URL alone is not sufficient proof of compatibility. Use injected-transport tests and the shared conformance contract to verify the adapter.
