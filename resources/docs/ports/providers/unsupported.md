# Provider availability

The provider on this page is implemented in the PHP reference but is not a built-in provider in the TypeScript or Python core port. The available built-in adapters are [OpenAI](/providers/openai), [Anthropic](/providers/anthropic) and [Mistral](/providers/mistral).

## Check support explicitly

An unregistered provider is rejected before any HTTP request is made. Select a supported adapter or register a custom provider explicitly.

::: language-group
```ts
import { registeredProviders } from '@particle-academy/prism';

console.log(registeredProviders());
```

```python
from prism import PrismError, resolve_provider

# Replace this placeholder with the provider key you want to check.
provider_key = "provider-to-check"
try:
    resolve_provider(provider_key)
except PrismError as error:
    if error.code != "unsupported_provider_action":
        raise
    print(f"No adapter is registered for {provider_key}.")
```
:::

## Next steps

Select PHP for this provider's reference examples, use one of the supported adapters, or implement and test a [custom provider](/advanced/custom-providers). An OpenAI-compatible URL alone is insufficient: the port's OpenAI adapter expects the Responses API.

The separate [Prism Perplexity satellite](/packages/perplexity) has TypeScript and Python implementations for its own endpoints. It does not register a Perplexity core provider.
