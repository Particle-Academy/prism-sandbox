# Files

The file builder uploads content and works with provider-side file IDs. Both ports currently implement these operations with OpenAI.

## Upload and inspect a file

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const files = Prism.files().using('openai')
  .withProviderOptions({ purpose: 'user_data' });
const uploaded = await files.upload(
  new TextEncoder().encode('Notes for the greenhouse project.'),
  'notes.txt',
  'text/plain',
);

console.log(uploaded);
console.log(await files.getMetadata(uploaded.id));
```

```python
from prism import Prism

files = (
    Prism.files().using("openai")
    .with_provider_options({"purpose": "user_data"})
)
uploaded = files.upload(
    b"Notes for the greenhouse project.",
    "notes.txt",
    "text/plain",
)

print(uploaded)
print(files.get_metadata(uploaded.id))
```
:::

## Lifecycle

The builder also exposes list, download and delete operations. Store the returned ID in your application and apply your own ownership checks before allowing anyone to access it. A file ID is scoped to its provider/account; it is not a portable identifier across providers.

Set a purpose appropriate to the provider operation. Batch input files use `batch`; document inputs have their own model and file-format constraints. See [Documents](/input-modalities/documents) and [Batch processing](/core-concepts/batch).
