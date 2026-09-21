# Batch processing

Submit work for asynchronous provider processing. Both ports currently implement batch operations with OpenAI.

## Create a batch from an uploaded input file

Prepare a provider-compatible JSONL input file and upload it with purpose `batch`. The example uses an existing input file ID; replace it with the ID returned by your upload.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const batches = Prism.batch().using('openai')
  .withProviderOptions({ endpoint: '/v1/responses', completion_window: '24h' });
const job = await batches.create(null, 'file-your-batch-input');

console.log(job);
```

```python
from prism import Prism

batches = (
    Prism.batch().using("openai")
    .with_provider_options({
        "endpoint": "/v1/responses",
        "completion_window": "24h",
    })
)
job = batches.create(input_file_id="file-your-batch-input")

print(job)
```
:::

## Retrieve results

Use `retrieve` to check a saved batch ID. Only fetch results once the job has completed, and inspect individual result failures as well as the job status.

::: language-group
```ts
import { Prism } from '@particle-academy/prism';

const batches = Prism.batch().using('openai');
const job = await batches.retrieve('batch-your-job-id');
console.log(job);
// After your application confirms completion:
// const results = await batches.getResults('batch-your-job-id');
```

```python
from prism import Prism

batches = Prism.batch().using("openai")
job = batches.retrieve("batch-your-job-id")
print(job)
# After your application confirms completion:
# results = batches.get_results("batch-your-job-id")
```
:::

Persist batch IDs and per-item identifiers so your application can reconcile output with submitted work. Avoid immediately resubmitting a batch after a timeout: the provider may already have accepted it.
