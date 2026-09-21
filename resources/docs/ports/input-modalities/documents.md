# Document input

Attach a document to a text request. Provider support for URLs, inline bytes and uploaded file IDs differs, so use a representation supported by your selected provider/model.

## Send a PDF

::: language-group
```ts
import { Document, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Summarize this report.', [
    Document.fromLocalPath('report.pdf'),
  ])
  .asText();

console.log(response.text);
```

```python
from prism import Document, Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Summarize this report.", [
        Document.from_local_path("report.pdf"),
    ])
    .as_text()
)

print(response.text)
```
:::

The file path is local to your server. Apply file-size, ownership and content checks before sending a user-supplied document. For provider-side uploads and IDs, see [Files](/core-concepts/files).
