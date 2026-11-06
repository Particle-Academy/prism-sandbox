import {test} from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import {createRequire} from 'node:module';
import {extractTypeScriptEnvironment as typescript,extractPythonEnvironment as python} from './provider-port-environment.mjs';
const ts=createRequire(path.join(path.resolve(process.env.PRISM_SETUP_PORT_TS_ROOT??'.'),'package.json'))('typescript');

test('TypeScript recognizes static names in legacy and current configuration calls',()=>{
 assert.deepEqual(typescript(`readEnv('OPENAI_API_KEY'); configuredString(config.url, "OPENAI_URL", ''); nullableString(config.project, 'OPENAI_PROJECT');`,'',ts),['OPENAI_API_KEY','OPENAI_PROJECT','OPENAI_URL']);
});
test('TypeScript follows only reached helpers, including aliases and nested configuration defaults',()=>{
 const source=`import {anthropicBeta as beta, thinkingBudgetDefault} from '../../internal/provider-config.js'; beta(config.beta); thinkingBudgetDefault(config.budget);`;
 const helper=`function anthropicBeta(value: unknown){readEnvironment('ANTHROPIC_BETA');return nullableString(value,'ANTHROPIC_BETA_FEATURES');} function thinkingBudgetDefault(value: unknown){return environmentValue('ANTHROPIC_DEFAULT_THINKING_BUDGET',1024);} function unused(){readEnvironment('OTHER_SECRET');}`;
 assert.deepEqual(typescript(source,helper,ts),['ANTHROPIC_BETA','ANTHROPIC_BETA_FEATURES','ANTHROPIC_DEFAULT_THINKING_BUDGET']);
});
test('TypeScript parses without executing source and ignores comments and quoted decoys',()=>{
 assert.deepEqual(typescript(`throw Error('must never run'); // readEnv('COMMENT_SECRET')
 const quoted="readEnv('QUOTED_SECRET')"; configuredString(config.key,'OPENAI_API_KEY','');`,'',ts),['OPENAI_API_KEY']);
});
test('TypeScript refuses dynamic names, malformed source and oversized source',()=>{
 assert.throws(()=>typescript(`configuredString(config.key, chooseName(), '');`,'',ts));
 assert.throws(()=>typescript(`configuredString(`,'',ts));
 assert.throws(()=>typescript('x'.repeat(1000000),'',ts));
 assert.deepEqual(typescript('// no configuration calls','',ts),[]);
});
test('Python recognizes legacy calls and multiline current configuration calls',()=>{
 assert.deepEqual(python(`import os
os.environ.get("OPENAI_API_KEY")
configured_string(url,
 'OPENAI_URL', '')
nullable_string(project, 'OPENAI_PROJECT')`,''),['OPENAI_API_KEY','OPENAI_PROJECT','OPENAI_URL']);
});
test('Python follows reached configuration helpers and aliases without importing them',()=>{
 const source=`from prism.providers.config import anthropic_beta as beta, thinking_budget_default
beta(value)
thinking_budget_default(value)`;
 const helper=`import os
def anthropic_beta(value):
 return nullable_string(value, "ANTHROPIC_BETA") or os.environ.get("ANTHROPIC_BETA_FEATURES")
def thinking_budget_default(value):
 return _environment_value("ANTHROPIC_DEFAULT_THINKING_BUDGET", 1024)
def unused():
 return os.environ.get("OTHER_SECRET")`;
 assert.deepEqual(python(source,helper),['ANTHROPIC_BETA','ANTHROPIC_BETA_FEATURES','ANTHROPIC_DEFAULT_THINKING_BUDGET']);
});
test('Python ignores comments/docstrings and never executes provider imports or top-level statements',()=>{
 assert.deepEqual(python(`"""os.environ.get('DOCSTRING_SECRET')"""
import a_provider_that_does_not_exist
raise RuntimeError('must never execute')
# os.environ.get('COMMENT_SECRET')
configured_string(value, 'OPENAI_API_KEY', '')`,''),['OPENAI_API_KEY']);
});
test('Python refuses dynamic names, malformed source and oversized source',()=>{
 assert.throws(()=>python(`configured_string(value, choose_name(), '')`,''));
 assert.throws(()=>python('def broken(',''));
 assert.throws(()=>python('x'.repeat(1000000),''));
 assert.deepEqual(python('# no configuration calls',''),[]);
});
