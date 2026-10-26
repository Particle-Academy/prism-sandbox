# Document input

Attach a document to a text request using a `Document` value object. Providers differ in which forms they accept (URL, inline bytes, uploaded file ID, pre-split text), so check [Provider support](#provider-support) for the provider you select.

## Send a PDF

::: language-group
```ts
import { Document, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Summarize this report.', [
    Document.fromLocalPath('report.pdf').titled('report.pdf'),
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
        Document.from_local_path("report.pdf").titled("report.pdf"),
    ])
    .as_text()
)

print(response.text)
```
:::

The file path is local to your server. Apply file-size, ownership and content checks before sending a user-supplied document. OpenAI reads the file type from the title of an inline document, so give a title with the right extension; an untitled document is sent with the name `document`.

## Ways to create a document

| TypeScript | Python | What it holds |
|---|---|---|
| `Document.fromLocalPath(path, mimeType?)` | `Document.from_local_path(path, mime_type=None)` | The file's bytes, read immediately. A missing or empty file raises `unreadable_media_file`. |
| `Document.fromUrl(url, mimeType?)` | `Document.from_url(url, mime_type=None)` | A URL. Nothing is fetched; the provider fetches it. |
| `Document.fromBase64(base64, mimeType?)` | `Document.from_base64(data, mime_type=None)` | Base64-encoded bytes. |
| `Document.fromRawContent(bytes, mimeType?)` | `Document.from_raw_content(content, mime_type=None)` | Raw bytes. |
| `Document.fromFileId(fileId)` | `Document.from_file_id(file_id)` | A file already uploaded to the provider. See [Files](/core-concepts/files). |
| `Document.fromText(text, title?)` | `Document.from_text(text, title=None)` | Text, sent as a `text/plain` document rather than as part of the prompt. |
| `Document.fromChunks(chunks, title?)` | `Document.from_chunks(chunks, title=None)` | Pre-split text. Anthropic only. |

Name a document with `titled(title)`, which returns the same document so it chains onto any factory. The title is sent as `filename` to OpenAI, `title` to Anthropic and `document_name` to Mistral. The second argument of `fromUrl`, `fromBase64` and the other byte factories is always the **MIME type**, never the title.

MIME types are guessed from the file extension when you don't pass one (TypeScript recognises `.pdf`, `.txt` and `.md` for documents; Python uses `mimetypes`). Pass one explicitly for bytes and base64.

## Send text and chunks

::: language-group
```ts
import { Document, Prism } from '@particle-academy/prism';

const policy = Document.fromText('Refunds are issued within 14 days of purchase.', 'Refund policy');

const handbook = Document.fromChunks(
  ['Section 1: Opening hours are 9 to 5.', 'Section 2: Staff take lunch between 12 and 1.'],
  'Staff handbook',
);

const response = await Prism.text()
  .using('anthropic', 'claude-sonnet-4-20250514')
  .withMaxTokens(512)
  .withPrompt('When are refunds issued, and when is lunch?', [policy, handbook])
  .asText();

console.log(response.text);
```

```python
from prism import Document, Prism

policy = Document.from_text("Refunds are issued within 14 days of purchase.", "Refund policy")

handbook = Document.from_chunks(
    ["Section 1: Opening hours are 9 to 5.", "Section 2: Staff take lunch between 12 and 1."],
    "Staff handbook",
)

response = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-20250514")
    .with_max_tokens(512)
    .with_prompt("When are refunds issued, and when is lunch?", [policy, handbook])
    .as_text()
)

print(response.text)
```
:::

## Send a document URL to Mistral

Mistral accepts documents by URL only. It fetches the document itself.

::: language-group
```ts
import { Document, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('mistral', 'mistral-small-latest')
  .withPrompt('List the key findings.', [
    Document.fromUrl('https://example.com/annual-report.pdf').titled('Annual report'),
  ])
  .asText();

console.log(response.text);
```

```python
from prism import Document, Prism

response = (
    Prism.text()
    .using("mistral", "mistral-small-latest")
    .with_prompt("List the key findings.", [
        Document.from_url("https://example.com/annual-report.pdf").titled("Annual report"),
    ])
    .as_text()
)

print(response.text)
```
:::

## Provider support

| Provider | URL | Bytes | File ID | Text documents | Chunks |
|---|---|---|---|---|---|
| OpenAI | Yes | Yes, as a `data:` URI with the title as filename; MIME type required | Yes | Sent as bytes | No |
| Anthropic | Yes | Yes, as base64; MIME type required | Yes | Sent as text (see below) | Yes |
| Mistral | Yes | No | No | No | No |

Anthropic sends any `text/*` document (from `fromText`, or a `.txt` or `.md` file) as plain text instead of base64. The bytes must be valid UTF-8; anything else raises `unsupported_media` instead of sending corrupted text. Whatever `text/*` type you declared, it is sent as `text/plain`.

A form the provider cannot send raises `unsupported_media` before any request is made. The message names the provider and the forms it accepts. Size, page and format limits are the provider's; check its documentation.

## Download a document first

`Document.fromUrl` never downloads anything on your server. If you need the bytes, for example to send a document to a provider that cannot reach the URL, use the guarded `fetchPublic()` / `fetch_public()`, then build a new document from the bytes. A fetched URL-based document is still sent as its URL. The method, its limits and its refusal codes are the same as for images: see [Fetch a URL into bytes](/input-modalities/images#fetch-a-url-into-bytes).

::: warning Do not use TypeScript `fetch()` on untrusted URLs
TypeScript media values also have an unguarded `fetch()`. It will request internal addresses, cloud metadata endpoints and unlimited-size bodies. Use it only for URLs your own code wrote. If a user, a model or stored data can influence the URL, use `fetchPublic()`.
:::

## Errors

| Code | When |
|---|---|
| `unreadable_media_file` | `fromLocalPath` could not read the file, or it is empty. |
| `unsupported_media` | The provider cannot send the document in the form you gave: chunks to OpenAI or Mistral, anything but a URL to Mistral, bytes without a MIME type, or a text document that is not valid UTF-8 for Anthropic. |
| `unfetchable_media` | A fetch was called on a document without a URL, or the server answered with an error status. |

See [Error handling](/advanced/error-handling) for the error object.
