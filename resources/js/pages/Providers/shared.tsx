import { Badge, CodeBlock } from '@particle-academy/react-fancy';
import { type ReactNode } from 'react';
import { KineticNav } from '../../components/kinetic';

export type ProviderGuide = {
    id: string; name: string; signupUrl: string; keyUrl: string; billing: string;
    regions: { name: string; note: string }[]; steps: string[]; sources: string[];
    researchedOn: string; verifiedOn: string | null; stale: boolean;
    portSupport: { ts: boolean; py: boolean }; capabilities: string[];
    console?: { url: string; classification: 'auth-gated'; documentedSource: string };
    environment: { option: string; name: string; default: string | number | boolean | null; sensitive: boolean }[];
    portEnvironment: { typescript: string[]; python: string[] };
};

export function SetupLayout({ version, children }: { version: string; children: ReactNode }) {
    return <div className="k-page min-h-screen"><KineticNav version={version} /><main className="mx-auto max-w-6xl px-6 py-12">{children}</main></div>;
}

export function PortBadges({ provider }: { provider: ProviderGuide }) {
    return <div className="flex flex-wrap gap-2"><Badge color="pink">PHP</Badge>{provider.portSupport.ts && <Badge color="cyan">TypeScript</Badge>}{provider.portSupport.py && <Badge color="teal">Python</Badge>}</div>;
}

export function ResearchStatus({ provider }: { provider: ProviderGuide }) {
    return <p className="mt-4 text-sm text-[var(--k-ink-3)]">{provider.verifiedOn ? `Walked through on ${provider.verifiedOn}.` : `Researched from ${provider.name}'s official documentation on ${provider.researchedOn}. Not yet walked through end to end.`}{provider.stale && ' These instructions were last checked more than 90 days ago; review the official sources before continuing.'}</p>;
}

export function StaticCode({ code, label }: { code: string; label: string }) {
    return <CodeBlock className="my-4 min-w-0" code={code} title={label} language="plaintext" copyable copyLabels={{ copy: `Copy ${label}` }} />;
}

export function environmentText(provider: ProviderGuide, language: string): string {
    if (language !== 'php') return provider.portEnvironment[language as 'typescript' | 'python'].map(name => `# Set ${name} privately in your own environment.`).join('\n');
    return provider.environment.map(field => {
        if (field.sensitive || field.default === null || field.default === '') return `# Set ${field.name} privately if required for your configuration.`;
        return `# ${field.option} (default)\n# ${field.name}=${typeof field.default === 'string' ? field.default : JSON.stringify(field.default)}`;
    }).join('\n');
}

export function verifySnippet(provider: ProviderGuide, language: string): string {
    if (language === 'typescript') return `import { Prism } from '@particle-academy/prism';\n\nconst model = process.env.PRISM_SETUP_MODEL;\nif (!model) throw new Error('Set PRISM_SETUP_MODEL to a model your account can use.');\ntry {\n  const result = await Prism.text()\n    .using('${provider.id}', model)\n    .withPrompt('Say hello.')\n    .withMaxTokens(32)\n    .asText();\n  console.log(result.text);\n} catch (error) {\n  console.error(error instanceof Error ? error.name : 'Request failed');\n  process.exitCode = 1;\n}`;
    if (language === 'python') return `import os\nfrom prism import Prism\n\nmodel = os.environ.get('PRISM_SETUP_MODEL')\nif not model:\n    raise RuntimeError('Set PRISM_SETUP_MODEL to a model your account can use.')\ntry:\n    result = (Prism.text()\n        .using('${provider.id}', model)\n        .with_prompt('Say hello.')\n        .with_max_tokens(32)\n        .as_text())\n    print(result.text)\nexcept Exception as error:\n    print(type(error).__name__)\n    raise SystemExit(1) from None`;
    if (provider.id === 'replicate') return `use Prism\\Prism\\Facades\\Prism;\n\n// One billed image; approximately US$0.003, subject to current model pricing.\n$result = Prism::image()\n    ->using('replicate', 'black-forest-labs/flux-schnell')\n    ->withPrompt('A small blue circle on a white background')\n    ->withProviderOptions(['num_outputs' => 1, 'aspect_ratio' => '1:1'])\n    ->generate();\n\necho count($result->images), PHP_EOL;`;
    return `use Prism\\Prism\\Facades\\Prism;\n\n// Run in your own configured Laravel app (for example, php artisan tinker).\n$model = getenv('PRISM_SETUP_MODEL');\nif (!$model) {\n    throw new RuntimeException('Set PRISM_SETUP_MODEL to your model${provider.id === 'azure' ? ' deployment' : ''}.');\n}\ntry {\n    $result = Prism::text()\n        ->using('${provider.id}', $model)\n        ->withPrompt('Say hello.')\n        ->withMaxTokens(32)\n        ->asText();\n    echo $result->text, PHP_EOL;\n} catch (Throwable $error) {\n    echo get_class($error), PHP_EOL;\n}`;
}
