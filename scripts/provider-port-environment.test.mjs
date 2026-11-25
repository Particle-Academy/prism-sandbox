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
test('TypeScript infers names passed through wrapper helpers that forward to a name position',()=>{
 const source=`import {configuredUrl, inheritsEnvironmentCredentials, scopedString as scoped} from '../../internal/provider-config.js';
 const url=configuredUrl(config.url,'MISTRAL_URL',DEFAULT_URL); const inherit=inheritsEnvironmentCredentials(url,'MISTRAL_URL',DEFAULT_URL); scoped(config.apiKey,'MISTRAL_API_KEY',inherit,'');`;
 const helper=`function configuredUrl(value: unknown, name: string, fallback: string){readEnvironment(name);return configuredString(value,name,fallback);}
 function inheritsEnvironmentCredentials(url: string, urlName: string, fallback: string){return url===configuredString(undefined,urlName,fallback);}
 function scopedString(value: unknown, name: string, inherit: boolean, fallback: string){return inherit?configuredString(value,name,fallback):fallback;}
 function twice(name: string){return scopedString(undefined,name,true,'');}
 function notAName(fallback: string){return configuredString(undefined,'MISTRAL_TIMEOUT',fallback);}`;
 assert.deepEqual(typescript(source,helper,ts),['MISTRAL_API_KEY','MISTRAL_URL']);
 assert.deepEqual(typescript(`twice('MISTRAL_NESTED'); notAName('NOT_A_NAME');`,helper,ts),['MISTRAL_NESTED','MISTRAL_TIMEOUT']);
 assert.throws(()=>typescript(`scopedString(config.apiKey, chooseName(), true, '');`,helper,ts));
});
test('Python infers names passed through wrapper helpers that forward to a name position',()=>{
 const source=`from prism.providers.config import configured_url, inherits_environment_credentials, scoped_string as scoped
url = configured_url(url, "MISTRAL_URL", DEFAULT_URL)
inherit = inherits_environment_credentials(url, "MISTRAL_URL", DEFAULT_URL)
scoped(api_key, "MISTRAL_API_KEY", inherit, "")`;
 const helper=`import os
def configured_url(value, name, fallback):
    os.environ.get(name)
    return configured_string(value, name, fallback)
def inherits_environment_credentials(url, url_name, fallback):
    return url == configured_string(UNSET, url_name, fallback)
def scoped_string(value, name, inherit, fallback):
    return configured_string(value, name, fallback) if inherit else fallback
def twice(name):
    return scoped_string(UNSET, name, True, "")
def not_a_name(fallback):
    return configured_string(UNSET, "MISTRAL_TIMEOUT", fallback)`;
 assert.deepEqual(python(source,helper),['MISTRAL_API_KEY','MISTRAL_URL']);
 assert.deepEqual(python(`twice("MISTRAL_NESTED")
not_a_name("NOT_A_NAME")`,helper),['MISTRAL_NESTED','MISTRAL_TIMEOUT']);
 assert.throws(()=>python(`scoped_string(api_key, choose_name(), True, "")`,helper));
});
