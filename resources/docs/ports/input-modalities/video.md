# Video input

Both ports have a Video media value object, but the built-in OpenAI, Anthropic and Mistral text adapters do not provide the PHP reference's video-capable provider integration. Video objects require a compatible provider adapter before they can be sent.

## Represent a video without sending it

::: language-group
```ts
import { Video } from '@particle-academy/prism';

const video = Video.fromUrl('https://example.com/greenhouse.mp4');
console.log(video.toObject());
```

```python
from prism import Video

video = Video.from_url("https://example.com/greenhouse.mp4")
print(video.to_dict())
```
:::

For a supported workflow, your application can extract still images and use [Image input](/input-modalities/images), or extract audio and use [transcription](/core-concepts/audio). Those are separate preprocessing steps and are not performed automatically by Prism. Select PHP for the reference's provider-specific video examples.
