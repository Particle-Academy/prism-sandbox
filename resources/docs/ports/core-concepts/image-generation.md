# Image generation

Generate images from a text prompt. Both ports implement image generation for **OpenAI** only. Anthropic and Mistral fail with `unsupported_provider_action`.

## Generate an image

::: language-group
```ts
import { writeFileSync } from 'node:fs';
import { Prism } from '@particle-academy/prism';

const response = await Prism.images()
  .using('openai', 'gpt-image-1')
  .withPrompt('A watercolor illustration of a small greenhouse.')
  .generate();

const image = response.firstImage();
const bytes = image?.rawContent();

if (bytes) {
  writeFileSync('greenhouse.png', bytes);
} else if (image?.url) {
  console.log('Download from', image.url);
}
```

```python
from pathlib import Path
from prism import Prism

response = (
    Prism.images()
    .using("openai", "gpt-image-1")
    .with_prompt("A watercolor illustration of a small greenhouse.")
    .generate()
)

image = response.first_image()
data = image.raw_content() if image else None

if data:
    Path("greenhouse.png").write_bytes(data)
elif image and image.url:
    print("Download from", image.url)
```
:::

An image comes back either as base64 content or as a URL, depending on the model and `response_format`. Check which one you have before saving or displaying it. A returned URL is not fetched for you.

`withPrompt()` / `with_prompt()` **replaces** the prompt: an image request has one prompt, not a conversation.

## Provider options

OpenAI reads these keys from `withProviderOptions()` / `with_provider_options()`. A key you do not set is left out of the request; any other key is ignored.

| Option | Meaning |
|---|---|
| `n` | Number of images to generate. |
| `size` | Image size, for example `1024x1024`. |
| `quality` | Quality level the model supports. |
| `style` | Style, for models that support it. |
| `response_format` | `url` or `b64_json`, for models that support it. |
| `background` | Background handling, for models that support it (for example `transparent`). |
| `output_format` | Output file format, for models that support it (for example `png`, `webp`). |
| `user` | An end-user identifier OpenAI uses for abuse monitoring. |

Which options and values a model accepts is decided by OpenAI. A value the model rejects comes back as `provider_response_error`.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.images()
  .using('openai', 'gpt-image-1')
  .withPrompt('A greenhouse icon on a plain background.')
  .withProviderOptions({ n: 2, size: '1024x1024', background: 'transparent', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

response = (
    Prism.images()
    .using("openai", "gpt-image-1")
    .with_prompt("A greenhouse icon on a plain background.")
    .with_provider_options({
        "n": 2,
        "size": "1024x1024",
        "background": "transparent",
        "output_format": "png",
    })
    .generate()
)

print(len(response.images))
```
:::

## The response

`generate()` returns an `ImagesResponse`.

| Field | Type | Meaning |
|---|---|---|
| `images` | `GeneratedImage[]` (Python: tuple) | Every image the provider returned, in its order. |
| `usage` | `Usage` | `promptTokens` / `completionTokens` (Python: `prompt_tokens` / `completion_tokens`). `0` when the model reports no usage. |
| `meta` | `Meta` | `id` and `model`. |
| `additionalContent` (Python: `additional_content`) | object | Provider extras; empty for OpenAI. |
| `raw` | object or `null` | The provider's decoded response body. |

`firstImage()` / `first_image()` returns the first image, or `null` / `None` when there is none.

### GeneratedImage

`GeneratedImage` is an `Image` media object, so you can also pass it back into a [prompt](/input-modalities/images).

| Member | TypeScript | Python | Meaning |
|---|---|---|---|
| URL | `url` | `url` | The hosted image URL, or null. |
| Base64 | `base64()` | `base64()` | The image as base64, or null. |
| Bytes | `rawContent()` | `raw_content()` | The decoded image bytes, or null when only a URL was returned. |
| MIME type | `mimeType()` | `mime_type()` | Null for OpenAI: it does not report one. |
| Revised prompt | `revisedPrompt` | `revised_prompt` | The prompt OpenAI actually used, when it rewrote yours; otherwise null. |
| | `hasRevisedPrompt()` | `has_revised_prompt()` | Whether a revised prompt was returned. |

## Errors

| Code | When |
|---|---|
| `no_image_prompt` | `withPrompt()` was never called. Nothing is sent. An empty string is sent, and the provider rejects it. |
| `unsupported_provider_action` | The provider has no image generation, or `using()` was not called. |
| `provider_response_error` | The provider answered with an HTTP error or a body that is not a JSON object. The status is on `httpStatus` (TypeScript) / `status` (Python). |

See [Error handling](/advanced/error-handling). To send an existing image to a language model, see [Image input](/input-modalities/images).
