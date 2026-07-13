import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { GradGlyph, KineticFooter, KineticNav, Marquee, Reveal } from '../components/kinetic';
import { SearchPalette, useSearchPalette } from '../components/SearchPalette';

const PROVIDERS = [
    'ANTHROPIC', 'OPENAI', 'GEMINI', 'VERTEX AI', 'AZURE', 'MISTRAL', 'GROQ', 'OLLAMA',
    'OPENROUTER', 'DEEPSEEK', 'XAI', 'QWEN', 'REPLICATE', 'REQUESTY', 'VOYAGE AI', 'Z.AI',
];

const FEATURES: { title: string; blurb: string; href?: string; badge?: string }[] = [
    {
        title: 'One API, every provider',
        blurb: 'Switch between 16 providers — Anthropic, OpenAI, Gemini, Ollama and more — without touching your application code.',
    },
    {
        title: 'Fluent text generation',
        blurb: 'A chainable, Laravel-native interface for prompts, system messages, conversations, and multi-step generation.',
    },
    {
        title: 'Tools & function calling',
        blurb: 'Define typed tools the model can call — with automatic argument coercion, error handling, and multi-step loops.',
    },
    {
        title: 'Structured output',
        blurb: 'Schema-validated responses mapped to type-safe objects, with native JSON Schema support per provider.',
    },
    {
        title: 'Streaming, batches & files',
        blurb: 'First-class streaming events, the Batches API for bulk workloads, and provider file management.',
    },
    {
        title: 'Testing built in',
        blurb: 'Fake any provider response, assert on requests, and ship AI features with a green test suite.',
    },
    {
        title: 'Human-in-the-loop approval',
        blurb: 'Mark tools clientExecuted() or requiresApproval() — the model proposes, your app or a human decides. Deny-by-default, streamable approval events.',
        href: '/docs/core-concepts/human-in-the-loop',
        badge: 'New',
    },
    {
        title: 'Reasoning, dialed in',
        blurb: 'Provider-agnostic withReasoning(), Anthropic adaptive thinking with output effort, and reasoning-token visibility in every Usage.',
        badge: 'New',
    },
    {
        title: 'Self-hosted & gateway ready',
        blurb: 'Point the OpenAI provider at vLLM, LiteLLM, or any compatible backend via api_format — or route through OpenRouter and Requesty.',
        badge: 'New',
    },
    {
        title: 'Observability & telemetry',
        blurb: 'Opt-in lifecycle events for every generation — token usage, cost, latency, and step/tool spans. Subscribe a Laravel listener for cost tracking, or drop in the OpenTelemetry bridge for traces in Arize Phoenix and any OTLP backend.',
        badge: 'New',
    },
];

/** Parallax glow layers driven by scroll (Kinetic hero). */
function HeroGlows() {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = ref.current;
        if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        let raf = 0;
        const onScroll = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const y = window.scrollY;
                el.querySelectorAll<HTMLElement>('[data-depth]').forEach((layer) => {
                    layer.style.transform = `translateY(${y * Number(layer.dataset.depth)}px)`;
                });
            });
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', onScroll);
            cancelAnimationFrame(raf);
        };
    }, []);

    return (
        <div ref={ref} className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
            <div
                data-depth="0.18"
                className="absolute -top-40 left-[8%] size-[34rem] rounded-full opacity-25 blur-3xl"
                style={{ background: 'radial-gradient(circle, #ff2e88, transparent 65%)' }}
            />
            <div
                data-depth="0.1"
                className="absolute -top-16 right-[4%] size-[30rem] rounded-full opacity-20 blur-3xl"
                style={{ background: 'radial-gradient(circle, #1fd4ff, transparent 65%)' }}
            />
            <div
                data-depth="0.05"
                className="absolute inset-0 opacity-[0.07]"
                style={{
                    backgroundImage: 'linear-gradient(#f4f3fb 1px, transparent 1px), linear-gradient(90deg, #f4f3fb 1px, transparent 1px)',
                    backgroundSize: '56px 56px',
                    maskImage: 'radial-gradient(ellipse 80% 60% at 50% 30%, #000 30%, transparent 75%)',
                }}
            />
        </div>
    );
}

function InstallChip() {
    const [copied, setCopied] = useState(false);
    const cmd = 'composer require particle-academy/prism';

    return (
        <button
            type="button"
            onClick={() => {
                navigator.clipboard?.writeText(cmd).then(() => {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1600);
                });
            }}
            className="group mt-8 inline-flex items-center gap-3 rounded-xl border px-4 py-2.5 font-mono text-sm transition-colors"
            style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-2)', background: 'var(--k-bg-1)' }}
        >
            <span style={{ color: 'var(--k-mag)' }}>$</span>
            {cmd}
            <span className="k-mono transition-colors group-hover:text-[var(--k-cyan)]">{copied ? 'copied ✓' : 'copy'}</span>
        </button>
    );
}

export default function Landing({ version }: { version: string }) {
    const { open, setOpen } = useSearchPalette();

    return (
        <div className="k-page">
            <Head>
                <title>Laravel × LLMs</title>
                <meta
                    head-key="description"
                    name="description"
                    content="Prism is a Laravel package for integrating Large Language Models — one fluent API across Anthropic, OpenAI, Gemini and 13 more providers. Actively maintained by Particle Academy."
                />
                <meta property="og:title" content="Prism — Laravel × LLMs" />
                <meta property="og:description" content="One fluent Laravel API for 16 LLM providers. Text, tools, structured output, streaming, batches." />
                <meta property="og:type" content="website" />
            </Head>

            <KineticNav version={version} onSearch={() => setOpen(true)} />
            <SearchPalette open={open} onClose={() => setOpen(false)} />

            {/* Hero */}
            <section className="relative">
                <HeroGlows />
                <div className="relative mx-auto max-w-7xl px-6 pt-24 pb-16 sm:pt-32">
                    <p className="k-mono mb-6">A Laravel package for LLMs · {version}</p>
                    <h1
                        className="max-w-4xl text-5xl font-extrabold sm:text-7xl"
                        style={{ letterSpacing: '-0.04em', lineHeight: 0.98, color: 'var(--k-ink)' }}
                    >
                        Laravel <span className="k-grad-text italic">×</span> LLMs,
                        <br />
                        one fluent <span className="k-grad-text italic">API</span>.
                    </h1>
                    <p className="mt-7 max-w-2xl text-lg" style={{ color: 'var(--k-ink-2)' }}>
                        Prism gives your Laravel app text generation, tools, structured output, streaming, embeddings, and batch
                        processing across sixteen AI providers — behind one chainable interface.
                    </p>
                    <InstallChip />
                    <div className="mt-9 flex flex-wrap gap-3">
                        <Link href="/docs/getting-started/introduction" className="k-btn k-btn--grad">
                            Get started →
                        </Link>
                        <a href="https://github.com/Particle-Academy/prism" className="k-btn k-btn--ghost">
                            GitHub
                        </a>
                        <a href="https://packagist.org/packages/particle-academy/prism" className="k-btn k-btn--ghost">
                            Packagist
                        </a>
                    </div>
                </div>
            </section>

            {/* Provider ticker */}
            <div className="k-hairline-top">
                <Marquee items={PROVIDERS} />
            </div>

            {/* Features */}
            <section className="k-hairline-top">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <Reveal>
                        <p className="k-mono mb-3">Capabilities</p>
                        <h2 className="max-w-xl text-3xl font-bold sm:text-4xl" style={{ letterSpacing: '-0.03em', color: 'var(--k-ink)' }}>
                            Everything an AI feature needs, <span className="k-grad-text italic">Laravel-shaped</span>.
                        </h2>
                    </Reveal>
                    <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((feature, i) => {
                            const card = (
                                <div className="k-card h-full p-6">
                                    <p className="k-mono mb-3 flex items-center gap-2">
                                        <span className="k-grad-text text-sm font-bold tracking-normal">{String(i + 1).padStart(2, '0')}</span>
                                        {feature.badge && (
                                            <span
                                                className="rounded px-1.5 py-0.5 text-[10px] font-bold text-white"
                                                style={{ background: 'var(--k-grad)', letterSpacing: '0.08em' }}
                                            >
                                                {feature.badge.toUpperCase()}
                                            </span>
                                        )}
                                    </p>
                                    <h3 className="mb-2 font-semibold" style={{ color: 'var(--k-ink)' }}>
                                        {feature.title}
                                    </h3>
                                    <p className="text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>
                                        {feature.blurb}
                                    </p>
                                </div>
                            );

                            return (
                                <Reveal key={feature.title} delay={i * 70}>
                                    {feature.href ? (
                                        <Link href={feature.href} className="block h-full">
                                            {card}
                                        </Link>
                                    ) : (
                                        card
                                    )}
                                </Reveal>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Takeover note */}
            <section className="k-hairline-top" style={{ background: 'var(--k-bg-1)' }}>
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <Reveal>
                        <p className="k-mono mb-3">The fork</p>
                        <blockquote className="max-w-3xl text-2xl font-bold sm:text-4xl" style={{ letterSpacing: '-0.03em', lineHeight: 1.2, color: 'var(--k-ink)' }}>
                            Prism&apos;s original maintainers went quiet in March 2026. We picked it up — <span className="k-grad-text italic">the [un]official takeover</span> — and kept shipping.
                        </blockquote>
                        <div className="mt-8 grid max-w-3xl gap-4 text-sm sm:grid-cols-3" style={{ color: 'var(--k-ink-2)' }}>
                            <div>
                                <p className="k-grad-text text-2xl font-extrabold">40+</p>
                                <p className="k-mono mt-1">community PRs absorbed</p>
                            </div>
                            <div>
                                <p className="k-grad-text text-2xl font-extrabold">16</p>
                                <p className="k-mono mt-1">providers supported</p>
                            </div>
                            <div>
                                <p className="k-grad-text text-2xl font-extrabold">1,800+</p>
                                <p className="k-mono mt-1">tests, phpstan clean</p>
                            </div>
                        </div>
                        <p className="mt-8 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>
                            <GradGlyph className="mr-2 inline-grid size-5 align-middle text-[10px]" />
                            <code>particle-academy/prism</code> is a drop-in replacement for <code>prism-php/prism</code> — the{' '}
                            <code>Prism\Prism</code> namespace is unchanged. Security-patched, actively released, and documented
                            right here, always in sync with the installed version.
                        </p>
                    </Reveal>
                </div>
            </section>

            {/* Reverse marquee + footer */}
            <div className="k-hairline-top">
                <Marquee items={['TEXT', 'STREAMING', 'TOOLS', 'STRUCTURED OUTPUT', 'EMBEDDINGS', 'IMAGES', 'AUDIO', 'BATCHES', 'FILES', 'MODERATION']} reverse />
            </div>
            <KineticFooter />
        </div>
    );
}
