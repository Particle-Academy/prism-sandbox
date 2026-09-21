# Moderation

Send content to a moderation model before deciding how your application should handle it. The ports implement moderation through OpenAI.

## Moderate text

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.moderation()
  .using('openai', 'omni-moderation-latest')
  .withInput('Text submitted by a user.')
  .asModeration();

console.log(response.results);
```

```python
from prism import Prism

response = (
    Prism.moderation()
    .using("openai", "omni-moderation-latest")
    .with_input("Text submitted by a user.")
    .as_moderation()
)

print(response.results)
```
:::

Inspect the per-input results and category scores. Your application owns its thresholds and review policy. A provider error is not a clean moderation result; handle it explicitly using [Error handling](/advanced/error-handling).
