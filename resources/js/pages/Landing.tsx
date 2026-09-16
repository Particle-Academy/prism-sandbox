import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { GradGlyph, KineticFooter, KineticNav, Marquee, Reveal } from '../components/kinetic';
import { SearchPalette, useSearchPalette } from '../components/SearchPalette';

/**
 * The nine package families, described by what each one is FOR.
 *
 * Kept honest deliberately: `prism-human-plus` has never been tagged and the
 * TypeScript and Python ports have no releases at all, so nothing here promises
 * a version that does not exist. The per-package pages carry the detail.
 */
const ECOSYSTEM = [
    { name: 'Harness', package: 'prism-harness', link: '/docs/packages/harness', blurb: 'Durable agent sessions — threads, modes, tool permissions, approvals, subagents and budgets.' },
    { name: 'Workspace', package: 'prism-workspace', link: '/docs/packages/workspace', blurb: 'A sandboxed disk an agent can write to, behind a path guard tested against 134 escape attempts.' },
    { name: 'Memory', package: 'prism-memory', link: '/docs/packages/memory', blurb: 'Recall the parts of a long conversation that matter now, sized to the token budget you have left.' },
    { name: 'MCP', package: 'prism-mcp', link: '/docs/packages/mcp', blurb: 'Speak Model Context Protocol as a client or a server, with tool definitions that survive the round trip.' },
    { name: 'Browser', package: 'prism-browser', link: '/docs/packages/browser', blurb: 'Guarded open-web automation: an allow-list of hosts and actions, with private addresses refused out of the box.' },
    { name: 'Human+', package: 'prism-human-plus', link: '/docs/packages/human-plus', blurb: 'Put a person in the surface as a participant — and keep confirmation tools reserved for them.' },
    { name: 'Perplexity', package: 'prism-perplexity', link: '/docs/packages/perplexity', blurb: 'The Perplexity Agent API as a first-class provider, including its search filters and cost reporting.' },
    { name: 'OpenTelemetry', package: 'prism-opentelemetry', link: '/docs/packages/opentelemetry', blurb: 'Export agent runs as spans to any OTel collector, separate from the framework’s own events.' },
    { name: 'Language ports', package: 'prism-ts · prism-py', link: '/docs/packages/language-ports', blurb: 'The same nine families in TypeScript and Python, pinned to one conformance corpus across all three languages.' },
] as const;

const PROVIDERS = [
    'ANTHROPIC', 'OPENAI', 'GEMINI', 'VERTEX AI', 'AZURE', 'MISTRAL', 'GROQ', 'OLLAMA',
    'OPENROUTER', 'DEEPSEEK', 'XAI', 'QWEN', 'REPLICATE', 'REQUESTY', 'VOYAGE AI', 'ELEVENLABS',
    'PERPLEXITY', 'Z.AI',
];

const FEATURES: { title: string; blurb: string; href?: string; badge?: string }[] = [
    {
        title: 'One API, every provider',
        blurb: 'One request shape across 18 providers — Anthropic, OpenAI, Gemini, Ollama and more. Switch provider, keep your application code.',
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
        <div className="mt-8">
            <p className="k-mono mb-2">PHP / Laravel reference</p>
            <button
                type="button"
                onClick={() => {
                    navigator.clipboard?.writeText(cmd).then(() => {
                        setCopied(true);
                        setTimeout(() => setCopied(false), 1600);
                    });
                }}
                className="group inline-flex items-center gap-3 rounded-xl border px-4 py-2.5 font-mono text-sm transition-colors"
                style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-2)', background: 'var(--k-bg-1)' }}
            >
                <span style={{ color: 'var(--k-mag)' }}>$</span>
                {cmd}
                <span className="k-mono transition-colors group-hover:text-[var(--k-cyan)]">{copied ? 'copied ✓' : 'copy'}</span>
            </button>
        </div>
    );
}

export default function Landing({ version }: { version: string }) {
    const { open, setOpen } = useSearchPalette();

    return (
        <div className="k-page">
            <Head>
                <title>Prism — one provider interface, and a harness for agents</title>
                <meta
                    head-key="description"
                    name="description"
                    content="Prism is a cross-language provider API ecosystem of nine package families: a mature PHP/Laravel reference across 18 providers, a durable agent harness, and focused TypeScript and Python ports."
                />
                <meta property="og:title" content="Prism — one provider interface, and a harness for agents" />
                <meta property="og:description" content="A mature PHP/Laravel reference across 18 providers, a durable agent harness, and seven more optional families — in three languages." />
                <meta property="og:type" content="website" />
            </Head>

            <KineticNav version={version} onSearch={() => setOpen(true)} />
            <SearchPalette open={open} onClose={() => setOpen(false)} />

            {/* Hero */}
            <section className="relative">
                <HeroGlows />
                <div className="relative mx-auto max-w-7xl px-6 pt-24 pb-16 sm:pt-32">
                    <p className="k-mono mb-6">Nine package families · PHP reference · TypeScript + Python ports · {version}</p>
                    {/*
                     * TWO promises, because the ecosystem is two things and a
                     * headline naming only the first is why this page read as
                     * "just Prism". Core is a provider interface and stays one;
                     * the Harness is what you build agents on. Saying both here
                     * is not a widening of core's remit — it is the boundary,
                     * stated at the top instead of four sections down.
                     */}
                    <h1
                        className="max-w-4xl text-5xl font-extrabold sm:text-7xl"
                        style={{ letterSpacing: '-0.04em', lineHeight: 0.98, color: 'var(--k-ink)' }}
                    >
                        One interface for every provider.
                        <br />
                        A <span className="k-grad-text italic">harness</span> for everything you build on it.
                    </h1>
                    <p className="mt-7 max-w-2xl text-lg" style={{ color: 'var(--k-ink-2)' }}>
                        Prism is a cross-language provider API ecosystem. PHP and Laravel are the mature reference implementation across eighteen providers;
                        TypeScript and Python carry the same contract, measured against one shared corpus. Core remains the provider API shuttle; agent capabilities ship as optional satellite packages.
                    </p>
                    <InstallChip />
                    <div className="mt-9 flex flex-wrap gap-3">
                        <Link href="/docs/languages/php" className="k-btn k-btn--grad">
                            Build with PHP →
                        </Link>
                        {/* The Harness earns a top-line CTA: it is the reason
                            this is an agent platform and not a client library. */}
                        <Link href="/docs/packages/harness" className="k-btn k-btn--ghost">Meet the Harness</Link>
                        <Link href="/docs/packages/language-ports" className="k-btn k-btn--ghost">Explore language ports</Link>
                        <a href="https://github.com/Particle-Academy/prism" className="k-btn k-btn--ghost">
                            GitHub
                        </a>
                        <a href="https://packagist.org/packages/particle-academy/prism" className="k-btn k-btn--ghost">
                            Packagist
                        </a>
                    </div>
                </div>
            </section>

            {/* Harness — a deliberately standalone section, not another core feature card. */}
            <section className="k-hairline-top relative overflow-hidden" style={{ background: 'var(--k-bg-1)' }}>
                <div className="pointer-events-none absolute -right-40 -top-40 size-[34rem] rounded-full bg-cyan-400/10 blur-3xl" aria-hidden />
                <div className="relative mx-auto max-w-7xl px-6 py-24">
                    <div className="grid gap-10 lg:grid-cols-[1.2fr_.8fr] lg:items-end">
                        <Reveal>
                            <p className="k-mono mb-3">Prism Harness · separate package</p>
                            <h2 className="max-w-3xl text-4xl font-bold sm:text-6xl" style={{ letterSpacing: '-0.045em', lineHeight: 1, color: 'var(--k-ink)' }}>
                                Agents that survive <span className="k-grad-text italic">the request.</span>
                            </h2>
                            <p className="mt-6 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                                Laravel workers boot, serve, and disappear. Harness reconstructs durable sessions from storage, so a later request—or a different queue worker—finds the same thread, selected mode and model, and run state.
                            </p>
                        </Reveal>
                        <Reveal delay={100}>
                            <div className="k-card p-6 text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>
                                <strong style={{ color: 'var(--k-ink)' }}>Shipped:</strong> durable sessions, Eloquent threads, persisted mode/model selection, guarded state stores, exclusive locking, faithful tool-loop history, and a Fancy Flow seam.
                                <br /><br />
                                <strong style={{ color: 'var(--k-ink)' }}>Still design:</strong> mode classes, permission-policy orchestration, pending-approval workflows, and nested subagent execution.
                            </div>
                        </Reveal>
                    </div>
                    <div className="mt-12 grid overflow-hidden rounded-2xl border sm:grid-cols-2 lg:grid-cols-4" style={{ borderColor: 'var(--k-hairline-2)' }}>
                        {[
                            ['01', 'Resolve', 'Address a session by participant and scope; no long-lived PHP object required.'],
                            ['02', 'Rehydrate', 'Recover conversation history and session state in a fresh worker.'],
                            ['03', 'Lock', 'Serialize overlapping work; one run finishes before the next starts, and a timeout throws.'],
                            ['04', 'Continue', 'Record the complete exchange, including every tool step, for faithful replay.'],
                        ].map(([number, title, copy], index) => (
                            <Reveal key={title} delay={index * 70}>
                                <div className="h-full p-6 lg:min-h-48" style={{ background: 'var(--k-bg)', borderLeft: index ? '1px solid var(--k-hairline-2)' : undefined }}>
                                    <span className="k-grad-text font-mono text-sm font-bold">{number}</span>
                                    <h3 className="mt-6 font-semibold" style={{ color: 'var(--k-ink)' }}>{title}</h3>
                                    <p className="mt-2 text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>{copy}</p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link href="/docs/packages/harness" className="k-btn k-btn--grad">Explore Harness →</Link>
                        <a href="https://github.com/Particle-Academy/prism-harness" className="k-btn k-btn--ghost">View source</a>
                    </div>
                </div>
            </section>

            {/* Browser and Human+ */}
            <section className="k-hairline-top">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <Reveal>
                        <p className="k-mono mb-3">New satellite capabilities</p>
                        <h2 className="max-w-3xl text-3xl font-bold sm:text-5xl" style={{ letterSpacing: '-0.04em', color: 'var(--k-ink)' }}>
                            Compose around Harness. <span className="k-grad-text italic">Ship on your own clock.</span>
                        </h2>
                        <p className="mt-5 max-w-2xl leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                            Browser and Human+ bind attachments to the same durable Harness identity. Each installs on its own and carries its own version, so you adopt one the day you need it.
                        </p>
                    </Reveal>
                    <div className="mt-10 grid gap-5 lg:grid-cols-2">
                        <Reveal><Link href="/docs/packages/browser" className="k-card block h-full p-7"><p className="k-mono mb-4">prism-browser · first slice</p><h3 className="text-2xl font-bold" style={{ color: 'var(--k-ink)' }}>Guarded open-web automation</h3><p className="mt-4 text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>Policy-first navigation and typed actions over bounded, provenance-framed observations. Stable package-issued references replace raw selectors and arbitrary JavaScript.</p><p className="k-mono mt-6">Read the boundary →</p></Link></Reveal>
                        <Reveal delay={100}><Link href="/docs/packages/human-plus" className="k-card block h-full p-7"><p className="k-mono mb-4">prism-human-plus · first slice</p><h3 className="text-2xl font-bold" style={{ color: 'var(--k-ink)' }}>Join the surface as a participant</h3><p className="mt-4 text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>A PHP agent joins a live Fancy surface through an invitation and relay. The app owns stable handles, staged writes, undo, cursors and presence, and the agent works through them.</p><p className="k-mono mt-6">Explore Human+ →</p></Link></Reveal>
                    </div>
                </div>
            </section>

            {/*
             * The whole ecosystem, named.
             *
             * Browser and Human+ get cards above because they are the newest
             * slices; MCP, Memory, Workspace and OpenTelemetry appeared nowhere
             * on this page at all, which is a strange way to promote an
             * ecosystem of nine families. One line each, linked, and honest
             * about which ones are the reference and which are still moving.
             */}
            <section className="k-hairline-top">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <Reveal>
                        <p className="k-mono mb-3">Nine families · one boundary</p>
                        <h2 className="max-w-3xl text-3xl font-bold sm:text-5xl" style={{ letterSpacing: '-0.04em', color: 'var(--k-ink)' }}>
                            Core stays small. The <span className="k-grad-text italic">ecosystem</span> does the rest.
                        </h2>
                        <p className="mt-5 max-w-2xl leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                            Install the ones your application needs, each on its own version. Core stays a provider shuttle, so a provider fix
                            reaches you the day it ships.
                        </p>
                    </Reveal>
                    <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {ECOSYSTEM.map((family, index) => (
                            <Reveal key={family.link} delay={index * 40}>
                                <Link href={family.link} className="k-card block h-full p-6">
                                    <p className="k-mono mb-2">{family.package}</p>
                                    <h3 className="text-lg font-bold" style={{ color: 'var(--k-ink)' }}>{family.name}</h3>
                                    <p className="mt-2 text-sm leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>{family.blurb}</p>
                                </Link>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* Language ports */}
            <section className="k-hairline-top" style={{ background: 'var(--k-bg-1)' }}>
                <div className="mx-auto grid max-w-7xl gap-10 px-6 py-20 lg:grid-cols-2 lg:items-center">
                    <Reveal><p className="k-mono mb-3">One contract · more languages</p><h2 className="text-3xl font-bold sm:text-5xl" style={{ letterSpacing: '-0.04em', color: 'var(--k-ink)' }}>Prism speaks <span className="k-grad-text italic">three languages.</span></h2><p className="mt-5 max-w-xl leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>TypeScript and Python ports implement every capability the PHP reference has — text, structured output, streaming, embeddings, media, audio and more — across Anthropic, Mistral and OpenAI. Both install from their own registry.</p></Reveal>
                    <Reveal delay={100}><div className="k-card p-7"><div className="grid gap-5 sm:grid-cols-2"><div><p className="k-mono">TypeScript</p><p className="mt-2 text-sm" style={{ color: 'var(--k-ink-3)' }}>Node 20+, strict types, native promises.</p></div><div><p className="k-mono">Python</p><p className="mt-2 text-sm" style={{ color: 'var(--k-ink-3)' }}>Python 3.10+, zero runtime dependencies.</p></div></div><p className="mt-6 border-t pt-5 text-sm leading-relaxed" style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-3)' }}><code>prism-parity</code> keeps the shared behavior measurable: one language-independent corpus runs the same fixture through all three languages and compares the bytes, and its manifest is built to admit the next port.</p><Link href="/docs/packages/language-ports" className="k-mono mt-5 inline-block">See how parity is measured →</Link></div></Reveal>
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
                                <p className="k-grad-text text-2xl font-extrabold">{version.replace(/^v/, '')}</p>
                                <p className="k-mono mt-1">current core release</p>
                            </div>
                            <div>
                                <p className="k-grad-text text-2xl font-extrabold">18</p>
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
