# Error handling

Port failures use `PrismError` with a stable `code`. Branch on the code, not on a human-readable message. PHP uses its exception classes; those class names are not the port API.

## Handle an unsupported provider

This example fails during provider selection without making an HTTP request.

::: language-group
```ts
import { Prism, PrismError } from '@particle-academy/prism';

try {
  Prism.text().using('not-a-registered-provider', 'model');
} catch (error) {
  if (error instanceof PrismError && error.code === 'unsupported_provider_action') {
    console.log('Choose a provider implemented by this port.');
  } else {
    throw error;
  }
}
```

```python
from prism import Prism, PrismError

try:
    Prism.text().using("not-a-registered-provider", "model")
except PrismError as error:
    if error.code == "unsupported_provider_action":
        print("Choose a provider implemented by this port.")
    else:
        raise
```
:::

## Application policy

Distinguish invalid input, unsupported capabilities and provider failures before deciding whether to retry. Bound retries and preserve the original failure if the retry policy is exhausted. Do not retry unsupported operations.

Avoid logging credentials, complete request bodies or sensitive prompt content.
