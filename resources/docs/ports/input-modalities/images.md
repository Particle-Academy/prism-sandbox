# Image input

Attach an image to a text request using an image media value object. Choose a provider/model that accepts image inputs.

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

Replace the example URL with an accessible image you are authorized to send. Media objects also support local paths and base64 content. Supply the correct MIME type for inline content. To generate a new image instead, see [Image generation](/core-concepts/image-generation).
