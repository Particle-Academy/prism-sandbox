# Configuration

The ports read provider credentials from the server environment. Configuration passed to `using` takes precedence over the environment, field by field. Keep credentials on the server; TypeScript examples are Node.js code, not browser code.

## Environment variables

Each provider reads its variables when the request builder calls `using`. Load your environment with your application framework or process manager before that point.

| Variable | Provider | Default | Meaning |
|---|---|---|---|
| `OPENAI_API_KEY` | OpenAI | none | Sent as a bearer token. |
| `OPENAI_URL` | OpenAI | `https://api.openai.com/v1` | Base URL. |
| `OPENAI_ORGANIZATION` | OpenAI | none | Sent as `OpenAI-Organization`. |
| `OPENAI_PROJECT` | OpenAI | none | Sent as `OpenAI-Project`. |
| `OPENAI_API_FORMAT` | OpenAI, **TypeScript only** | `responses` | The only format implemented is `responses`; any other value makes every call fail with `unsupported_provider_action`. Python has no variable for this; it accepts only the `api_format` config key. |
| `ANTHROPIC_API_KEY` | Anthropic | none | Sent as `x-api-key`. |
| `ANTHROPIC_URL` | Anthropic | `https://api.anthropic.com/v1` | Base URL. |
| `ANTHROPIC_API_VERSION` | Anthropic | `2023-06-01` | Sent as `anthropic-version`. |
| `ANTHROPIC_BETA_FEATURES` | Anthropic | none | Sent as `anthropic-beta` when set. |
| `MISTRAL_API_KEY` | Mistral | none | Sent as a bearer token. |
| `MISTRAL_URL` | Mistral | `https://api.mistral.ai/v1` | Base URL. |

::: warning Anthropic beta header
The ports read `ANTHROPIC_BETA_FEATURES`. The PHP package's `ANTHROPIC_BETA` variable is not read by either port.
:::

## Explicit provider configuration

Pass a configuration object as the third argument to `using`. TypeScript uses camelCase keys; Python uses snake_case keys.

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

The accepted keys differ by provider and by language:

| Provider | TypeScript keys | Python keys |
|---|---|---|
| OpenAI | `apiKey`, `url`, `organization`, `project`, `apiFormat`, `transport`, `streamTransport`, `binaryTransport` | `api_key`, `url`, `organization`, `project`, `api_format`, `transport`, `stream_transport`, `timeout` |
| Anthropic | `apiKey`, `url`, `apiVersion`, `betaFeatures`, `transport`, `streamTransport` | `api_key`, `url`, `api_version`, `beta_features`, `transport`, `stream_transport`, `timeout` |
| Mistral | `apiKey`, `url`, `transport`, `streamTransport`, `binaryTransport` | `api_key`, `url`, `transport`, `stream_transport`, `timeout` |

In Python an unknown key raises `TypeError`, because the keys are passed to the provider constructor as keyword arguments. TypeScript ignores keys a provider does not read.

An unknown provider name fails in both languages with `unsupported_provider_action`.

## Timeouts and client options

The two ports handle network deadlines differently.

- **TypeScript** has no default timeout. `withClientOptions()` takes an object that the default transports spread into the `fetch` options, so pass an `AbortSignal` there. A custom transport receives the same object as `request.clientOptions`.
- **Python** has a 30-second default per request. Change it with the `timeout` configuration key, in seconds. Python's `with_client_options()` stores the options on the request, but no provider passes them to the transport, so they have no effect on text, structured or streaming calls.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('anthropic', 'claude-sonnet-4-5')
  .withClientOptions({ signal: AbortSignal.timeout(30_000) })
  .withPrompt('Hello!')
  .asText();
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-5", {"timeout": 60.0})
    .with_prompt("Hello!")
    .as_text()
)
```
:::

## Testing configuration

The `transport` key replaces the HTTP layer for ordinary calls. Use it to assert the outgoing request and return a recorded response without network access. Streaming calls use a separate `streamTransport` / `stream_transport`, and TypeScript's OpenAI and Mistral providers use `binaryTransport` for audio. See [Testing](/core-concepts/testing).

The ports do not read `config/prism.php`, Laravel's `env()` helper or service-container bindings.
