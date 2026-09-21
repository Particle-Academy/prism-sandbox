# Audio

The audio builder has two directions: text to speech and speech to text. The ports implement speech generation with OpenAI, and transcription with OpenAI and Mistral.

## Text to speech

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.audio()
  .using('openai', 'tts-1')
  .withInput('Welcome to the greenhouse.')
  .withVoice('alloy')
  .asAudio();

console.log(response.audio);
```

```python
from prism import Prism

response = (
    Prism.audio()
    .using("openai", "tts-1")
    .with_input("Welcome to the greenhouse.")
    .with_voice("alloy")
    .as_audio()
)

print(response.audio)
```
:::

## Speech to text

Load an audio file into a media object and choose a transcription model. The file path below is local to your server.

::: language-group
```ts
import { Audio, Prism } from '@particle-academy/prism';

const response = await Prism.audio()
  .using('openai', 'whisper-1')
  .withInput(Audio.fromLocalPath('recording.mp3'))
  .asText();

console.log(response.text);
```

```python
from prism import Audio, Prism

response = (
    Prism.audio()
    .using("openai", "whisper-1")
    .with_input(Audio.from_local_path("recording.mp3"))
    .as_text()
)

print(response.text)
```
:::

Transcription is a separate provider capability from attaching audio to a text conversation. Do not assume a chat model accepts audio just because the audio builder supports transcription. Usage may be absent when the provider bills by duration rather than tokens.
