# Mistral

Set `MISTRAL_API_KEY` in your server environment. The Mistral adapter implements text, structured output, streaming, embeddings, transcription and fill-in-the-middle (FIM). Images, moderation, speech generation, files and batches are not available through Mistral in the ports; calling them raises `unsupported_provider_action`.

## Text generation

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('mistral', 'mistral-small-latest')
  .withPrompt('Explain how a heat pump works.')
  .asText();
console.log(response.text);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("mistral", "mistral-small-latest")
    .with_prompt("Explain how a heat pump works.")
    .as_text()
)
print(response.text)
```
:::

## Configuration

Pass configuration as the third argument of `using()`. Anything you leave out falls back to the environment variable, then to the default.

| TypeScript key | Python key | Environment variable | Default | Meaning |
|---|---|---|---|---|
| `apiKey` | `api_key` | `MISTRAL_API_KEY` | none | Sent as `Authorization: Bearer …`. |
| `url` | `url` | `MISTRAL_URL` | `https://api.mistral.ai/v1` | Base URL. |
| `transport` | `transport` | | built-in | HTTP transport for JSON calls, for tests or custom networking. |
| `streamTransport` | `stream_transport` | | built-in | HTTP transport for streaming calls. |
| `binaryTransport` | | | built-in | TypeScript only: transport for transcription uploads. |
| | `timeout` | | `30` | Python only: request timeout in seconds. |

## Provider options

Text, structured and streaming requests read one provider option:

| Option | Type | Sent as | Meaning |
|---|---|---|---|
| `reasoning_effort` | string, e.g. `'high'` or `'none'` | `reasoning_effort` | Reasoning effort, on models that support it. |

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('mistral', 'magistral-medium-latest')
  .withPrompt('Is 1013 a prime number? Explain briefly.')
  .withProviderOptions({ reasoning_effort: 'high' })
  .asText();
console.log(response.text, response.usage.thoughtTokens);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("mistral", "magistral-medium-latest")
    .with_prompt("Is 1013 a prime number? Explain briefly.")
    .with_provider_options({"reasoning_effort": "high"})
    .as_text()
)
print(response.text, response.usage.thought_tokens)
```
:::

Tool choice is sent only when the request has tools.

## Structured output

Mistral enforces the schema: the adapter sends it as a strict `json_schema` response format. Mistral does not accept a schema and tools in the same request, so **tools are dropped from structured requests**.

::: language-group
```ts
import { ObjectSchema, Prism, StringSchema } from '@particle-academy/prism';

const schema = new ObjectSchema(
  'city',
  'A city and its country',
  [new StringSchema('name', 'The city'), new StringSchema('country', 'The country')],
  { requiredFields: ['name', 'country'] },
);

const response = await Prism.structured()
  .using('mistral', 'mistral-small-latest')
  .withSchema(schema)
  .withPrompt('Name the capital of Portugal.')
  .asStructured();

console.log(response.structured);
```

```python
from prism import Prism
from prism.schema import ObjectSchema, StringSchema

schema = ObjectSchema(
    "city",
    "A city and its country",
    (StringSchema("name", "The city"), StringSchema("country", "The country")),
    ("name", "country"),
)

response = (
    Prism.structured()
    .using("mistral", "mistral-small-latest")
    .with_schema(schema)
    .with_prompt("Name the capital of Portugal.")
    .as_structured()
)

print(response.structured)
```
:::

## Embeddings

Every input is sent in one request, and the vectors come back in input order. Mistral reports total tokens only. No provider options are read.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.embeddings()
  .using('mistral', 'mistral-embed')
  .fromArray(['The greenhouse is warm.', 'The cellar is cold.'])
  .asEmbeddings();

console.log(response.embeddings.length, response.usage.tokens);
```

```python
from prism import Prism

response = (
    Prism.embeddings()
    .using("mistral", "mistral-embed")
    .from_array(["The greenhouse is warm.", "The cellar is cold."])
    .as_embeddings()
)

print(len(response.embeddings), response.usage.tokens)
```
:::

## Transcription

Mistral transcription takes the same provider options as OpenAI's: `language`, `prompt`, `response_format` and `temperature`. The audio must carry bytes (a local file, base64 or raw content). A URL-only `Audio` raises `no_audio_content`.

::: language-group
```ts
import { Audio, Prism } from '@particle-academy/prism';

const response = await Prism.audio()
  .using('mistral', 'voxtral-mini-latest')
  .withInput(Audio.fromLocalPath('meeting.mp3'))
  .withProviderOptions({ language: 'en' })
  .asText();

console.log(response.text);
```

```python
from prism import Audio, Prism

response = (
    Prism.audio()
    .using("mistral", "voxtral-mini-latest")
    .with_input(Audio.from_local_path("meeting.mp3"))
    .with_provider_options({"language": "en"})
    .as_text()
)

print(response.text)
```
:::

## Fill-in-the-middle

Supply the text before the gap with `withPrompt()` and, optionally, the text after it with `withSuffix()`. Without a suffix, the model completes to the end. Mistral is the only built-in provider with this capability.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.fim()
  .using('mistral', 'codestral-latest')
  .withPrompt('function add(a, b) {')
  .withSuffix('}')
  .withMaxTokens(64)
  .withTemperature(0)
  .withTopP(1)
  .withStop(['\n\n'])
  .asText();

console.log(response.text, response.finishReason, response.usage.completionTokens);
```

```python
from prism import Prism

response = (
    Prism.fim()
    .using("mistral", "codestral-latest")
    .with_prompt("function add(a, b) {")
    .with_suffix("}")
    .with_max_tokens(64)
    .with_temperature(0)
    .with_top_p(1)
    .with_stop(["\n\n"])
    .as_text()
)

print(response.text, response.finish_reason, response.usage.completion_tokens)
```
:::

| TypeScript | Python | Meaning |
|---|---|---|
| `withPrompt(text)` | `with_prompt(text)` | The text before the gap. |
| `withSuffix(text \| null)` | `with_suffix(text)` | The text after the gap. |
| `withMaxTokens(n)` | `with_max_tokens(n)` | Output token limit. |
| `withTemperature(t)` | `with_temperature(t)` | Sampling temperature. `0` is sent. |
| `withTopP(p)` | `with_top_p(p)` | Nucleus sampling. |
| `withStop(stop)` | `with_stop(stop)` | One string or a list; generation ends at any of them. |
| `withClientOptions({...})` | `with_client_options({...})` | Transport options. |

The result is a `FimResponse`:

| TypeScript | Python | Contents |
|---|---|---|
| `text` | `text` | The generated middle. |
| `finishReason` | `finish_reason` | `Stop`, `Length` when the token limit was reached, otherwise `Unknown`. |
| `usage` | `usage` | Prompt and completion token counts. |
| `meta` | `meta` | Response ID and model. In TypeScript, `meta.rateLimits` is filled from Mistral's headers. |
| `raw` | `raw` | The decoded response body. |

## Images and documents

Mistral accepts images as a URL or as bytes with a MIME type, but not as file IDs. Documents can only be sent as URLs, which Mistral fetches itself. See [Image input](/input-modalities/images) and [Document input](/input-modalities/documents).

Use a model suited to each capability. Text, embeddings, transcription and FIM models are not interchangeable.
