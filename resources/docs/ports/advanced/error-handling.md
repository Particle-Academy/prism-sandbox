# Error handling

Failures raise `PrismError` with a stable `code`. Branch on the code, not on the message: codes stay the same between releases, while messages may be reworded. The PHP package's exception classes do not exist in the ports.

## Handle an unsupported provider

This example fails during provider selection without making an HTTP request.

::: language-group
```ts
import { Prism, PrismError } from '@particle-academy/prism';

try {
  Prism.text().using('not-a-registered-provider', 'model');
} catch (error) {
  if (error instanceof PrismError && error.code === 'unsupported_provider_action') {
    console.log('Choose a provider implemented by this port.');
  } else {
    throw error;
  }
}
```

```python
from prism import Prism, PrismError

try:
    Prism.text().using("not-a-registered-provider", "model")
except PrismError as error:
    if error.code == "unsupported_provider_action":
        print("Choose a provider implemented by this port.")
    else:
        raise
```
:::

## The error object

The fields have different names in the two languages.

| TypeScript | Python | Contents |
|---|---|---|
| `code` | `code` | The stable code, a string from the table below. In Python, `ErrorCode` lists them; `ErrorCode` members compare equal to the plain strings. |
| `message` | `message` (also `str(error)`) | A human-readable description. Not stable. |
| `httpStatus` | `status` | The provider's HTTP status, when the failure came from an HTTP response. Otherwise `null` / `None`. |
| `responseBody` | `body` | The provider's raw response body, when it was kept. Often `null` / `None`, for example on streaming and audio failures. |
| `cause` | `__cause__` | The underlying error, when there was one (for example the file-system error behind `unreadable_media_file`). |

In TypeScript, refused URLs from `fetchPublic()` throw `PrismUrlRefused`, a subclass of `PrismError` whose `code` is one of the six URL refusal codes. Python has no subclass: it raises `PrismError` with the same codes.

A provider response body can echo your prompt or other request content. Treat `responseBody` / `body` as sensitive when logging.

## Error codes

| Code | Meaning | Raised when |
|---|---|---|
| `prompt_and_messages` | A request has both a prompt and a message list. | Building the request (`toRequest()` or a terminal method) after calling both `withPrompt()` and `withMessages()`. |
| `max_tokens_exceeded` | Generation stopped at the output-token limit. | OpenAI text or structured responses cut short. Raise `withMaxTokens()`, especially for reasoning models. |
| `provider_response_error` | The provider failed or returned an unusable payload. | Any HTTP status of 400 or above, a body that is empty or carries an `error`, an OpenAI batch given both or neither of items and an input file. `httpStatus` / `status` is set when there was an HTTP status. |
| `unsupported_provider_action` | The provider, or this port, does not do what was asked. | `using()` with an unregistered key; a capability the provider does not implement; an OpenAI `apiFormat` other than `responses`; a custom provider refusing part of a request. |
| `malformed_tool_call_arguments` | A tool call's arguments are not valid JSON. | Reading the arguments of a tool call the model produced. |
| `unknown_message_type` | A message or message part cannot be mapped. | Sending, or restoring from stored data, a message or part of a type this port does not know. |
| `tool_loop_not_supported` | The response ended on tool calls, and the ports do not run tools. | A non-streamed text or structured response that finishes on tool calls. Use streaming to receive tool calls; see [Tools](/core-concepts/tools-function-calling). |
| `canonical_json_unencodable` | A value cannot be encoded as JSON without being changed. | TypeScript only: a value sent to a provider contains `undefined`, a function or a symbol, for example a provider option set to `undefined`. Use `null` or leave the key out. Python raises `TypeError` instead. |
| `missing_schema` | A structured request has no schema. | Building a structured request without `withSchema()`. |
| `unsupported_structured_model` | The model cannot produce structured output. | OpenAI structured requests to `o1-mini` or `o1-preview`. |
| `no_embedding_input` | An embeddings request has no input. | Building an embeddings request without `fromInput()`, `fromArray()` or `fromFile()`. |
| `unreadable_input_file` | The embeddings input file cannot be read. | `fromFile()` / `from_file()` with a missing or unreadable path. |
| `no_image_prompt` | An image generation request has no prompt. | Building an images request without `withPrompt()`. |
| `no_moderation_input` | A moderation request has no input. | Building a moderation request without `withInput()`. |
| `unreadable_media_file` | A media file cannot be read, or is empty. | `fromLocalPath()` / `from_local_path()` on any media type. |
| `unfetchable_media` | A media URL could not be downloaded. | Calling a fetch on media with no URL, or the server answering with an error status. |
| `unsupported_media` | The provider cannot send a media part in the form given. | Sending an image, document or audio as a URL, bytes, file ID or chunks the provider does not accept, or bytes without a MIME type. The message names the accepted forms. |
| `wrong_audio_input` | An audio request has the input for the other direction. | Text to speech given an `Audio`, or speech to text given a string. |
| `no_audio_content` | A transcription input has no bytes. | Speech to text with an `Audio` that only has a URL. |
| `scheme_not_allowed` | Guarded fetch: not an absolute `http`/`https` URL. | `fetchPublic()` / `fetch_public()`. |
| `private_address_refused` | Guarded fetch: the host is or resolves to a private, reserved or invalid address. | `fetchPublic()` / `fetch_public()`. |
| `host_did_not_resolve` | Guarded fetch: the hostname did not resolve. | `fetchPublic()` / `fetch_public()`. |
| `redirect_refused` | Guarded fetch: a redirect pointed at a refused or invalid destination. | `fetchPublic()` / `fetch_public()`. |
| `too_many_redirects` | Guarded fetch: more redirects than allowed. | `fetchPublic()` / `fetch_public()`. |
| `media_too_large` | Guarded fetch: the body is larger than the byte limit. | `fetchPublic()` / `fetch_public()`. |

The guarded fetch is described in [Image input](/input-modalities/images#fetch-a-url-into-bytes).

A few failures are not `PrismError`: invalid arguments such as a negative `maxRedirects` raise the language's own error (`RangeError` in TypeScript, `ValueError` in Python), and network failures from the HTTP transport (DNS errors, refused connections, timeouts) arrive as the transport's own errors.

## Decide what to retry

::: language-group
```ts
import { PrismError } from '@particle-academy/prism';

const RETRYABLE_STATUSES = new Set([408, 429, 500, 502, 503, 504, 529]);

export function isRetryable(error: unknown): boolean {
  if (!(error instanceof PrismError)) {
    return false;
  }

  return (
    error.code === 'provider_response_error' &&
    error.httpStatus !== null &&
    RETRYABLE_STATUSES.has(error.httpStatus)
  );
}
```

```python
from prism import ErrorCode, PrismError

RETRYABLE_STATUSES = {408, 429, 500, 502, 503, 504, 529}


def is_retryable(error: BaseException) -> bool:
    if not isinstance(error, PrismError):
        return False

    return (
        error.code == ErrorCode.PROVIDER_RESPONSE_ERROR
        and error.status is not None
        and error.status in RETRYABLE_STATUSES
    )
```
:::

Every other code describes a problem with the request, the input or the configuration. Repeating the same call gives the same result.

## Application policy

Bound retries, and keep the original failure if the retry policy runs out. Never retry an unsupported operation or a refused URL. Retrying a non-idempotent operation (a file upload or batch creation) after an ambiguous timeout can create duplicates. For rate limits, see [Rate limits](/advanced/rate-limits).

Avoid logging credentials, complete request bodies or sensitive prompt content.
