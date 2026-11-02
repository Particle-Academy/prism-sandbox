import { Link, usePage } from '@inertiajs/react';
import { Button, cn } from '@particle-academy/react-fancy';
import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import { FEATURES, featureHref } from '../data/ecosystem';

/** Rises into place when scrolled into view (Kinetic reveal). */
export function Reveal({ children, delay = 0, className }: { children: ReactNode; delay?: number; className?: string }) {
    const ref = useRef<HTMLDivElement>(null);
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const io = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setInView(true);
                    io.disconnect();
                }
            },
            { rootMargin: '0px 0px -10% 0px' },
        );
        io.observe(el);
        return () => io.disconnect();
    }, []);

    return (
        <div ref={ref} className={cn('k-reveal', inView && 'is-in', className)} style={{ transitionDelay: `${delay}ms` }}>
            {children}
        </div>
    );
}

/** Edge-to-edge marquee strip of mono items with magenta separators. */
export function Marquee({ items, reverse = false }: { items: string[]; reverse?: boolean }) {
    const doubled = [...items, ...items];

    return (
        <div className={cn('k-marquee py-4', reverse && 'k-marquee--reverse')} aria-hidden>
            <div className="k-marquee__track">
                {doubled.map((item, i) => (
                    <span key={i} className="k-mono flex items-center gap-10 whitespace-nowrap text-sm">
                        {item} <span style={{ color: 'var(--k-mag)' }}>✸</span>
                    </span>
                ))}
            </div>
        </div>
    );
}

export function GradGlyph({ className }: { className?: string }) {
    return (
        <span
            className={cn('grid size-7 place-items-center rounded-md text-sm font-extrabold text-white', className)}
            style={{ background: 'var(--k-grad)' }}
        >
            P
        </span>
    );
}

/** A shell command with a copy button, in the Kinetic install-chip style. */
export function CopyCommand({ command, className }: { command: string; className?: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <button
            type="button"
            onClick={() => {
                navigator.clipboard?.writeText(command).then(() => {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1600);
                });
            }}
            className={cn(
                'group inline-flex max-w-full items-center gap-3 rounded-xl border px-4 py-2.5 text-left font-mono text-sm transition-colors',
                className,
            )}
            style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-2)', background: 'var(--k-bg-1)' }}
        >
            <span style={{ color: 'var(--k-mag)' }}>$</span>
            <span className="min-w-0 break-all">{command}</span>
            <span className="k-mono shrink-0 transition-colors group-hover:text-[var(--k-cyan)]">{copied ? 'copied ✓' : 'copy'}</span>
        </button>
    );
}

/** A link that is an Inertia visit on this site and a plain anchor off it. */
export function SmartLink({ href, className, style, children }: { href: string; className?: string; style?: CSSProperties; children: ReactNode }) {
    if (/^https?:\/\//.test(href)) {
        return (
            <a href={href} className={className} style={style}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={className} style={style}>
            {children}
        </Link>
    );
}

const navLink = 'rounded-lg px-3 py-1.5 transition-colors hover:text-[var(--k-cyan)]';

/** The Features group: a disclosure listing every feature page, as real links. */
function FeaturesMenu() {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onPointer = (event: PointerEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) setOpen(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
        };
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                aria-expanded={open}
                aria-controls="k-features-menu"
                onClick={() => setOpen((v) => !v)}
                className={cn(navLink, 'flex items-center gap-1')}
                style={{ color: open ? 'var(--k-cyan)' : undefined }}
            >
                Features
                <span aria-hidden className={cn('text-[10px] transition-transform', open && 'rotate-180')}>
                    ▾
                </span>
            </button>
            {open && (
                <div
                    id="k-features-menu"
                    className="absolute left-1/2 top-full z-50 mt-2 w-[34rem] -translate-x-1/2 rounded-xl border p-3 shadow-2xl"
                    style={{ borderColor: 'var(--k-hairline-2)', background: 'var(--k-bg-1)' }}
                >
                    <div className="grid grid-cols-2 gap-1">
                        {FEATURES.map((feature) => (
                            <Link
                                key={feature.slug}
                                href={featureHref(feature.slug)}
                                onClick={() => setOpen(false)}
                                className="rounded-lg px-3 py-2 transition-colors hover:bg-white/5"
                            >
                                <span className="block text-sm font-semibold" style={{ color: 'var(--k-ink)' }}>
                                    {feature.title}
                                </span>
                                <span className="block text-xs" style={{ color: 'var(--k-ink-3)' }}>
                                    {feature.category}
                                </span>
                            </Link>
                        ))}
                    </div>
                    <Link
                        href="/features"
                        onClick={() => setOpen(false)}
                        className="k-mono mt-2 block border-t px-3 pt-3 transition-colors hover:text-[var(--k-cyan)]"
                        style={{ borderColor: 'var(--k-hairline-2)' }}
                    >
                        All features →
                    </Link>
                </div>
            )}
        </div>
    );
}

/** The narrow-screen menu: every nav destination, features included. */
function MobileMenu({ demoEnabled, onSearch }: { demoEnabled: boolean; onSearch?: () => void }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) return;
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    const close = () => setOpen(false);
    const item = 'block rounded-lg px-3 py-2.5 text-base transition-colors hover:bg-white/5';

    return (
        <div className="md:hidden">
            <button
                type="button"
                aria-expanded={open}
                aria-controls="k-mobile-menu"
                aria-label={open ? 'Close site menu' : 'Open site menu'}
                onClick={() => setOpen((v) => !v)}
                className="grid size-9 place-items-center rounded-lg border"
                style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-2)' }}
            >
                <span aria-hidden className="text-lg leading-none">
                    {open ? '✕' : '☰'}
                </span>
            </button>
            {open && (
                <div
                    id="k-mobile-menu"
                    className="absolute inset-x-0 top-full max-h-[calc(100vh-61px)] overflow-y-auto border-b px-4 pt-3 pb-6"
                    style={{ borderColor: 'var(--k-hairline)', background: 'var(--k-bg)' }}
                >
                    {onSearch && (
                        <button
                            type="button"
                            onClick={() => {
                                close();
                                onSearch();
                            }}
                            className="mb-3 w-full rounded-lg border px-3 py-2.5 text-left text-sm"
                            style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-3)' }}
                        >
                            Search docs…
                        </button>
                    )}
                    <nav aria-label="Site" style={{ color: 'var(--k-ink-2)' }}>
                        <Link href="/docs" onClick={close} className={item}>
                            Docs
                        </Link>
                        <Link href="/packages" onClick={close} className={item}>
                            Packages
                        </Link>
                        <Button href="/providers/setup" onClick={close} variant="ghost" className={item}>Provider setup</Button>
                        <p className="k-mono mt-3 px-3 pb-1">Features</p>
                        <div className="grid grid-cols-1 gap-0.5 min-[420px]:grid-cols-2">
                            {FEATURES.map((feature) => (
                                <Link
                                    key={feature.slug}
                                    href={featureHref(feature.slug)}
                                    onClick={close}
                                    className="block rounded-lg px-3 py-2 text-sm transition-colors hover:bg-white/5"
                                >
                                    {feature.title}
                                </Link>
                            ))}
                        </div>
                        <Link href="/features" onClick={close} className="k-mono block px-3 py-2">
                            All features →
                        </Link>
                        <div className="mt-3 border-t pt-3" style={{ borderColor: 'var(--k-hairline-2)' }}>
                            <Link href="/leaderboard" onClick={close} className={item}>
                                Leaderboard
                            </Link>
                            {demoEnabled && (
                                <Link href="/demo" onClick={close} className={cn(item, 'font-semibold text-[var(--k-cyan)]')}>
                                    Demo
                                </Link>
                            )}
                            <a href="https://github.com/Particle-Academy/prism" className={item}>
                                GitHub
                            </a>
                            <a href="https://packagist.org/packages/particle-academy/prism" className={item}>
                                Packagist
                            </a>
                        </div>
                    </nav>
                </div>
            )}
        </div>
    );
}

/** Top navigation bar shared by every page. */
export function KineticNav({ version, onSearch }: { version: string; onSearch?: () => void }) {
    const { demoEnabled = false } = usePage<{ demoEnabled?: boolean }>().props;

    return (
        <header className="sticky top-0 z-40 border-b backdrop-blur-md" style={{ borderColor: 'var(--k-hairline)', background: 'rgba(7,7,11,0.85)' }}>
            <div className="relative mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3.5">
                <Link href="/" className="flex items-center gap-2.5 font-bold tracking-tight" style={{ color: 'var(--k-ink)' }}>
                    <GradGlyph />
                    Prism
                    <span className="k-mono mt-0.5 hidden sm:inline">{version}</span>
                </Link>
                <nav aria-label="Main" className="hidden items-center gap-1 text-sm md:flex" style={{ color: 'var(--k-ink-2)' }}>
                    <Link href="/docs" className={navLink}>
                        Docs
                    </Link>
                    <Link href="/packages" className={navLink}>
                        Packages
                    </Link>
                    <Button href="/providers/setup" variant="ghost" size="sm">Provider setup</Button>
                    <FeaturesMenu />
                    <Link href="/leaderboard" className={navLink}>
                        Leaderboard
                    </Link>
                    {demoEnabled && (
                        <Link href="/demo" className="rounded-lg px-3 py-1.5 font-semibold text-[var(--k-cyan)] transition-colors hover:text-white">
                            Demo
                        </Link>
                    )}
                    {onSearch && (
                        <button
                            type="button"
                            onClick={onSearch}
                            className="ml-2 hidden items-center gap-2 rounded-lg border px-3 py-1.5 text-xs transition-colors lg:flex"
                            style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-3)' }}
                        >
                            Search docs…
                            <kbd className="k-mono rounded border px-1.5 py-0.5 text-[10px]" style={{ borderColor: 'var(--k-hairline-2)' }}>
                                ⌘K
                            </kbd>
                        </button>
                    )}
                    <a href="https://github.com/Particle-Academy/prism" className={navLink}>
                        GitHub
                    </a>
                    <a href="https://packagist.org/packages/particle-academy/prism" className={cn(navLink, 'hidden lg:block')}>
                        Packagist
                    </a>
                </nav>
                <MobileMenu demoEnabled={demoEnabled} onSearch={onSearch} />
            </div>
        </header>
    );
}

function FooterGroup({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div>
            <p className="k-mono mb-4">{title}</p>
            <ul className="space-y-2.5 text-sm">{children}</ul>
        </div>
    );
}

function FooterLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <li>
            <SmartLink href={href} className="transition-colors hover:text-[var(--k-cyan)]" style={{ color: 'var(--k-ink-2)' }}>
                {children}
            </SmartLink>
        </li>
    );
}

/** Site footer, grouped: Packages, Features, Learn, Connect. */
export function KineticFooter() {
    const { demoEnabled = false } = usePage<{ demoEnabled?: boolean }>().props;

    return (
        <footer className="k-hairline-top mt-24">
            <div className="mx-auto max-w-7xl px-6 py-12">
                <div className="grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-4">
                    <FooterGroup title="Packages">
                        <FooterLink href="/packages">All packages</FooterLink>
                        {demoEnabled && <FooterLink href="/demo">Demo</FooterLink>}
                    </FooterGroup>
                    <FooterGroup title="Features">
                        {FEATURES.map((feature) => (
                            <FooterLink key={feature.slug} href={featureHref(feature.slug)}>
                                {feature.title}
                            </FooterLink>
                        ))}
                    </FooterGroup>
                    <FooterGroup title="Learn">
                        <FooterLink href="/docs">Docs</FooterLink>
                        <FooterLink href="/docs/languages/php">PHP guide</FooterLink>
                        <FooterLink href="/docs/languages/typescript">TypeScript guide</FooterLink>
                        <FooterLink href="/docs/languages/python">Python guide</FooterLink>
                        <FooterLink href="/docs/about/how-we-maintain-prism">How we maintain Prism</FooterLink>
                    </FooterGroup>
                    <FooterGroup title="Connect">
                        <FooterLink href="https://github.com/Particle-Academy/prism">GitHub</FooterLink>
                        <FooterLink href="https://packagist.org/packages/particle-academy/prism">Packagist</FooterLink>
                        <FooterLink href="https://www.npmjs.com/package/@particle-academy/prism">npm</FooterLink>
                        <FooterLink href="https://pypi.org/project/prism-ai-core/">PyPI</FooterLink>
                        <FooterLink href="/leaderboard">Leaderboard</FooterLink>
                    </FooterGroup>
                </div>
                <div className="mt-12 flex flex-col justify-between gap-3 border-t pt-8 sm:flex-row" style={{ borderColor: 'var(--k-hairline-2)' }}>
                    <p className="k-mono">MIT License · created by TJ Miller &amp; the open-source community</p>
                    <p className="k-mono">
                        maintained by{' '}
                        <a href="https://github.com/Particle-Academy" className="k-grad-text normal-case tracking-normal">
                            Particle Academy
                        </a>
                        {' · '}
                        <a href="https://ui.particle.academy" className="normal-case tracking-normal transition-colors hover:text-[var(--k-cyan)]">
                            Fancy UI
                        </a>
                    </p>
                </div>
            </div>
        </footer>
    );
}
