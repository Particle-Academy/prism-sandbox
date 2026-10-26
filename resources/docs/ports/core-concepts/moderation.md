# Moderation

Send text to a moderation model before deciding how your application should handle it. Both ports implement moderation for **OpenAI** only, and for text inputs only. Anthropic and Mistral fail with `unsupported_provider_action`.

## Moderate text

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.moderation()
  .using('openai', 'omni-moderation-latest')
  .withInput('Text submitted by a user.')
  .asModeration();

if (response.isFlagged()) {
  for (const result of response.flagged()) {
    console.log('Flagged for', result.flaggedCategories());
  }
}
```

```python
from prism import Prism

response = (
    Prism.moderation()
    .using("openai", "omni-moderation-latest")
    .with_input("Text submitted by a user.")
    .as_moderation()
)

if response.is_flagged():
    for result in response.flagged():
        print("Flagged for", result.flagged_categories())
```
:::

`withInput()` / `with_input()` takes one or more strings and **appends**, so several calls moderate several inputs in one request. The request carries only the model and the inputs; provider options are not sent.

## The response

`asModeration()` / `as_moderation()` returns a `ModerationResponse`.

| Member | TypeScript | Python | Meaning |
|---|---|---|---|
| Results | `results` | `results` | One `ModerationResult` per input, in input order. |
| Meta | `meta` | `meta` | `id` and `model`. |
| Raw | `raw` | `raw` | The provider's decoded response body, or null. |
| Any flagged | `isFlagged()` | `is_flagged()` | True when **any** input was flagged. |
| First flagged | `firstFlagged()` | `first_flagged()` | The first flagged result, or null. |
| All flagged | `flagged()` | `flagged()` | Only the flagged results. |

Use `isFlagged()` rather than checking `results[0]`: with several inputs, the problem may be in the second one.

### ModerationResult

| Member | TypeScript | Python | Type | Meaning |
|---|---|---|---|---|
| Verdict | `flagged` | `flagged` | boolean | Whether this input was flagged. |
| Categories | `categories` | `categories` | map of name to boolean | Each category and whether it tripped. |
| Scores | `categoryScores` | `category_scores` | map of name to number | The model's score per category. |
| Tripped categories | `flaggedCategories()` | `flagged_categories()` | string list | Only the categories that tripped. |

A category value that is not a real boolean (or score that is not a number) in the provider's answer is dropped, not guessed at.

## Fail closed

`isFlagged()` is false when there are no results at all. Before you let content through, check that you got one result per input; treat anything else, and any error, as "not cleared".

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

async function isCleared(inputs: string[]): Promise<boolean> {
  try {
    const response = await Prism.moderation()
      .using('openai', 'omni-moderation-latest')
      .withInput(...inputs)
      .asModeration();

    return response.results.length === inputs.length && !response.isFlagged();
  } catch {
    return false;
  }
}
```

```python
from prism import Prism, PrismError

def is_cleared(inputs: list[str]) -> bool:
    try:
        response = (
            Prism.moderation()
            .using("openai", "omni-moderation-latest")
            .with_input(*inputs)
            .as_moderation()
        )
    except PrismError:
        return False

    return len(response.results) == len(inputs) and not response.is_flagged()
```
:::

Your application owns its thresholds and review policy. Category scores let you apply your own thresholds and explain a decision to the person it affects.

## Errors

| Code | When |
|---|---|
| `no_moderation_input` | No input was given. Refused before sending, so an empty call cannot come back as "nothing flagged". |
| `unsupported_provider_action` | The provider has no moderation, or `using()` was not called. |
| `provider_response_error` | The provider answered with an HTTP error or a body that is not a JSON object. The status is on `httpStatus` (TypeScript) / `status` (Python). |

See [Error handling](/advanced/error-handling).
