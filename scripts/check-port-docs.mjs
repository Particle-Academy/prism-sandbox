// Run against checked-out ports: node scripts/check-port-docs.mjs ../prism-ts ../prism-py
// No provider requests: TypeScript is typechecked, Python builders use mocked terminals.
import { readdirSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve, join } from 'node:path';
import { createRequire } from 'node:module';
import { spawnSync } from 'node:child_process';

const root = resolve(import.meta.dirname, '..');
const tsRoot = resolve(process.argv[2] ?? '../prism-ts');
const pyRoot = resolve(process.argv[3] ?? '../prism-py');
const require = createRequire(join(tsRoot, 'package.json'));
const ts = require('typescript');
const temp = mkdtempSync(join(tmpdir(), 'prism-port-docs-'));
const snippets = [];
function scan(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) scan(path);
    else if (entry.name.endsWith('.md')) {
      let index = 0;
      for (const match of readFileSync(path, 'utf8').matchAll(/^```(ts|python)\s*\r?\n([\s\S]*?)^```/gm)) {
        snippets.push({ path, index: ++index, language: match[1], code: match[2] });
      }
    }
  }
}

try {
  scan(join(root, 'resources/docs/ports'));
  const files = snippets.filter(s => s.language === 'ts').map((snippet, index) => {
    const file = join(temp, `example-${index}.mts`);
    writeFileSync(file, snippet.code);
    return file;
  });
  const program = ts.createProgram(files, {
    strict: true, noEmit: true, skipLibCheck: true,
    target: ts.ScriptTarget.ES2022,
    module: ts.ModuleKind.NodeNext, moduleResolution: ts.ModuleResolutionKind.NodeNext,
    paths: { '@particle-academy/prism': [join(tsRoot, 'src/index.ts')] },
    typeRoots: [join(tsRoot, 'node_modules/@types')], types: ['node'],
  });
  const diagnostics = ts.getPreEmitDiagnostics(program);
  if (diagnostics.length) {
    const tsSnippets = snippets.filter(s => s.language === 'ts');
    for (const d of diagnostics) {
      const index = files.indexOf(d.file?.fileName);
      console.error(index >= 0 ? `${tsSnippets[index].path} example ${tsSnippets[index].index}` : d.file?.fileName);
      console.error(ts.flattenDiagnosticMessageText(d.messageText, '\n'));
    }
    process.exitCode = 1;
  } else console.log(`TypeScript: ${files.length} examples typecheck against the port source.`);

  const python = spawnSync('python', [join(root, 'scripts/check-port-docs.py'), pyRoot], {
    input: JSON.stringify(snippets.filter(s => s.language === 'python')),
    encoding: 'utf8', env: { ...process.env, PYTHONDONTWRITEBYTECODE: '1' },
  });
  process.stdout.write(python.stdout ?? '');
  process.stderr.write(python.stderr ?? '');
  if (python.error) console.error(python.error.message);
  if (python.status !== 0) process.exitCode = 1;
} finally {
  rmSync(temp, { recursive: true, force: true });
}
