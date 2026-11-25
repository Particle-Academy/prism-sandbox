import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';

const variable=/^[A-Z][A-Z0-9_]*$/;
function bounded(source){assert.equal(typeof source,'string');assert(source.length<1000000,'Provider source is too large');}

// Parse source only. Never import a provider or evaluate its configuration.
export function extractTypeScriptEnvironment(source,configuration,ts){
 bounded(source);bounded(configuration);
 const parse=(name,text)=>{const tree=ts.createSourceFile(name,text,ts.ScriptTarget.Latest,true,ts.ScriptKind.TS);assert.equal(tree.parseDiagnostics.length,0,'Invalid TypeScript source');return tree;};
 const provider=parse('provider.ts',source),helper=parse('provider-config.ts',configuration),functions=new Map(),aliases=new Map(),visited=new Set(),names=new Set();
 for(const item of helper.statements)if(ts.isFunctionDeclaration(item)&&item.name)functions.set(item.name.text,item);
 for(const item of provider.statements)if(ts.isImportDeclaration(item)&&ts.isStringLiteral(item.moduleSpecifier)&&item.moduleSpecifier.text.endsWith('/internal/provider-config.js')){const bindings=item.importClause?.namedBindings;if(bindings&&ts.isNamedImports(bindings))for(const specifier of bindings.elements)aliases.set(specifier.name.text,specifier.propertyName?.text??specifier.name.text);}
 const positions=new Map([['readEnv',[0]],['readEnvironment',[0]],['environmentValue',[0]],['configuredString',[1]],['nullableString',[1]]]);
 // A configuration helper that forwards one of its own parameters into a name
 // position takes an environment name at that position too (scopedString,
 // configuredUrl, inheritsEnvironmentCredentials...). Inferred from the helper
 // source to a fixpoint, so a new wrapper cannot silently hide its call sites'
 // names the way the credential-scoping wrappers did (G-370).
 for(let changed=true;changed;){changed=false;
  for(const[name,definition]of functions){if(!definition.body)continue;
   const parameters=definition.parameters.map(p=>ts.isIdentifier(p.name)?p.name.text:null),known=new Set(positions.get(name)??[]),before=known.size;
   const visit=node=>{if(ts.isCallExpression(node)&&ts.isIdentifier(node.expression))for(const position of positions.get(node.expression.text)??[]){const value=node.arguments[position];if(value&&ts.isIdentifier(value)){const index=parameters.indexOf(value.text);if(index>=0)known.add(index);}}ts.forEachChild(node,visit);};
   visit(definition.body);if(known.size>before){positions.set(name,[...known].sort());changed=true;}
  }
 }
 function walk(node,parameters){
  if(ts.isCallExpression(node)&&ts.isIdentifier(node.expression)){
   const called=aliases.get(node.expression.text)??node.expression.text;
   for(const position of positions.get(called)??[]){const value=node.arguments[position];if(value&&ts.isStringLiteral(value)){assert(variable.test(value.text),'Invalid environment name');names.add(value.text);}else assert(value&&ts.isIdentifier(value)&&parameters.has(value.text),'Environment name must be a static string');}
   if(functions.has(called)&&!visited.has(called)){visited.add(called);const definition=functions.get(called);assert(definition.body,'Missing configuration helper body');walk(definition.body,new Set(definition.parameters.filter(p=>ts.isIdentifier(p.name)).map(p=>p.name.text)));}
  }
  ts.forEachChild(node,child=>walk(child,parameters));
 }
 walk(provider,new Set());return [...names].sort();
}

const pythonExtractor=String.raw`
import ast, json, re, sys
data=json.load(sys.stdin)
assert all(isinstance(value,str) and len(value)<1000000 for value in data.values())
provider=ast.parse(data['source'])
helper=ast.parse(data['configuration'])
functions={node.name:node for node in helper.body if isinstance(node,(ast.FunctionDef,ast.AsyncFunctionDef))}
aliases={alias.asname or alias.name:alias.name for node in provider.body if isinstance(node,ast.ImportFrom) and node.module=='prism.providers.config' for alias in node.names}
positions={'configured_string':{1},'nullable_string':{1},'_environment_value':{0}}
names=set()
visited=set()
def environ_get(node):
    return isinstance(node,ast.Attribute) and node.attr=='get' and isinstance(node.value,ast.Attribute) and node.value.attr=='environ' and isinstance(node.value.value,ast.Name) and node.value.value.id=='os'
def call_positions(node,names_for):
    return {0} if environ_get(node.func) else names_for.get(node.func.id,set()) if isinstance(node.func,ast.Name) else set()
# A helper forwarding one of its own parameters into a name position takes an
# environment name there too (scoped_string, configured_url, ...), inferred to a
# fixpoint so a new wrapper cannot hide its call sites' names (G-370).
changed=True
while changed:
    changed=False
    for name,definition in functions.items():
        parameters=[arg.arg for arg in definition.args.posonlyargs+definition.args.args]
        known=set(positions.get(name,set()))
        before=len(known)
        for node in ast.walk(definition):
            if isinstance(node,ast.Call):
                for position in call_positions(node,positions):
                    value=node.args[position] if len(node.args)>position else None
                    if isinstance(value,ast.Name) and value.id in parameters:
                        known.add(parameters.index(value.id))
        if len(known)>before:
            positions[name]=known
            changed=True
def walk(node,parameters):
    if isinstance(node,ast.Call):
        called=aliases.get(node.func.id,node.func.id) if isinstance(node.func,ast.Name) else None
        for position in sorted({0} if environ_get(node.func) else positions.get(called,set())):
            value=node.args[position] if len(node.args)>position else None
            if isinstance(value,ast.Constant) and isinstance(value.value,str):
                assert re.fullmatch(r'[A-Z][A-Z0-9_]*',value.value),'Invalid environment name'
                names.add(value.value)
            else:
                assert isinstance(value,ast.Name) and value.id in parameters,'Environment name must be a static string'
        if called in functions and called not in visited:
            visited.add(called)
            definition=functions[called]
            bound={arg.arg for arg in definition.args.posonlyargs+definition.args.args+definition.args.kwonlyargs}
            for statement in definition.body: walk(statement,bound)
    for child in ast.iter_child_nodes(node): walk(child,parameters)
walk(provider,set())
print(json.dumps(sorted(names)))
`;

export function extractPythonEnvironment(source,configuration,python='python'){
 bounded(source);bounded(configuration);
 const env=Object.fromEntries(Object.entries(process.env).filter(([key])=>/^(PATH|SYSTEMROOT|WINDIR|COMSPEC|PATHEXT|TEMP|TMP|USERPROFILE|HOME|LANG|LC_ALL)$/i.test(key)));
 const result=spawnSync(python,['-I','-S','-c',pythonExtractor],{input:JSON.stringify({source,configuration}),env,encoding:'utf8',windowsHide:true,timeout:10000,maxBuffer:1024*1024});
 assert.equal(result.status,0,'Python configuration source must parse with static environment names');
 const names=JSON.parse(result.stdout);assert(Array.isArray(names)&&names.every(name=>typeof name==='string'&&variable.test(name)));return names;
}
