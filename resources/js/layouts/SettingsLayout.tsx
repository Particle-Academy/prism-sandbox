import { Link, usePage } from '@inertiajs/react';
import { cn } from '@particle-academy/react-fancy';
import type { ReactNode } from 'react';
import { AppLayout } from '@/layouts/AppLayout';

const TABS = [
    { label: 'Profile', href: '/settings/profile' },
    { label: 'Password', href: '/settings/password' },
];

/** Settings shell: the app nav + a Profile/Password sub-nav + a content card. */
export function SettingsLayout({ title, children }: { title: string; children: ReactNode }) {
    const { url } = usePage();

    return (
        <AppLayout title={`${title} · Settings`}>
            <h1 className="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Settings</h1>
            <p className="mt-1 text-sm text-zinc-500">Manage your account.</p>

            <div className="mt-6 flex flex-col gap-8 md:flex-row">
                <nav className="flex gap-1 md:w-48 md:flex-col">
                    {TABS.map((tab) => (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            className={cn(
                                'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                url.startsWith(tab.href)
                                    ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                    : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200',
                            )}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>
                <div className="max-w-xl flex-1 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{title}</h2>
                    <div className="mt-4">{children}</div>
                </div>
            </div>
        </AppLayout>
    );
}
