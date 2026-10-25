# Image input

Attach an image to a text request using an `Image` value object. Choose a provider and model that accept image input. To generate a new image instead, see [Image generation](/core-concepts/image-generation).

## Send an image URL

::: language-group
```ts
import { Image, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Describe this image.', [
    Image.fromUrl('https://example.com/greenhouse.png'),
  ])
  .asText();

console.log(response.text);
```

```python
from prism import Image, Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Describe this image.", [
        Image.from_url("https://example.com/greenhouse.png"),
    ])
    .as_text()
)

print(response.text)
```
:::

A URL is sent to the provider as a URL. Your server does not download it: the provider fetches it. That means the URL must be reachable from the provider, and the provider learns it. If you need the bytes on your side first, see [Fetch a URL into bytes](#fetch-a-url-into-bytes).

## Ways to create an image

Every factory returns an `Image`. The same factories exist on `Document`, `Audio` and `Video`.

| TypeScript | Python | What it holds |
|---|---|---|
| `Image.fromUrl(url, mimeType?)` | `Image.from_url(url, mime_type=None)` | A URL. Nothing is fetched. |
| `Image.fromLocalPath(path, mimeType?)` | `Image.from_local_path(path, mime_type=None)` | The file's bytes, read immediately. |
| `Image.fromPath(path)` | `Image.from_path(path)` | Alias for `fromLocalPath` / `from_local_path`. |
| `Image.fromBase64(base64, mimeType?)` | `Image.from_base64(data, mime_type=None)` | Base64-encoded bytes. |
| `Image.fromRawContent(bytes, mimeType?)` | `Image.from_raw_content(content, mime_type=None)` | Raw bytes: a `Uint8Array` in TypeScript, `bytes` in Python. |
| `Image.fromFileId(fileId)` | `Image.from_file_id(file_id)` | The ID of a file already uploaded to the provider. |

`fromLocalPath` reads the file when it is called. A missing, unreadable or empty file raises `unreadable_media_file`. When you don't pass a MIME type, it is guessed from the file extension: TypeScript knows `.png`, `.jpg`, `.jpeg`, `.gif` and `.webp` for images, and Python uses the standard `mimetypes` module. If the extension is not recognised, the MIME type is left unset rather than guessed.

Always pass a MIME type to `fromBase64` and `fromRawContent`. Bytes without one cannot be sent inline, and the request fails with `unsupported_media`.

`as(name)` in TypeScript and `as_(name)` in Python attach a filename to any media value, for providers that want one.

## Send a local file or bytes

The path is local to your server.

::: language-group
```ts
import { readFileSync } from 'node:fs';
import { Image, Prism } from '@particle-academy/prism';

const fromDisk = Image.fromLocalPath('photos/greenhouse.png');

const fromBytes = Image.fromRawContent(
  Uint8Array.from(readFileSync('photos/seedlings.jpg')),
  'image/jpeg',
);

const fromBase64 = Image.fromBase64(process.env.THUMBNAIL_BASE64 ?? '', 'image/png');

const response = await Prism.text()
  .using('anthropic', 'claude-sonnet-4-20250514')
  .withPrompt('Compare these three images.', [fromDisk, fromBytes, fromBase64])
  .asText();

console.log(response.text);
```

```python
import os
from pathlib import Path

from prism import Image, Prism

from_disk = Image.from_local_path("photos/greenhouse.png")

from_bytes = Image.from_raw_content(
    Path("photos/seedlings.jpg").read_bytes(),
    "image/jpeg",
)

from_base64 = Image.from_base64(os.environ.get("THUMBNAIL_BASE64", ""), "image/png")

response = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-20250514")
    .with_prompt("Compare these three images.", [from_disk, from_bytes, from_base64])
    .as_text()
)

print(response.text)
```
:::

## Send an uploaded file ID

Upload the file once with [Files](/core-concepts/files), then refer to it by ID. OpenAI and Anthropic accept image file IDs; Mistral does not.

::: language-group
```ts
import { Image, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('What is in this picture?', [Image.fromFileId('file-abc123')])
  .asText();

console.log(response.text);
```

```python
from prism import Image, Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("What is in this picture?", [Image.from_file_id("file-abc123")])
    .as_text()
)

print(response.text)
```
:::

## Fetch a URL into bytes

Media values never download anything on their own. Reading a property of an `Image` built from a URL never starts a request. To download a URL on your server, for example to inspect it, store it, or send bytes to a provider that cannot reach the URL, call the guarded fetch explicitly: `fetchPublic()` in TypeScript, `fetch_public()` in Python. It downloads the URL into the same object and returns it.

A value built from a URL is still sent to providers **as its URL** after fetching. To send the downloaded bytes instead, build a new image from them, as below.

::: language-group
```ts
import { Image, Prism, PrismUrlRefused } from '@particle-academy/prism';

async function describe(userSuppliedUrl: string): Promise<string> {
  let fetched: Image;

  try {
    fetched = await Image.fromUrl(userSuppliedUrl).fetchPublic({
      maxRedirects: 3,
      maxBytes: 5 * 1024 * 1024,
    });
  } catch (error) {
    if (error instanceof PrismUrlRefused) {
      return `That URL was refused (${error.code}).`;
    }
    throw error;
  }

  // Send the bytes, not the URL.
  const image = Image.fromRawContent(fetched.rawContent() ?? new Uint8Array(), fetched.mimeType());

  const response = await Prism.text()
    .using('openai', 'gpt-4o')
    .withPrompt('Describe this image.', [image])
    .asText();

  return response.text;
}

console.log(await describe('https://example.com/greenhouse.png'));
```

```python
from prism import ErrorCode, Image, Prism, PrismError

URL_REFUSALS = {
    ErrorCode.SCHEME_NOT_ALLOWED,
    ErrorCode.PRIVATE_ADDRESS_REFUSED,
    ErrorCode.HOST_DID_NOT_RESOLVE,
    ErrorCode.REDIRECT_REFUSED,
    ErrorCode.TOO_MANY_REDIRECTS,
    ErrorCode.MEDIA_TOO_LARGE,
}


def describe(user_supplied_url: str) -> str:
    try:
        fetched = Image.from_url(user_supplied_url).fetch_public(
            max_redirects=3,
            max_bytes=5 * 1024 * 1024,
        )
    except PrismError as error:
        if error.code in URL_REFUSALS:
            return f"That URL was refused ({error.code})."
        raise

    # Send the bytes, not the URL.
    image = Image.from_raw_content(fetched.raw_content() or b"", fetched.mime_type())

    response = (
        Prism.text()
        .using("openai", "gpt-4o")
        .with_prompt("Describe this image.", [image])
        .as_text()
    )
    return response.text


print(describe("https://example.com/greenhouse.png"))
```
:::

Once fetched, the value carries the bytes (`rawContent()` / `raw_content()`), and the content type the server returned becomes its MIME type unless you set one. Check that type before trusting it: it is whatever the remote server said.

### What the guarded fetch checks

Before each request, including every redirect, it checks:

- **Scheme:** only absolute `http://` and `https://` URLs are allowed.
- **Destination:** the host is resolved, and **every** address it resolves to must be public. Refused ranges include loopback, private networks (`10/8`, `172.16/12`, `192.168/16`), carrier-grade NAT (`100.64/10`), link-local (`169.254/16`, where cloud metadata endpoints live), documentation, benchmarking, multicast and reserved ranges, and their IPv6 equivalents (`::1`, `fc00::/7`, `fe80::/10` and others). IPv4 addresses embedded in IPv4-mapped, NAT64 and 6to4 IPv6 addresses are judged by the embedded address. A host that does not resolve is refused.
- **Redirects:** followed one hop at a time, each destination checked before it is requested. The default limit is 5.
- **Size:** the body is capped at `maxBytes` / `max_bytes`, 32 MiB by default. A declared `Content-Length` over the cap is refused before reading. Otherwise the download stops as soon as it goes over.

| Option | TypeScript | Python | Default |
|---|---|---|---|
| Redirect hops allowed after the first request | `maxRedirects` | `max_redirects` | `5` |
| Largest body accepted, in bytes | `maxBytes` | `max_bytes` | `33554432` (32 MiB) |
| Custom DNS resolver; every address it returns is checked | `resolver` | `resolver` | System DNS |
| HTTP transport; must not follow redirects itself | not available | `transport` | `UrllibTransport` |

A negative or non-integer limit raises `RangeError` in TypeScript and `ValueError` in Python. Python's default transport applies a 30-second timeout to each hop. TypeScript's `fetchPublic` has no timeout of its own.

Refusals carry one of these codes. In TypeScript they are thrown as `PrismUrlRefused`, a subclass of `PrismError`. Python has no separate class: it raises `PrismError` with the same `code`.

| Code | Meaning |
|---|---|
| `scheme_not_allowed` | The URL is not an absolute `http` or `https` URL. |
| `private_address_refused` | The host is, or resolves to, a private, reserved or invalid address. |
| `host_did_not_resolve` | The hostname returned no addresses. |
| `redirect_refused` | A redirect pointed at a refused destination or an invalid URL. |
| `too_many_redirects` | The chain was longer than the redirect limit. |
| `media_too_large` | The body was larger than the byte limit. |

A non-2xx answer that is not a redirect raises `unfetchable_media` (a plain `PrismError` in both languages), as does calling the fetch on a value that has no URL.

::: warning DNS is checked, not pinned
The guard checks the addresses a hostname resolves to, then makes the request, which resolves the name again. A hostile DNS server can answer differently the second time (DNS rebinding). For URLs from untrusted sources, also block private ranges at the network level: an egress proxy or firewall rule.
:::

::: warning TypeScript `fetch()` is unguarded
TypeScript media values also have `fetch()`, which downloads the URL with the platform `fetch` and no checks at all: any scheme `fetch` supports, any address including `localhost` and cloud metadata endpoints, redirects followed automatically, and no size limit. Use it only for URLs your own code wrote, such as a constant or your own storage bucket. If a user, a model, a tool result or stored data can influence any part of the URL, use `fetchPublic()`. Python has no unguarded fetch.
:::

## Provider support

| Provider | URL | Bytes (local path, base64, raw) | File ID |
|---|---|---|---|
| OpenAI | Yes | Yes, as a `data:` URI; MIME type required | Yes |
| Anthropic | Yes | Yes, as base64; MIME type required | Yes |
| Mistral | Yes | Yes, as a `data:` URI; MIME type required | No |

A form the provider cannot send raises `unsupported_media` before any request is made. The message names the provider and the forms it accepts. Model-level limits (formats, dimensions, count per request) are the provider's; check its documentation.

## Errors

| Code | When |
|---|---|
| `unreadable_media_file` | `fromLocalPath` could not read the file, or it is empty. |
| `unsupported_media` | The provider cannot send the image in the form you gave, or bytes have no MIME type. |
| `unfetchable_media` | A fetch was called on a value without a URL, or the server answered with an error status. |
| URL refusal codes | See the table above. |

See [Error handling](/advanced/error-handling) for the error object.
