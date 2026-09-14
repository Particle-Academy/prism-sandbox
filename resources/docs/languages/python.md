# Building with Python

`prism-ai-core` is the Python port of Prism, imported as `prism`. It has zero
runtime dependencies and requires Python 3.10+.

For the shared contract and parity limits, read
[Language ports](/packages/language-ports). This page covers only the Python
implementation.

## Install

```bash
pip install prism-ai-core
```

::: warning Not `prism-ai`
`prism-ai` on PyPI is an unrelated package. Install `prism-ai-core`.
:::

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

The port implements every capability the PHP reference has: text, structured
output, streaming, embeddings, images, moderation, text to speech, speech to
text, files, batches and fill-in-the-middle. It ships three providers:
Anthropic, Mistral and OpenAI.

Not every capability reaches every provider. Images, moderation, text to speech,
files and batches work with OpenAI only, and fill-in-the-middle works with
Mistral only.

The port does **not** ship the multi-step tool execution loop or the PHP
reference's other fifteen providers. A response that stops on tool calls raises
`tool_loop_not_supported` instead of pretending the loop completed.

