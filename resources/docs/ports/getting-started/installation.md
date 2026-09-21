# Installation

## Requirements

TypeScript requires Node.js 20 or later. Python requires Python 3.10 or later. Both run independently of Laravel. Install the package in your application's server environment.

## Install the package

::: language-group
```bash [language=typescript]
npm install @particle-academy/prism
```

```bash [language=python]
pip install prism-ai-core
```
:::

The Python distribution is `prism-ai-core` and its import is `prism`. The unrelated `prism-ai` distribution is not this project. Use your application's lockfile to make installations reproducible.

## Configure credentials

Provide `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` or `MISTRAL_API_KEY` through your server environment. The ports do not publish a Laravel configuration file and do not require an Artisan command. See [Configuration](/getting-started/configuration) for explicit per-request configuration.

## Import Prism

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const request = Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Hello!')
  .toRequest();
```

```python
from prism import Prism

request = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Hello!")
    .to_request()
)
```
:::

Building a request does not send it. Use the terminal method shown in [Introduction](/getting-started/introduction) when you are ready to call the provider.
