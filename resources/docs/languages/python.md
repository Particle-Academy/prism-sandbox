# Building with Python

`prism-py` is the Python port of Prism's focused text slice, published in source
under the distribution name `prism-ai-core` and imported as `prism`. It has zero
runtime dependencies and requires Python 3.10+.

For the shared contract and parity limits, read
[Language ports](/packages/language-ports). This page covers only the Python
implementation.

::: warning Install from source
`prism-ai-core` is not on PyPI yet. Install it from a checkout — the steps are just
below.
:::

## Try it from source

```bash
git clone https://github.com/Particle-Academy/prism-py.git
python -m pip install -e ./prism-py
```

The editable install exposes the package from the checked-out source. Use an
isolated virtual environment for development.

## First request

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Explain why the sky is blue in one sentence.")
    .as_text()
)

print(response.text)
```

The current surface is synchronous: `as_text()` returns the response rather
than an awaitable. Configuration accepts explicit provider options and falls
back to environment variables, and the HTTP transport is injectable for tests.

Failures raise `PrismError` with a stable `code`; applications should branch on
the code rather than the English message.

## Exact current scope

The port ships Anthropic and OpenAI text providers,
message value objects, provider-tool mapping, serialization, and rehydration.
The port does **not** currently ship streaming, structured output, embeddings,
the multi-step tool execution loop, or the PHP reference's wider provider and
media surface. A response that stops on tool calls raises
`tool_loop_not_supported` instead of pretending the loop completed.

