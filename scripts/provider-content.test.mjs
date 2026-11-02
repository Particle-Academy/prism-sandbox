import {test} from 'node:test';import assert from 'node:assert/strict';import fs from 'node:fs';import path from 'node:path';
import {validate} from './check-provider-content.mjs';
const folder=path.resolve(import.meta.dirname,'../resources/providers'),schema=JSON.parse(fs.readFileSync(path.join(folder,'schema.json'))),example=JSON.parse(fs.readFileSync(path.join(folder,'openai.json')));
test('researched but unverified content passes without pretending a human check',()=>{assert.equal(example.verifiedOn,null);assert.doesNotThrow(()=>validate(example,schema));});
test('missing fields, malformed dates and unknown fields fail the actual schema',()=>{for(const mutate of [x=>delete x.sources,x=>x.researchedOn='2026-02-30',x=>x.key='forbidden',x=>x.portSupport.ts='yes']){const item=structuredClone(example);mutate(item);assert.throws(()=>validate(item,schema));}});
