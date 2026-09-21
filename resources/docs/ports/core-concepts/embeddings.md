# Embeddings

Embeddings turn text into numeric vectors for retrieval and similarity comparison. OpenAI and Mistral expose this capability in both ports.

## Embed multiple inputs

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.embeddings()
  .using('openai', 'text-embedding-3-small')
  .fromArray(['A guide to heat pumps', 'Installing solar panels'])
  .asEmbeddings();

console.log(response.embeddings);
```

```python
from prism import Prism

response = (
    Prism.embeddings()
    .using("openai", "text-embedding-3-small")
    .from_array(["A guide to heat pumps", "Installing solar panels"])
    .as_embeddings()
)

print(response.embeddings)
```
:::

Use `fromInput()` / `from_input()` for a single string. Keep the embedding model and vector dimensions consistent between indexing and searching. Core returns vectors; storage, indexing and recall belong to your application or [Prism Memory](/packages/memory).
