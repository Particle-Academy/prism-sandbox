# Mistral

Set `MISTRAL_API_KEY` in your server environment. The ports implement text, structured output, streaming, embeddings, transcription and fill-in-the-middle with Mistral.

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

## Fill-in-the-middle

Supply the text before and after the missing region. Mistral is the built-in provider for this capability in all three languages.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.fim()
  .using('mistral', 'codestral-latest')
  .withPrompt('function add(a, b) {')
  .withSuffix('}')
  .withMaxTokens(64)
  .asText();
console.log(response.text);
```

```python
from prism import Prism

response = (
    Prism.fim()
    .using("mistral", "codestral-latest")
    .with_prompt("function add(a, b) {")
    .with_suffix("}")
    .with_max_tokens(64)
    .as_text()
)
print(response.text)
```
:::

Use a model appropriate to the capability. Text, embeddings, transcription and FIM model names are not interchangeable.
