# Workspace

`particle-academy/prism-workspace`

::: language-group
```bash [language=php]
composer require particle-academy/prism-workspace
```

```bash [language=typescript]
npm install @particle-academy/prism-workspace
```

```bash [language=python]
pip install prism-ai-workspace
```
:::

Prism Workspace provides session-scoped file storage for agents in PHP,
TypeScript and Python. Path validation restricts file operations to the
workspace root. The PHP implementation integrates with Laravel Storage.

::: language-group
```php
use Prism\Workspace\Facades\PrismWorkspace;

$workspace = PrismWorkspace::for($session);

$workspace->write('reports/q1.md', $content);
$workspace->read('reports/q1.md');
$workspace->list();                       // a lazy collection
```

```ts
import { Workspace } from '@particle-academy/prism-workspace';

const workspace = new Workspace('/srv/agent-workspaces', session);

await workspace.write('reports/q1.md', content);
await workspace.read('reports/q1.md');

for await (const entry of workspace.list()) {
  console.log(entry.path);
}
```

```python
from prism_workspace import SyncWorkspace

workspace = SyncWorkspace("/srv/agent-workspaces", session)

workspace.write("reports/q1.md", content)
workspace.read("reports/q1.md")

for entry in workspace.list():
    print(entry.path)
```
:::

## Path validation

Paths may come from model output or user input and must be validated before
access. The guard rejects traversal, absolute and UNC paths, reserved device
names, alternate data streams, and encoded separators. Shared conformance
tests check path validation across PHP, TypeScript and Python.

## Directory listings

`list()` yields entries incrementally, so you can process a directory without
loading the complete listing into memory. PHP returns a lazy collection;
TypeScript uses an async iterator, and Python uses an iterator.

## Scope

Workspace provides file operations. Writing a file does not execute it or
grant access outside the workspace.

- [Source](https://github.com/Particle-Academy/prism-workspace)
- TypeScript and Python ports exist and are exercised by the same escape corpus.
  See [Language ports](/docs/packages/language-ports) for what a port currently
  guarantees.
