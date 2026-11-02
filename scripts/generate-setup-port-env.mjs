import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';

const root=path.resolve(import.meta.dirname,'..'),args=process.argv.slice(2),check=args.includes('--check');
const paths=args.filter(a=>a!=='--check');assert.equal(paths.length,2,'Pass checked-out TypeScript and Python port roots');
const result={schemaVersion:1,typescript:{},python:{},sources:[]};
for(const provider of ['openai','anthropic','mistral'])for(const[language,relative]of [['typescript',`src/providers/${provider}/${provider}.ts`],['python',`src/prism/providers/${provider}/provider.py`]]){
 const file=path.join(path.resolve(paths[language==='typescript'?0:1]),relative),source=fs.readFileSync(file,'utf8');assert(source.length<1000000);
 const expression=language==='typescript'?/\breadEnv\('([A-Z][A-Z0-9_]*)'\)/g:/\bos\.environ\.get\("([A-Z][A-Z0-9_]*)"/g;
 const names=[...new Set([...source.matchAll(expression)].map(m=>m[1]))].sort();assert(names.length>0);assert(names.every(n=>n.startsWith(provider.toUpperCase()+'_')));
 result[language][provider]=names;
 result.sources.push({language,provider,path:relative});
}
const target=path.join(root,'resources/providers/port-environment.json'),body=JSON.stringify(result,null,2)+'\n';
if(check)assert.equal(fs.readFileSync(target,'utf8'),body,'Port environment names/source drifted: regenerate and review');else fs.writeFileSync(target,body);
console.log('Port environment names match the actual provider sources (3 TypeScript, 3 Python).');
