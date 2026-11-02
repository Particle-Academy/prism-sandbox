# Image generation

Generate images from a text prompt. Both ports implement image generation for **OpenAI** only. Anthropic and Mistral fail with `unsupported_provider_action`.

## Generate an image

::: language-group
```ts
import { writeFileSync } from 'node:fs';
import { Prism } from '@particle-academy/prism';

const response = await Prism.images()
  .using('openai', 'gpt-image-2.5-sunburst')
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
    .using("openai", "gpt-image-2.5-sunburst")
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
  .using('openai', 'gpt-image-2.5-sunburst')
  .withPrompt('A greenhouse icon on a plain background.')
  .withProviderOptions({ n: 2, size: '1024x1024', background: 'transparent', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

response = (
    Prism.images()
    .using("openai", "gpt-image-2.5-sunburst")
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

## OpenAI reference gallery

These images were generated with PHP Prism 0.144.1. The TypeScript and Python
snippets below make the equivalent OpenAI request; they did not generate these
reference images. Each label states the requested model, because the API response
did not report a model ID. Costs are calculated from returned token usage, not an
account invoice. All outputs use the same prompt and 1024×1024 medium settings.

<div class="image-examples">

<section class="image-example">

### gpt-image-2.5-sunburst

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const prompt =
  "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats " +
  "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light " +
  "paths emerge and connect a small constellation of abstract tool, memory, search and " +
  "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous " +
  "negative space, balanced square composition, polished technical magazine cover. Keep the " +
  "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or " +
  "third-party logos.";

const response = await Prism.images()
  .using('openai', 'gpt-image-2.5-sunburst')
  .withPrompt(prompt)
  .withProviderOptions({ size: '1024x1024', quality: 'medium', n: 1, background: 'opaque', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

prompt = (
    "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats "
    "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light "
    "paths emerge and connect a small constellation of abstract tool, memory, search and "
    "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous "
    "negative space, balanced square composition, polished technical magazine cover. Keep the "
    "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or "
    "third-party logos."
)

response = (
    Prism.images()
    .using("openai", "gpt-image-2.5-sunburst")
    .with_prompt(prompt)
    .with_provider_options({"size": "1024x1024", "quality": "medium", "n": 1, "background": "opaque", "output_format": "png"})
    .generate()
)

print(len(response.images))
```
:::

<details>
<summary>PHP reference snippet that generated this image</summary>

```php
use Prism\Prism\Facades\Prism;

$prompt =
    'An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats ' .
    'above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light ' .
    'paths emerge and connect a small constellation of abstract tool, memory, search and ' .
    'conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous ' .
    'negative space, balanced square composition, polished technical magazine cover. Keep the ' .
    'scene abstract and welcoming. No people, faces, lettering, labels, watermarks or ' .
    'third-party logos.';

$response = Prism::image()
    ->using('openai', 'gpt-image-2.5-sunburst')
    ->withPrompt($prompt)
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'medium',
        'n' => 1,
        'background' => 'opaque',
        'output_format' => 'png',
    ])
    ->withClientOptions(['timeout' => 300, 'connect_timeout' => 15, 'allow_redirects' => false])
    ->generate();
```

</details>

<figure>
<img src="/assets/examples/openai/gpt-image-2.5-sunburst.webp" alt="Glass prism on a navy workbench sends pink, cyan and teal beams toward a wrench, database, magnifying glass and chat bubble." width="1024" height="1024" loading="lazy" decoding="async">
<figcaption>Generated with the snippet above. Requested model: <code>gpt-image-2.5-sunburst</code>. 1024×1024 · 2026-11-01 · Prism 0.144.1 · $0.013700 calculated from returned token usage.</figcaption>
</figure>

</section>

<section class="image-example">

### gpt-image-2.5-flare

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const prompt =
  "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats " +
  "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light " +
  "paths emerge and connect a small constellation of abstract tool, memory, search and " +
  "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous " +
  "negative space, balanced square composition, polished technical magazine cover. Keep the " +
  "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or " +
  "third-party logos.";

const response = await Prism.images()
  .using('openai', 'gpt-image-2.5-flare')
  .withPrompt(prompt)
  .withProviderOptions({ size: '1024x1024', quality: 'medium', n: 1, background: 'opaque', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

prompt = (
    "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats "
    "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light "
    "paths emerge and connect a small constellation of abstract tool, memory, search and "
    "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous "
    "negative space, balanced square composition, polished technical magazine cover. Keep the "
    "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or "
    "third-party logos."
)

response = (
    Prism.images()
    .using("openai", "gpt-image-2.5-flare")
    .with_prompt(prompt)
    .with_provider_options({"size": "1024x1024", "quality": "medium", "n": 1, "background": "opaque", "output_format": "png"})
    .generate()
)

print(len(response.images))
```
:::

<details>
<summary>PHP reference snippet that generated this image</summary>

```php
use Prism\Prism\Facades\Prism;

$prompt =
    'An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats ' .
    'above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light ' .
    'paths emerge and connect a small constellation of abstract tool, memory, search and ' .
    'conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous ' .
    'negative space, balanced square composition, polished technical magazine cover. Keep the ' .
    'scene abstract and welcoming. No people, faces, lettering, labels, watermarks or ' .
    'third-party logos.';

$response = Prism::image()
    ->using('openai', 'gpt-image-2.5-flare')
    ->withPrompt($prompt)
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'medium',
        'n' => 1,
        'background' => 'opaque',
        'output_format' => 'png',
    ])
    ->withClientOptions(['timeout' => 300, 'connect_timeout' => 15, 'allow_redirects' => false])
    ->generate();
```

</details>

<figure>
<img src="/assets/examples/openai/gpt-image-2.5-flare.webp" alt="Floating glass prism sends pink and cyan light ribbons toward translucent tool, database, search and conversation icons." width="1024" height="1024" loading="lazy" decoding="async">
<figcaption>Generated with the snippet above. Requested model: <code>gpt-image-2.5-flare</code>. 1024×1024 · 2026-11-01 · Prism 0.144.1 · $0.013700 calculated from returned token usage.</figcaption>
</figure>

</section>

<section class="image-example">

### gpt-image-2

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const prompt =
  "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats " +
  "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light " +
  "paths emerge and connect a small constellation of abstract tool, memory, search and " +
  "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous " +
  "negative space, balanced square composition, polished technical magazine cover. Keep the " +
  "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or " +
  "third-party logos.";

const response = await Prism.images()
  .using('openai', 'gpt-image-2')
  .withPrompt(prompt)
  .withProviderOptions({ size: '1024x1024', quality: 'medium', n: 1, background: 'opaque', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

prompt = (
    "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats "
    "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light "
    "paths emerge and connect a small constellation of abstract tool, memory, search and "
    "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous "
    "negative space, balanced square composition, polished technical magazine cover. Keep the "
    "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or "
    "third-party logos."
)

response = (
    Prism.images()
    .using("openai", "gpt-image-2")
    .with_prompt(prompt)
    .with_provider_options({"size": "1024x1024", "quality": "medium", "n": 1, "background": "opaque", "output_format": "png"})
    .generate()
)

print(len(response.images))
```
:::

<details>
<summary>PHP reference snippet that generated this image</summary>

```php
use Prism\Prism\Facades\Prism;

$prompt =
    'An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats ' .
    'above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light ' .
    'paths emerge and connect a small constellation of abstract tool, memory, search and ' .
    'conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous ' .
    'negative space, balanced square composition, polished technical magazine cover. Keep the ' .
    'scene abstract and welcoming. No people, faces, lettering, labels, watermarks or ' .
    'third-party logos.';

$response = Prism::image()
    ->using('openai', 'gpt-image-2')
    ->withPrompt($prompt)
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'medium',
        'n' => 1,
        'background' => 'opaque',
        'output_format' => 'png',
    ])
    ->withClientOptions(['timeout' => 300, 'connect_timeout' => 15, 'allow_redirects' => false])
    ->generate();
```

</details>

<figure>
<img src="/assets/examples/openai/gpt-image-2.webp" alt="Clear prism on a dark platform connects illuminated stacked panels, blue cubes, a magnifying glass and conversation bubbles." width="1024" height="1024" loading="lazy" decoding="async">
<figcaption>Generated with the snippet above. Requested model: <code>gpt-image-2</code>. 1024×1024 · 2026-11-01 · Prism 0.144.1 · $0.053210 calculated from returned token usage.</figcaption>
</figure>

</section>

<section class="image-example">

### gpt-image-1.5

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const prompt =
  "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats " +
  "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light " +
  "paths emerge and connect a small constellation of abstract tool, memory, search and " +
  "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous " +
  "negative space, balanced square composition, polished technical magazine cover. Keep the " +
  "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or " +
  "third-party logos.";

const response = await Prism.images()
  .using('openai', 'gpt-image-1.5')
  .withPrompt(prompt)
  .withProviderOptions({ size: '1024x1024', quality: 'medium', n: 1, background: 'opaque', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

prompt = (
    "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats "
    "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light "
    "paths emerge and connect a small constellation of abstract tool, memory, search and "
    "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous "
    "negative space, balanced square composition, polished technical magazine cover. Keep the "
    "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or "
    "third-party logos."
)

response = (
    Prism.images()
    .using("openai", "gpt-image-1.5")
    .with_prompt(prompt)
    .with_provider_options({"size": "1024x1024", "quality": "medium", "n": 1, "background": "opaque", "output_format": "png"})
    .generate()
)

print(len(response.images))
```
:::

<details>
<summary>PHP reference snippet that generated this image</summary>

```php
use Prism\Prism\Facades\Prism;

$prompt =
    'An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats ' .
    'above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light ' .
    'paths emerge and connect a small constellation of abstract tool, memory, search and ' .
    'conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous ' .
    'negative space, balanced square composition, polished technical magazine cover. Keep the ' .
    'scene abstract and welcoming. No people, faces, lettering, labels, watermarks or ' .
    'third-party logos.';

$response = Prism::image()
    ->using('openai', 'gpt-image-1.5')
    ->withPrompt($prompt)
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'medium',
        'n' => 1,
        'background' => 'opaque',
        'output_format' => 'png',
    ])
    ->withClientOptions(['timeout' => 300, 'connect_timeout' => 15, 'allow_redirects' => false])
    ->generate();
```

</details>

<figure>
<img src="/assets/examples/openai/gpt-image-1.5.webp" alt="Faceted glass prism splits a white beam into pink, cyan and teal paths beside database, search, wrench and conversation symbols." width="1024" height="1024" loading="lazy" decoding="async">
<figcaption>Generated with the snippet above. Requested model: <code>gpt-image-1.5</code>. 1024×1024 · 2026-11-01 · Prism 0.144.1 · $0.038082 calculated from returned token usage.</figcaption>
</figure>

</section>

<section class="image-example">

### gpt-image-1-mini

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const prompt =
  "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats " +
  "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light " +
  "paths emerge and connect a small constellation of abstract tool, memory, search and " +
  "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous " +
  "negative space, balanced square composition, polished technical magazine cover. Keep the " +
  "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or " +
  "third-party logos.";

const response = await Prism.images()
  .using('openai', 'gpt-image-1-mini')
  .withPrompt(prompt)
  .withProviderOptions({ size: '1024x1024', quality: 'medium', n: 1, background: 'opaque', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

prompt = (
    "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats "
    "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light "
    "paths emerge and connect a small constellation of abstract tool, memory, search and "
    "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous "
    "negative space, balanced square composition, polished technical magazine cover. Keep the "
    "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or "
    "third-party logos."
)

response = (
    Prism.images()
    .using("openai", "gpt-image-1-mini")
    .with_prompt(prompt)
    .with_provider_options({"size": "1024x1024", "quality": "medium", "n": 1, "background": "opaque", "output_format": "png"})
    .generate()
)

print(len(response.images))
```
:::

<details>
<summary>PHP reference snippet that generated this image</summary>

```php
use Prism\Prism\Facades\Prism;

$prompt =
    'An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats ' .
    'above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light ' .
    'paths emerge and connect a small constellation of abstract tool, memory, search and ' .
    'conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous ' .
    'negative space, balanced square composition, polished technical magazine cover. Keep the ' .
    'scene abstract and welcoming. No people, faces, lettering, labels, watermarks or ' .
    'third-party logos.';

$response = Prism::image()
    ->using('openai', 'gpt-image-1-mini')
    ->withPrompt($prompt)
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'medium',
        'n' => 1,
        'background' => 'opaque',
        'output_format' => 'png',
    ])
    ->withClientOptions(['timeout' => 300, 'connect_timeout' => 15, 'allow_redirects' => false])
    ->generate();
```

</details>

<figure>
<img src="/assets/examples/openai/gpt-image-1-mini.webp" alt="Geometric glass prism above flat pink, blue and teal paths ending in abstract tool and conversation shapes on navy." width="1024" height="1024" loading="lazy" decoding="async">
<figcaption>Generated with the snippet above. Requested model: <code>gpt-image-1-mini</code>. 1024×1024 · 2026-11-01 · Prism 0.144.1 · $0.008660 calculated from returned token usage.</figcaption>
</figure>

</section>

<section class="image-example">

### chatgpt-image-latest

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const prompt =
  "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats " +
  "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light " +
  "paths emerge and connect a small constellation of abstract tool, memory, search and " +
  "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous " +
  "negative space, balanced square composition, polished technical magazine cover. Keep the " +
  "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or " +
  "third-party logos.";

const response = await Prism.images()
  .using('openai', 'chatgpt-image-latest')
  .withPrompt(prompt)
  .withProviderOptions({ size: '1024x1024', quality: 'medium', n: 1, background: 'opaque', output_format: 'png' })
  .generate();

console.log(response.images.length);
```

```python
from prism import Prism

prompt = (
    "An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats "
    "above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light "
    "paths emerge and connect a small constellation of abstract tool, memory, search and "
    "conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous "
    "negative space, balanced square composition, polished technical magazine cover. Keep the "
    "scene abstract and welcoming. No people, faces, lettering, labels, watermarks or "
    "third-party logos."
)

response = (
    Prism.images()
    .using("openai", "chatgpt-image-latest")
    .with_prompt(prompt)
    .with_provider_options({"size": "1024x1024", "quality": "medium", "n": 1, "background": "opaque", "output_format": "png"})
    .generate()
)

print(len(response.images))
```
:::

<details>
<summary>PHP reference snippet that generated this image</summary>

```php
use Prism\Prism\Facades\Prism;

$prompt =
    'An editorial illustration of the Prism AI ecosystem: a clear glass triangular prism floats ' .
    'above a deep navy workbench. One soft white beam enters; hot pink, cyan and teal light ' .
    'paths emerge and connect a small constellation of abstract tool, memory, search and ' .
    'conversation shapes. Precise geometry, tactile glass, crisp soft shadows, generous ' .
    'negative space, balanced square composition, polished technical magazine cover. Keep the ' .
    'scene abstract and welcoming. No people, faces, lettering, labels, watermarks or ' .
    'third-party logos.';

$response = Prism::image()
    ->using('openai', 'chatgpt-image-latest')
    ->withPrompt($prompt)
    ->withProviderOptions([
        'size' => '1024x1024',
        'quality' => 'medium',
        'n' => 1,
        'background' => 'opaque',
        'output_format' => 'png',
    ])
    ->withClientOptions(['timeout' => 300, 'connect_timeout' => 15, 'allow_redirects' => false])
    ->generate();
```

</details>

<figure>
<img src="/assets/examples/openai/chatgpt-image-latest.webp" alt="Glass prism splits light across a navy workbench toward a database, pink chat bubbles, a wrench and gear, and a search panel." width="1024" height="1024" loading="lazy" decoding="async">
<figcaption>Generated with the snippet above. Requested model: <code>chatgpt-image-latest</code>. 1024×1024 · 2026-11-01 · Prism 0.144.1 · $0.037842 calculated from returned token usage.</figcaption>
</figure>

</section>

</div>

The three legacy model IDs above are scheduled to shut down on December 1, 2026;
check [OpenAI’s deprecation schedule](https://developers.openai.com/api/docs/deprecations)
before making a new request.

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
