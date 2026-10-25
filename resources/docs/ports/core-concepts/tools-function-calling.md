# Tools and function calling

The ports describe tools to the model and deliver the model's tool calls to you. They do **not** run tools: there is no automatic tool-execution loop. Your code decides whether to run a requested tool.

Two consequences follow:

- A **non-streaming** response that ends on tool calls throws `PrismError` with code `tool_loop_not_supported`.
- To receive tool calls, **stream** the request and handle `ToolCallEvent`. See [Receive a tool call](#receive-a-tool-call).

## Describe a tool

Give the tool a name, a description and its parameters. Parameters are required unless you pass `false` as the last argument.

::: language-group
```ts
import { Prism, Tool } from '@particle-academy/prism';

const weather = new Tool()
  .as('weather')
  .for('Look up the weather in a city')
  .withStringParameter('city', 'The city to look up')
  .withNumberParameter('days', 'How many days to forecast', false)
  .withBooleanParameter('metric', 'Use metric units', false);

const request = Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('What is the weather in Chicago?')
  .withTools([weather])
  .toRequest();

console.log(request.tools().length);
```

```python
from prism import Prism, Tool

weather = (
    Tool()
    .as_("weather")
    .for_("Look up the weather in a city")
    .with_string_parameter("city", "The city to look up")
    .with_number_parameter("days", "How many days to forecast", False)
    .with_boolean_parameter("metric", "Use metric units", False)
)

request = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("What is the weather in Chicago?")
    .with_tools([weather])
    .to_request()
)

print(len(request.tools))
```
:::

Python spells `as` and `for` as `as_()` and `for_()` because they are keywords. Python can also take both in the constructor: `Tool("weather", "Look up the weather in a city")`.

The order you add parameters is the order of the JSON Schema properties, and the order you pass tools is the order the model sees them.

## Complex parameters

`withParameter()` / `with_parameter()` takes any [schema](/core-concepts/structured-output#schema-types), so a parameter can be an enum, an array or an object.

::: language-group
```ts
import { ArraySchema, EnumSchema, ObjectSchema, StringSchema, Tool } from '@particle-academy/prism';

const search = new Tool()
  .as('search_products')
  .for('Search the product catalogue')
  .withParameter(new EnumSchema('category', 'Product category', ['books', 'music', 'games']))
  .withParameter(new ArraySchema('tags', 'Tags to match', new StringSchema('tag', 'A tag')), false)
  .withParameter(
    new ObjectSchema(
      'price',
      'Price range',
      [new StringSchema('currency', 'ISO currency code')],
      { requiredFields: ['currency'] },
    ),
    false,
  );
```

```python
from prism import Tool
from prism.schema import ArraySchema, EnumSchema, ObjectSchema, StringSchema

search = (
    Tool()
    .as_("search_products")
    .for_("Search the product catalogue")
    .with_parameter(EnumSchema("category", "Product category", ("books", "music", "games")))
    .with_parameter(ArraySchema("tags", "Tags to match", StringSchema("tag", "A tag")), False)
    .with_parameter(
        ObjectSchema(
            "price",
            "Price range",
            (StringSchema("currency", "ISO currency code"),),
            ("currency",),
        ),
        False,
    )
)
```
:::

## Strict tools

`withProviderOptions({ strict: true })` on a tool asks OpenAI to enforce the parameter schema exactly. **Only OpenAI reads it**; Anthropic and Mistral ignore it. OpenAI's strict mode requires every parameter to be required.

::: language-group
```ts
import { Tool } from '@particle-academy/prism';

const lookup = new Tool()
  .as('lookup_order')
  .for('Find an order by id')
  .withStringParameter('order_id', 'The order id')
  .withProviderOptions({ strict: true });
```

```python
from prism import Tool

lookup = (
    Tool()
    .as_("lookup_order")
    .for_("Find an order by id")
    .with_string_parameter("order_id", "The order id")
    .with_provider_options({"strict": True})
)
```
:::

## Tool choice

`withToolChoice()` takes a `ToolChoice` member, a tool name, or a `Tool` (its name is used).

| Choice | OpenAI | Anthropic | Mistral |
|---|---|---|---|
| `ToolChoice.Auto` / `AUTO` | `auto` | `auto` | `auto` |
| `ToolChoice.Any` / `ANY` (must call some tool) | `required` | `any` | `any` |
| `ToolChoice.None` / `NONE` | `none` | `none` | `none` |
| A tool name or `Tool` | that function | that tool | that function |

When no choice is set, none is sent and the provider's default applies. Mistral sends a tool choice only when the request also has tools.

::: language-group
```ts
import { Prism, Tool, ToolChoice } from '@particle-academy/prism';

const weather = new Tool().as('weather').for('Look up the weather').withStringParameter('city', 'City');

const forced = Prism.text()
  .using('anthropic', 'claude-sonnet-4-5')
  .withTools([weather])
  .withToolChoice(weather)
  .withPrompt('Chicago?');

const anyTool = Prism.text()
  .using('anthropic', 'claude-sonnet-4-5')
  .withTools([weather])
  .withToolChoice(ToolChoice.Any)
  .withPrompt('Chicago?');
```

```python
from prism import Prism, Tool, ToolChoice

weather = Tool("weather", "Look up the weather").with_string_parameter("city", "City")

forced = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-5")
    .with_tools([weather])
    .with_tool_choice(weather)
    .with_prompt("Chicago?")
)

any_tool = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-5")
    .with_tools([weather])
    .with_tool_choice(ToolChoice.ANY)
    .with_prompt("Chicago?")
)
```
:::

## Receive a tool call

Stream the request. Each `ToolCallEvent` carries a complete `ToolCall`: the arguments have finished arriving before the event is emitted.

| TypeScript | Python | Meaning |
|---|---|---|
| `id` | `id` | The call id. |
| `name` | `name` | The tool the model asked for. |
| `arguments` | `arguments` | The arguments as the provider sent them: a JSON string or an object. |
| `parsedArguments()` | `decoded_arguments()` | The arguments as an object. Throws `malformed_tool_call_arguments` if they are not valid JSON. |

Treat the tool name and arguments as untrusted input. Check that the name is one you offered and validate the arguments before acting on them.

::: language-group
```ts
import { Prism, Tool, ToolCallEvent } from '@particle-academy/prism';

const weather = new Tool().as('weather').for('Look up the weather in a city').withStringParameter('city', 'City');

for await (const event of Prism.text()
  .using('openai', 'gpt-4o')
  .withTools([weather])
  .withPrompt('What is the weather in Chicago?')
  .asStream()) {
  if (event instanceof ToolCallEvent && event.toolCall.name === 'weather') {
    const args = event.toolCall.parsedArguments();
    console.log(`The model wants the weather for ${String(args.city)}`);
  }
}
```

```python
from prism import Prism, Tool
from prism.streaming import ToolCallEvent

weather = Tool("weather", "Look up the weather in a city").with_string_parameter("city", "City")

stream = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_tools([weather])
    .with_prompt("What is the weather in Chicago?")
    .as_stream()
)

for event in stream:
    if isinstance(event, ToolCallEvent) and event.tool_call and event.tool_call.name == "weather":
        args = event.tool_call.decoded_arguments()
        print(f"The model wants the weather for {args.get('city')}")
```
:::

## Tool handlers

The two languages differ here:

- **TypeScript** `Tool` can hold a handler: `using(handler)` stores an async function and `handle(args)` calls it. Prism never calls it; you call `handle()` after your own checks. `handle()` throws a `TypeError` if no handler was set.
- **Python** `Tool` has no handler. Keep a mapping from tool name to your function.

::: language-group
```ts
import { Tool, type ToolCall } from '@particle-academy/prism';

const weather = new Tool()
  .as('weather')
  .for('Look up the weather in a city')
  .withStringParameter('city', 'City')
  .using(async (args) => `It is sunny in ${String(args.city)}.`);

async function run(call: ToolCall): Promise<string> {
  if (call.name !== weather.name()) {
    throw new Error(`Unknown tool ${call.name}`);
  }

  return weather.handle(call.parsedArguments());
}
```

```python
from collections.abc import Callable
from typing import Any

from prism import ToolCall


def weather(args: dict[str, Any]) -> str:
    return f"It is sunny in {args.get('city')}."


HANDLERS: dict[str, Callable[[dict[str, Any]], str]] = {"weather": weather}


def run(call: ToolCall) -> str:
    handler = HANDLERS.get(call.name)
    if handler is None:
        raise ValueError(f"Unknown tool {call.name}")
    return handler(call.decoded_arguments())
```
:::

## Provider tools

Built-in provider tools, such as OpenAI's `web_search`, are added with `withProviderTools()` and a `ProviderTool(type, name, options)`. **Only OpenAI sends them**; Anthropic and Mistral ignore them. The provider runs these tools itself, and details appear in the response's `additionalContent`.

::: language-group
```ts
import { Prism, ProviderTool } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withProviderTools([new ProviderTool('web_search')])
  .withPrompt('What happened in the news today?')
  .asText();
```

```python
from prism import Prism, ProviderTool

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_provider_tools([ProviderTool("web_search")])
    .with_prompt("What happened in the news today?")
    .as_text()
)
```
:::

## Errors

| Code | When |
|---|---|
| `tool_loop_not_supported` | A non-streaming text or structured response ended on tool calls. |
| `malformed_tool_call_arguments` | A tool call's arguments are not valid JSON, when parsed or when the call is sent back in a message history. |

## Execute tools in an agent workflow

Use [Harness modes and tools](/harness/modes) for orchestration, permissions and budgets. A model asking to use a tool is not authorization to run it. Keep permission checks and required human confirmation in the application-owned execution boundary.
