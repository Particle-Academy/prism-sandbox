# Text generation

Build a request with a provider, model and prompt, then execute it. OpenAI, Anthropic and Mistral support text in both ports.

## Generate text

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withSystemPrompt('You are a concise technical writer.')
  .withPrompt('Explain how a heat pump works.')
  .withMaxTokens(300)
  .usingTemperature(0.3)
  .asText();

console.log(response.text);
console.log(response.usage.promptTokens, response.usage.completionTokens);
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_system_prompt("You are a concise technical writer.")
    .with_prompt("Explain how a heat pump works.")
    .with_max_tokens(300)
    .using_temperature(0.3)
    .as_text()
)

print(response.text)
print(response.usage.prompt_tokens, response.usage.completion_tokens)
```
:::

## System prompts

`withSystemPrompt()` **appends**: calling it twice sends two system prompts, in call order. `withSystemPrompts()` **replaces** the whole list. Both accept a string or a `SystemMessage` (the plural form takes `SystemMessage` objects only).

::: language-group
```ts
import { Prism, SystemMessage } from '@particle-academy/prism';

const pending = Prism.text()
  .using('anthropic', 'claude-sonnet-4-5')
  .withSystemPrompt('You are a support agent for Acme.')
  .withSystemPrompt('Answer in one paragraph.');

// Discards both prompts above.
pending.withSystemPrompts([new SystemMessage('You are a translator.')]);
```

```python
from prism import Prism, SystemMessage

pending = (
    Prism.text()
    .using("anthropic", "claude-sonnet-4-5")
    .with_system_prompt("You are a support agent for Acme.")
    .with_system_prompt("Answer in one paragraph.")
)

# Discards both prompts above.
pending.with_system_prompts([SystemMessage("You are a translator.")])
```
:::

## Images and documents in the prompt

`withPrompt()` takes an optional second argument: a list of message parts sent with the prompt in the same user turn. See [Images](/input-modalities/images) and [Documents](/input-modalities/documents) for the forms each provider accepts.

::: language-group
```ts
import { Image, Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('What is in this picture?', [Image.fromUrl('https://example.com/photo.jpg')])
  .asText();
```

```python
from prism import Image, Prism

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_prompt("What is in this picture?", [Image.from_url("https://example.com/photo.jpg")])
    .as_text()
)
```
:::

## Conversation history

Pass message value objects to supply earlier turns. Keep the system prompt separate from the conversation.

::: language-group
```ts
import { AssistantMessage, Prism, UserMessage } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withMessages([
    new UserMessage('What is a heat pump?'),
    new AssistantMessage('A device that moves heat between places.'),
    new UserMessage('Can it cool a room too?'),
  ])
  .asText();
console.log(response.text);
```

```python
from prism import AssistantMessage, Prism, UserMessage

response = (
    Prism.text()
    .using("openai", "gpt-4o")
    .with_messages([
        UserMessage("What is a heat pump?"),
        AssistantMessage("A device that moves heat between places."),
        UserMessage("Can it cool a room too?"),
    ])
    .as_text()
)
print(response.text)
```
:::

A request can carry a prompt **or** a message list, not both. Setting both fails with `prompt_and_messages` when the request is built. To continue a conversation, put the new user turn at the end of the message list.

`response.messages` is the input conversation plus the assistant reply, so you can pass it to the next request. It does not include system prompts.

## Generation settings

| TypeScript | Python | Effect |
|---|---|---|
| `withMaxTokens(n)` | `with_max_tokens(n)` | Output token limit. Anthropic requires one and defaults to 4096 when unset. |
| `usingTemperature(t)` | `using_temperature(t)` | Sampling temperature. |
| `usingTopP(p)` | `using_top_p(p)` | Nucleus sampling. |
| `usingTopK(k)` | `using_top_k(k)` | Top-k sampling. **Sent to Anthropic only**; OpenAI and Mistral ignore it. |
| `withReasoning(false)` | `with_reasoning(False)` | Asks the provider to skip reasoning: OpenAI sends `reasoning.effort = minimal`, Anthropic drops `thinking`. Mistral ignores it. `withReasoning(true)` sends nothing. |
| `withProviderOptions({...})` | `with_provider_options({...})` | Provider-specific request fields; see below. Replaces any earlier options. |
| `withTools([...])`, `withToolChoice(...)` | `with_tools([...])`, `with_tool_choice(...)` | See [Tools and function calling](/core-concepts/tools-function-calling). |
| `withProviderTools([...])` | `with_provider_tools([...])` | Built-in provider tools such as OpenAI's `web_search`. **Sent to OpenAI only.** |
| `withClientOptions({...})` | `with_client_options({...})` | Transport options; see [Configuration](/getting-started/configuration#timeouts-and-client-options). |
| `withMaxSteps(n)` | `with_max_steps(n)` | Recorded on the request but has no effect: the ports do not run tools, so every response has exactly one step. |

## Provider options

`withProviderOptions()` passes fields that only one provider understands. Each provider reads a fixed set of keys and ignores the rest:

- **OpenAI:** `reasoning`, `text_verbosity`, `metadata`, `parallel_tool_calls`, `previous_response_id`, `service_tier`, `store`, `truncation`.
- **Anthropic:** `thinking`, `effort`, `metadata`, `stop_sequences`.
- **Mistral:** `reasoning_effort`.

The provider pages ([OpenAI](/providers/openai), [Anthropic](/providers/anthropic), [Mistral](/providers/mistral)) describe each key.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-5')
  .withProviderOptions({ reasoning: { effort: 'low' }, store: false })
  .withPrompt('Summarise the plot of Hamlet.')
  .asText();
```

```python
from prism import Prism

response = (
    Prism.text()
    .using("openai", "gpt-5")
    .with_provider_options({"reasoning": {"effort": "low"}, "store": False})
    .with_prompt("Summarise the plot of Hamlet.")
    .as_text()
)
```
:::

## Response callback

In TypeScript, `asText()` accepts an optional callback that receives the pending request and the response before the promise resolves. Python's `as_text()` takes no arguments; use the returned response directly.

```ts
import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Hello!')
  .asText((pending, result) => {
    console.log(pending.model(), result.usage.completionTokens);
  });
```

## The response

| TypeScript | Python | Type | Meaning |
|---|---|---|---|
| `text` | `text` | string | The generated text. |
| `finishReason` | `finish_reason` | `FinishReason` | Why generation stopped; see below. |
| `usage` | `usage` | `Usage` | Token counts. |
| `meta` | `meta` | `Meta` | Response id, model and rate limits. |
| `messages` | `messages` | list of messages | The input conversation plus the assistant reply. |
| `steps` | `steps` | list of steps | One entry per provider round trip. Always one in the ports. Each step carries the same fields as the response for that round trip. |
| `toolCalls` | `tool_calls` | list of `ToolCall` | Always empty; a response that ends on tool calls throws instead. See [Tools](/core-concepts/tools-function-calling#receive-a-tool-call). |
| `toolResults` | `tool_results` | list of `ToolResult` | Always empty. |
| `additionalContent` | `additional_content` | object | Provider-specific extras, such as Anthropic and Mistral `thinking`, or OpenAI `reasoningSummaries` and web-search details. |
| `raw` | `raw` | object or null | The provider's decoded response body. |

`Usage`:

| TypeScript | Python | Meaning |
|---|---|---|
| `promptTokens` | `prompt_tokens` | Input tokens. For OpenAI this excludes cached tokens, which are reported separately. |
| `completionTokens` | `completion_tokens` | Output tokens. |
| `cacheWriteInputTokens` | `cache_write_input_tokens` | Tokens written to the prompt cache (Anthropic), or null. |
| `cacheReadInputTokens` | `cache_read_input_tokens` | Tokens read from the prompt cache, or null. |
| `thoughtTokens` | `thought_tokens` | Reasoning tokens, or null when the provider reported none. |
| `cost` | `cost` | Always null for the three supported providers. |

`Meta`:

| TypeScript | Python | Meaning |
|---|---|---|
| `id` | `id` | The provider's response id. |
| `model` | `model` | The model that answered. |
| `rateLimits` | `rate_limits` | Parsed rate-limit headers; see [Rate limits](/advanced/rate-limits). |
| `serviceTier` | `service_tier` | OpenAI's service tier, or null. |

`FinishReason` values: `Stop`, `Length`, `ContentFilter`, `ToolCalls`, `Pause`, `Refusal`, `Error`, `Other`, `Unknown` (Python: `STOP`, `LENGTH` and so on).

## Truncated output

What happens when the model reaches the token limit depends on the provider:

- **OpenAI** throws `PrismError` with code `max_tokens_exceeded`. On a reasoning model, raise the limit to cover reasoning tokens.
- **Anthropic and Mistral** return the partial text with `finishReason` set to `Length`. Check it before treating the answer as complete.

::: language-group
```ts
import { FinishReason, Prism, PrismError } from '@particle-academy/prism';

try {
  const response = await Prism.text()
    .using('anthropic', 'claude-sonnet-4-5')
    .withMaxTokens(200)
    .withPrompt('Write a long essay about rivers.')
    .asText();

  if (response.finishReason === FinishReason.Length) {
    console.warn('The answer was cut off.');
  }
} catch (error) {
  if (error instanceof PrismError && error.code === 'max_tokens_exceeded') {
    console.warn('The provider ran out of output tokens.');
  } else {
    throw error;
  }
}
```

```python
from prism import FinishReason, Prism, PrismError

try:
    response = (
        Prism.text()
        .using("anthropic", "claude-sonnet-4-5")
        .with_max_tokens(200)
        .with_prompt("Write a long essay about rivers.")
        .as_text()
    )

    if response.finish_reason is FinishReason.LENGTH:
        print("The answer was cut off.")
except PrismError as error:
    if error.code != "max_tokens_exceeded":
        raise
    print("The provider ran out of output tokens.")
```
:::

## Errors

Every failure is a `PrismError` with a stable `code`. The message text can change between releases; branch on the code.

| Code | When |
|---|---|
| `prompt_and_messages` | Both `withPrompt()` and `withMessages()` were set. |
| `max_tokens_exceeded` | OpenAI stopped at the token limit. |
| `tool_loop_not_supported` | The response ended on tool calls. Use [streaming](/core-concepts/tools-function-calling#receive-a-tool-call) to receive them. |
| `provider_response_error` | The provider returned an HTTP error, an empty body or an error payload. The HTTP status is on `httpStatus` (TypeScript) or `status` (Python); TypeScript also sets `responseBody`, Python `body`. |
| `unsupported_provider_action` | An unknown provider name, a call before `using()`, or an OpenAI `apiFormat` other than `responses`. |
| `unknown_message_type` | A value in the message list is not a message class. |
| `malformed_tool_call_arguments` | A `ToolCall` in the history has arguments that are not valid JSON. |

Media parts add their own codes; see [Images](/input-modalities/images). The full list is in [Error handling](/advanced/error-handling).

## Inspect a request

Use `toRequest()` / `to_request()` to build a request without sending it. Use [streaming](/core-concepts/streaming-output) for incremental output and [structured output](/core-concepts/structured-output) for schema-shaped results. The [Harness](/packages/harness) owns durable conversations and agent orchestration.
