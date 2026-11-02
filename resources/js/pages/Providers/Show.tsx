import { Button, Callout, Stepper, Tabs } from '@particle-academy/react-fancy';
import { environmentText, PortBadges, ResearchStatus, SetupLayout, StaticCode, verifySnippet, type ProviderGuide } from './shared';

export default function Show({ provider, version }: { provider: ProviderGuide; version: string }) {
    const languages = ['php', ...(provider.portSupport.ts ? ['typescript'] : []), ...(provider.portSupport.py ? ['python'] : [])];
    return <SetupLayout version={version}>
        <Button href="/providers/setup" variant="ghost">All provider guides</Button>
        <h1 className="mt-6 text-4xl font-bold text-[var(--k-ink)]">Set up {provider.name}</h1>
        <div className="mt-4"><PortBadges provider={provider} /></div>
        <ResearchStatus provider={provider} />
        <div className="my-6"><Callout color="cyan">Keep credentials on your own machine or application server. There is nowhere to enter or test a key on this page.</Callout></div>
        <Callout color="amber">{provider.billing}</Callout>
        {provider.regions.map(region => <p key={region.name} className="mt-4 text-sm"><strong>{region.name}:</strong> {region.note}</p>)}
        <div className="my-8 flex flex-wrap gap-3"><Button href={provider.signupUrl}>{provider.id === 'ollama' ? 'Official installation guide' : 'Official account setup'}</Button><Button href={provider.keyUrl} variant="ghost">{provider.id === 'ollama' ? 'Official local API instructions' : provider.id === 'vertex' ? 'Official authentication instructions' : 'Official key instructions'}</Button></div>
        {provider.console && <p className="mb-8"><Button href={provider.console.url} variant="ghost">Open your {provider.name} console (sign-in required)</Button></p>}
        <Stepper aria-label={`${provider.name} setup steps`} orientation="vertical" showContent="all" steps={[...provider.steps.map((step, index) => ({ id: `step-${index + 1}`, title: step.title, content: <p className="text-[var(--k-ink)]">{step.body}</p> })), { id: 'local', title: 'Configure your .env', content: <p className="text-[var(--k-ink)]">Use the environment names below in your own application. Keep its environment file out of source control. Never paste a key into this website.</p> }, { id: 'verify', title: 'Run the local check', content: <p className="text-[var(--k-ink)]">Choose a model you can use and run the matching example below on your own machine. Check any applicable usage charges first.</p> }]} />
        {!provider.portSupport.ts && <p className="my-6 text-sm text-[var(--k-ink-2)]">This provider is available in PHP Prism. The TypeScript and Python ports do not implement it.</p>}
        <h2 className="mt-10 text-2xl font-semibold text-[var(--k-ink)]">Configure and verify locally</h2>
        <p className="mt-3 text-sm text-[var(--k-ink-2)]">Environment names and defaults below come from the installed package. Choose a model you can use by setting <code>PRISM_SETUP_MODEL</code> on your machine. Run the verification only when you are ready for {provider.id === 'ollama' ? 'one local request' : 'one billed request'}. No request is sent by this page.</p>
        {provider.capabilities.includes('text') && provider.id !== 'ollama' && <p className="mt-3 text-sm text-[var(--k-ink-3)]">Approximate text cost is input tokens × your model’s input rate, plus at most 32 output tokens × its output rate, divided by the rate’s token unit. Add any provider fees. Check your model and region’s current price; the token limit is not a money cap.</p>}
        <Tabs defaultTab="php"><Tabs.List>{languages.map(language => <Tabs.Tab key={language} value={language}>{language === 'php' ? 'PHP' : language === 'typescript' ? 'TypeScript' : 'Python'}</Tabs.Tab>)}</Tabs.List><Tabs.Panels>{languages.map(language => <Tabs.Panel key={language} value={language}><StaticCode label={`${language} environment names`} code={environmentText(provider, language)} language={language === 'php' ? 'env' : 'sh'} /><StaticCode label={`${language} local verification`} code={verifySnippet(provider, language)} language={language === 'php' ? 'php' : language === 'typescript' ? 'ts' : 'py'} /></Tabs.Panel>)}</Tabs.Panels></Tabs>
        <h2 className="mt-10 text-xl font-semibold text-[var(--k-ink)]">Official sources</h2><div className="mt-3 flex flex-col items-start gap-2">{provider.sources.map((url, index) => <Button key={url} href={url} variant="ghost">{provider.name} documentation {index + 1}</Button>)}</div>
    </SetupLayout>;
}
