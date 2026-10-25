# OpenAI

Set `OPENAI_API_KEY` in your server environment. The OpenAI adapter uses the Responses API for text, structured output and streaming. It also implements embeddings, image generation, moderation, speech generation, transcription, files and batches.

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

## Configuration

Pass configuration as the third argument of `using()`. Anything you leave out falls back to the environment variable, then to the default.

| TypeScript key | Python key | Environment variable | Default | Meaning |
|---|---|---|---|---|
| `apiKey` | `api_key` | `OPENAI_API_KEY` | none | Sent as `Authorization: Bearer …`. |
| `url` | `url` | `OPENAI_URL` | `https://api.openai.com/v1` | Base URL. Must implement the Responses API. |
| `organization` | `organization` | `OPENAI_ORGANIZATION` | none | Sent as the `OpenAI-Organization` header. |
| `project` | `project` | `OPENAI_PROJECT` | none | Sent as the `OpenAI-Project` header. |
| `apiFormat` | `api_format` | `OPENAI_API_FORMAT` (TypeScript only) | `responses` | Only `responses` is implemented. Any other value makes text, structured and stream calls fail with `unsupported_provider_action`. |
| `transport` | `transport` | | built-in | HTTP transport for JSON calls, for tests or custom networking. |
| `streamTransport` | `stream_transport` | | built-in | HTTP transport for streaming calls. |
| `binaryTransport` | | | built-in | TypeScript only: transport for audio, file and batch calls. |
| | `timeout` | | `30` | Python only: request timeout in seconds. |

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o', {
    apiKey: process.env.OPENAI_API_KEY,
    organization: 'org-example',
    project: 'proj_example',
  })
  .withPrompt('Hello!')
  .asText();
console.log(response.text);
```

```python
import os
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o", {
        "api_key": os.environ["OPENAI_API_KEY"],
        "organization": "org-example",
        "project": "proj_example",
        "timeout": 60,
    })
    .with_prompt("Hello!")
    .as_text()
)
print(response.text)
```
:::

## Provider options

Text, structured and streaming requests read these keys from `withProviderOptions()` / `with_provider_options()`. Each is sent to OpenAI under the same name only when set. `false` and `0` are sent; `null` / `None` is not.

| Option | Type | Sent as | Meaning |
|---|---|---|---|
| `reasoning` | object, e.g. `{ effort: 'low', summary: 'auto' }` | `reasoning` | Reasoning settings for reasoning models. Overrides `withReasoning()`. |
| `text_verbosity` | `'low'`, `'medium'` or `'high'` | `text.verbosity` | Answer length. Ignored when empty. Kept alongside the schema on structured requests. |
| `service_tier` | string, e.g. `'auto'`, `'default'`, `'flex'` | `service_tier` | Processing tier. The tier OpenAI used is returned in `meta.serviceTier` / `meta.service_tier`. |
| `store` | boolean | `store` | Whether OpenAI stores the response. |
| `previous_response_id` | string | `previous_response_id` | Continue from a stored response. |
| `truncation` | `'auto'` or `'disabled'` | `truncation` | What OpenAI does when the input exceeds the context window. |
| `parallel_tool_calls` | boolean | `parallel_tool_calls` | Whether the model may request several tool calls at once. |
| `metadata` | object of strings | `metadata` | Your own key-value tags on the response. |

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-5')
  .withPrompt('Draft a two-line product update.')
  .withProviderOptions({
    reasoning: { effort: 'low' },
    text_verbosity: 'low',
    service_tier: 'flex',
    store: false,
    metadata: { feature: 'release-notes' },
  })
  .asText();

console.log(response.text, response.meta.serviceTier);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-5")
    .with_prompt("Draft a two-line product update.")
    .with_provider_options({
        "reasoning": {"effort": "low"},
        "text_verbosity": "low",
        "service_tier": "flex",
        "store": False,
        "metadata": {"feature": "release-notes"},
    })
    .as_text()
)

print(response.text, response.meta.service_tier)
```
:::

### Reasoning

`withReasoning(false)` / `with_reasoning(False)` sends `reasoning: { effort: 'minimal' }`. `withReasoning(true)` sends nothing, so the model's default applies. A `reasoning` provider option always wins over both.

Reasoning summaries come back in `response.additionalContent.reasoningSummaries` (TypeScript) / `response.additional_content["reasoningSummaries"]` (Python), as a list of strings. Reasoning token counts are in `usage.thoughtTokens` / `usage.thought_tokens`.

A response cut off at the output-token limit raises `max_tokens_exceeded`. Reasoning models spend output tokens on hidden reasoning, so raise `withMaxTokens()` if you see this.

## Structured output

The default structured mode is chosen from the model name:

- **Structured** (strict JSON schema, enforced by OpenAI): `gpt-4o`, `gpt-4.1`, `gpt-4.5`, `chatgpt-4o`, `o3-mini`, any `gpt-N` with N of 5 or higher, and the `o` reasoning series (`o3`, `o4-mini`…). Fine-tuned models (`ft:<base>:…`) follow their base model.
- **JSON mode** (valid JSON, schema not enforced): every other name, including custom model names served at your `url`.
- **Refused** with `unsupported_structured_model`: `o1-mini` and `o1-preview` (and their dated versions).

Force a mode with `usingStructuredMode(StructuredMode.Json)` in TypeScript, or `using_structured_mode(StructuredMode.JSON)` in Python (`from prism.enums import StructuredMode`). In strict mode, every property must be listed in the object's required fields and additional properties must stay disallowed (the default); OpenAI rejects other schemas. See [Structured output](/core-concepts/structured-output).

## Tools

Mark a tool as strict with a tool-level provider option. Strict tools make OpenAI follow the parameter schema exactly; the same schema rules as strict structured output apply. The adapter maps tools and tool choice, but does not run tools. See [Tools](/core-concepts/tools-function-calling).

::: language-group
```ts
import { Prism, Tool } from '@particle-academy/prism';

const weather = new Tool()
  .as('weather')
  .for('Look up the weather in a city')
  .withStringParameter('city', 'The city to look up')
  .withProviderOptions({ strict: true });

const request = Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('What is the weather in Chicago?')
  .withTools([weather])
  .withProviderOptions({ parallel_tool_calls: false })
  .toRequest();

console.log(request.tools().length);
```

```python
from prism import Prism, Tool

weather = (
    Tool()
    .as_("weather")
    .for_("Look up the weather in a city")
    .with_string_parameter("city", "The city to look up")
    .with_provider_options({"strict": True})
)

request = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("What is the weather in Chicago?")
    .with_tools([weather])
    .with_provider_options({"parallel_tool_calls": False})
    .to_request()
)

print(len(request.tools))
```
:::

OpenAI's built-in tools (web search, image generation and others) are added with `withProviderTools()` / `with_provider_tools()` and are sent before your own tools. Web-search activity is returned in `additionalContent` under `searchQueries`, `openPageUrls` and `findInPagePatterns`; generated images under `generatedImages`.

## Other capabilities

Each capability reads its own provider options. Details and examples are on the Core Concepts pages.

| Capability | Provider options read | Page |
|---|---|---|
| Embeddings | `dimensions`, `encoding_format`, `user` | [Embeddings](/core-concepts/embeddings) |
| Image generation | `n`, `size`, `quality`, `style`, `response_format`, `background`, `output_format`, `user` | [Image generation](/core-concepts/image-generation) |
| Moderation | none | [Moderation](/core-concepts/moderation) |
| Text to speech | `response_format` (default `mp3`), `speed`, `instructions`; voice defaults to `alloy` | [Audio](/core-concepts/audio) |
| Speech to text | `language`, `prompt`, `response_format`, `temperature` | [Audio](/core-concepts/audio) |
| Files | `purpose` (upload default `assistants`; also filters a list) | [Files](/core-concepts/files) |
| Batches | `completion_window` (default `24h`) | [Batches](/core-concepts/batch) |

Image and document input is covered in [Image input](/input-modalities/images) and [Document input](/input-modalities/documents).

## Limits

- Only the Responses API is implemented. An alternate base URL that offers only Chat Completions will not work, even if it advertises OpenAI compatibility.
- The ports do not run the automatic tool-execution loop. A non-streamed response that ends on tool calls raises `tool_loop_not_supported`; see [Tools](/core-concepts/tools-function-calling).
- Rate-limit headers are read on text and structured responses. See [Rate limits](/advanced/rate-limits).
