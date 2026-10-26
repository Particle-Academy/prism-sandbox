# Testing

Inject a fake transport in the provider config to exercise request building and response parsing without a live provider. No credentials or network connection are needed. The ports do not use Laravel's facade fake or PHPUnit assertions.

Every provider (`openai`, `anthropic`, `mistral`) takes the same transport keys:

| Key (TypeScript) | Key (Python) | Carries |
|---|---|---|
| `transport` | `transport` | JSON requests: text, structured, embeddings, images, moderation. In Python also speech, transcription, files and batch. |
| `streamTransport` | `stream_transport` | Streamed text (`asStream()` / `as_stream()`). |
| `binaryTransport` | — | TypeScript only: speech, transcription, files and batch, whose bodies are bytes or multipart forms. |

A capability whose transport you did not replace uses the real network one, so inject every transport the code under test touches.

## Return a recorded response

The fake checks the outgoing prompt and returns the OpenAI Responses API shape.

::: language-group
```ts
import assert from 'node:assert/strict';
import { Prism, type HttpTransport } from '@particle-academy/prism';

const transport: HttpTransport = async (request) => {
  assert.ok(request.body.includes('Hello'));
  const body = {
    id: 'resp_test', status: 'completed', model: 'gpt-4o',
    output: [{ type: 'message', status: 'completed',
      content: [{ type: 'output_text', text: 'Hi!' }] }],
    usage: { input_tokens: 1, output_tokens: 2 },
  };
  return { status: 200, headers: {}, body, rawBody: JSON.stringify(body) };
};

const response = await Prism.text()
  .using('openai', 'gpt-4o', { apiKey: 'test-only', transport })
  .withPrompt('Hello')
  .asText();
assert.equal(response.text, 'Hi!');
```

```python
import json
from prism import HttpRequest, HttpResponse, Prism

class FakeTransport:
    def send(self, request: HttpRequest) -> HttpResponse:
        assert b"Hello" in request.body
        body = {
            "id": "resp_test", "status": "completed", "model": "gpt-4o",
            "output": [{"type": "message", "status": "completed",
                "content": [{"type": "output_text", "text": "Hi!"}]}],
            "usage": {"input_tokens": 1, "output_tokens": 2},
        }
        return HttpResponse(status=200, body=json.dumps(body).encode())

response = (
    Prism.text()
    .using("openai", "gpt-4o", {"api_key": "test-only", "transport": FakeTransport()})
    .with_prompt("Hello")
    .as_text()
)
assert response.text == "Hi!"
```
:::

## Stream a recorded response

A stream transport returns the response body as chunks of server-sent events. The chunks do not have to line up with events, so you can also test a payload split mid-JSON.

::: language-group
```ts
import assert from 'node:assert/strict';
import { Prism, TextDeltaEvent, type HttpStreamTransport } from '@particle-academy/prism';

async function* chunks(): AsyncGenerator<string> {
  yield 'data: {"type":"response.created","response":{"model":"gpt-4o"}}\n\n';
  yield 'data: {"type":"response.output_text.delta","delta":"Hel","item_id":"m1"}\n\n';
  yield 'data: {"type":"response.output_text.delta","delta":"lo","item_id":"m1"}\n\n';
  yield 'data: {"type":"response.completed","response":{"status":"completed","usage":{"input_tokens":5,"output_tokens":2}}}\n\n';
}

const streamTransport: HttpStreamTransport = async () => ({ status: 200, headers: {}, chunks: chunks() });

let text = '';
for await (const event of Prism.text()
  .using('openai', 'gpt-4o', { apiKey: 'test-only', streamTransport })
  .withPrompt('Hello')
  .asStream()) {
  if (event instanceof TextDeltaEvent) {
    text += event.delta;
  }
}
assert.equal(text, 'Hello');
```

```python
from prism import HttpRequest, Prism
from prism.http import HttpStreamResponse
from prism.streaming.events import TextDeltaEvent

CHUNKS = [
    'data: {"type":"response.created","response":{"model":"gpt-4o"}}\n\n',
    'data: {"type":"response.output_text.delta","delta":"Hel","item_id":"m1"}\n\n',
    'data: {"type":"response.output_text.delta","delta":"lo","item_id":"m1"}\n\n',
    'data: {"type":"response.completed","response":{"status":"completed",'
    '"usage":{"input_tokens":5,"output_tokens":2}}}\n\n',
]

class FakeStreamTransport:
    def stream(self, request: HttpRequest) -> HttpStreamResponse:
        return HttpStreamResponse(status=200, headers={}, chunks=iter(CHUNKS))

events = (
    Prism.text()
    .using("openai", "gpt-4o", {"api_key": "test-only", "stream_transport": FakeStreamTransport()})
    .with_prompt("Hello")
    .as_stream()
)
text = "".join(event.delta for event in events if isinstance(event, TextDeltaEvent))
assert text == "Hello"
```
:::

## Audio, files and batch

In TypeScript these capabilities go through `binaryTransport`. Its request carries a `multipart` form when one is uploaded, and its response is raw `bytes`. In Python they use the ordinary `transport`; a multipart upload arrives already encoded in `request.body`.

This fake answers a transcription request:

::: language-group
```ts
import assert from 'node:assert/strict';
import { Audio, Prism, type HttpBinaryTransport } from '@particle-academy/prism';

const binaryTransport: HttpBinaryTransport = async (request) => {
  assert.ok(request.url.endsWith('/audio/transcriptions'));
  assert.equal(request.multipart?.fields.model, 'whisper-1');
  assert.equal(request.multipart?.files[0]?.filename, 'visit.mp3');
  return {
    status: 200,
    headers: { 'content-type': 'application/json' },
    bytes: new TextEncoder().encode(JSON.stringify({ text: 'Hello there' })),
  };
};

const response = await Prism.audio()
  .using('openai', 'whisper-1', { apiKey: 'test-only', binaryTransport })
  .withInput(Audio.fromRawContent(new Uint8Array([1, 2, 3]), 'audio/mpeg').as('visit.mp3'))
  .asText();
assert.equal(response.text, 'Hello there');
```

```python
import json
from prism import Audio, HttpRequest, HttpResponse, Prism

class FakeTransport:
    def send(self, request: HttpRequest) -> HttpResponse:
        assert request.url.endswith("/audio/transcriptions")
        assert request.headers["Content-Type"].startswith("multipart/form-data")
        assert b'filename="visit.mp3"' in (request.body or b"")
        return HttpResponse(status=200, body=json.dumps({"text": "Hello there"}).encode())

response = (
    Prism.audio()
    .using("openai", "whisper-1", {"api_key": "test-only", "transport": FakeTransport()})
    .with_input(Audio.from_raw_content(b"\x01\x02\x03", "audio/mpeg").as_("visit.mp3"))
    .as_text()
)
assert response.text == "Hello there"
```
:::

For speech, return the audio bytes and a `content-type` header; `response.audio.mimeType()` / `mime_type()` takes its value from that header.

## Test failure paths

Return error statuses and malformed bodies to check how your application reacts. An HTTP error becomes `PrismError` with code `provider_response_error` and the status on `httpStatus` (TypeScript) / `status` (Python).

::: language-group
```ts
import assert from 'node:assert/strict';
import { Prism, PrismError, type HttpTransport } from '@particle-academy/prism';

const transport: HttpTransport = async () => {
  const body = { error: { message: 'Rate limit reached' } };
  return { status: 429, headers: {}, body, rawBody: JSON.stringify(body) };
};

await assert.rejects(
  Prism.text().using('openai', 'gpt-4o', { apiKey: 'test-only', transport }).withPrompt('Hello').asText(),
  (error: unknown) =>
    error instanceof PrismError && error.code === 'provider_response_error' && error.httpStatus === 429,
);
```

```python
import json
from prism import HttpRequest, HttpResponse, Prism, PrismError

class RateLimitedTransport:
    def send(self, request: HttpRequest) -> HttpResponse:
        body = {"error": {"message": "Rate limit reached"}}
        return HttpResponse(status=429, body=json.dumps(body).encode())

try:
    (
        Prism.text()
        .using("openai", "gpt-4o", {"api_key": "test-only", "transport": RateLimitedTransport()})
        .with_prompt("Hello")
        .as_text()
    )
    raise AssertionError("expected a PrismError")
except PrismError as error:
    assert error.code == "provider_response_error"
    assert error.status == 429
```
:::

Also assert that unsupported operations fail (code `unsupported_provider_action`) rather than treating an empty result as a valid fallback.
