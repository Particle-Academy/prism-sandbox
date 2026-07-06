import { Link, usePage } from '@inertiajs/react';
import { Badge } from '@particle-academy/react-fancy';
import { AppLayout } from '@/layouts/AppLayout';

type Auth = { user: { name: string } | null };

const CARDS = [
    { title: 'Browse components', blurb: 'Find the right Fancy primitive for the screen you\'re building.', href: 'https://ui.particle.academy/packages', external: true },
    { title: 'Add a component', blurb: 'npx fancy-ui add <name> vendors the source straight into your app.', href: 'https://ui.particle.academy/docs', external: true },
    { title: 'Account settings', blurb: 'Update your profile and password.', href: '/settings/profile', external: false },
];

export default function Dashboard() {
    const user = usePage<{ auth: Auth }>().props.auth?.user;

    return (
        <AppLayout title="Dashboard">
            <div className="flex items-center gap-3">
                <h1 className="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    Welcome back{user ? `, ${user.name}` : ''}
                </h1>
                <Badge color="violet" variant="soft">
                    you're in
                </Badge>
            </div>
            <p className="mt-1 text-sm text-zinc-500">
                This is your authenticated dashboard. Edit it at <code className="rounded bg-zinc-100 px-1 font-mono text-xs dark:bg-zinc-800">resources/js/Pages/Dashboard.tsx</code>.
            </p>

            <div className="mt-8 grid gap-4 sm:grid-cols-3">
                {CARDS.map((c) => {
                    const cls =
                        'group flex flex-col rounded-xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-violet-700';
                    const inner = (
                        <>
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-zinc-900 dark:text-zinc-100">{c.title}</h3>
                                <span className="text-violet-500 opacity-0 transition group-hover:opacity-100">→</span>
                            </div>
                            <p className="mt-1.5 text-sm text-zinc-500">{c.blurb}</p>
                        </>
                    );
                    return c.external ? (
                        <a key={c.title} href={c.href} target="_blank" rel="noopener" className={cls}>
                            {inner}
                        </a>
                    ) : (
                        <Link key={c.title} href={c.href} className={cls}>
                            {inner}
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}
