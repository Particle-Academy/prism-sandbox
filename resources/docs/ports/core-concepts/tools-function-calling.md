# Tools and function calling

The ports can describe tools and serialize them into requests. Their core text implementations do **not** implement PHP's automatic multi-step tool-execution loop: a response requiring that loop raises `tool_loop_not_supported`.

## Describe a tool

This example constructs a request without sending it or executing a tool.

::: language-group
```ts
import { Prism, Tool } from '@particle-academy/prism';

const weather = new Tool()
  .as('weather')
  .for('Look up the weather in a city')
  .withStringParameter('city', 'The city to look up');

const request = Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('What is the weather in Chicago?')
  .withTools([weather])
  .toRequest();

console.log(request);
```

```python
from prism import Prism, Tool

weather = (
    Tool()
    .as_("weather")
    .for_("Look up the weather in a city")
    .with_string_parameter("city", "The city to look up")
)

request = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("What is the weather in Chicago?")
    .with_tools([weather])
    .to_request()
)

print(request)
```
:::

## Execute tools in an agent workflow

Use [Harness modes and tools](/harness/modes) for orchestration, permissions and budgets. A model asking to use a tool is not authorization to run it. Keep permission checks and required human confirmation in the application-owned execution boundary.

Select PHP for the core package's automatic tool-loop API.
