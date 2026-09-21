# Structured output and schemas

Describe the output with schema value objects and execute a structured request. The ports support structured output with OpenAI, Anthropic and Mistral; the provider controls how the schema is transmitted and enforced.

## Define and use a schema

::: language-group
```ts
import { ObjectSchema, Prism, StringSchema } from '@particle-academy/prism';

const schema = new ObjectSchema(
  'person',
  'A person mentioned in the prompt',
  [new StringSchema('name', 'Their name')],
  { requiredFields: ['name'] },
);

const response = await Prism.structured()
  .using('openai', 'gpt-4o')
  .withSchema(schema)
  .withPrompt('Ada Lovelace wrote notes on the Analytical Engine.')
  .asStructured();

console.log(response.structured);
```

```python
from prism import Prism
from prism.schema import ObjectSchema, StringSchema

schema = ObjectSchema(
    "person",
    "A person mentioned in the prompt",
    (StringSchema("name", "Their name"),),
    ("name",),
)

response = (
    Prism.structured()
    .using("openai", "gpt-4o")
    .with_schema(schema)
    .with_prompt("Ada Lovelace wrote notes on the Analytical Engine.")
    .as_structured()
)

print(response.structured)
```
:::

## Schema types

Both ports include string, number, boolean, enum, array and object schemas. TypeScript passes optional object settings in a trailing options object; Python accepts its schema fields directly. Required object properties must be declared in the required-fields list, not merely included in the properties list.

## Handling failure

Parsing, provider refusal and unsupported schema behavior can fail. Catch `PrismError` and branch on its code as described in [Error handling](/advanced/error-handling). A schema-shaped result still needs application-level validation before it authorizes actions or writes data.
