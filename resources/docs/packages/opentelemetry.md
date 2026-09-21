# Prism OpenTelemetry

A bridge from Prism's telemetry events to
[GenAI-convention](https://opentelemetry.io/docs/specs/semconv/gen-ai/)
OpenTelemetry spans — a root span per generation, child spans per step and per
tool call, exported over OTLP to
[Arize Phoenix](https://github.com/Arize-ai/phoenix), Jaeger, Tempo, Grafana or
anything else that speaks OTLP.

::: tip PHP telemetry events
See [Telemetry](/advanced/telemetry) for the PHP generation event stream.
TypeScript and Python use the subscriber API shown below.
:::

## Integration

In PHP, the bridge subscribes to Laravel events emitted by Prism. TypeScript
and Python expose subscriber methods that your application calls at generation,
step and tool boundaries. Supply a tracer connected to your OpenTelemetry SDK.

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
```

```bash [language=python]
pip install prism-ai-opentelemetry
```
:::

Install and configure an OpenTelemetry SDK and exporter for your runtime.

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

The PHP package requires PHP 8.2+ and `particle-academy/prism` 0.111 or newer.
The Artisan and PHP autoload settings above do not apply to TypeScript or Python.

## Instrument a generation

PHP subscribes to Prism's Laravel events when the package is enabled. The two
zero-dependency ports expose the same subscriber boundary directly so any
OpenTelemetry SDK can sit behind it.

::: language-group
```php
use Prism\Prism\Facades\Prism;

$response = Prism::text()
    ->using('anthropic', 'claude-sonnet-4-5')
    ->withPrompt('Summarise the incident.')
    ->asText(); // the bridge records Prism's emitted telemetry events
```

```ts
import { TelemetrySubscriber } from '@particle-academy/prism-opentelemetry';

const telemetry = new TelemetrySubscriber(tracer);
const context = {
  traceId: 'run-42',
  operation: 'text',
  provider: 'anthropic',
  model: 'claude-sonnet-4-5',
};

telemetry.onGenerationStarted(context, prompt);
telemetry.onGenerationCompleted('run-42', { finishReason: 'stop', output: responseText });
```

```python
from prism_opentelemetry import GenerationContext, TelemetrySubscriber

telemetry = TelemetrySubscriber(tracer)
context = GenerationContext(
    trace_id="run-42",
    operation="text",
    provider="anthropic",
    model="claude-sonnet-4-5",
)

telemetry.on_generation_started(context, prompt)
telemetry.on_generation_completed("run-42", finish_reason="stop", output=response_text)
```
:::

## PHP configuration

PHP applications can publish the optional configuration with
`php artisan vendor:publish --tag=prism-opentelemetry-config`. The TypeScript
and Python ports take their options when the subscriber is constructed.

| Key | Env | Default | Does |
| --- | --- | --- | --- |
| `enabled` | `PRISM_OTEL_ENABLED` | `true` | Subscribe the span-building listener |
| `tracer_name` | `PRISM_OTEL_TRACER_NAME` | `prism` | Instrumentation scope name |
| `record_exceptions` | `PRISM_OTEL_RECORD_EXCEPTIONS` | `true` | Record the exception on failed spans |

## Privacy

The bridge reads span **metadata** only — tokens, timing, model, finish reason.
It never puts prompt or completion text on a span.

In PHP, `prism.telemetry.capture_content` controls whether Prism includes prompt
and completion text in its events. It is off by default and is independent of
the bridge's metadata-only span policy.

Source: [Particle-Academy/prism-opentelemetry](https://github.com/Particle-Academy/prism-opentelemetry).
