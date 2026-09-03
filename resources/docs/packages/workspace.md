# Workspace

`particle-academy/prism-workspace`

A sandboxed place for an agent to keep its work: a scoped Laravel `Storage` disk
with an agent-shaped API over it, and a path guard that is the actual product.

```php
use Prism\Workspace\Facades\PrismWorkspace;

$workspace = PrismWorkspace::for($session);

$workspace->write('reports/q1.md', $content);
$workspace->read('reports/q1.md');
$workspace->list();                       // streamed, not materialised
```

## The path guard is the point

An agent chooses its own filenames. That is the whole hazard: every path an
agent writes is attacker-influenced the moment any part of the conversation is,
and "it is only a filename" is how a sandbox stops being one.

So the guard is not a `str_starts_with` on the root. It refuses traversal,
absolute paths, UNC paths, device names, alternate data streams, encoded
separators and byte-level tricks — and it is tested against a corpus of 134
escape attempts organised by hazard family rather than by a flat list, so a new
family can be added without renumbering the cases other repositories skip by id.

The corpus runs in CI in **all three languages**, from one shared set of cases,
because a guard that refuses an attack in PHP and admits it in TypeScript is not
a guard — it is a guard-shaped difference between two ports.

## Streamed, not materialised

`list()` returns a lazy collection. A workspace an agent has been building in
for an hour can hold more entries than fit comfortably in memory, and a listing
that has to be fully realised before its first row is read is a listing that
falls over exactly when the agent has been most productive.

## Scope

**Files only.** The workspace stores what an agent writes; it does not run it.
There is no code execution surface here, so nothing you put in a workspace
becomes executable by putting it there.

- [Source](https://github.com/Particle-Academy/prism-workspace)
- TypeScript and Python ports exist and are exercised by the same escape corpus.
  See [Language ports](/docs/packages/language-ports) for what a port currently
  guarantees.
