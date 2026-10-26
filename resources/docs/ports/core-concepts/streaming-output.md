# Streaming output

Streaming emits typed events as the provider responds. TypeScript uses an async iterator; Python uses a synchronous iterator. OpenAI, Anthropic and Mistral support streaming in both ports.

## Consume text deltas

::: language-group
```ts
import { Prism, TextDeltaEvent } from '@particle-academy/prism';

const stream = Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Explain how solar panels work.')
  .asStream();

for await (const event of stream) {
  if (event instanceof TextDeltaEvent) {
    process.stdout.write(event.delta);
  }
}
```

```python
from prism import Prism
from prism.streaming import TextDeltaEvent

stream = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Explain how solar panels work.")
    .as_stream()
)

for event in stream:
    if isinstance(event, TextDeltaEvent):
        print(event.delta, end="", flush=True)
```
:::

The stream accumulates nothing. To keep the full text, collect the deltas yourself or read `TextCompleteEvent.text`.

In Python, the event classes are in `prism.streaming`, not the top-level `prism` package. In TypeScript they are exported from `@particle-academy/prism`.

## Event types

Every event has an `id` and a `timestamp`, and a `type()` method that returns a `StreamEventType` value. TypeScript timestamps are in milliseconds; Python timestamps are in whole seconds.

| Class | `type()` | Fields (TypeScript / Python) | Emitted |
|---|---|---|---|
| `StreamStartEvent` | `stream_start` | `model` | Once, when the provider opens the response. |
| `TextStartEvent` | `text_start` | `messageId` / `message_id` | When a text block begins. |
| `TextDeltaEvent` | `text_delta` | `delta`, `messageId` / `message_id` | For each piece of text. |
| `TextCompleteEvent` | `text_complete` | `text`, `messageId` / `message_id` | When a text block ends, with its full text. |
| `ToolCallEvent` | `tool_call` | `toolCall` / `tool_call` (a `ToolCall`), `messageId` / `message_id` | When a tool call's arguments are complete. See [Tools](/core-concepts/tools-function-calling#receive-a-tool-call). |
| `ErrorEvent` | `error` | `code`, `message`, `recoverable` | When the provider reports an error mid-stream. |
| `StreamEndEvent` | `stream_end` | `finishReason` / `finish_reason`, `usage` | Once, at the end. `usage` is null when the provider reported none. |

`StreamEventType` members: TypeScript `StreamStart`, `TextStart`, `TextDelta`, `TextComplete`, `ToolCall`, `Error`, `StreamEnd`; Python `STREAM_START`, `TEXT_START` and so on. Each event's `toObject()` / `to_dict()` returns a JSON-ready form with snake_case keys.

## Handle errors and the end of the stream

A stream can fail in two ways, and you must handle both:

- **The request is refused** (HTTP 4xx or 5xx). The call throws `PrismError` with code `provider_response_error`. In TypeScript this happens on the first iteration of the `for await` loop. In Python it happens when `as_stream()` is called, before you iterate.
- **The provider fails mid-stream** (OpenAI and Anthropic). You receive an `ErrorEvent`. Its `code` is the provider's own error code (for example `server_error`), not a `PrismError` code. Anthropic streams that end without a closing message produce an `ErrorEvent` with code `incomplete_stream`, followed by `StreamEndEvent`.

Receiving text deltas alone does not prove that generation finished. Check `StreamEndEvent`: a stream that reached the token limit ends with `finishReason` `Length` and does **not** throw `max_tokens_exceeded`, for any provider.

::: language-group
```ts
import {
  ErrorEvent,
  FinishReason,
  Prism,
  PrismError,
  StreamEndEvent,
  TextDeltaEvent,
} from '@particle-academy/prism';

let text = '';
let completed = false;

try {
  for await (const event of Prism.text().using('anthropic', 'claude-sonnet-4-5').withPrompt('Hi!').asStream()) {
    if (event instanceof TextDeltaEvent) {
      text += event.delta;
    } else if (event instanceof ErrorEvent) {
      console.error(`Stream failed: ${event.code} ${event.message}`);
    } else if (event instanceof StreamEndEvent) {
      completed = event.finishReason === FinishReason.Stop;
    }
  }
} catch (error) {
  if (error instanceof PrismError) {
    console.error(error.code, error.httpStatus);
  } else {
    throw error;
  }
}

console.log(completed ? text : 'Incomplete answer');
```

```python
from prism import FinishReason, Prism, PrismError
from prism.streaming import ErrorEvent, StreamEndEvent, TextDeltaEvent

text = ""
completed = False

try:
    for event in Prism.text().using("anthropic", "claude-sonnet-4-5").with_prompt("Hi!").as_stream():
        if isinstance(event, TextDeltaEvent):
            text += event.delta
        elif isinstance(event, ErrorEvent):
            print(f"Stream failed: {event.code} {event.message}")
        elif isinstance(event, StreamEndEvent):
            completed = event.finish_reason is FinishReason.STOP
except PrismError as error:
    print(error.code, error.status)

print(text if completed else "Incomplete answer")
```
:::

## Tool calls

Streaming is how the ports deliver tool calls. A non-streaming request whose response ends on tool calls throws `tool_loop_not_supported`; a streamed one emits a `ToolCallEvent` for each call. Detect tool calls by that event, not by the finish reason: Anthropic and Mistral end such a stream with `ToolCalls`, but OpenAI ends it with `Stop`. See [Receive a tool call](/core-concepts/tools-function-calling#receive-a-tool-call).

A tool-call event is not permission to execute a tool. Use the [Harness](/packages/harness) for managed tool workflows.

## Forwarding a stream

The iterator is a provider event stream, not an HTTP response. Your web framework owns SSE headers, cancellation and client disconnect handling. `toObject()` / `to_dict()` gives each event a JSON form you can forward. Laravel helpers such as `asEventStreamResponse()` are specific to the PHP integration.

## Testing a stream

Streaming does not use the `transport` configuration key. Inject `streamTransport` (TypeScript) or `stream_transport` (Python), which returns the status, headers and an iterable of raw SSE text chunks. The chunks do not need to align with event boundaries.

::: language-group
```ts
import { Prism, TextDeltaEvent, type HttpStreamTransport } from '@particle-academy/prism';

const sse = [
  'data: {"type":"response.created","response":{"model":"gpt-4o"}}\n\n',
  'data: {"type":"response.output_text.delta","item_id":"msg_1","delta":"Hi"}\n\n',
  'data: {"type":"response.completed","response":{"status":"completed"}}\n\n',
];

const streamTransport: HttpStreamTransport = async () => ({
  status: 200,
  headers: {},
  chunks: (async function* () {
    yield* sse;
  })(),
});

for await (const event of Prism.text()
  .using('openai', 'gpt-4o', { apiKey: 'test-only', streamTransport })
  .withPrompt('Hello')
  .asStream()) {
  if (event instanceof TextDeltaEvent) {
    console.log(event.delta);
  }
}
```

```python
from prism import Prism
from prism.http import HttpRequest, HttpStreamResponse
from prism.streaming import TextDeltaEvent

SSE = [
    'data: {"type":"response.created","response":{"model":"gpt-4o"}}\n\n',
    'data: {"type":"response.output_text.delta","item_id":"msg_1","delta":"Hi"}\n\n',
    'data: {"type":"response.completed","response":{"status":"completed"}}\n\n',
]

class FakeStreamTransport:
    def stream(self, request: HttpRequest) -> HttpStreamResponse:
        return HttpStreamResponse(status=200, headers={}, chunks=iter(SSE))

stream = (
    Prism.text()
    .using("openai", "gpt-4o", {"api_key": "test-only", "stream_transport": FakeStreamTransport()})
    .with_prompt("Hello")
    .as_stream()
)

for event in stream:
    if isinstance(event, TextDeltaEvent):
        print(event.delta)
```
:::

See [Testing](/core-concepts/testing) for non-streaming fakes.
