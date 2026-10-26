# Structured output and schemas

Describe the output with schema value objects and execute a structured request. The ports support structured output with OpenAI, Anthropic and Mistral; each provider transmits and enforces the schema differently.

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

`Prism.structured()` has every method of `Prism.text()` (system prompts, messages, temperature, provider options and so on) plus `withSchema()` and `usingStructuredMode()`.

::: warning Python imports
In Python, `StringSchema`, `NumberSchema` and `BooleanSchema` can be imported from `prism`, but `ObjectSchema`, `ArraySchema` and `EnumSchema` are only in `prism.schema`. Import all six from `prism.schema` to keep it simple. In TypeScript every schema class is exported from `@particle-academy/prism`.
:::

## Schema types

Every schema takes a name and a description first. TypeScript passes the remaining settings in a trailing options object; Python takes them as dataclass fields, by position or keyword.

| Schema | Required arguments | Options (TypeScript / Python) |
|---|---|---|
| `StringSchema` | name, description | `nullable`, `pattern`, `format` |
| `NumberSchema` | name, description | `nullable`, `multipleOf` / `multiple_of`, `minimum`, `maximum`, `exclusiveMinimum` / `exclusive_minimum`, `exclusiveMaximum` / `exclusive_maximum` |
| `BooleanSchema` | name, description | `nullable` |
| `EnumSchema` | name, description, options | `nullable` |
| `ArraySchema` | name, description, items (one schema) | `nullable`, `minItems` / `min_items`, `maxItems` / `max_items` |
| `ObjectSchema` | name, description, properties | `requiredFields` / `required_fields`, `allowAdditionalProperties` / `allow_additional_properties` (default false), `nullable` |

Rules that apply to every type:

- **Required properties must be listed.** A property in `properties` is optional unless its name is in the object's required-fields list. OpenAI's strict mode rejects a schema whose properties are not all required, so list every property and use `nullable` for values that can be absent.
- **`nullable: true`** makes the JSON Schema type a union with `null` (for example `["string", "null"]`). On an `EnumSchema` it also adds `null` to the allowed options.
- **`EnumSchema` always declares `type: "string"`**, so give it string options.
- **`additionalProperties` is false** unless you allow it.

The ports do not have the PHP package's `AnyOfSchema` or `RawSchema`.

::: language-group
```ts
import {
  ArraySchema,
  BooleanSchema,
  EnumSchema,
  NumberSchema,
  ObjectSchema,
  StringSchema,
} from '@particle-academy/prism';

const review = new ObjectSchema(
  'review',
  'A product review',
  [
    new StringSchema('title', 'A short headline'),
    new NumberSchema('rating', 'Stars from 1 to 5', { minimum: 1, maximum: 5 }),
    new BooleanSchema('recommended', 'Whether the reviewer recommends it'),
    new EnumSchema('sentiment', 'Overall tone', ['positive', 'neutral', 'negative']),
    new ArraySchema('pros', 'What the reviewer liked', new StringSchema('pro', 'One point'), { maxItems: 5 }),
    new StringSchema('reviewer_email', 'Contact address if given', { nullable: true, format: 'email' }),
  ],
  { requiredFields: ['title', 'rating', 'recommended', 'sentiment', 'pros', 'reviewer_email'] },
);
```

```python
from prism.schema import (
    ArraySchema,
    BooleanSchema,
    EnumSchema,
    NumberSchema,
    ObjectSchema,
    StringSchema,
)

review = ObjectSchema(
    "review",
    "A product review",
    (
        StringSchema("title", "A short headline"),
        NumberSchema("rating", "Stars from 1 to 5", minimum=1, maximum=5),
        BooleanSchema("recommended", "Whether the reviewer recommends it"),
        EnumSchema("sentiment", "Overall tone", ("positive", "neutral", "negative")),
        ArraySchema("pros", "What the reviewer liked", StringSchema("pro", "One point"), max_items=5),
        StringSchema("reviewer_email", "Contact address if given", nullable=True, format="email"),
    ),
    required_fields=("title", "rating", "recommended", "sentiment", "pros", "reviewer_email"),
)
```
:::

## How each provider enforces the schema

| Provider | Method | Enforced? |
|---|---|---|
| OpenAI | Strict `json_schema` response format, or JSON mode; see [Structured mode](#structured-mode). | Yes in structured mode. |
| Anthropic | Appends a user message containing the schema and asking for JSON only. | No. The model can answer in prose. |
| Mistral | Strict `json_schema` response format. Tools set on a structured request are **not sent**, because Mistral rejects a schema and tools in one request. | Yes. |

## Structured mode

`usingStructuredMode()` chooses how OpenAI receives the schema. Anthropic and Mistral ignore it.

| Mode | OpenAI behaviour |
|---|---|
| `StructuredMode.Auto` (default) | Structured mode for models that support it (`gpt-4o`, `chatgpt-4o`, `gpt-4.1`, `gpt-4.5`, `gpt-5` and later, and the o-series), JSON mode otherwise. A fine-tuned `ft:` model is judged by its base model. |
| `StructuredMode.Structured` | Always sends the schema with `strict: true`. |
| `StructuredMode.Json` | Sends `json_object`: the reply is valid JSON, but its shape is not enforced and the schema is not sent. Describe the shape in your prompt. |

Under `Auto`, `o1-mini` and `o1-preview` fail with `unsupported_structured_model`.

::: language-group
```ts
import { ObjectSchema, Prism, StringSchema, StructuredMode } from '@particle-academy/prism';

const response = await Prism.structured()
  .using('openai', 'gpt-4o')
  .withSchema(new ObjectSchema('city', 'A city', [new StringSchema('name', 'City name')], { requiredFields: ['name'] }))
  .usingStructuredMode(StructuredMode.Structured)
  .withPrompt('Name the capital of France.')
  .asStructured();
```

```python
from prism import Prism
from prism.enums import StructuredMode
from prism.schema import ObjectSchema, StringSchema

response = (
    Prism.structured()
    .using("openai", "gpt-4o")
    .with_schema(ObjectSchema("city", "A city", (StringSchema("name", "City name"),), ("name",)))
    .using_structured_mode(StructuredMode.STRUCTURED)
    .with_prompt("Name the capital of France.")
    .as_structured()
)
```
:::

In Python, `StructuredMode` is imported from `prism.enums`.

## The response

| TypeScript | Python | Type | Meaning |
|---|---|---|---|
| `structured` | `structured` | object or null | The parsed object. **Null when the reply was not a JSON object.** |
| `text` | `text` | string | The model's raw reply, kept so you can see why parsing failed. |
| `finishReason` | `finish_reason` | `FinishReason` | Why generation stopped. |
| `usage` | `usage` | `Usage` | Token counts, as for [text](/core-concepts/text-generation#the-response). |
| `meta` | `meta` | `Meta` | Response id, model and rate limits. |
| `steps` | `steps` | list of steps | Always one. |
| `additionalContent` | `additional_content` | object | Provider-specific extras. |
| `raw` | `raw` | object or null | The provider's decoded response body. |

`structured` is read from the whole reply, or from the first fenced code block if the whole reply does not parse. A JSON array or scalar is not accepted. A null `structured` is not an error: check for it.

The parsed object has not been validated against your schema by the ports. Validate it in your application before it authorizes an action or writes data, especially for Anthropic, where the schema is only requested.

In TypeScript, `asStructured()` accepts the same optional callback as `asText()`. Python's `as_structured()` takes no arguments.

## Errors

| Code | When |
|---|---|
| `missing_schema` | `withSchema()` was not called before the request was built. |
| `unsupported_structured_model` | OpenAI under `Auto` mode, for a model that cannot produce structured output. |
| `max_tokens_exceeded` | OpenAI stopped at the token limit. Anthropic and Mistral instead return with `finishReason` `Length`, usually with unparseable text and a null `structured`. |
| `tool_loop_not_supported` | The response ended on tool calls. |
| `provider_response_error` | The provider returned an HTTP error, an empty body or an error payload. |

See [Error handling](/advanced/error-handling) for catching `PrismError`.
