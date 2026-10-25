# Batch processing

Submit many text requests for asynchronous processing at a lower price, then collect the results when the provider has finished. Both ports implement batch operations for **OpenAI** only. Anthropic and Mistral fail with `unsupported_provider_action`.

`Prism.batch().using(provider)` takes a provider and an optional provider config, and no model: each request in the batch names its own.

## Operations

| TypeScript | Python | Returns |
|---|---|---|
| `create(items?, inputFileId?)` | `create(items=None, input_file_id=None)` | `BatchJob` |
| `retrieve(batchId)` | `retrieve(batch_id)` | `BatchJob` |
| `list(limit?, afterId?, beforeId?)` | `list(limit=None, after_id=None, before_id=None)` | `BatchListResult` |
| `getResults(batchId)` | `get_results(batch_id)` | `BatchResultItem[]` (Python: tuple) |
| `cancel(batchId)` | `cancel(batch_id)` | `BatchJob` (status `cancelling`) |

OpenAI pages batches forward only, so `beforeId` / `before_id` is not sent.

## Create a batch from requests

Build each request with the text builder, freeze it with `toRequest()` / `to_request()`, and give it a `custom_id` of your own. The port writes the JSONL input file, uploads it with purpose `batch`, and creates the batch against OpenAI's Responses endpoint.

::: language-group
```ts
import { BatchRequestItem, Prism } from '@particle-academy/prism';

const questions = ['What is a heat pump?', 'How do solar panels work?'];

const items = questions.map(
  (question, index) =>
    new BatchRequestItem(
      `question-${index}`,
      Prism.text().using('openai', 'gpt-4o-mini').withPrompt(question).toRequest(),
    ),
);

const job = await Prism.batch().using('openai').create(items);
console.log(job.id, job.status);
```

```python
from prism import BatchRequestItem, Prism

questions = ["What is a heat pump?", "How do solar panels work?"]

items = [
    BatchRequestItem(
        custom_id=f"question-{index}",
        request=Prism.text().using("openai", "gpt-4o-mini").with_prompt(question).to_request(),
    )
    for index, question in enumerate(questions)
]

job = Prism.batch().using("openai").create(items)
print(job.id, job.status)
```
:::

## Create a batch from an uploaded file

If you already have a JSONL input file, upload it with purpose `batch` (see [Files](/core-concepts/files)) and pass its ID instead of items. Every line must target `/v1/responses`: the ports always create the batch against that endpoint.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const job = await Prism.batch()
  .using('openai')
  .withProviderOptions({ completion_window: '24h' })
  .create(null, 'file-your-batch-input');

console.log(job.id, job.inputFileId);
```

```python
from prism import Prism

job = (
    Prism.batch()
    .using("openai")
    .with_provider_options({"completion_window": "24h"})
    .create(input_file_id="file-your-batch-input")
)

print(job.id, job.input_file_id)
```
:::

Pass items **or** an input file ID, never both and never neither; either mistake fails with `provider_response_error` before any request is sent.

The only provider option read for a batch is `completion_window`, which defaults to `24h`.

## Poll and collect results

Persist the batch ID. Poll with `retrieve`, then read results once the batch has stopped.

::: language-group
```ts
import { BatchResultStatus, BatchStatus, Prism } from '@particle-academy/prism';

const batches = Prism.batch().using('openai');
const job = await batches.retrieve('batch-your-job-id');

const finished = [BatchStatus.Completed, BatchStatus.Failed, BatchStatus.Expired, BatchStatus.Cancelled];

if (finished.includes(job.status)) {
  for (const item of await batches.getResults(job.id)) {
    if (item.status === BatchResultStatus.Succeeded) {
      console.log(item.customId, item.text);
    } else {
      console.log(item.customId, item.status, item.errorMessage);
    }
  }
} else {
  console.log(`${job.requestCounts.succeeded} of ${job.requestCounts.total} done`);
}
```

```python
from prism import BatchResultStatus, BatchStatus, Prism

batches = Prism.batch().using("openai")
job = batches.retrieve("batch-your-job-id")

finished = {BatchStatus.COMPLETED, BatchStatus.FAILED, BatchStatus.EXPIRED, BatchStatus.CANCELLED}

if job.status in finished:
    for item in batches.get_results(job.id):
        if item.status == BatchResultStatus.SUCCEEDED:
            print(item.custom_id, item.text)
        else:
            print(item.custom_id, item.status, item.error_message)
else:
    print(f"{job.request_counts.succeeded} of {job.request_counts.total} done")
```
:::

`getResults()` reads both the output file and the error file, so failed requests are included alongside successful ones. Before the batch has written either file it returns an empty list. Results arrive in the order the provider finished them: match them back to your work by `customId` / `custom_id`.

Avoid resubmitting a batch after a timeout: the provider may already have accepted it. Use `list()` or your stored ID to check first.

## Result objects

### BatchJob

| Field | TypeScript | Python | Type | Meaning |
|---|---|---|---|---|
| ID | `id` | `id` | string | The batch ID. |
| Status | `status` | `status` | `BatchStatus` | Where the batch is in its lifecycle. |
| Counts | `requestCounts` | `request_counts` | `BatchJobRequestCounts` | Requests per state. |
| Created | `createdAt` | `created_at` | string or null | ISO 8601, UTC. |
| Expires | `expiresAt` | `expires_at` | string or null | ISO 8601, UTC. |
| Ended | `endedAt` | `ended_at` | string or null | When the batch completed (ISO 8601, UTC). |
| Results URL | `resultsUrl` | `results_url` | string or null | Null on OpenAI; use `getResults()`. |
| Input file | `inputFileId` | `input_file_id` | string or null | The JSONL input file. |
| Output file | `outputFileId` | `output_file_id` | string or null | Successful results, once written. |
| Error file | `errorFileId` | `error_file_id` | string or null | Failed requests, once written. |
| Errors | `errors` | `errors` | `BatchJobError[]` | Validation errors for the batch itself: `code`, `message`, `line` (or null), `param` (or null). |

An unrecognised status from the provider fails with `provider_response_error` rather than being mapped to a guess.

### BatchStatus

| TypeScript | Python | Value |
|---|---|---|
| `BatchStatus.Validating` | `BatchStatus.VALIDATING` | `validating` |
| `BatchStatus.InProgress` | `BatchStatus.IN_PROGRESS` | `in_progress` |
| `BatchStatus.Finalizing` | `BatchStatus.FINALIZING` | `finalizing` |
| `BatchStatus.Completed` | `BatchStatus.COMPLETED` | `completed` |
| `BatchStatus.Failed` | `BatchStatus.FAILED` | `failed` |
| `BatchStatus.Cancelling` | `BatchStatus.CANCELLING` | `cancelling` |
| `BatchStatus.Cancelled` | `BatchStatus.CANCELLED` | `cancelled` |
| `BatchStatus.Expired` | `BatchStatus.EXPIRED` | `expired` |

### BatchJobRequestCounts

| Field | Meaning |
|---|---|
| `total` | Requests in the batch. |
| `succeeded` | Requests that completed. |
| `failed` | Requests that failed. |
| `processing` | `total − succeeded − failed`. |
| `canceled`, `expired` | Always `0` on OpenAI, which does not report them. |

### BatchListResult

| Field | TypeScript | Python | Meaning |
|---|---|---|---|
| Jobs | `data` | `data` | The `BatchJob`s on this page. |
| More pages | `hasMore` | `has_more` | Whether another page follows. |
| Last ID | `lastId` | `last_id` | Pass as `afterId` / `after_id` for the next page. |

### BatchResultItem

| Field | TypeScript | Python | Meaning |
|---|---|---|---|
| Custom ID | `customId` | `custom_id` | The ID you gave the request. |
| Status | `status` | `status` | `BatchResultStatus`: `succeeded`, `errored` or `expired` (`canceled` exists but OpenAI does not produce it). |
| Text | `text` | `text` | The model's text; null on failure. |
| Usage | `usage` | `usage` | `Usage`; null on failure. |
| Message ID | `messageId` | `message_id` | The provider's response ID; null on failure. |
| Model | `model` | `model` | The model that answered; null on failure. |
| Error type | `errorType` | `error_type` | The provider's error code; null on success. |
| Error message | `errorMessage` | `error_message` | The provider's error message; null on success. |

Read `status` first: it says which pair of fields means anything.

## Errors

| Code | When |
|---|---|
| `unsupported_provider_action` | The provider has no batch operations, or `using()` was not called. |
| `provider_response_error` | Items and an input file ID were both given, or neither; the provider answered with an HTTP error or an `error` object; or it reported a batch status the ports do not know. |

See [Error handling](/advanced/error-handling).
