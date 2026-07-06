import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Centered-card layout for the login / register / password pages. */
export function AuthLayout({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-zinc-50 px-4 py-12 dark:bg-zinc-950">
            <Head title={title} />
            <Link href="/" className="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100">
                <span className="grid h-8 w-8 place-items-center rounded-md bg-violet-600 text-sm font-bold text-white">F</span>
                Fancy
            </Link>
            <div className="w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div className="mb-5 text-center">
                    <h1 className="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{title}</h1>
                    {description && <p className="mt-1 text-sm text-zinc-500">{description}</p>}
                </div>
                {children}
            </div>
        </div>
    );
}
