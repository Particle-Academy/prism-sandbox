# Embeddings

Embeddings turn text into numeric vectors for retrieval and similarity comparison. Both ports implement embeddings for **OpenAI** and **Mistral**. Anthropic has no embeddings endpoint; asking it for one fails with `unsupported_provider_action`.

## Embed multiple inputs

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const inputs = ['A guide to heat pumps', 'Installing solar panels'];

const response = await Prism.embeddings()
  .using('openai', 'text-embedding-3-small')
  .fromArray(inputs)
  .asEmbeddings();

response.embeddings.forEach((embedding, index) => {
  console.log(inputs[index], embedding.embedding.length);
});
```

```python
from prism import Prism

inputs = ["A guide to heat pumps", "Installing solar panels"]

response = (
    Prism.embeddings()
    .using("openai", "text-embedding-3-small")
    .from_array(inputs)
    .as_embeddings()
)

for text, embedding in zip(inputs, response.embeddings):
    print(text, len(embedding.embedding))
```
:::

`response.embeddings` is in input order: the ports sort the provider's answer by its own index before returning it, so the n-th vector belongs to the n-th input even when the provider replies out of order.

## Adding inputs

Every input method **appends**, so you can combine them and all inputs go out in one request.

| TypeScript | Python | Adds |
|---|---|---|
| `fromInput(text)` | `from_input(text)` | One string. |
| `fromArray(texts)` | `from_array(texts)` | Every string in the list. |
| `fromFile(path)` | `from_file(path)` | The whole file, read as UTF-8, as **one** input. |

`fromFile()` reads the file immediately, on the line that names it. A missing or unreadable file fails there with `unreadable_input_file`, before any request is sent.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.embeddings()
  .using('openai', 'text-embedding-3-small')
  .fromInput('What is a heat pump?')
  .fromFile('docs/heat-pumps.md')
  .asEmbeddings();

console.log(response.embeddings.length); // 2
```

```python
from prism import Prism

response = (
    Prism.embeddings()
    .using("openai", "text-embedding-3-small")
    .from_input("What is a heat pump?")
    .from_file("docs/heat-pumps.md")
    .as_embeddings()
)

print(len(response.embeddings))  # 2
```
:::

## Provider options

OpenAI reads three keys from `withProviderOptions()` / `with_provider_options()`. Keys you do not set are left out of the request.

| Option | Type | Meaning |
|---|---|---|
| `dimensions` | number | Shorten the vector to this many dimensions (`text-embedding-3-*` models). |
| `encoding_format` | string | Leave unset, or `float`. The ports read vectors as number arrays only: with `base64`, every `Embedding` comes back empty. |
| `user` | string | An end-user identifier OpenAI uses for abuse monitoring. |

Mistral sends only the model and the inputs. None of the options above are forwarded to it.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.embeddings()
  .using('openai', 'text-embedding-3-large')
  .fromInput('Installing solar panels')
  .withProviderOptions({ dimensions: 256, user: 'user-1234' })
  .asEmbeddings();

console.log(response.embeddings[0]?.embedding.length); // 256
```

```python
from prism import Prism

response = (
    Prism.embeddings()
    .using("openai", "text-embedding-3-large")
    .from_input("Installing solar panels")
    .with_provider_options({"dimensions": 256, "user": "user-1234"})
    .as_embeddings()
)

print(len(response.embeddings[0].embedding))  # 256
```
:::

## Mistral

Mistral reads `MISTRAL_API_KEY` (and optionally `MISTRAL_URL`) from the environment, or takes `apiKey` / `api_key` in the provider config.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.embeddings()
  .using('mistral', 'mistral-embed')
  .fromArray(['A guide to heat pumps', 'Installing solar panels'])
  .asEmbeddings();

console.log(response.usage.tokens);
```

```python
from prism import Prism

response = (
    Prism.embeddings()
    .using("mistral", "mistral-embed")
    .from_array(["A guide to heat pumps", "Installing solar panels"])
    .as_embeddings()
)

print(response.usage.tokens)
```
:::

## The response

`asEmbeddings()` / `as_embeddings()` returns an `EmbeddingsResponse`.

| Field | Type | Meaning |
|---|---|---|
| `embeddings` | `Embedding[]` (Python: tuple) | One vector per input, in input order. |
| `usage` | `EmbeddingsUsage` | `tokens`: the total the provider reported, or `null` / `None` when it reported nothing. |
| `meta` | `Meta` | `id` and `model` as the provider reported them. |
| `raw` | object or `null` | The provider's decoded response body. |

`Embedding.embedding` is the vector: `number[]` in TypeScript, a tuple of floats in Python. A non-numeric value in the provider's answer is dropped rather than turned into `0`, so a malformed vector shows up as a shorter one.

Use the same model, and the same `dimensions`, when you index and when you search: vectors from different models or sizes are not comparable. Core returns vectors only; storage, indexing and recall belong to your application or [Prism Memory](/packages/memory).

## Errors

Failures are `PrismError` with a stable `code`. See [Error handling](/advanced/error-handling).

| Code | When |
|---|---|
| `no_embedding_input` | No input was added before `asEmbeddings()` / `toRequest()`. Nothing is sent. |
| `unreadable_input_file` | `fromFile()` could not read the path. |
| `unsupported_provider_action` | The provider has no embeddings (Anthropic), or `using()` was not called. |
| `provider_response_error` | The provider answered with an HTTP error or a body that is not a JSON object. The status is on `httpStatus` (TypeScript) / `status` (Python). |

::: language-group
```ts
import { Prism, PrismError } from '@particle-academy/prism';

try {
  await Prism.embeddings().using('openai', 'text-embedding-3-small').asEmbeddings();
} catch (error) {
  if (error instanceof PrismError && error.code === 'no_embedding_input') {
    console.log('Add at least one input.');
  } else {
    throw error;
  }
}
```

```python
from prism import Prism, PrismError

try:
    Prism.embeddings().using("openai", "text-embedding-3-small").as_embeddings()
except PrismError as error:
    if error.code == "no_embedding_input":
        print("Add at least one input.")
    else:
        raise
```
:::
