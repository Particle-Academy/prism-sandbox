# Handling rate limits

Providers report quota in response headers, and reject requests with HTTP 429 once a limit is used up. The ports read the headers into `ProviderRateLimit` values on successful responses. What to do about them, including retries, is your application's decision.

## Read response metadata

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Hello!')
  .asText();

for (const limit of response.meta.rateLimits) {
  console.log(limit.name, limit.remaining, '/', limit.limit, 'resets at', limit.resetsAt?.toISOString());
}
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("Hello!")
    .as_text()
)

for limit in response.meta.rate_limits:
    print(limit.name, limit.remaining, "/", limit.limit, "resets at", limit.resets_at)
```
:::

## `ProviderRateLimit`

| TypeScript | Python | Type | Contents |
|---|---|---|---|
| `name` | `name` | string | The bucket: see the table below. |
| `limit` | `limit` | integer or `null` / `None` | The size of the bucket. |
| `remaining` | `remaining` | integer or `null` / `None` | How much of it is left. |
| `resetsAt` | `resets_at` | `Date` / timezone-aware `datetime` in UTC, or `null` / `None` | When the bucket refills. `null` / `None` when the provider did not say or the value could not be read. |

A bucket is only reported when the provider sent both its limit and its remaining count. `toObject()` / `to_dict()` serialise a limit with `resets_at` as an ISO-8601 UTC string.

## Which responses carry it

| Provider | Buckets | Reset header | Populated on |
|---|---|---|---|
| OpenAI | `requests`, `tokens` | A duration (`6ms`, `30s`, `5m`, `1h`) converted to a time | Text and structured responses |
| Anthropic | `requests`, `tokens`, `input-tokens`, `output-tokens` | An RFC 3339 time | Text and structured responses |
| Mistral | `tokens` | Seconds from now, converted to a time | Text and structured responses; also FIM responses in TypeScript |

Every other response (streams, embeddings, images, audio, files, batches, moderation) has no rate-limit data. A reset time computed from a duration is relative to when the response was read.

An empty list means no recognised rate-limit headers came back. It does not mean requests are unlimited.

## When a limit is hit

A rejected request raises `PrismError` with code `provider_response_error` and the HTTP status: `httpStatus === 429` in TypeScript, `status == 429` in Python. Anthropic signals overload with `529`.

**No retry-after value is exposed.** The error carries the status and, for most calls, the response body, but not the response headers, so the provider's `retry-after` header cannot be read through the ports. Use backoff with jitter, or the `resetsAt` / `resets_at` of the last successful response.

::: language-group
```ts
import { PrismError, Prism } from '@particle-academy/prism';
import type { TextResponse } from '@particle-academy/prism';

async function textWithBackoff(prompt: string, attempts = 4): Promise<TextResponse> {
  for (let attempt = 1; ; attempt++) {
    try {
      return await Prism.text().using('openai', 'gpt-4o').withPrompt(prompt).asText();
    } catch (error) {
      const limited = error instanceof PrismError && (error.httpStatus === 429 || error.httpStatus === 529);

      if (!limited || attempt >= attempts) {
        throw error;
      }

      const delay = Math.min(30_000, 1_000 * 2 ** (attempt - 1)) * (0.5 + Math.random() / 2);
      await new Promise((resolve) => setTimeout(resolve, delay));
    }
  }
}

console.log((await textWithBackoff('Hello!')).text);
```

```python
import random
import time

from prism import Prism, PrismError, Response


def text_with_backoff(prompt: str, attempts: int = 4) -> Response:
    attempt = 1
    while True:
        try:
            return Prism.text().using("openai", "gpt-4o").with_prompt(prompt).as_text()
        except PrismError as error:
            limited = error.status in (429, 529)
            if not limited or attempt >= attempts:
                raise
            delay = min(30.0, 2 ** (attempt - 1)) * (0.5 + random.random() / 2)
            time.sleep(delay)
            attempt += 1


print(text_with_backoff("Hello!").text)
```
:::

## Bound retry behaviour

Limit concurrency as well as retries, and stop retrying once the request's deadline or budget is used up. Never blindly retry a non-idempotent operation, such as a file upload or batch creation, after an ambiguous timeout.

Laravel queue middleware and Guzzle retry helpers in the PHP package are framework-specific. In the ports, put the retry policy around your application call, as above, or in an injected transport, and test it with recorded 429 responses (see [Testing](/core-concepts/testing)).
