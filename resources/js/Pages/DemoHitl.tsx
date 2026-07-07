import { Head } from '@inertiajs/react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useEffect, useRef, useState } from 'react';
import { KineticFooter, KineticNav, Reveal } from '../components/kinetic';

type ApprovalRequest = {
    approval_id: string;
    tool_name: string;
    arguments: Record<string, unknown>;
};

type Phase = 'idle' | 'streaming' | 'awaiting-approval' | 'resuming' | 'done' | 'error';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

function makeEcho(): Echo<'reverb'> {
    window.Pusher = Pusher;

    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

async function post(url: string, body: Record<string, unknown>): Promise<Response> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify(body),
    });
}

export default function DemoHitl({ version, hasApiKey, prompt }: { version: string; hasApiKey: boolean; prompt: string }) {
    const [phase, setPhase] = useState<Phase>('idle');
    const [text, setText] = useState('');
    const [approval, setApproval] = useState<ApprovalRequest | null>(null);
    const [toolResult, setToolResult] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const echoRef = useRef<Echo<'reverb'> | null>(null);
    const runIdRef = useRef<string>('');

    useEffect(
        () => () => {
            echoRef.current?.disconnect();
        },
        [],
    );

    const subscribe = (runId: string) => {
        echoRef.current ??= makeEcho();
        echoRef.current.channel(`hitl-demo.${runId}`).listen('.demo.event', (payload: { type: string; data: Record<string, unknown> }) => {
            const { type, data } = payload;

            if (type === 'text_delta') {
                setText((t) => t + ((data.delta as string) ?? ''));
            }

            if (type === 'tool_approval_request') {
                setApproval({
                    approval_id: data.approval_id as string,
                    tool_name: data.tool_name as string,
                    arguments: (data.arguments as Record<string, unknown>) ?? {},
                });
                setPhase('awaiting-approval');
            }

            if (type === 'tool_result') {
                const result = data.tool_result as { result?: string } | undefined;
                if (result?.result) {
                    setToolResult(result.result);
                }
            }

            if (type === 'stream_end') {
                setPhase((p) => (p === 'awaiting-approval' ? p : 'done'));
            }

            if (type === 'error') {
                setError((data.message as string) ?? 'Stream error');
                setPhase('error');
            }
        });
    };

    const run = async () => {
        setPhase('streaming');
        setText('');
        setApproval(null);
        setToolResult(null);
        setError(null);

        const runId = crypto.randomUUID();
        runIdRef.current = runId;
        subscribe(runId);

        const response = await post('/demo/hitl/run', { run_id: runId });

        if (!response.ok) {
            setError(((await response.json()) as { error?: string }).error ?? 'Request failed');
            setPhase('error');
        }
    };

    const decide = async (approved: boolean) => {
        setPhase('resuming');
        setApproval(null);
        setText((t) => t + '\n\n');

        const response = await post('/demo/hitl/resume', { run_id: runIdRef.current, approved });

        if (!response.ok) {
            setError(((await response.json()) as { error?: string }).error ?? 'Resume failed');
            setPhase('error');
        }
    };

    const busy = phase === 'streaming' || phase === 'resuming';

    return (
        <div className="k-page">
            <Head>
                <title>Human-in-the-Loop Demo</title>
                <meta head-key="description" name="description" content="Live demo: a Prism tool call pauses for human approval, streamed over Laravel Reverb." />
            </Head>

            <KineticNav version={version} />

            <div className="mx-auto max-w-3xl px-6 py-14">
                <Reveal>
                    <p className="k-mono mb-3">Live demo · streamed over Reverb</p>
                    <h1 className="text-3xl font-extrabold sm:text-5xl" style={{ letterSpacing: '-0.04em', color: 'var(--k-ink)' }}>
                        The model wants to act.
                        <br />
                        <span className="k-grad-text italic">You decide.</span>
                    </h1>
                    <p className="mt-5 text-sm leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                        This runs a real Prism request against Anthropic with an <code>issue_refund</code> tool marked{' '}
                        <code>requiresApproval()</code>. Every stream event is broadcast over your local Reverb instance —
                        when the model calls the tool, the stream pauses and an approval card appears here.
                    </p>
                </Reveal>

                {!hasApiKey && (
                    <div className="doc-callout doc-callout--warning mt-8">
                        <p className="doc-callout-title">Setup needed</p>
                        <p className="text-sm">
                            Add <code>ANTHROPIC_API_KEY</code> to the app&apos;s <code>.env</code> to run the live demo.
                        </p>
                    </div>
                )}

                <div className="k-card mt-8 p-6">
                    <p className="k-mono mb-2">Scenario</p>
                    <p className="text-sm" style={{ color: 'var(--k-ink-2)' }}>
                        “{prompt}”
                    </p>

                    <button type="button" onClick={run} disabled={!hasApiKey || busy} className="k-btn k-btn--grad mt-5 disabled:opacity-40">
                        {phase === 'idle' || phase === 'done' || phase === 'error' ? 'Run the demo →' : 'Running…'}
                    </button>
                </div>

                {(text !== '' || busy) && (
                    <div className="k-card mt-5 p-6">
                        <p className="k-mono mb-3">Assistant {busy && <span style={{ color: 'var(--k-mag)' }}>● live</span>}</p>
                        <p className="whitespace-pre-wrap text-sm leading-relaxed" style={{ color: 'var(--k-ink)' }}>
                            {text || '…'}
                        </p>
                        {toolResult && (
                            <p className="k-mono mt-4 rounded-lg border px-3 py-2 normal-case tracking-normal" style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-cyan)' }}>
                                tool → {toolResult}
                            </p>
                        )}
                    </div>
                )}

                {approval && (
                    <div className="k-card mt-5 p-6" style={{ borderColor: 'var(--k-mag)' }}>
                        <p className="k-mono mb-3" style={{ color: 'var(--k-mag)' }}>
                            Approval required
                        </p>
                        <p className="text-sm" style={{ color: 'var(--k-ink)' }}>
                            The model wants to call <code>{approval.tool_name}</code> with:
                        </p>
                        <pre className="mt-3 rounded-lg border p-3 text-xs" style={{ borderColor: 'var(--k-hairline)', color: 'var(--k-ink-2)' }}>
                            {JSON.stringify(approval.arguments, null, 2)}
                        </pre>
                        <div className="mt-4 flex gap-3">
                            <button type="button" onClick={() => decide(true)} className="k-btn k-btn--grad">
                                Approve
                            </button>
                            <button type="button" onClick={() => decide(false)} className="k-btn k-btn--ghost">
                                Deny
                            </button>
                        </div>
                    </div>
                )}

                {error && (
                    <div className="doc-callout doc-callout--danger mt-5">
                        <p className="doc-callout-title">Error</p>
                        <p className="text-sm">{error}</p>
                    </div>
                )}

                <p className="k-mono mt-10">
                    How it works:{' '}
                    <a href="/docs/core-concepts/human-in-the-loop" className="k-grad-text normal-case tracking-normal">
                        Human-in-the-Loop docs
                    </a>
                </p>
            </div>

            <KineticFooter />
        </div>
    );
}
