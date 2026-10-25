# Files

The file builder stores content with a provider and works with the file IDs it returns. Both ports implement file operations for **OpenAI** only. Anthropic and Mistral fail with `unsupported_provider_action`.

`Prism.files().using(provider)` takes a provider and an optional provider config. It takes **no model**: these are storage operations, not generations.

## Operations

| TypeScript | Python | Returns |
|---|---|---|
| `upload(content: Uint8Array, filename, mimeType?)` | `upload(content: bytes, filename, mime_type=None)` | `FileData` |
| `list(limit?, afterId?, beforeId?)` | `list(limit=None, after_id=None, before_id=None)` | `FileListResult` |
| `getMetadata(fileId)` | `get_metadata(file_id)` | `FileData` |
| `download(fileId)` | `download(file_id)` | `Uint8Array` / `bytes` |
| `delete(fileId)` | `delete(file_id)` | `DeleteFileResult` |

OpenAI reads one provider option, `purpose`. On `upload` it sets the file's purpose (`assistants` when unset; use `user_data` for files you attach to prompts and `batch` for batch input). On `list` it filters by purpose. OpenAI pages forward only, so `beforeId` / `before_id` is not sent.

## Upload and inspect a file

::: language-group
```ts
import { readFileSync } from 'node:fs';
import { Prism } from '@particle-academy/prism';

const files = Prism.files()
  .using('openai')
  .withProviderOptions({ purpose: 'user_data' });

const uploaded = await files.upload(
  readFileSync('storage/reports/greenhouse.pdf'),
  'greenhouse.pdf',
  'application/pdf',
);

const metadata = await files.getMetadata(uploaded.id);
console.log(metadata.filename, metadata.sizeBytes, metadata.createdAt);
```

```python
from pathlib import Path
from prism import Prism

files = (
    Prism.files()
    .using("openai")
    .with_provider_options({"purpose": "user_data"})
)

uploaded = files.upload(
    Path("storage/reports/greenhouse.pdf").read_bytes(),
    "greenhouse.pdf",
    "application/pdf",
)

metadata = files.get_metadata(uploaded.id)
print(metadata.filename, metadata.size_bytes, metadata.created_at)
```
:::

## Use a file in a prompt

Reference the stored file with `Document.fromFileId()` / `Document.from_file_id()` (or `Image.fromFileId()` for an image) and pass it with the prompt.

::: language-group
```ts
import { Document, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Summarise the attached report.', [Document.fromFileId('file-abc123')])
  .asText();
console.log(response.text);
```

```python
from prism import Document, Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Summarise the attached report.", [Document.from_file_id("file-abc123")])
    .as_text()
)
print(response.text)
```
:::

A file ID belongs to the provider account that issued it. It is not portable: an OpenAI file ID sent to Anthropic is passed to Anthropic as one of its own IDs and fails there.

## List, download and delete

::: language-group
```ts
import { writeFileSync } from 'node:fs';
import { Prism } from '@particle-academy/prism';

const files = Prism.files().using('openai');

let afterId: string | null = null;
do {
  const page = await files.list(100, afterId);
  for (const file of page.data) {
    console.log(file.id, file.filename, file.purpose);
  }
  afterId = page.hasMore ? page.lastId : null;
} while (afterId !== null);

writeFileSync('copy.pdf', await files.download('file-abc123'));

const result = await files.delete('file-abc123');
if (!result.deleted) {
  console.log('The provider did not delete', result.id);
}
```

```python
from pathlib import Path
from prism import Prism

files = Prism.files().using("openai")

after_id = None
while True:
    page = files.list(100, after_id)
    for file in page.data:
        print(file.id, file.filename, file.purpose)
    if not page.has_more or page.last_id is None:
        break
    after_id = page.last_id

Path("copy.pdf").write_bytes(files.download("file-abc123"))

result = files.delete("file-abc123")
if not result.deleted:
    print("The provider did not delete", result.id)
```
:::

Check `deleted` rather than assuming success: OpenAI can answer a delete with HTTP 200 and `deleted: false`.

Store returned IDs in your application and apply your own ownership checks before letting anyone read or delete a file through them.

## Result objects

### FileData

| Field | TypeScript | Python | Type | Meaning |
|---|---|---|---|---|
| ID | `id` | `id` | string | The provider's file ID. |
| Name | `filename` | `filename` | string or null | The stored file name. |
| MIME type | `mimeType` | `mime_type` | string or null | Always null on OpenAI, which does not report one. |
| Size | `sizeBytes` | `size_bytes` | number or null | Size in bytes. |
| Created | `createdAt` | `created_at` | string or null | ISO 8601 timestamp in UTC. |
| Purpose | `purpose` | `purpose` | string or null | The file's purpose. |
| Raw | `raw` | `raw` | object or null | The provider's decoded record. |

### FileListResult

| Field | TypeScript | Python | Meaning |
|---|---|---|---|
| Files | `data` | `data` | The `FileData` on this page. |
| More pages | `hasMore` | `has_more` | Whether another page follows. |
| First ID | `firstId` | `first_id` | The first ID on the page, or null. |
| Last ID | `lastId` | `last_id` | The last ID on the page; pass it as `afterId` / `after_id` for the next page. |

### DeleteFileResult

| Field | Type | Meaning |
|---|---|---|
| `id` | string | The file ID. |
| `deleted` | boolean | The provider's own answer: whether the file was deleted. |

## Errors

| Code | When |
|---|---|
| `unsupported_provider_action` | The provider has no file operations, or `using()` was not called. |
| `provider_response_error` | The provider answered with an HTTP error, or with HTTP 200 carrying an `error` object. The status is on `httpStatus` (TypeScript) / `status` (Python). |

See [Documents](/input-modalities/documents), [Batch processing](/core-concepts/batch) and [Error handling](/advanced/error-handling).
