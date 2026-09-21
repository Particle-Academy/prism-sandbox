# Image generation

Generate images from a text prompt. The built-in image generation implementation in both ports uses OpenAI.

## Generate an image

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.images()
  .using('openai', 'gpt-image-1')
  .withPrompt('A watercolor illustration of a small greenhouse.')
  .generate();

console.log(response.images);
```

```python
from prism import Prism

response = (
    Prism.images()
    .using("openai", "gpt-image-1")
    .with_prompt("A watercolor illustration of a small greenhouse.")
    .generate()
)

print(response.images)
```
:::

Generated images are media value objects. Inspect whether a result contains a URL or base64 content before saving or displaying it. Provider options are model-specific; use the options supported by the selected OpenAI model.

To send an existing image to a language model, see [Image input](/input-modalities/images).
