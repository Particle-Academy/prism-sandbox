# Audio

The audio builder has two directions: text to speech and speech to text. One builder serves both; the terminal method you call decides which.

| Direction | Terminal | Providers in both ports |
|---|---|---|
| Text to speech | `asAudio()` / `as_audio()` | OpenAI |
| Speech to text | `asText()` / `as_text()` | OpenAI, Mistral |

Any other provider fails with `unsupported_provider_action`.

## Text to speech

::: language-group
```ts
import { writeFileSync } from 'node:fs';
import { Prism } from '@particle-academy/prism';

const response = await Prism.audio()
  .using('openai', 'gpt-4o-mini-tts')
  .withInput('Welcome to the greenhouse.')
  .withVoice('nova')
  .withProviderOptions({ response_format: 'wav', speed: 1.1 })
  .asAudio();

const bytes = response.audio.rawContent();
if (bytes) {
  writeFileSync(`welcome.${response.audio.type}`, bytes);
}
```

```python
from pathlib import Path
from prism import Prism

response = (
    Prism.audio()
    .using("openai", "gpt-4o-mini-tts")
    .with_input("Welcome to the greenhouse.")
    .with_voice("nova")
    .with_provider_options({"response_format": "wav", "speed": 1.1})
    .as_audio()
)

data = response.audio.raw_content()
if data:
    Path(f"welcome.{response.audio.type}").write_bytes(data)
```
:::

`withVoice()` / `with_voice()` is optional; OpenAI requires a voice, so `alloy` is sent when you set none.

OpenAI reads these provider options for speech:

| Option | Meaning |
|---|---|
| `response_format` | Audio format, for example `mp3`, `wav`, `opus`. Also becomes `audio.type`; `mp3` when unset. |
| `speed` | Playback speed. |
| `instructions` | Speaking-style instructions, for models that support them. |

`asAudio()` returns an `AudioResponse`:

| Field | Type | Meaning |
|---|---|---|
| `audio` | `GeneratedAudio` | The generated speech. |
| `additionalContent` (Python: `additional_content`) | object | Provider extras; empty for OpenAI. |

`GeneratedAudio` is an `Audio` media object:

| Member | TypeScript | Python | Meaning |
|---|---|---|---|
| Bytes | `rawContent()` | `raw_content()` | The audio bytes. |
| Base64 | `base64()` | `base64()` | The audio as base64. |
| MIME type | `mimeType()` | `mime_type()` | The response's content type, or `audio/<type>` when it sent none. |
| Format | `type` | `type` | The format name, for example `mp3`. Not a MIME type. |

## Speech to text

Load the recording into an `Audio` media object and choose a transcription model.

::: language-group
```ts
import { Audio, Prism } from '@particle-academy/prism';

const response = await Prism.audio()
  .using('openai', 'whisper-1')
  .withInput(Audio.fromLocalPath('recording.mp3'))
  .withProviderOptions({ language: 'en', temperature: 0 })
  .asText();

console.log(response.text);
```

```python
from prism import Audio, Prism

response = (
    Prism.audio()
    .using("openai", "whisper-1")
    .with_input(Audio.from_local_path("recording.mp3"))
    .with_provider_options({"language": "en", "temperature": 0})
    .as_text()
)

print(response.text)
```
:::

The audio must carry its bytes: build it with `fromLocalPath`, `fromRawContent` or `fromBase64` (Python: `from_local_path`, `from_raw_content`, `from_base64`). A URL is never fetched implicitly; an `Audio` that holds only a URL or a file id fails with `no_audio_content`. To transcribe a URL, fetch it yourself first. Use the guarded `fetchPublic()` / `fetch_public()` for any URL a user supplied: it refuses private and loopback addresses, unsafe redirects and oversized answers with a stable code such as `private_address_refused` or `media_too_large` (see [Image input](/input-modalities/images)).

::: language-group
```ts
import { Audio, Prism } from '@particle-academy/prism';

const audio = await Audio.fromUrl('https://example.com/podcast.mp3').fetchPublic();

const response = await Prism.audio()
  .using('openai', 'whisper-1')
  .withInput(audio.as('podcast.mp3'))
  .asText();
console.log(response.text);
```

```python
from prism import Audio, Prism

audio = Audio.from_url("https://example.com/podcast.mp3").fetch_public()

response = (
    Prism.audio()
    .using("openai", "whisper-1")
    .with_input(audio.as_("podcast.mp3"))
    .as_text()
)
print(response.text)
```
:::

The upload's file name is the one set with `as()` / `as_()`, or `audio.<ext>` derived from the MIME type. Providers use the extension to detect the format.

OpenAI and Mistral read the same provider options for transcription. Each is sent as a form field when set:

| Option | Meaning |
|---|---|
| `language` | The spoken language, as an ISO-639-1 code. |
| `prompt` | Text to guide spelling and style. |
| `response_format` | Leave unset, or use `json` / `verbose_json`. The ports parse a JSON reply, so `text`, `srt` and `vtt` fail with `provider_response_error`. |
| `temperature` | Sampling temperature. |

`asText()` returns an `AudioTextResponse`:

| Field | Type | Meaning |
|---|---|---|
| `text` | string | The transcript. |
| `usage` | `Usage` or `null` / `None` | Token usage when the provider reports it. Null when it bills by duration and reports none. |
| `additionalContent` (Python: `additional_content`) | object | The provider's full decoded reply, for example the segments returned with `verbose_json`. |

### Mistral

::: language-group
```ts
import { Audio, Prism } from '@particle-academy/prism';

const response = await Prism.audio()
  .using('mistral', 'voxtral-mini-latest')
  .withInput(Audio.fromLocalPath('recording.mp3'))
  .withProviderOptions({ language: 'en' })
  .asText();

console.log(response.text);
```

```python
from prism import Audio, Prism

response = (
    Prism.audio()
    .using("mistral", "voxtral-mini-latest")
    .with_input(Audio.from_local_path("recording.mp3"))
    .with_provider_options({"language": "en"})
    .as_text()
)

print(response.text)
```
:::

Mistral has no text to speech; `asAudio()` on it fails with `unsupported_provider_action`.

## Audio in a conversation

::: warning Chat models do not receive audio
None of the ports' text providers (OpenAI, Anthropic, Mistral) sends an `Audio` part attached to a prompt or a `UserMessage`. The part is left out of the request, and the model answers from the text alone. Transcribe the audio first and send the transcript.
:::

::: language-group
```ts
import { Audio, Prism } from '@particle-academy/prism';

const transcript = await Prism.audio()
  .using('openai', 'whisper-1')
  .withInput(Audio.fromLocalPath('recording.mp3'))
  .asText();

const summary = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt(`Summarise this site visit:\n\n${transcript.text}`)
  .asText();
console.log(summary.text);
```

```python
from prism import Audio, Prism

transcript = (
    Prism.audio()
    .using("openai", "whisper-1")
    .with_input(Audio.from_local_path("recording.mp3"))
    .as_text()
)

summary = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt(f"Summarise this site visit:\n\n{transcript.text}")
    .as_text()
)
print(summary.text)
```
:::

## Errors

| Code | When |
|---|---|
| `wrong_audio_input` | `asAudio()` was given an `Audio`, or `asText()` was given a string. |
| `no_audio_content` | The `Audio` for transcription has no bytes (it holds only a URL or file id). |
| `unreadable_media_file` | `fromLocalPath()` could not read the file, or the file is empty. |
| `unsupported_provider_action` | The provider does not implement that direction, or `using()` was not called. |
| `provider_response_error` | The provider answered with an HTTP error or a reply that is not a JSON object. The status is on `httpStatus` (TypeScript) / `status` (Python). |
| `scheme_not_allowed`, `private_address_refused`, `host_did_not_resolve`, `redirect_refused`, `too_many_redirects`, `media_too_large` | `fetchPublic()` refused the URL. TypeScript throws these as `PrismUrlRefused`, a `PrismError` subclass; Python raises `PrismError`. |
| `unfetchable_media` | The fetched URL answered with an error status. |

To test audio code without a provider, inject a fake transport. In TypeScript, audio goes through the provider's `binaryTransport`; in Python, through the ordinary `transport`. See [Testing](/core-concepts/testing).
