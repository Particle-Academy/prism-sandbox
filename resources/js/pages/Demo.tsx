import { ContentRenderer } from '@particle-academy/react-fancy';
import { useState } from 'react';
import { KineticFooter, KineticNav } from '../components/kinetic';

type Result = {
    text: string;
    model: string;
    tokens: { prompt: number | null; completion: number | null };
};

export default function Demo({ version, model, configured }: { version: string; model: string; configured: boolean }) {
    const [prompt, setPrompt] = useState('Explain what a unified LLM provider interface buys you, in three sentences.');
    const [result, setResult] = useState<Result | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [running, setRunning] = useState(false);

    async function run(event: React.FormEvent) {
        event.preventDefault();
        setRunning(true);
        setError(null);
        setResult(null);

        try {
            const response = await fetch('/demo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ prompt }),
            });

            const body = await response.json();

            if (!response.ok) {
                // 429 has no message body of its own — say what actually happened
                // rather than rendering "undefined".
                setError(response.status === 429 ? 'Rate limited. This demo runs on a shared free quota — try again in a minute.' : (body.message ?? 'Something went wrong.'));
                return;
            }

            setResult(body);
        } catch {
            setError('Could not reach the server.');
        } finally {
            setRunning(false);
        }
    }

    return (
        <div className="k-page">
            <KineticNav version={version} />

            <main className="mx-auto max-w-4xl px-6 py-16">
                <p className="k-mono mb-4">Live · {model}</p>
                <h1 className="text-5xl font-extrabold tracking-[-.05em] sm:text-6xl">
                    Try <span className="k-grad-text">Prism</span>
                </h1>
                <p className="mt-5 text-lg" style={{ color: 'var(--k-ink-2)' }}>
                    One generation, through the same interface every provider goes through. Running on Groq's free tier, so
                    it is rate limited and deliberately small — the full testbed lives in{' '}
                    <a href="https://github.com/Particle-Academy/prism-labs" className="underline hover:text-[var(--k-cyan)]">
                        prism-labs
                    </a>
                    .
                </p>

                {!configured && (
                    <p className="k-mono mt-10 rounded-xl border p-5" style={{ borderColor: 'var(--k-hairline)', color: 'var(--k-ink-3)' }}>
                        This demo is not configured yet. It needs GROQ_API_KEY.
                    </p>
                )}

                <form onSubmit={run} className="mt-10 flex flex-col gap-4">
                    <textarea
                        value={prompt}
                        onChange={event => setPrompt(event.target.value)}
                        maxLength={600}
                        rows={4}
                        disabled={!configured}
                        className="k-card w-full resize-y p-5 leading-7 disabled:opacity-50"
                        style={{ color: 'var(--k-ink)' }}
                        aria-label="Prompt"
                    />
                    <div className="flex items-center gap-4">
                        <button type="submit" className="k-btn" disabled={running || !configured || prompt.trim() === ''}>
                            {running ? 'Generating…' : 'Generate'}
                        </button>
                        <span className="k-mono text-sm" style={{ color: 'var(--k-ink-3)' }}>
                            {prompt.length}/600
                        </span>
                    </div>
                </form>

                {error && (
                    <p className="mt-8 rounded-xl border p-5" style={{ borderColor: 'var(--k-hairline)', color: 'var(--k-mag)' }}>
                        {error}
                    </p>
                )}

                {result && (
                    <section className="k-card mt-8 p-6 sm:p-8">
                        {/* Sanitised by default, and it must stay that way: this is
                            model output rendered on a public page, and a model can
                            be talked into emitting markup. */}
                        <ContentRenderer value={result.text} format="markdown" className="leading-7 text-[var(--k-ink)]" />
                        <p className="k-mono mt-6 text-sm" style={{ color: 'var(--k-ink-3)' }}>
                            {result.model} · {result.tokens.prompt ?? '—'} prompt + {result.tokens.completion ?? '—'} completion tokens
                        </p>
                    </section>
                )}
            </main>

            <KineticFooter />
        </div>
    );
}
