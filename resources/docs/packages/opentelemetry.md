# Prism OpenTelemetry

A bridge from Prism's telemetry events to
[GenAI-convention](https://opentelemetry.io/docs/specs/semconv/gen-ai/)
OpenTelemetry spans — a root span per generation, child spans per step and per
tool call, exported over OTLP to
[Arize Phoenix](https://github.com/Arize-ai/phoenix), Jaeger, Tempo, Grafana or
anything else that speaks OTLP. Spans carry both the OpenTelemetry `gen_ai.*`
attributes and the [OpenInference](https://github.com/Arize-ai/openinference)
attributes Phoenix uses to render LLM traces.

::: tip PHP telemetry events
See [Telemetry](/advanced/telemetry) for the PHP generation event stream.
TypeScript and Python use the subscriber API shown below.
:::

## Integration

In PHP, the bridge subscribes to Laravel events emitted by Prism. TypeScript
and Python expose subscriber methods that your application calls at generation,
step and tool boundaries, and write spans through a small `Tracer` interface
that you connect to your OpenTelemetry SDK.

The PHP event flow is:

```
Prism core (events)                 →  this bridge          →  OTLP backend
Events\Telemetry\GenerationStarted     builds root span        Arize Phoenix,
                 \StepCompleted        + child step spans      Jaeger, Tempo,
                 \ToolInvoked          + child tool spans      Grafana, …
                 \GenerationCompleted  ends the root span
                 \GenerationFailed     ends w/ error status
```

## Installation

::: language-group
```bash [language=php]
composer require particle-academy/prism-opentelemetry

# Plus an OpenTelemetry SDK and an OTLP exporter — transport of your choice.
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

```bash [language=typescript]
npm install @particle-academy/prism-opentelemetry

# Plus the OpenTelemetry API and an SDK + exporter of your choice, e.g.
npm install @opentelemetry/api @opentelemetry/sdk-node @opentelemetry/exporter-trace-otlp-proto
```

```bash [language=python]
pip install prism-ai-opentelemetry

# Plus the OpenTelemetry SDK and an exporter of your choice, e.g.
pip install opentelemetry-sdk opentelemetry-exporter-otlp
```
:::

The TypeScript and Python packages have no dependencies of their own; the
OpenTelemetry packages above are what the adapter under **Connect the tracer**,
below, talks to.

For PHP, enable Prism telemetry; it is disabled by default:

```dotenv
PRISM_TELEMETRY_ENABLED=true
```

Configure the PHP OpenTelemetry SDK to export to your collector:

```dotenv
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:6006   # Phoenix OTLP endpoint
```

The PHP bridge takes its tracer from the globally registered OpenTelemetry
tracer provider (`Globals::tracerProvider()`), so the SDK autoload settings
above are all it needs. If the OpenTelemetry API is not installed, the bridge
does nothing.

The PHP package requires PHP 8.2+ and `particle-academy/prism` 0.111 or newer.
These environment settings do not apply to TypeScript or Python.

## Connect the tracer (TypeScript and Python)

The TypeScript and Python subscribers do not import OpenTelemetry. They write
through the package's own `Tracer` interface — one method, `startSpan` /
`start_span` — and a `Span` with `setAttribute`, `setStatus`,
`recordException` and `end`. Timestamps are epoch **nanoseconds**, and a child
span receives its parent as the package's own `Span` object.

Add this adapter once to connect that interface to the real OpenTelemetry API:

::: language-group
```ts
// prism-otel.ts
import {
  context,
  trace,
  SpanKind,
  SpanStatusCode,
  type Span as OtelSpan,
  type Tracer as OtelTracer,
} from '@opentelemetry/api';
import type { AttributeValue, Span, StartSpanOptions, Tracer } from '@particle-academy/prism-opentelemetry';

// The subscriber works in epoch nanoseconds; @opentelemetry/api takes epoch milliseconds.
const millis = (nanos?: number): number | undefined =>
  nanos === undefined ? undefined : nanos / 1_000_000;

class PrismSpan implements Span {
  readonly inner: OtelSpan;

  constructor(inner: OtelSpan) {
    this.inner = inner;
  }

  setAttribute(key: string, value: AttributeValue): void {
    this.inner.setAttribute(key, typeof value === 'object' ? [...value] : value);
  }

  setStatus(status: { code: 'ok' | 'error'; message?: string }): void {
    this.inner.setStatus(
      status.code === 'ok'
        ? { code: SpanStatusCode.OK }
        : { code: SpanStatusCode.ERROR, message: status.message },
    );
  }

  recordException(error: unknown): void {
    this.inner.recordException(error instanceof Error ? error : String(error));
  }

  end(endTimeNanos?: number): void {
    this.inner.end(millis(endTimeNanos));
  }
}

export function prismTracer(tracer: OtelTracer): Tracer {
  return {
    startSpan(name: string, options: StartSpanOptions = {}): Span {
      const parent = options.parent instanceof PrismSpan ? options.parent.inner : null;

      // A root span joins whatever span is active in your app (an HTTP request, a job).
      const ctx = parent === null ? context.active() : trace.setSpan(context.active(), parent);

      const span = tracer.startSpan(
        name,
        {
          kind: parent === null || options.kind === 'client' ? SpanKind.CLIENT : SpanKind.INTERNAL,
          startTime: millis(options.startTimeNanos),
        },
        ctx,
      );

      return new PrismSpan(span);
    },
  };
}
```

```python
# prism_otel.py
from __future__ import annotations

from collections.abc import Sequence

from opentelemetry import trace
from opentelemetry.trace import SpanKind, Status, StatusCode

from prism_opentelemetry import AttributeValue, Span


class PrismSpan:
    def __init__(self, inner: trace.Span) -> None:
        self.inner = inner

    def set_attribute(self, key: str, value: AttributeValue) -> None:
        if isinstance(value, Sequence) and not isinstance(value, str):
            value = list(value)
        self.inner.set_attribute(key, value)

    def set_status(self, code: str, message: str | None = None) -> None:
        if code == "ok":
            self.inner.set_status(Status(StatusCode.OK))
        else:
            self.inner.set_status(Status(StatusCode.ERROR, message))

    def record_exception(self, error: BaseException | None) -> None:
        if error is not None:
            self.inner.record_exception(error)

    def end(self, end_time_nanos: int | None = None) -> None:
        # Both sides use epoch nanoseconds, so the timestamp passes straight through.
        self.inner.end(end_time=end_time_nanos)


class PrismTracer:
    def __init__(self, tracer: trace.Tracer) -> None:
        self._tracer = tracer

    def start_span(
        self, name: str, start_time_nanos: int | None = None, parent: Span | None = None
    ) -> PrismSpan:
        # A root span joins whatever span is active in your app (a request, a job).
        context = (
            trace.set_span_in_context(parent.inner) if isinstance(parent, PrismSpan) else None
        )

        return PrismSpan(
            self._tracer.start_span(
                name,
                context=context,
                kind=SpanKind.CLIENT if context is None else SpanKind.INTERNAL,
                start_time=start_time_nanos,
            )
        )
```
:::

Configure the OpenTelemetry SDK and exporter as usual for your runtime, then
pass `prismTracer(trace.getTracer('prism'))` /
`PrismTracer(trace.get_tracer("prism"))` to the subscriber.

## Instrument a generation

PHP subscribes to Prism's Laravel events when the package is enabled; there is
nothing to call. In TypeScript and Python, call the subscriber at each
boundary of a generation, keyed by a trace id you choose:

| Call | When | Records |
| --- | --- | --- |
| `onGenerationStarted(context, input?, tools?)` | before the provider request | opens the root span |
| `onToolInvoked(traceId, tool)` | after each tool call | a tool span, buffered until its step completes |
| `onStepCompleted(traceId, stepIndex, model, provider, usage?)` | after each model turn | a step span, parent of that step's tool spans |
| `onGenerationCompleted(traceId, { finishReason, usage, output, rateLimits })` | on success | finish reason, usage, rate limits, output; ends the root span |
| `onGenerationFailed(traceId, error)` | on failure | error status, the exception, rate limits carried by the error; ends the root span |

Python uses the same calls in snake case (`on_generation_started`, …), with
keyword arguments `finish_reason`, `usage`, `output` and `rate_limits` on
`on_generation_completed`.

::: language-group
```php
use Prism\Prism\Facades\Prism;

$response = Prism::text()
    ->using('anthropic', 'claude-sonnet-4-5')
    ->withPrompt('Summarise the incident.')
    ->asText(); // the bridge records Prism's emitted telemetry events
```

```ts
import { trace } from '@opentelemetry/api';
import {
  TelemetrySubscriber,
  advertisedToolDigest,
  type AdvertisedTool,
} from '@particle-academy/prism-opentelemetry';
import { prismTracer } from './prism-otel';

const telemetry = new TelemetrySubscriber(prismTracer(trace.getTracer('prism')));

const searchTool = {
  name: 'search_docs',
  description: 'Search the product documentation.',
  parameters: { type: 'object', properties: { query: { type: 'string' } } },
};

// The tools the model was offered, in the order they were sent.
const tools: AdvertisedTool[] = [
  { ...searchTool, digest: advertisedToolDigest(searchTool) },
];

telemetry.onGenerationStarted(
  { traceId: 'run-42', operation: 'text', provider: 'anthropic', model: 'claude-sonnet-4-5', sessionId: 'conv-7' },
  prompt,
  tools,
);

telemetry.onToolInvoked('run-42', {
  name: 'search_docs',
  callId: 'call_1',
  stepIndex: 0,
  parameters: { query: 'refund policy' },
  result: 'Refunds are issued within 14 days.',
  failed: false,
  error: null,
});

telemetry.onStepCompleted('run-42', 0, 'claude-sonnet-4-5', 'anthropic', {
  promptTokens: 812,
  completionTokens: 64,
  cacheReadInputTokens: 2048,
});

telemetry.onGenerationCompleted('run-42', {
  finishReason: 'stop',
  usage: { promptTokens: 1650, completionTokens: 210, cost: 0.0081 },
  output: responseText,
  rateLimits: [{ name: 'requests', limit: 1000, remaining: 998, resetsAt: new Date(Date.now() + 60_000) }],
});

// Or, when the generation throws:
telemetry.onGenerationFailed('run-43', error);
```

```python
from datetime import datetime, timedelta, timezone

from opentelemetry import trace
from prism_opentelemetry import (
    AdvertisedTool,
    GenerationContext,
    PendingTool,
    RateLimit,
    TelemetrySubscriber,
    Usage,
    advertised_tool_digest,
)

from prism_otel import PrismTracer

telemetry = TelemetrySubscriber(PrismTracer(trace.get_tracer("prism")))

schema = {"type": "object", "properties": {"query": {"type": "string"}}}
# The tools the model was offered, in the order they were sent.
tools = [
    AdvertisedTool(
        name="search_docs",
        digest=advertised_tool_digest("search_docs", "Search the product documentation.", schema),
        description="Search the product documentation.",
        parameters=schema,
    )
]

telemetry.on_generation_started(
    GenerationContext(
        trace_id="run-42",
        operation="text",
        provider="anthropic",
        model="claude-sonnet-4-5",
        session_id="conv-7",
    ),
    prompt,
    tools,
)

telemetry.on_tool_invoked(
    "run-42",
    PendingTool(
        name="search_docs",
        call_id="call_1",
        step_index=0,
        parameters={"query": "refund policy"},
        result="Refunds are issued within 14 days.",
    ),
)

telemetry.on_step_completed(
    "run-42", 0, "claude-sonnet-4-5", "anthropic",
    Usage(prompt_tokens=812, completion_tokens=64, cache_read_input_tokens=2048),
)

telemetry.on_generation_completed(
    "run-42",
    finish_reason="stop",
    usage=Usage(prompt_tokens=1650, completion_tokens=210, cost=0.0081),
    output=response_text,
    rate_limits=[
        RateLimit(
            name="requests",
            limit=1000,
            remaining=998,
            resets_at=datetime.now(timezone.utc) + timedelta(seconds=60),
        )
    ],
)

# Or, when the generation raises:
telemetry.on_generation_failed("run-43", error)
```
:::

Notes on the arguments:

- **`operation`** is written to `gen_ai.operation.name` exactly as you pass
  it and also names the root span (`"<operation> <model>"`). `text` and
  `structured` mark the root as an OpenInference `CHAIN`, `embeddings` as
  `EMBEDDING`, and anything else as `LLM`. `sessionId` / `userId`
  (`session_id` / `user_id`) become `session.id` / `user.id`.
- **`Usage`** takes `promptTokens`, `completionTokens`,
  `cacheReadInputTokens`, `cacheWriteInputTokens`, `thoughtTokens` and `cost`
  (snake case in Python). As in Prism, `promptTokens` *excludes* cached
  tokens; the bridge adds the cache counts back so the exported input token
  count covers every token that went in. A field you leave out (or `null`) is
  not exported, rather than exported as `0`.
- **`RateLimit`** is one provider quota bucket: `name`, `limit`, `remaining`,
  `resetsAt` (a `Date`; `resets_at`, a `datetime`, in Python). A `prism-ts`
  `ProviderRateLimit` already has this shape. In `onGenerationFailed`, rate
  limits are read from the error's own `rateLimits` / `rate_limits` property
  when it has one.
- **Tools** are matched to their step by `stepIndex`. A tool whose step never
  completes is attached to the root span when the generation ends, rather than
  dropped. `failed: true` gives the tool span an error status and records
  `error`.
- **`AdvertisedTool`** is `name` + `digest`, with optional `description` and
  `parameters`. Compute the digest with `advertisedToolDigest` /
  `advertised_tool_digest`: it hashes name, description and parameters the
  same way in all three languages, so a digest from a TypeScript service can be
  compared with one from a PHP service. It is not an MCP trust pin; the two
  hash different inputs.

## Configuration

### PHP

Publish the configuration with
`php artisan vendor:publish --tag=prism-opentelemetry-config`.

| Key | Env | Default | Does |
| --- | --- | --- | --- |
| `enabled` | `PRISM_OTEL_ENABLED` | `true` | Subscribe the span-building listener |
| `tracer_name` | `PRISM_OTEL_TRACER_NAME` | `prism` | Instrumentation scope name |
| `record_exceptions` | `PRISM_OTEL_RECORD_EXCEPTIONS` | `true` | Record the exception on failed spans |
| `content_max_length` | `PRISM_OTEL_CONTENT_MAX_LENGTH` | `65536` | Byte cap on each captured-content attribute; `0` disables the cap |

What content is captured is decided by Prism core's own telemetry settings in
`config/prism.php`, not by this file:

| Key | Env | Default | Does |
| --- | --- | --- | --- |
| `prism.telemetry.enabled` | `PRISM_TELEMETRY_ENABLED` | `false` | Emit telemetry events at all |
| `prism.telemetry.capture_content` | `PRISM_TELEMETRY_CAPTURE_CONTENT` | `false` | Include prompts, completions, tool arguments and results |
| `prism.telemetry.capture_media` | `PRISM_TELEMETRY_CAPTURE_MEDIA` | `false` | Include attachment bytes in captured content |

### TypeScript and Python

Options are passed to the subscriber's constructor — `TelemetryOptions` in
TypeScript, keyword arguments in Python:

| TypeScript | Python | Default | Does |
| --- | --- | --- | --- |
| `captureContent` | `capture_content` | `false` | Export prompts, completions, tool arguments and results, and tool descriptions and schemas |
| `captureMedia` | `capture_media` | `false` | Export attachment bytes inside captured content |
| `maxContentLength` | `max_content_length` | `65536` | Character cap on each captured-content attribute; `0` disables the cap |
| `recordExceptions` | `record_exceptions` | `true` | Record the exception on failed generation and tool spans |
| `now` | `now` | system clock | Clock returning epoch nanoseconds |

::: language-group
```ts
const telemetry = new TelemetrySubscriber(prismTracer(trace.getTracer('prism')), undefined, {
  captureContent: true,
  maxContentLength: 16_384,
});
```

```python
telemetry = TelemetrySubscriber(
    PrismTracer(trace.get_tracer("prism")),
    capture_content=True,
    max_content_length=16_384,
)
```
:::

The second TypeScript argument is the `SpanStore` holding in-flight
generations; leave it `undefined` for a fresh one.

## Privacy

**By default, spans carry metadata only**: model and provider, timing, finish
reason, token counts, cost, provider rate limits, tool names, tool call ids,
and each offered tool's name and digest. These are always exported, because
they contain nothing the user wrote or the model returned.

**Content is exported only when you turn on content capture** —
`prism.telemetry.capture_content` in PHP (`PRISM_TELEMETRY_CAPTURE_CONTENT=true`),
`captureContent` / `capture_content` in TypeScript and Python. With it on:

| Span | Attributes added |
| --- | --- |
| root | `input.value` (the prompt, system prompts and messages) and `output.value` (the text, structured output, embedding count and dimensions, or generated image URLs and mime types), with `input.mime_type` / `output.mime_type` |
| root | `llm.tools.<i>.tool.description` and `llm.tools.<i>.tool.json_schema` for each offered tool |
| step (PHP) | `llm.input_messages.<i>.message.role` / `.content`, `llm.output_messages.0.message.*` and `output.value` |
| tool | `tool.parameters` and `input.value` (the arguments) and `output.value` (the result) |

Treat capture as sending user data to your telemetry backend. Enable it only
where that backend is trusted to hold it.

**Each captured attribute is truncated.** Values longer than the limit are cut
and end in `…[truncated]`. The limit is `content_max_length`
(`PRISM_OTEL_CONTENT_MAX_LENGTH`, 65,536 bytes) in PHP and `maxContentLength` /
`max_content_length` (65,536 characters) in TypeScript and Python. Tool names
are capped separately at 512 bytes (characters in TypeScript and Python), and at
most 64 offered tools are exported.

**Attachment bytes are withheld even with content capture on.** An image,
document, audio or video part in captured content keeps its kind, mime type,
file id and filename, and its `base64` is replaced by `null` plus
`omitted_bytes`, the decoded size of what was left out. To export the bytes as
well, turn on `prism.telemetry.capture_media`
(`PRISM_TELEMETRY_CAPTURE_MEDIA=true`) in PHP, or `captureMedia` /
`capture_media` in TypeScript and Python. Exported bytes are still truncated.
Bytes that a user pastes into a prompt as text are text, and are exported with
the prompt.

URLs of generated images are exported without their query string or fragment
(PHP), so signed-URL tokens do not reach the span.

## Spans and attributes

| Span | Name | Kind |
| --- | --- | --- |
| Generation | `<operation> <model>` — in PHP, operation is `chat`, `embeddings` or `image_generation` | client |
| Step | `step <index>` | internal |
| Tool call | `execute_tool <tool name>` | internal |

**Root span:**

| Attribute | Value |
| --- | --- |
| `gen_ai.system`, `llm.provider`, `llm.system` | provider |
| `gen_ai.operation.name` | operation |
| `gen_ai.request.model`, `llm.model_name` | model |
| `openinference.span.kind` | `CHAIN`, `EMBEDDING` or `LLM` |
| `session.id`, `user.id` | when supplied |
| `gen_ai.response.finish_reasons` | `[finish reason]` |
| `gen_ai.usage.input_tokens`, `llm.token_count.prompt` | prompt + cache read + cache write tokens |
| `gen_ai.usage.output_tokens`, `llm.token_count.completion` | completion tokens (includes reasoning) |
| `llm.token_count.total` | input + output |
| `gen_ai.usage.cache_read.input_tokens`, `llm.token_count.prompt_details.cache_read` | when reported |
| `gen_ai.usage.cache_write.input_tokens`, `llm.token_count.prompt_details.cache_write` | when reported |
| `gen_ai.usage.reasoning.output_tokens`, `llm.token_count.completion_details.reasoning` | when reported |
| `gen_ai.usage.cost` | when reported |
| `prism.rate_limit.buckets` | names of the exported buckets |
| `prism.rate_limit.<bucket>.limit` / `.remaining` / `.resets_at_unix` | per bucket; reset time as integer Unix seconds |
| `llm.tools.<i>.tool.name`, `prism.tools.<i>.digest` | each offered tool, in the order sent |

**Step span:** `openinference.span.kind` = `LLM`, `prism.step.index`,
`llm.model_name`, `llm.provider`, and the same usage attributes for that turn.
PHP step spans also carry the step's finish reason.

**Tool span:** `gen_ai.operation.name` = `execute_tool`, `gen_ai.tool.name`,
`gen_ai.tool.call.id`, `openinference.span.kind` = `TOOL`, `tool.name`,
`tool.id`, `prism.tool.index`. TypeScript and Python tool spans also get an
`ok` or `error` status.

Rate-limit bucket names are exported only if they consist of `a`–`z`, `0`–`9`,
`-` and `_` and are at most 64 characters; others are dropped. At most 16
buckets are exported, the first of a repeated name wins, and a provider that
reports no rate limits adds no `prism.rate_limit.*` attributes at all.

::: warning Long tool lists and the span attribute limit
OpenTelemetry SDKs cap a span at 128 attributes by default and drop the rest
silently. Each offered tool adds two attributes (four with content capture), so
the bridge writes the tool list **last** — a long list loses its tail, never the
usage, finish reason or output. To keep every tool, raise
`OTEL_SPAN_ATTRIBUTE_COUNT_LIMIT` or configure your tracer provider's span
limits.
:::

Source: [Particle-Academy/prism-opentelemetry](https://github.com/Particle-Academy/prism-opentelemetry).
