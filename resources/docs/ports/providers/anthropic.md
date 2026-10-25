# Anthropic

Set `ANTHROPIC_API_KEY` in your server environment. The Anthropic adapter uses the Messages API and implements text, structured output and streaming. Embeddings, images, audio, moderation, files and batches are not available through Anthropic in the ports; calling them raises `unsupported_provider_action`.

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

Anthropic requires a maximum output length. When you don't call `withMaxTokens()`, the adapter sends `max_tokens: 4096`. System prompts are sent in Anthropic's top-level `system` field.

## Configuration

Pass configuration as the third argument of `using()`. Anything you leave out falls back to the environment variable, then to the default.

| TypeScript key | Python key | Environment variable | Default | Meaning |
|---|---|---|---|---|
| `apiKey` | `api_key` | `ANTHROPIC_API_KEY` | none | Sent as the `x-api-key` header. |
| `url` | `url` | `ANTHROPIC_URL` | `https://api.anthropic.com/v1` | Base URL. `/messages` is appended. |
| `apiVersion` | `api_version` | `ANTHROPIC_API_VERSION` | `2023-06-01` | Sent as the `anthropic-version` header. |
| `betaFeatures` | `beta_features` | `ANTHROPIC_BETA_FEATURES` | none | Sent as the `anthropic-beta` header, e.g. `files-api-2025-04-14`. Separate several with commas. |
| `transport` | `transport` | | built-in | HTTP transport for JSON calls, for tests or custom networking. |
| `streamTransport` | `stream_transport` | | built-in | HTTP transport for streaming calls. |
| | `timeout` | | `30` | Python only: request timeout in seconds. |

The beta variable is `ANTHROPIC_BETA_FEATURES`, not the PHP package's `ANTHROPIC_BETA`.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('anthropic', 'claude-sonnet-4-20250514', {
    apiKey: process.env.ANTHROPIC_API_KEY,
    betaFeatures: 'files-api-2025-04-14',
  })
  .withMaxTokens(512)
  .withPrompt('Hello!')
  .asText();
console.log(response.text);
```

```python
import os
from prism import Prism

response = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-20250514", {
        "api_key": os.environ["ANTHROPIC_API_KEY"],
        "beta_features": "files-api-2025-04-14",
        "timeout": 120,
    })
    .with_max_tokens(512)
    .with_prompt("Hello!")
    .as_text()
)
print(response.text)
```
:::

## Provider options

Text, structured and streaming requests read these keys from `withProviderOptions()` / `with_provider_options()`. A key that is not set is not sent.

| Option | Type | Sent as | Meaning |
|---|---|---|---|
| `thinking` | object or `false` | `thinking` | Extended thinking. See below. |
| `effort` | string, e.g. `'low'`, `'medium'`, `'high'` | `output_config.effort` | How much effort the model spends, on models that support it. |
| `metadata` | object, e.g. `{ user_id: '…' }` | `metadata` | Request metadata. Use an opaque ID, never personal data. |
| `stop_sequences` | list of strings | `stop_sequences` | Strings that end generation. The one that matched is returned in `additionalContent.stopSequence`. |

### Thinking

| You pass | Sent to Anthropic |
|---|---|
| `{ enabled: true, budgetTokens: 2048 }` | `{ type: 'enabled', budget_tokens: 2048 }` |
| `{ enabled: true }`, or a non-integer budget | `{ type: 'enabled', budget_tokens: 1024 }` |
| `{ type: 'adaptive' }` | sent as given |
| `{ type: 'enabled', budget_tokens: 4000 }` (Anthropic's own spelling) | sent as given |
| `false`, `{}`, or `{ enabled: false }` | nothing |

`withReasoning(false)` / `with_reasoning(False)` always turns thinking off, even when a `thinking` option is set. `withReasoning(true)` sends nothing by itself: thinking needs a budget, so set the `thinking` option. The thinking budget counts towards `max_tokens`, so raise `withMaxTokens()` above it.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('anthropic', 'claude-sonnet-4-20250514')
  .withMaxTokens(8000)
  .withPrompt('Plan a three-day trip to Lisbon.')
  .withProviderOptions({
    thinking: { enabled: true, budgetTokens: 4000 },
    stop_sequences: ['END OF PLAN'],
    metadata: { user_id: 'user-7f3a' },
  })
  .asText();

console.log(response.text);
console.log(response.additionalContent.thinking);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-20250514")
    .with_max_tokens(8000)
    .with_prompt("Plan a three-day trip to Lisbon.")
    .with_provider_options({
        "thinking": {"enabled": True, "budgetTokens": 4000},
        "stop_sequences": ["END OF PLAN"],
        "metadata": {"user_id": "user-7f3a"},
    })
    .as_text()
)

print(response.text)
print(response.additional_content.get("thinking"))
```
:::

The response's `additionalContent` / `additional_content` carries:

| Key | Contents |
|---|---|
| `thinking` | Text of the first thinking block. |
| `thinking_signature` | Its signature. |
| `thinking_blocks` | Every thinking and redacted-thinking block, for replaying the conversation. |
| `content_blocks` | All content blocks as Anthropic returned them. |
| `stop_reason` | Anthropic's raw stop reason. |
| `stopSequence` | The stop sequence that matched, if any. |

Thinking token counts are in `usage.thoughtTokens` / `usage.thought_tokens`.

## Structured output

Anthropic has no schema-enforcing mode. For structured requests the adapter appends a user message asking for JSON that matches your schema, then parses the reply. The model can still answer with something else: then `structured` is `null` / `None` and the raw reply is in `text`. Validate the result before you rely on it. See [Structured output](/core-concepts/structured-output).

## Images and documents

| Input | Forms Anthropic accepts |
|---|---|
| Images | URL, file ID, or bytes with a MIME type (`image/jpeg`, `image/png`, `image/gif`, `image/webp`) |
| Documents | URL, file ID, pre-split chunks (`Document.fromChunks`), text, or bytes with a MIME type such as `application/pdf` |

Text documents (any `text/*` type, including `Document.fromText`) are sent as plain text, not base64, and must be valid UTF-8. A file ID refers to a file uploaded through Anthropic's Files API, which needs the `files-api-2025-04-14` beta header (`betaFeatures`). The ports cannot upload files to Anthropic, so upload with another client. Anthropic's limits on image size, count and PDF pages apply. See [Image input](/input-modalities/images) and [Document input](/input-modalities/documents).

## Limits

- File and batch operations are not implemented for Anthropic in the ports.
- The ports do not run the automatic tool-execution loop. A non-streamed response that ends on tool calls raises `tool_loop_not_supported`; see [Tools](/core-concepts/tools-function-calling).
- Rate-limit headers (`requests`, `tokens`, `input-tokens`, `output-tokens`) are read on text and structured responses. See [Rate limits](/advanced/rate-limits).
