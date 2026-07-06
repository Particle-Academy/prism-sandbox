import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, cn } from '@particle-academy/react-fancy';
import type { ReactNode } from 'react';

type Auth = { user: { name: string; email: string } | null };

const NAV = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Settings', href: '/settings/profile' },
];

/** Authenticated app shell: top nav + sign-out + content area. */
export function AppLayout({ title, children }: { title?: string; children: ReactNode }) {
    const { props, url } = usePage<{ auth: Auth }>();
    const user = props.auth?.user;

    return (
        <div className="flex min-h-screen flex-col bg-zinc-50 dark:bg-zinc-950">
            {title && <Head title={title} />}
            <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
                    <div className="flex items-center gap-6">
                        <Link href="/dashboard" className="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100">
                            <span className="grid h-7 w-7 place-items-center rounded-md bg-violet-600 text-sm font-bold text-white">F</span>
                            Fancy
                        </Link>
                        <nav className="flex items-center gap-1">
                            {NAV.map((item) => {
                                const active = url.startsWith(item.href.replace('/profile', ''));
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={cn(
                                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                            active
                                                ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                                : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                    <div className="flex items-center gap-3">
                        {user && <span className="hidden text-sm text-zinc-500 sm:inline">{user.name}</span>}
                        <Button variant="ghost" size="sm" onClick={() => router.post('/logout')}>
                            Sign out
                        </Button>
                    </div>
                </div>
            </header>
            <main className="mx-auto w-full max-w-6xl flex-1 px-6 py-10">{children}</main>
        </div>
    );
}
