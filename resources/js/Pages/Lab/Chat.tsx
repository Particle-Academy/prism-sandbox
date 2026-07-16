import { Head } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import { KineticFooter, KineticNav } from '../../components/kinetic';

type Provider = 'openai' | 'anthropic';
type Metrics = { latency_ms: number; prompt_tokens: number; completion_tokens: number; cost: number | null; steps: number; tool_calls: number; finish_reason: string };

export default function Chat({ version, models, availability, phoenixUrl }: { version: string; models: Record<Provider, string>; availability: Record<Provider, boolean>; phoenixUrl: string }) {
    const [provider, setProvider] = useState<Provider>('openai');
    const [model, setModel] = useState(models.openai);
    const [feature, setFeature] = useState<'text' | 'tools'>('tools');
    const [prompt, setPrompt] = useState('Inspect the Prism runtime, then explain whether telemetry is ready for a real generation.');
    const [result, setResult] = useState<{ text: string; metrics: Metrics; phoenix_url: string } | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [running, setRunning] = useState(false);
    const keyWarning = useMemo(() => availability[provider] ? null : `Add ${provider === 'openai' ? 'OPENAI_API_KEY' : 'ANTHROPIC_API_KEY'} to the sandbox .env to run this provider.`, [availability, provider]);

    function chooseProvider(next: Provider) { setProvider(next); setModel(models[next]); setResult(null); setError(null); }

    async function submit(event: FormEvent) {
        event.preventDefault(); setRunning(true); setError(null); setResult(null);
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        try {
            const response = await fetch('/lab/chat', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ provider, model, feature, prompt }) });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message ?? 'Generation failed.');
            setResult(body);
        } catch (reason) { setError(reason instanceof Error ? reason.message : 'Generation failed.'); }
        finally { setRunning(false); }
    }

    return <div className="k-page"><Head title="Prism Lab"><meta name="description" content="Run real Prism generations and inspect their telemetry in Phoenix." /></Head><KineticNav version={version} />
        <main className="mx-auto max-w-7xl px-6 py-16"><div className="mb-7 flex gap-2"><a className="k-btn k-btn--grad" href="/lab/chat">Chat console</a><a className="k-btn k-btn--ghost" href="/lab/tests">Test suite</a></div><p className="k-mono mb-4">Local only · live telemetry</p><div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"><div><h1 className="text-5xl font-extrabold tracking-[-.05em] sm:text-7xl">Prism <span className="k-grad-text">Lab</span></h1><p className="mt-5 max-w-2xl text-lg text-[var(--k-ink-2)]">Run a real model request through Prism’s event layer and OpenTelemetry bridge. Every successful run should appear in Phoenix.</p></div><a className="k-btn k-btn--ghost" href={phoenixUrl} target="_blank" rel="noreferrer">Open Phoenix ↗</a></div>
        <div className="mt-12 grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(340px,.85fr)]"><form onSubmit={submit} className="k-card space-y-6 p-6 sm:p-8"><div className="grid gap-4 sm:grid-cols-3"><Field label="Provider"><select value={provider} onChange={e => chooseProvider(e.target.value as Provider)} className="lab-input"><option value="openai">OpenAI</option><option value="anthropic">Anthropic</option></select></Field><Field label="Model"><input className="lab-input" value={model} onChange={e => setModel(e.target.value)} /></Field><Field label="Feature"><select className="lab-input" value={feature} onChange={e => setFeature(e.target.value as 'text' | 'tools')}><option value="tools">Tools · multi-step</option><option value="text">Text</option></select></Field></div><Field label="Prompt"><textarea className="lab-input min-h-48 resize-y" value={prompt} onChange={e => setPrompt(e.target.value)} /></Field>{keyWarning && <Notice>{keyWarning}</Notice>}<button className="k-btn k-btn--grad disabled:cursor-wait disabled:opacity-60" disabled={running}>{running ? 'Running generation…' : 'Run generation →'}</button></form>
        <section className="k-card min-h-96 p-6 sm:p-8"><p className="k-mono mb-5">Trace output</p>{error && <Notice>{error}</Notice>}{!result && !error && <p className="text-[var(--k-ink-3)]">Response text, token usage, tool activity, and latency will appear here.</p>}{result && <div className="space-y-7"><div className="whitespace-pre-wrap leading-7 text-[var(--k-ink)]">{result.text}</div><div className="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-[var(--k-hairline)] sm:grid-cols-3">{Object.entries(result.metrics).map(([key, value]) => <div key={key} className="bg-[var(--k-bg-1)] p-4"><div className="k-mono">{key.replaceAll('_', ' ')}</div><div className="mt-1 font-semibold tabular-nums">{value ?? '—'}</div></div>)}</div><a className="k-btn k-btn--ghost" href={result.phoenix_url} target="_blank" rel="noreferrer">Inspect trace in Phoenix ↗</a></div>}</section></div></main><KineticFooter /></div>;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="block"><span className="k-mono mb-2 block">{label}</span>{children}</label>; }
function Notice({ children }: { children: React.ReactNode }) { return <div className="rounded-xl border border-[var(--k-mag)]/40 bg-[rgba(255,46,136,.08)] p-4 text-sm text-[var(--k-ink-2)]">{children}</div>; }
