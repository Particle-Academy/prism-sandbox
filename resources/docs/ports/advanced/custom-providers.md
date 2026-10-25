# Custom providers

A provider is a class that extends the port's `Provider` base class, registered under a key you choose. After registration, `using('your-key', model, config)` builds it like any built-in provider. Implement only the capabilities your provider supports. The base class answers everything else with a `PrismError` whose code is `unsupported_provider_action`.

## A minimal provider

This provider sends text requests to an in-house completion service that takes `{ model, prompt, max_tokens }` and returns `{ id, text, input_tokens, output_tokens }`. The service and its URL are placeholders; replace them with your own protocol.

::: language-group
```ts
// in-house.ts
import {
  AssistantMessage,
  FinishReason,
  Meta,
  PrismError,
  Provider,
  TextResponse,
  Usage,
  UserMessage,
  fetchTransport,
  registerProvider,
} from '@particle-academy/prism';
import type { HttpTransport, TextRequest } from '@particle-academy/prism';

export interface InHouseConfig {
  apiKey?: string;
  url?: string;
  transport?: HttpTransport;
}

interface InHouseReply {
  id?: string;
  text?: unknown;
  input_tokens?: number;
  output_tokens?: number;
}

export class InHouse extends Provider {
  override readonly providerName = 'InHouse';

  readonly #apiKey: string;
  readonly #url: string;
  readonly #transport: HttpTransport;

  constructor(config: InHouseConfig = {}) {
    super();
    this.#apiKey = config.apiKey ?? process.env.IN_HOUSE_API_KEY ?? '';
    this.#url = config.url ?? 'https://llm.internal.example/v1/complete';
    this.#transport = config.transport ?? fetchTransport;
  }

  override async text(request: TextRequest): Promise<TextResponse> {
    if (request.tools().length > 0) {
      throw PrismError.unsupportedProviderAction('tools', this.providerName);
    }

    const prompt = request
      .messages()
      .filter((message): message is UserMessage => message instanceof UserMessage)
      .map((message) => message.content)
      .join('\n\n');

    const response = await this.#transport({
      url: this.#url,
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${this.#apiKey}` },
      body: JSON.stringify({ model: request.model(), prompt, max_tokens: request.maxTokens() }),
      clientOptions: request.clientOptions(),
    });

    if (response.status >= 400) {
      throw PrismError.providerResponseError(`InHouse error [${response.status}]`, {
        httpStatus: response.status,
        responseBody: response.rawBody,
      });
    }

    const reply = response.body as InHouseReply | null;

    if (reply === null || typeof reply.text !== 'string') {
      throw PrismError.providerResponseError('InHouse returned no text.', {
        httpStatus: response.status,
        responseBody: response.rawBody,
      });
    }

    return new TextResponse({
      steps: [],
      text: reply.text,
      finishReason: FinishReason.Stop,
      usage: new Usage(reply.input_tokens ?? 0, reply.output_tokens ?? 0),
      meta: new Meta(reply.id ?? '', request.model()),
      messages: [...request.messages(), new AssistantMessage(reply.text)],
    });
  }
}

registerProvider('in-house', (config) => new InHouse(config as InHouseConfig));
```

```python
# in_house.py
from __future__ import annotations

import json
import os
from typing import Any

from prism import (
    AssistantMessage,
    FinishReason,
    HttpRequest,
    Meta,
    PrismError,
    Provider,
    Request,
    Response,
    Transport,
    UrllibTransport,
    Usage,
    UserMessage,
    register_provider,
)


class InHouse(Provider):
    def __init__(
        self,
        api_key: str | None = None,
        url: str | None = None,
        transport: Transport | None = None,
        timeout: float = 30.0,
    ) -> None:
        self._api_key = api_key if api_key is not None else os.environ.get("IN_HOUSE_API_KEY", "")
        self._url = url or "https://llm.internal.example/v1/complete"
        self._transport: Transport = transport or UrllibTransport()
        self._timeout = timeout

    def text(self, request: Request) -> Response:
        if request.tools:
            raise PrismError.unsupported_provider_action("tools", "InHouse")

        prompt = "\n\n".join(
            message.content for message in request.messages if isinstance(message, UserMessage)
        )

        response = self._transport.send(
            HttpRequest(
                "POST",
                self._url,
                headers={
                    "Content-Type": "application/json",
                    "Authorization": f"Bearer {self._api_key}",
                },
                body=json.dumps(
                    {"model": request.model, "prompt": prompt, "max_tokens": request.max_tokens}
                ).encode("utf-8"),
                timeout=self._timeout,
            )
        )

        if response.status >= 400:
            raise PrismError.provider_response_error(
                f"InHouse error [{response.status}]",
                status=response.status,
                body=response.body.decode("utf-8", "replace"),
            )

        try:
            reply: Any = json.loads(response.body)
        except ValueError:
            reply = None

        if not isinstance(reply, dict) or not isinstance(reply.get("text"), str):
            raise PrismError.provider_response_error(
                "InHouse returned no text.",
                status=response.status,
                body=response.body.decode("utf-8", "replace"),
            )

        text: str = reply["text"]

        return Response(
            steps=[],
            text=text,
            finish_reason=FinishReason.STOP,
            tool_calls=[],
            tool_results=[],
            usage=Usage(reply.get("input_tokens", 0), reply.get("output_tokens", 0)),
            meta=Meta(reply.get("id", ""), request.model),
            messages=[*request.messages, AssistantMessage(text)],
        )


register_provider("in-house", InHouse)
```
:::

Registration is process-wide. Import the module once during application start-up, and register only keys your code chooses: never build a provider key from user input.

The configuration passed to `using()` reaches your constructor. In TypeScript the factory receives it as one object; in Python the factory is called with it as keyword arguments, so a class with keyword parameters can be registered directly.

## Use and test it

Inject a transport to test the provider without network access.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';
import type { HttpTransport } from '@particle-academy/prism';
import './in-house.js';

const recorded: HttpTransport = async () => ({
  status: 200,
  headers: {},
  body: { id: 'reply-1', text: 'Hello from in-house.', input_tokens: 4, output_tokens: 5 },
  rawBody: '',
});

const response = await Prism.text()
  .using('in-house', 'house-small', { transport: recorded })
  .withPrompt('Say hello.')
  .asText();

console.log(response.text, response.usage.completionTokens);
```

```python
import json

from prism import HttpRequest, HttpResponse, Prism

import in_house  # noqa: F401  (registers "in-house")


class RecordedTransport:
    def send(self, request: HttpRequest) -> HttpResponse:
        reply = {"id": "reply-1", "text": "Hello from in-house.", "input_tokens": 4, "output_tokens": 5}
        return HttpResponse(200, json.dumps(reply).encode("utf-8"))


response = (
    Prism.text()
    .using("in-house", "house-small", {"transport": RecordedTransport()})
    .with_prompt("Say hello.")
    .as_text()
)

print(response.text, response.usage.completion_tokens)
```
:::

## Methods you can override

Every method takes the request object the builder produced. TypeScript methods return a `Promise` (an async generator for `stream`); Python methods are synchronous (`stream` returns an iterator).

| TypeScript | Python | Called by | Returns |
|---|---|---|---|
| `text(request)` | `text(request)` | `Prism.text()…asText()` | `TextResponse` / `Response` |
| `stream(request)` | `stream(request)` | `asStream()` / `as_stream()` | stream events |
| `structured(request)` | `structured(request)` | `Prism.structured()…asStructured()` | `StructuredResponse` |
| `embeddings(request)` | `embeddings(request)` | `Prism.embeddings()` | `EmbeddingsResponse` |
| `images(request)` | `images(request)` | `Prism.images()` | `ImagesResponse` |
| `moderation(request)` | `moderation(request)` | `Prism.moderation()` | `ModerationResponse` |
| `textToSpeech(request)` | `text_to_speech(request)` | `Prism.audio()…asAudio()` | `AudioResponse` |
| `speechToText(request)` | `speech_to_text(request)` | `Prism.audio()…asText()` | `AudioTextResponse` |
| `fim(request)` | `fim(request)` | `Prism.fim()` | `FimResponse` |
| `uploadFile(request)` | `upload_file(request)` | `Prism.files()` | `FileData` |
| `listFiles(request)` | `list_files(request)` | `Prism.files()` | `FileListResult` |
| `getFileMetadata(request)` | `get_file_metadata(request)` | `Prism.files()` | `FileData` |
| `deleteFile(request)` | `delete_file(request)` | `Prism.files()` | `DeleteFileResult` |
| `downloadFile(request)` | `download_file(request)` | `Prism.files()` | bytes |
| `batch(request)` | `batch(request)` | `Prism.batch()` | `BatchJob` |
| `retrieveBatch(request)` | `retrieve_batch(request)` | `Prism.batch()` | `BatchJob` |
| `listBatches(request)` | `list_batches(request)` | `Prism.batch()` | `BatchListResult` |
| `getBatchResults(request)` | `get_batch_results(request)` | `Prism.batch()` | list of `BatchResultItem` |
| `cancelBatch(request)` | `cancel_batch(request)` | `Prism.batch()` | `BatchJob` |

TypeScript providers must also set `providerName`, which appears in error messages. Python uses the class name.

## Refusing what you don't support

Leave a method unimplemented and the base class raises `unsupported_provider_action` for it. To refuse part of a capability, as the example does for tools, raise the same error yourself, so callers can handle it the same way:

- TypeScript: `throw PrismError.unsupportedProviderAction('tools', this.providerName);`
- Python: `raise PrismError.unsupported_provider_action("tools", "InHouse")`

Refuse anything you cannot honour (tools, media parts, provider options) instead of silently ignoring it. A request that quietly drops a tool or an image looks like a model that chose not to use it.

## Reuse a built-in adapter

To point a built-in adapter at a different deployment, register a factory that configures it, instead of writing a new provider:

::: language-group
```ts
import { OpenAI, Prism, registerProvider } from '@particle-academy/prism';

registerProvider('openai-eu', (config) => new OpenAI({ url: 'https://eu.api.openai.com/v1', ...config }));

const request = Prism.text()
  .using('openai-eu', 'gpt-4o')
  .withPrompt('Hello!')
  .toRequest();
console.log(request.model());
```

```python
from prism import OpenAI, Prism, register_provider

register_provider(
    "openai-eu",
    lambda **config: OpenAI(**{"url": "https://eu.api.openai.com/v1", **config}),
)

request = (
    Prism.text()
    .using("openai-eu", "gpt-4o")
    .with_prompt("Hello!")
    .to_request()
)
print(request.model)
```
:::

The OpenAI adapter uses the Responses API. An endpoint that advertises OpenAI compatibility may only implement Chat Completions, so a different URL alone does not make it work. Test against the real endpoint, or write a provider for its protocol.
