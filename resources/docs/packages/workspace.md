# Workspace

Prism Workspace gives an agent a directory of its own to keep its work in, in
PHP, TypeScript and Python. Every path is checked before it touches the disk, so
a path the model wrote cannot read or write outside the workspace.

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

The PHP package requires PHP 8.2+ and Laravel 12.69+ or 13.30+, and builds each
workspace on a Laravel filesystem disk (local, S3 or any other Flysystem
driver). The TypeScript package (Node.js 22+) and the Python package (3.10+)
have no runtime dependencies and work on the local filesystem.

Workspace is a storage API for your application code. It does not register
model tools and it never executes files. To let a model read or write files,
define your own tools that call these methods.

## Configuration

PHP works with no configuration: workspaces are created under
`storage/app/workspaces` on the `local` disk. To change that, publish
`config/prism-workspace.php`:

```bash
php artisan vendor:publish --tag=prism-workspace-config
```

| Key | Default | Env | Meaning |
|---|---|---|---|
| `disk` | `local` | `PRISM_WORKSPACE_DISK` | Any disk from `config/filesystems.php`. |
| `root` | `workspaces` | `PRISM_WORKSPACE_ROOT` | Directory on that disk that holds one subdirectory per owner. |
| `max_path_length` | `1024` | — | Maximum path length, in bytes. |
| `max_segment_length` | `255` | — | Maximum length of one path segment, in bytes. |
| `windows_max_path` | `259` | — | Maximum full path length on Windows local disks. Set `null` if long paths are enabled. |
| `authorize` | `false` | `PRISM_WORKSPACE_AUTHORIZE` | Check a Laravel Gate before every operation. See [Authorization](#authorization). |
| `gate_prefix` | `workspace` | `PRISM_WORKSPACE_GATE_PREFIX` | Prefix of the Gate abilities. |

TypeScript and Python take the same settings as constructor arguments: a base
directory, the owner, and the options `guard` (a `PathGuard` with custom
limits), `authorize` and `windowsMaxPath` / `windows_max_path`.

## Open a workspace

A workspace belongs to an **owner**. The same owner always resolves to the same
directory, so a later request or a fresh worker finds the files an earlier run
wrote.

::: language-group
```php
use Prism\Workspace\Facades\PrismWorkspace;

$workspace = PrismWorkspace::for($session);   // a Harness Session, model, WorkspaceOwner or string

$workspace->write('reports/q1.md', $content);
$markdown = $workspace->read('reports/q1.md');

foreach ($workspace->list('reports') as $entry) {
    echo $entry->path, PHP_EOL;
}
```

```ts
import { Workspace } from '@particle-academy/prism-workspace';

const workspace = new Workspace('/srv/agent-workspaces', session);

await workspace.write('reports/q1.md', content);
const markdown = await workspace.read('reports/q1.md');

for await (const entry of workspace.list('reports')) {
  console.log(entry.path);
}
```

```python
from prism_workspace import Workspace

workspace = Workspace("/srv/agent-workspaces", session)

await workspace.write("reports/q1.md", content)
markdown = await workspace.read("reports/q1.md")

async for entry in workspace.list("reports"):
    print(entry.path)
```
:::

Python's `Workspace` is async; `SyncWorkspace` has the same methods and blocks.
Both take the same constructor arguments.

### Owners

An owner can be:

- an object implementing `WorkspaceOwner` — `workspaceKey(): string` in PHP and
  TypeScript, `workspace_key() -> str` in Python;
- any object with a `key()` method returning a string, such as a Harness session;
- an Eloquent model (PHP only), addressed by its morph class and key;
- a non-empty string, such as a job id.

The key must be stable: if it changes between requests, the owner gets a new,
empty workspace. An owner that resolves to no key raises `WorkspaceFailed` with
`workspace_owner_not_addressable`.

::: language-group
```php
use Prism\Workspace\Contracts\WorkspaceOwner;

final class Project implements WorkspaceOwner
{
    public function __construct(private int $id) {}

    public function workspaceKey(): string
    {
        return "project:{$this->id}";
    }
}
```

```ts
import type { WorkspaceOwner } from '@particle-academy/prism-workspace';

class Project implements WorkspaceOwner {
  constructor(private readonly id: number) {}

  workspaceKey(): string {
    return `project:${this.id}`;
  }
}
```

```python
class Project:
    def __init__(self, id: int) -> None:
        self.id = id

    def workspace_key(self) -> str:
        return f"project:{self.id}"
```
:::

The directory name is `<slug>-<hash>`: a readable slug of the key (up to 48
characters) followed by the first 16 hex characters of its SHA-256. Owners whose
keys differ only in case get different directories. The address is the same in
all three languages, so they can share a base directory.

## Methods

| PHP | TypeScript | Python | Returns |
|---|---|---|---|
| `exists($path)` | `exists(path)` | `exists(path)` | bool |
| `read($path)` | `read(path)` | `read(path)` | file contents (TS and Python decode UTF-8) |
| — | `readBytes(path)` | `read_bytes(path)` | raw bytes |
| `readStream($path)` | `readStream(path)` | `read_stream(path, chunk_size=65536)` | PHP resource / Node `Readable` / iterator of byte chunks |
| `write($path, $contents)` | `write(path, contents)` | `write(path, contents)` | the workspace |
| `writeStream($path, $resource)` | `writeStream(path, source)` | `write_stream(path, source)` | the workspace |
| `append($path, $contents)` | `append(path, contents)` | `append(path, contents)` | the workspace |
| `copy($from, $to)` | `copy(from, to)` | `copy(source, destination)` | the workspace |
| `move($from, $to)` | `move(from, to)` | `move(source, destination)` | the workspace |
| `delete($path)` | `delete(path)` | `delete(path)` | the workspace |
| `makeDirectory($path)` | `makeDirectory(path)` | `make_directory(path)` | the workspace |
| `deleteDirectory($path)` | `deleteDirectory(path)` | `delete_directory(path)` | the workspace |
| `size($path)` | `size(path)` | `size(path)` | bytes |
| `lastModified($path)` | `lastModified(path)` | `last_modified(path)` | Unix timestamp, seconds |
| `list($directory = '', $recursive = true)` | `list(directory?, recursive?)` | `list(directory="", recursive=True)` | entries, streamed |
| `clear()` | `clear()` | `clear()` | the workspace; deletes everything inside it |
| `path($path)` | `path(path)` | `path(path)` | the guarded, normalised path |
| `address()` | `address` | `address` | the owner's directory name |
| `root()` | `root` | `root` | absolute directory (PHP returns `null` for non-local disks) |
| `disk()` | — | — | the underlying Laravel disk |

PHP's `read()` returns the file's bytes as a string, so it is safe for binary
files. In TypeScript and Python, use `readBytes()` / `read_bytes()` for
anything that is not UTF-8 text; `read()` would corrupt it. In TypeScript and
Python, paths may be passed as strings or as raw bytes (`Uint8Array` / `bytes`).
Python's `append()` takes text only.

Writes create missing parent directories. `copy()` guards both paths. `delete()`
on a file that does not exist is not an error.

::: warning `disk()` bypasses the guard
`disk()` returns the scoped Laravel disk itself. Paths passed to it are not
checked. Never pass a model-supplied path to it.
:::

### Listing entries

`list()` streams: PHP returns a `LazyCollection`, TypeScript an async iterator
and Python an iterator (`SyncWorkspace`) or async iterator (`Workspace`). A
directory of ten thousand files does not have to be loaded at once, and taking
the first five entries reads only five.

Each entry has:

| PHP / TypeScript | Python | Value |
|---|---|---|
| `path` | `path` | Path relative to the workspace root, with forward slashes. |
| `isDirectory` | `is_directory` | Whether the entry is a directory. |
| `size` | `size` | Size in bytes, or `null`/`None`. |
| `lastModified` | `last_modified` | Unix timestamp, or `null`/`None`. |

`size` and `lastModified` are filled from the listing when the PHP disk reports
them; TypeScript and Python always leave them empty so a listing never costs a
`stat` per file. Call `size()` / `lastModified()` when you need them. PHP and
Python entries also have `isFile()` / `is_file` and `name()` / `name`.

## Path validation

Paths usually come from model output, so every operation runs the path through
a guard before anything else happens. The guard refuses, without touching the
disk:

- traversal: any segment starting with `..`;
- absolute paths, drive letters, UNC and device-namespace paths;
- null bytes, control characters, invalid UTF-8;
- invisible and direction-changing characters, and characters that look like
  `/` or `\`;
- percent-encoded separators and dots (`%2e%2e%2f`);
- NTFS alternate data streams (`file.txt:hidden`), reserved device names (`CON`,
  `NUL`, `COM1`…), 8.3 short names (`PROGRA~1`), and segments with a leading or
  trailing dot or space;
- `~` home expansion;
- empty paths and paths over the length limits.

On local disks a second check resolves the real path and refuses a path that
leaves the workspace through a symbolic link or junction, and on Windows one
that exceeds `windows_max_path`.

A valid path is normalised: backslashes become `/`, `.` and empty segments are
removed. `path()` returns that normalised form without performing an operation.

## Authorization

Path validation keeps every operation inside the workspace. Authorization adds
your own rule on top: whether this user or agent may do this operation here.
It is off by default.

In PHP, set `'authorize' => true` and define Gate abilities named
`<gate_prefix>.read`, `.write`, `.delete` and `.list`. Each receives the
workspace and the guarded path (`null` for `clear()`). In PHP, `list()` of the
whole workspace (no directory argument) is not checked against the Gate; listing
a subdirectory checks `.list`. A denial throws Laravel's `AuthorizationException`.

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Prism\Workspace\Workspace;

Gate::define('workspace.read', fn (?User $user, Workspace $workspace, ?string $path) => true);
Gate::define('workspace.list', fn (?User $user, Workspace $workspace, ?string $path) => true);
Gate::define('workspace.write', fn (?User $user, Workspace $workspace, ?string $path)
    => $path === null || ! str_starts_with($path, 'published/'));
Gate::define('workspace.delete', fn (?User $user, Workspace $workspace, ?string $path) => $user !== null);
```

::: warning Make the user parameter nullable
Laravel does not run a Gate callback for a guest unless its first parameter is
nullable (`?User`). In a queue worker there is no authenticated user, so a
callback written `fn (User $user, ...)` denies every operation.
:::

In TypeScript and Python, pass an `authorize` callback. It is called with the
ability (`read`, `write`, `delete` or `list`), the workspace and the guarded
path (`undefined`/`None` for `clear()` and for listing the root); throw to
deny. In TypeScript it may be async. In Python it is a plain
function, and receives the `SyncWorkspace` even when you use the async
`Workspace`.

::: language-group
```ts
import { Workspace, type Authorizer } from '@particle-academy/prism-workspace';

const authorize: Authorizer = (ability, _workspace, path) => {
  if (ability !== 'read' && ability !== 'list' && path?.startsWith('published/')) {
    throw new Error('Published files are read-only.');
  }
};

const workspace = new Workspace('/srv/agent-workspaces', session, { authorize });
```

```python
from prism_workspace import SyncWorkspace, Workspace


def authorize(ability: str, workspace: SyncWorkspace, path: str | None) -> None:
    if ability not in ("read", "list") and path and path.startswith("published/"):
        raise PermissionError("Published files are read-only.")


workspace = Workspace("/srv/agent-workspaces", session, authorize=authorize)
```
:::

Every operation runs in the same order: guard the path, then authorize, then
check where the path leads on disk. Your policy never sees a path that failed
the guard.

## Errors

Two exceptions, each with a stable code. Branch on the code, not the message.

| | PHP | TypeScript | Python |
|---|---|---|---|
| Path refused | `PathRefused` — `->refusal` (`Refusal` enum), `->path` | `PathRefused` — `.code`, `.path` | `PathRefused` — `.code` (`Refusal` enum), `.path` |
| Operation failed | `WorkspaceFailed` — `->fault` (`Fault` enum) | `WorkspaceFailed` — `.code` | `WorkspaceFailed` — `.fault` (`Fault` enum) |

A refusal means something tried to leave the workspace; treat it as a security
event and alert on it. A failure is an ordinary operational problem.

**Refusal codes**

| Code | Refused because |
|---|---|
| `path_traverses_outside_workspace` | a segment starts with `..` |
| `path_is_absolute` | starts with `/`, a backslash or a drive letter (`C:`) |
| `path_is_unc_or_device_namespace` | starts with two separators (a UNC or device-namespace path) |
| `path_contains_null_byte` | null byte |
| `path_contains_control_character` | control character |
| `path_contains_invisible_character` | invisible or direction-changing character |
| `path_contains_separator_homoglyph` | a character that looks like a slash |
| `path_is_not_valid_utf8` | invalid UTF-8 |
| `path_contains_encoded_separator` | percent-encoded separator, dot or similar |
| `path_contains_alternate_data_stream` | a colon in a segment |
| `path_is_reserved_device_name` | `CON`, `NUL`, `COM1`, `LPT1` and similar |
| `path_has_edge_dot_or_space` | a segment begins or ends with a dot or space |
| `path_uses_short_name_alias` | an 8.3 short name such as `PROGRA~1` |
| `path_uses_home_expansion` | a leading `~` segment |
| `path_is_empty` | empty path |
| `path_too_long` | over the path length limit |
| `path_segment_too_long` | a segment over the segment length limit |
| `path_escapes_workspace_via_link` | the path leaves the workspace through a link |

**Failure codes**

| Code | Meaning |
|---|---|
| `workspace_file_missing` | the file does not exist (`read`, `readStream`, `size`, `lastModified`) |
| `workspace_write_failed` | a write, append, copy, move or directory creation failed |
| `workspace_delete_failed` | a delete failed |
| `workspace_owner_not_addressable` | the owner has no usable key |

::: language-group
```php
use Prism\Workspace\Exceptions\PathRefused;
use Prism\Workspace\Exceptions\Refusal;

try {
    $workspace->read($pathFromModel);
} catch (PathRefused $refused) {
    if ($refused->refusal === Refusal::Traversal) {
        report($refused);
    }
}
```

```ts
import { PathRefused } from '@particle-academy/prism-workspace';

try {
  await workspace.read(pathFromModel);
} catch (error) {
  if (error instanceof PathRefused && error.code === 'path_traverses_outside_workspace') {
    console.warn('Escape attempt', error.path);
  }
}
```

```python
from prism_workspace import PathRefused, Refusal

try:
    await workspace.read(path_from_model)
except PathRefused as refused:
    if refused.code is Refusal.TRAVERSAL:
        log.warning("Escape attempt: %r", refused.path)
```
:::

## Verify the boundary on your setup

The guard's test suite proves it on our disks. Your disk driver, root,
network share or case-insensitive volume may behave differently, so the escape
corpus ships with the package: 134 hostile paths, each with the refusal code it
must produce. `CorpusRunner` writes every one of them into a real workspace and
then searches the directories around the workspace for anything that got out.

::: language-group
```php
use Prism\Workspace\Facades\PrismWorkspace;
use Prism\Workspace\Security\CorpusRunner;

$report = (new CorpusRunner)->against(PrismWorkspace::for('corpus-check'));

if (! $report->passed()) {
    throw new RuntimeException($report->summary());
}
```

```ts
import { Workspace } from '@particle-academy/prism-workspace';
import { CorpusRunner } from '@particle-academy/prism-workspace/corpus';

const report = await new CorpusRunner().against(new Workspace(base, 'corpus-check'));

if (!report.passed()) throw new Error(report.summary());
```

```python
from prism_workspace import SyncWorkspace
from prism_workspace.corpus import CorpusRunner

report = CorpusRunner().against(SyncWorkspace(base, "corpus-check"))

if not report.passed():
    raise RuntimeError(report.summary())
```
:::

A run is safe against a live workspace: every attempt should be refused, so a
passing run writes nothing. The report lists `failures()` (attempts that were
accepted, refused with the wrong code, or failed with some other error) and
`strays` (files found outside the workspace). On disks without a local root,
such as S3, PHP skips the outside search. In TypeScript and Python, a search
that stopped at its file limit is reported in `sweepComplete` /
`sweep_complete` and does not pass.

## With Harness

A Harness `Session` has a `key()`, so it can be passed directly as the owner:
each session gets its own workspace, and a resumed session finds its files
again. Harness is not required — any stable string works.

A workspace is not deleted when its session ends. Call `clear()` when your
application no longer needs the files.

Source: [Particle-Academy/prism-workspace](https://github.com/Particle-Academy/prism-workspace).
