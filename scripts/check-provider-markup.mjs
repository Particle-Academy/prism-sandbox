import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';import {pathToFileURL} from 'node:url';
import {build} from 'vite';

const root=path.resolve(import.meta.dirname,'..'),pages=path.join(root,'resources/js/pages/Providers');
export function assertNoInputs(html){assert(!/<\s*(?:input|textarea|form)\b|\bcontenteditable\s*=/i.test(html),'Setup pages must never render inputs, textareas, forms or contenteditable');}
for(const file of fs.readdirSync(pages).filter(f=>f.endsWith('.tsx'))){const source=fs.readFileSync(path.join(pages,file),'utf8');assert(!/<\s*(?:input|textarea|select|form|button|a)\b/.test(source),'Use Fancy controls, never raw interactive HTML: '+file);assert(!/\b(?:contentEditable|onPaste|dangerouslySetInnerHTML)\b/.test(source),'Forbidden setup input surface: '+file);}

const data=JSON.parse(execFileSync('php',[path.join(root,'scripts/provider-setup-props.php')],{cwd:root,encoding:'utf8',maxBuffer:2000000}));
const temp=fs.mkdtempSync(path.join(root,'.provider-setup-check-'));assert.equal(path.dirname(temp),root);assert(path.basename(temp).startsWith('.provider-setup-check-'));
try{
 const entry='virtual:provider-setup-render';
 const source=`import React from 'react';import {renderToString} from 'react-dom/server';import {createInertiaApp} from '@inertiajs/react';import Index from ${JSON.stringify(path.join(pages,'Index.tsx').replaceAll('\\','/'))};import Show from ${JSON.stringify(path.join(pages,'Show.tsx').replaceAll('\\','/'))};export async function render(component,props){return (await createInertiaApp({page:{component,props:{errors:{},flash:{},demoEnabled:false,...props},url:'/providers/setup',version:'setup-check'},render:renderToString,resolve:()=>component==='Providers/Index'?Index:Show,setup:({App,props})=>React.createElement(App,props)})).body;}`;
 await build({root,configFile:false,logLevel:'error',plugins:[{name:'setup-render-fixture',resolveId:id=>id===entry?'\0'+entry:null,load:id=>id==='\0'+entry?source:null}],build:{ssr:true,outDir:temp,emptyOutDir:false,rollupOptions:{input:entry,output:{entryFileNames:'render.mjs'}}}});
 const {render}=await import(pathToFileURL(path.join(temp,'render.mjs')).href);let count=0;
 const index=await render('Providers/Index',data.index);assertNoInputs(index);count++;
 for(const props of data.providers){const html=await render('Providers/Show',props);assertNoInputs(html);assert(html.includes('data-react-fancy-stepper'));assert(html.includes('Not yet walked through end to end.')||html.includes('Walked through on '));count++;}
 if(process.argv.includes('--negative-input'))assertNoInputs(index+'<input value="owned-negative-control">');
 console.log(`No credential inputs: ${count} actual server-rendered setup pages; Fancy-only source guard passes.`);
}finally{assert.equal(path.dirname(temp),root);assert(path.basename(temp).startsWith('.provider-setup-check-'));fs.rmSync(temp,{recursive:true,force:true});}
