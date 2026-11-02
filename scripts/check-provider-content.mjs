import fs from 'node:fs';import path from 'node:path';import {pathToFileURL} from 'node:url';import {permitted} from './check-provider-links.mjs';

// The schema uses this bounded JSON Schema subset; reject unknown keywords so
// new constraints cannot silently become unenforced.
export function validate(value,schema,location='$'){
 const known=new Set(['$schema','type','additionalProperties','required','properties','items','minItems','minLength','pattern','format']);for(const key of Object.keys(schema))if(!known.has(key))throw Error('Unsupported schema keyword '+key);
 const types=Array.isArray(schema.type)?schema.type:[schema.type],actual=value===null?'null':Array.isArray(value)?'array':typeof value;
 if(!types.includes(actual))throw Error(location+' has wrong type');
 if(actual==='object'){
  for(const key of schema.required??[])if(!Object.hasOwn(value,key))throw Error(location+' missing '+key);
  for(const[key,item]of Object.entries(value)){if(!schema.properties?.[key]){if(schema.additionalProperties===false)throw Error(location+' unknown '+key);}else validate(item,schema.properties[key],location+'.'+key);}
 }
 if(actual==='array'){if(value.length<(schema.minItems??0))throw Error(location+' has too few items');for(const item of value)validate(item,schema.items,location+'[]');}
 if(actual==='string'){
  if(value.length<(schema.minLength??0)||schema.pattern&&!new RegExp(schema.pattern).test(value))throw Error(location+' invalid text');
  if(schema.format==='uri')permitted(value);
  if(schema.format==='date'&&(!/^\d{4}-\d{2}-\d{2}$/.test(value)||new Date(value).toISOString().slice(0,10)!==value||value>new Date().toISOString().slice(0,10)))throw Error(location+' invalid date');
 }
}
export function checkContents(folder){const schema=JSON.parse(fs.readFileSync(path.join(folder,'schema.json')));let count=0;for(const file of fs.readdirSync(folder).sort()){if(!file.endsWith('.json')||['schema.json','coverage.json','port-environment.json'].includes(file))continue;const content=JSON.parse(fs.readFileSync(path.join(folder,file)));validate(content,schema);if(content.id!==path.basename(file,'.json'))throw Error('Provider ID differs from filename');if(content.researchedOn<new Date(Date.now()-90*86400000).toISOString().slice(0,10)&&(content.verifiedOn??'')<new Date(Date.now()-90*86400000).toISOString().slice(0,10))console.warn('::warning::Setup guide last checked more than 90 days ago: '+content.id);count++;}if(count===0)throw Error('No provider content checked');return count;}
if(process.argv[1]&&import.meta.url===pathToFileURL(path.resolve(process.argv[1])).href)console.log(checkContents(path.resolve(import.meta.dirname,'../resources/providers'))+' provider guides match the JSON schema.');
