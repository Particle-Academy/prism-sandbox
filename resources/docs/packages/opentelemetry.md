# Prism OpenTelemetry

A bridge from Prism's telemetry events to
[GenAI-convention](https://opentelemetry.io/docs/specs/semconv/gen-ai/)
OpenTelemetry spans — a root span per generation, child spans per step and per
tool call, exported over OTLP to
[Arize Phoenix](https://github.com/Arize-ai/phoenix), Jaeger, Tempo, Grafana or
anything else that speaks OTLP.

::: tip Status: published
`v0.3.0` on Packagist. The Prism telemetry events it consumes ship in tagged
Prism releases — see [Telemetry](/advanced/telemetry) for the event stream
itself, which is worth subscribing to on its own.
:::

## Why it is a separate package

Core emits **neutral Laravel events**, and this package owns the
`open-telemetry/*` dependency and the GenAI attribute mapping. So
semantic-convention churn — and the OpenTelemetry PHP SDK's own release cadence
— moves at this package's pace, and your Prism upgrades stay about Prism.

```
Prism core (events)                 →  this bridge          →  OTLP backend
Events\Telemetry\GenerationStarted     builds root span        Arize Phoenix,
                 \StepCompleted        + child step spans      Jaeger, Tempo,
                 \ToolInvoked          + child tool spans      Grafana, …
                 \GenerationCompleted  ends the root span
                 \GenerationFailed     ends w/ error status
```

## Installation

```bash
composer require particle-academy/prism-opentelemetry

# Plus an OpenTelemetry SDK and an OTLP exporter — transport of your choice.
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

The SDK is a separate install on purpose: the bridge activates when the
OpenTelemetry API is present, so tracing travels with the machines you want it
on.

Turn Prism's telemetry on — it is **off by default** and a complete no-op until
you enable it:

```dotenv
PRISM_TELEMETRY_ENABLED=true
```

Then point the OpenTelemetry SDK at your collector:

```dotenv
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:6006   # Phoenix OTLP endpoint
```

Requires PHP 8.2+ and `particle-academy/prism` 0.111 or newer.

## Configuration

```bash
php artisan vendor:publish --tag=prism-opentelemetry-config
```

| Key | Env | Default | Does |
| --- | --- | --- | --- |
| `enabled` | `PRISM_OTEL_ENABLED` | `true` | Subscribe the span-building listener |
| `tracer_name` | `PRISM_OTEL_TRACER_NAME` | `prism` | Instrumentation scope name |
| `record_exceptions` | `PRISM_OTEL_RECORD_EXCEPTIONS` | `true` | Record the exception on failed spans |

## Privacy

The bridge reads span **metadata** only — tokens, timing, model, finish reason.
It never puts prompt or completion text on a span.

Whether that content is present on the events at all is governed upstream by
Prism's own `prism.telemetry.capture_content` flag, which is off by default.
Two switches, deliberately: the one that decides whether sensitive text is
captured belongs next to the thing that captures it, not in whichever exporter
happens to be installed.

Source: [Particle-Academy/prism-opentelemetry](https://github.com/Particle-Academy/prism-opentelemetry).
