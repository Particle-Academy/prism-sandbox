# Handling rate limits

Providers can report rate-limit metadata on a successful response and can reject requests when a limit is exhausted. Both cases belong in your application's request policy.

## Read response metadata

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Hello!')
  .asText();

console.log(response.meta.rateLimits);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Hello!")
    .as_text()
)

print(response.meta.rate_limits)
```
:::

## Bound retry behavior

An empty metadata list means no recognized rate-limit headers were returned. It does not mean requests are unlimited. Limit concurrency and retries, respect provider retry instructions where available, and stop retrying once the request deadline or budget is exhausted.

Laravel queue middleware and Guzzle retry helpers in the PHP reference are framework-specific. For the ports, put the retry policy around your application call or in an injected transport, and test it with recorded rate-limit failures. Never retry a non-idempotent operation blindly after an ambiguous timeout.
