# Build with Prism

Prism exposes the same provider-facing capabilities in PHP, TypeScript and
Python. Choose a language once and every example on this page follows it; the
concepts, capability names and failure boundaries stay the same.

For the cross-language contract, corpus, and current provider limits, read
[Language ports](/packages/language-ports).

## Install

::: language-group
```bash [language=php]
composer require particle-academy/prism
```

```bash [language=typescript]
npm install @particle-academy/prism
```

```bash [language=python]
pip install prism-ai-core
```
:::

PHP requires PHP 8.2+ and Laravel 12.61+ or 13.12+. The TypeScript package
requires Node 20+. The Python distribution requires Python 3.10+ and installs
the `prism` import.

::: warning Python package name
Install `prism-ai-core`, not `prism-ai`. The latter is an unrelated PyPI
package.
:::

## First request

::: language-group
```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

$response = Prism::text()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withPrompt('Explain why the sky is blue in one sentence.')
    ->asText();

echo $response->text;
```

```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Explain why the sky is blue in one sentence.')
  .asText();

console.log(response.text);
```

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
:::

Configuration is explicit first and environment second. The TypeScript call is
asynchronous; PHP and Python return synchronously. Each port exposes an
injectable HTTP transport so tests can run without a socket.

## Current surface

All three implementations cover text, structured output, streaming,
embeddings, images, moderation, text to speech, speech to text, files, batches
and fill-in-the-middle. PHP is the reference and carries eighteen providers.
The TypeScript and Python ports currently carry Anthropic, Mistral and OpenAI.

Not every capability reaches every provider. Images, moderation, text to
speech, files and batches work with OpenAI in the ports, and
fill-in-the-middle works with Mistral. Unsupported actions fail explicitly.

TypeScript and Python failures carry a stable machine-readable `code`. PHP
applications branch on exception type. Read the message for people, not for
control flow.
