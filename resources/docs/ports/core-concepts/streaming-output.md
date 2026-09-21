# Streaming output

Streaming emits typed events as the provider responds. TypeScript uses an async iterator; Python uses a synchronous iterator. OpenAI, Anthropic and Mistral have stream implementations.

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
from prism.streaming.events import TextDeltaEvent

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

## Forwarding a stream

The iterator is a provider event stream, not an HTTP response. Your web framework owns SSE headers, cancellation and client disconnect handling. Laravel helpers such as `asEventStreamResponse()` are specific to the PHP integration.

Handle stream error and completion events as well as text. Receiving a text delta alone does not prove that generation completed successfully. Do not treat a tool-call event as permission to execute a tool; use the [Harness](/packages/harness) for managed tool workflows.
