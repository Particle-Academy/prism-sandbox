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

## Request lifecycle

Use `toRequest()` / `to_request()` to inspect a request without sending it. Use [streaming](/core-concepts/streaming-output) for incremental output and [structured output](/core-concepts/structured-output) for schema-shaped results. The [Harness](/packages/harness) owns durable conversations and agent orchestration.
