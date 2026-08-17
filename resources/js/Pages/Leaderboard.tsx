import { Head } from '@inertiajs/react';
import { KineticFooter, KineticNav } from '../components/kinetic';

type Silo = { slug: string; name: string; xp: number; weight: number };

type Row = {
    rank: number;
    github_username: string;
    avatar_url: string | null;
    score: number;
    total_xp: number;
    achievements: number;
    silos: { slug: string; xp: number }[];
};

export default function Leaderboard({ version, silos, rows }: { version: string; silos: Silo[]; rows: Row[] }) {
    return (
        <div className="k-page">
            <Head title="Contributor Leaderboard — Prism">
                <meta name="description" content="Top community contributors to Prism, ranked by contribution XP." />
            </Head>

            <KineticNav version={version} />

            <section className="relative mx-auto max-w-6xl px-6 pt-24 pb-20 sm:pt-28">
                <p className="k-mono mb-4">Community · Contribution XP</p>
                <h1
                    className="text-4xl font-extrabold sm:text-6xl"
                    style={{ letterSpacing: '-0.04em', lineHeight: 1, color: 'var(--k-ink)' }}
                >
                    Contributor Leaderboard
                </h1>
                <p className="mt-4 max-w-2xl text-lg" style={{ color: 'var(--k-ink-2)' }}>
                    Every comment, issue, and pull request on the project earns XP. Here are the people building Prism.
                </p>

                {rows.length === 0 ? (
                    <p className="k-mono mt-16" style={{ color: 'var(--k-ink-2)' }}>
                        No contributions tracked yet — be the first.
                    </p>
                ) : (
                    <div className="mt-12 overflow-x-auto">
                        <table className="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr className="k-mono" style={{ color: 'var(--k-ink-2)' }}>
                                    <th className="py-3 pr-4 font-normal">#</th>
                                    <th className="py-3 pr-4 font-normal">Contributor</th>
                                    {silos.map((silo) => (
                                        <th key={silo.slug} className="py-3 pr-4 text-right font-normal">
                                            {silo.name}
                                        </th>
                                    ))}
                                    <th className="py-3 pl-4 text-right font-normal">Total XP</th>
                                    {/* The ranked column — silo XP weighted by significance. */}
                                    <th className="py-3 pl-4 text-right font-normal" title="Silo XP weighted by contribution significance — the ranking used here">
                                        Score
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.github_username} className="border-t" style={{ borderColor: 'var(--k-hairline-2)' }}>
                                        <td className="py-4 pr-4 tabular-nums" style={{ color: 'var(--k-ink-2)' }}>
                                            {row.rank}
                                        </td>
                                        <td className="py-4 pr-4">
                                            <a
                                                href={`https://github.com/${row.github_username}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex items-center gap-3 hover:opacity-80"
                                            >
                                                {row.avatar_url ? (
                                                    <img src={row.avatar_url} alt="" width={32} height={32} className="size-8 rounded-full" />
                                                ) : (
                                                    <span className="size-8 rounded-full" style={{ background: 'var(--k-bg-1)' }} />
                                                )}
                                                <span className="font-medium" style={{ color: 'var(--k-ink)' }}>
                                                    @{row.github_username}
                                                </span>
                                                {row.achievements > 0 && (
                                                    <span className="k-mono text-xs" style={{ color: 'var(--k-mag)' }} title="Achievements">
                                                        ★ {row.achievements}
                                                    </span>
                                                )}
                                            </a>
                                        </td>
                                        {row.silos.map((silo) => (
                                            <td key={silo.slug} className="py-4 pr-4 text-right tabular-nums" style={{ color: 'var(--k-ink-2)' }}>
                                                {silo.xp || '—'}
                                            </td>
                                        ))}
                                        <td className="py-4 pl-4 text-right tabular-nums" style={{ color: 'var(--k-ink-2)' }}>
                                            {row.total_xp}
                                        </td>
                                        <td className="py-4 pl-4 text-right font-semibold tabular-nums" style={{ color: 'var(--k-ink)' }}>
                                            {row.score}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <HowPointsWork silos={silos} />
            </section>

            <KineticFooter />
        </div>
    );
}

function HowPointsWork({ silos }: { silos: Silo[] }) {
    // Ranking is by weighted score, so showing XP alone would misexplain the
    // order — a contributor can sit above someone with more raw XP.
    const ranked = [...silos].sort((a, b) => b.xp * b.weight - a.xp * a.weight);
    const top = ranked[0];

    return (
        <div className="mt-20">
            <p className="k-mono mb-4">How points are earned</p>

            <p className="max-w-2xl" style={{ color: 'var(--k-ink-2)' }}>
                Every tracked contribution awards XP. Because some contributions are worth more than
                others, each type carries a weight, and the board ranks on the weighted{' '}
                <strong style={{ color: 'var(--k-ink)' }}>score</strong> — not on raw XP.
            </p>

            <div className="mt-8 overflow-x-auto">
                <table className="w-full min-w-[34rem] border-collapse text-left text-sm">
                    <thead>
                        <tr className="k-mono" style={{ color: 'var(--k-ink-2)' }}>
                            <th className="py-3 pr-4 font-normal">Contribution</th>
                            <th className="py-3 pr-4 text-right font-normal">XP each</th>
                            <th className="py-3 pr-4 text-right font-normal">Weight</th>
                            <th className="py-3 pl-4 text-right font-normal">Points each</th>
                        </tr>
                    </thead>
                    <tbody>
                        {ranked.map((silo) => (
                            <tr key={silo.slug} className="border-t" style={{ borderColor: 'var(--k-hairline)' }}>
                                <td className="py-3 pr-4" style={{ color: 'var(--k-ink)' }}>
                                    {silo.name}
                                </td>
                                <td className="py-3 pr-4 text-right tabular-nums" style={{ color: 'var(--k-ink-2)' }}>
                                    {silo.xp}
                                </td>
                                <td className="py-3 pr-4 text-right tabular-nums" style={{ color: 'var(--k-ink-2)' }}>
                                    ×{silo.weight}
                                </td>
                                <td
                                    className="py-3 pl-4 text-right font-semibold tabular-nums"
                                    style={{ color: 'var(--k-ink)' }}
                                >
                                    {Math.round(silo.xp * silo.weight * 10) / 10}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {top && (
                <p className="mt-6 max-w-2xl text-sm" style={{ color: 'var(--k-ink-3)' }}>
                    So one <strong style={{ color: 'var(--k-ink-2)' }}>{top.name.toLowerCase()}</strong> is worth{' '}
                    {Math.round(top.xp * top.weight * 10) / 10} points — the heaviest single contribution here.
                    Contributions absorbed from upstream count too: commits that land in this fork earn XP even when
                    the pull request was opened on <code>prism-php/prism</code>.
                </p>
            )}
        </div>
    );
}
