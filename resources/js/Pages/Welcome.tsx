import { Head, Link, usePage } from '@inertiajs/react';
import { Badge, Button, cn } from '@particle-academy/react-fancy';
import type { ReactNode } from 'react';

type Auth = { user: { name: string } | null };

const JUMP_OFFS: { title: string; blurb: string; href: string; external?: boolean }[] = [
    {
        title: 'Browse components',
        blurb: '~100 React + PHP primitives — tables, forms, modals, charts, whiteboard, code editor, slides…',
        href: 'https://ui.particle.academy/packages',
        external: true,
    },
    {
        title: 'Read the docs',
        blurb: 'Installation, theming, layouts, page transitions, SSR, SEO, and the Human+ guide.',
        href: 'https://ui.particle.academy/docs',
        external: true,
    },
    {
        title: 'Pick a design direction',
        blurb: '20 Inspiration Gallery styles — one studio site designed 20 ways, from Swiss-minimal to agent-native.',
        href: 'https://ui.particle.academy/gallery',
        external: true,
    },
    {
        title: 'Grab a starter kit',
        blurb: 'Full-app templates (Shop-n-Sub = catalog + FMS) and vendorable blocks via `npx fancy-ui add`.',
        href: 'https://ui.particle.academy/starter-kits',
        external: true,
    },
    {
        title: 'Make it agent-driveable',
        blurb: 'Wire agent-integrations MCP bridges so humans and agents share — and co-drive — the same UI.',
        href: 'https://ui.particle.academy/docs/developing-human-plus',
        external: true,
    },
    {
        title: 'Add a page',
        blurb: 'Drop a component in resources/js/Pages, point a route at Inertia::render(), and grow your app.',
        href: 'https://ui.particle.academy/docs/building',
        external: true,
    },
];

function Shell({ children }: { children: ReactNode }) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-zinc-200 dark:border-zinc-800">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <Link href="/" className="flex items-center gap-2 font-semibold">
                        <span className="grid h-7 w-7 place-items-center rounded-md bg-violet-600 text-sm font-bold text-white">F</span>
                        Fancy
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth?.user ? (
                            <Button as="a" href="/dashboard" color="violet">
                                Dashboard
                            </Button>
                        ) : (
                            <>
                                <Button as="a" href="/login" variant="ghost">
                                    Log in
                                </Button>
                                <Button as="a" href="/register" color="violet">
                                    Register
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-zinc-200 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800">
                Built with{' '}
                <a href="https://ui.particle.academy" className="font-medium text-violet-500 hover:underline">
                    Fancy UI
                </a>{' '}
                · Laravel + Inertia + React 19 + Tailwind v4 · MIT
            </footer>
        </div>
    );
}

export default function Welcome() {
    return (
        <Shell>
            <Head title="Welcome" />

            {/* Hero */}
            <section className="mx-auto max-w-6xl px-6 pt-20 pb-12 text-center">
                <Badge color="violet" variant="soft">
                    Fancy UI starter kit
                </Badge>
                <h1 className="mx-auto mt-5 max-w-3xl text-balance text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl dark:text-zinc-50">
                    Your Laravel app, with Fancy UI already wired in.
                </h1>
                <p className="mx-auto mt-4 max-w-2xl text-lg text-zinc-500">
                    Laravel + Inertia + React 19 + Tailwind v4, with{' '}
                    <span className="font-medium text-zinc-700 dark:text-zinc-300">Fancy Core</span> (react-fancy,
                    fancy-inertia, fancy-query) and full auth ready to go. Pick a jump-off point below and grow from here.
                </p>
                <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <Button as="a" href="/register" color="violet" size="lg">
                        Get started
                    </Button>
                    <Button as="a" href="https://ui.particle.academy/docs" variant="ghost" size="lg">
                        Read the docs →
                    </Button>
                </div>
            </section>

            {/* Jump-off points */}
            <section className="mx-auto max-w-6xl px-6 pb-24">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {JUMP_OFFS.map((j) => (
                        <a
                            key={j.title}
                            href={j.href}
                            target={j.external ? '_blank' : undefined}
                            rel={j.external ? 'noopener' : undefined}
                            className={cn(
                                'group flex flex-col rounded-xl border border-zinc-200 bg-white p-5 transition',
                                'hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md',
                                'dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-violet-700',
                            )}
                        >
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-zinc-900 dark:text-zinc-100">{j.title}</h3>
                                <span className="text-violet-500 opacity-0 transition group-hover:opacity-100">→</span>
                            </div>
                            <p className="mt-1.5 text-sm text-zinc-500">{j.blurb}</p>
                        </a>
                    ))}
                </div>

                <div className="mt-10 rounded-xl border border-zinc-200 bg-zinc-50 p-5 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-400">
                    <span className="font-medium text-zinc-800 dark:text-zinc-200">Tip:</span> edit this page at{' '}
                    <code className="rounded bg-zinc-200 px-1 py-0.5 font-mono text-xs dark:bg-zinc-800">
                        resources/js/Pages/Welcome.tsx
                    </code>
                    . Add components with{' '}
                    <code className="rounded bg-zinc-200 px-1 py-0.5 font-mono text-xs dark:bg-zinc-800">
                        npx fancy-ui add &lt;name&gt;
                    </code>
                    .
                </div>
            </section>
        </Shell>
    );
}
