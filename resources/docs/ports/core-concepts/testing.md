# Testing

Inject an HTTP transport to exercise request building and response parsing without a live provider. The ports do not use Laravel's facade fake or PHPUnit assertions.

## Return a recorded response

The fake checks the outgoing prompt and returns the OpenAI Responses API shape. No credentials or network connection are needed.

::: language-group
```ts
import assert from 'node:assert/strict';
import { Prism, type HttpTransport } from '@particle-academy/prism';

const transport: HttpTransport = async (request) => {
  assert.ok(request.body.includes('Hello'));
  const body = {
    id: 'resp_test', status: 'completed', model: 'gpt-4o',
    output: [{ type: 'message', status: 'completed',
      content: [{ type: 'output_text', text: 'Hi!' }] }],
    usage: { input_tokens: 1, output_tokens: 2 },
  };
  return { status: 200, headers: {}, body, rawBody: JSON.stringify(body) };
};

const response = await Prism.text()
  .using('openai', 'gpt-4o', { apiKey: 'test-only', transport })
  .withPrompt('Hello')
  .asText();
assert.equal(response.text, 'Hi!');
```

```python
import json
from prism import HttpRequest, HttpResponse, Prism

class FakeTransport:
    def send(self, request: HttpRequest) -> HttpResponse:
        assert b"Hello" in request.body
        body = {
            "id": "resp_test", "status": "completed", "model": "gpt-4o",
            "output": [{"type": "message", "status": "completed",
                "content": [{"type": "output_text", "text": "Hi!"}]}],
            "usage": {"input_tokens": 1, "output_tokens": 2},
        }
        return HttpResponse(status=200, body=json.dumps(body).encode())

response = (
    Prism.text()
    .using("openai", "gpt-4o", {"api_key": "test-only", "transport": FakeTransport()})
    .with_prompt("Hello")
    .as_text()
)
assert response.text == "Hi!"
```
:::

## Test failure paths too

Return error status codes and malformed responses to verify your application's behavior. Assert that unsupported operations fail, rather than treating a successful empty response as a valid fallback. Streaming tests require an injected streaming transport.

The shared [parity corpus](/packages/language-ports) compares the implementations. Application tests still need to assert your expected behavior independently of language agreement.
