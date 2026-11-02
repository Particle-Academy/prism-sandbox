import fs from 'node:fs';
import path from 'node:path';
import {pathToFileURL} from 'node:url';

const hosts=new Set(['platform.openai.com','developers.openai.com','platform.claude.com','console.mistral.ai','docs.mistral.ai','aistudio.google.com','ai.google.dev','console.x.ai','docs.x.ai','www.alibabacloud.com','modelstudio.console.alibabacloud.com','replicate.com','azure.microsoft.com','portal.azure.com','learn.microsoft.com']);
const consoleHosts={openai:['platform.openai.com','auth.openai.com'],anthropic:['platform.claude.com'],mistral:['console.mistral.ai','auth.mistral.ai','admin.mistral.ai'],gemini:['aistudio.google.com','accounts.google.com'],xai:['console.x.ai','accounts.x.ai','auth.x.ai'],qwen:['modelstudio.console.alibabacloud.com','signin.alibabacloud.com'],replicate:['replicate.com'],azure:['portal.azure.com','login.microsoftonline.com','login.microsoft.com']};
export function permitted(value){const url=new URL(value);const publicQuery=[...url.searchParams].every(([name,value])=>['hl','lang','locale','view'].includes(name)&&/^[A-Za-z0-9._-]{1,60}$/.test(value));if(url.protocol!=='https:'||url.username||url.password||url.port||!publicQuery||!hosts.has(url.hostname))throw Error('URL must be a credential-free HTTPS page on an approved official host');return url;}
export async function checkLink(value,{request=fetch,pause=ms=>new Promise(r=>setTimeout(r,ms)),timeout=12000,classification='public',provider=null}={}){
 if(!['public','auth-gated'].includes(classification))throw Error('Unknown link classification');
 const original=permitted(value);let url=original;
 if(classification==='auth-gated'&&!consoleHosts[provider]?.includes(original.hostname))throw Error('Console host does not belong to provider');
 for(let hop=0;hop<4;hop++){
  await pause(500);
  let response=await request(url.href,{method:'HEAD',redirect:'manual',credentials:'omit',signal:AbortSignal.timeout(timeout),headers:{Accept:'text/html','User-Agent':'Prism-provider-setup-link-check/1.0'}});
  if(response.status===405||response.status===501){await response.body?.cancel();await pause(500);response=await request(url.href,{method:'GET',redirect:'manual',credentials:'omit',signal:AbortSignal.timeout(timeout),headers:{Accept:'text/html','User-Agent':'Prism-provider-setup-link-check/1.0'}});}
  await response.body?.cancel();
  if(response.status>=300&&response.status<400){const location=response.headers.get('location');if(!location)throw Error('Redirect has no location');if(classification==='auth-gated'){const next=new URL(location,url);if(next.protocol!=='https:'||next.username||next.password||next.port||!consoleHosts[provider]?.includes(next.hostname))throw Error('Console redirects to a foreign host');return {status:'pass',classification,httpStatus:response.status,redirectHost:next.hostname};}const next=permitted(new URL(location,url).href);if(next.hostname!==original.hostname)throw Error('Redirect changes official host');url=next;continue;}
  if(classification==='auth-gated'&&[401,403].includes(response.status))return {status:'pass',classification,httpStatus:response.status};
  if(classification==='public'&&response.status!==200)throw Error('HTTP '+response.status);
  if(response.status<200||response.status>=400)throw Error('HTTP '+response.status);
  return {status:'pass',classification,httpStatus:response.status,finalUrl:url.href};
 }
 throw Error('Redirect bound exceeded');
}

export async function checkAll(folder,options={}){
 const rows=[];
 for(const file of fs.readdirSync(folder).sort()){
  if(!file.endsWith('.json')||['coverage.json','port-environment.json','schema.json'].includes(file))continue;
  const provider=JSON.parse(fs.readFileSync(path.join(folder,file),'utf8'));
  for(const [kind,url]of [['signupUrl',provider.signupUrl],['keyUrl',provider.keyUrl],...provider.sources.map(url=>['source',url]),...(provider.console?[['console',provider.console.url],['consoleSource',provider.console.documentedSource]]:[])]){
   try{rows.push({provider:provider.id,kind,url,...await checkLink(url,{...options,provider:provider.id,classification:kind==='console'?'auth-gated':'public'})});}catch(error){rows.push({provider:provider.id,kind,url,status:'fail',reason:error.message});}
  }
 }
 return {schemaVersion:1,generatedAt:new Date().toISOString(),status:rows.length&&rows.every(r=>r.status==='pass')?'pass':'fail',checked:rows.length,rows};
}
if(process.argv[1]&&import.meta.url===pathToFileURL(path.resolve(process.argv[1])).href){
 const root=path.resolve(import.meta.dirname,'..'),report=await checkAll(path.join(root,'resources/providers'));
 const output=process.argv.find(a=>a.startsWith('--report='))?.slice(9)??path.join(root,'provider-setup-links.json');fs.writeFileSync(output,JSON.stringify(report,null,2)+'\n');
 console.log(`${report.status}: ${report.checked} official setup links checked. Report: ${path.basename(output)}`);for(const row of report.rows.filter(r=>r.status==='fail'))console.log(`${row.provider} ${row.kind}: ${row.reason}`);if(report.status!=='pass')process.exitCode=1;
}
