import { Link } from '@inertiajs/react';
import { CopyCommand, KineticFooter, KineticNav, SmartLink } from '../components/kinetic';
import { SearchPalette, useSearchPalette } from '../components/SearchPalette';
import {
    FAMILIES,
    LANGUAGES,
    LANGUAGE_ORDER,
    featureBySlug,
    featureHref,
    installCommand,
    isExternal,
    registryUrl,
    repoUrl,
} from '../data/ecosystem';

export default function Packages({ version }: { version: string }) {
    const { open, setOpen } = useSearchPalette();
    const count = FAMILIES.reduce((n, f) => n + f.packages.length, 0);

    return (
        <div className="k-page">
            <KineticNav version={version} onSearch={() => setOpen(true)} />
            <SearchPalette open={open} onClose={() => setOpen(false)} />

            <section className="mx-auto max-w-7xl px-6 pt-20 pb-6 sm:pt-28">
                <p className="k-mono mb-4">Packages · {count} on Packagist, npm and PyPI</p>
                <h1 className="max-w-3xl text-4xl font-extrabold sm:text-6xl" style={{ letterSpacing: '-0.04em', lineHeight: 1, color: 'var(--k-ink)' }}>
                    Every package, <span className="k-grad-text italic">every language.</span>
                </h1>
                <p className="mt-5 max-w-2xl text-lg" style={{ color: 'var(--k-ink-2)' }}>
                    Each family is published per language, and every package is versioned independently. The registry link shows the current
                    release.
                </p>
                <nav aria-label="Families" className="mt-8 flex flex-wrap gap-2">
                    {FAMILIES.map((family) => (
                        <a
                            key={family.id}
                            href={`#${family.id}`}
                            className="k-mono rounded-full border px-3 py-1 transition-colors hover:text-[var(--k-cyan)]"
                            style={{ borderColor: 'var(--k-hairline-2)' }}
                        >
                            {family.name}
                        </a>
                    ))}
                </nav>
            </section>

            <div className="mx-auto max-w-7xl space-y-6 px-6 py-12">
                {FAMILIES.map((family) => {
                    const feature = featureBySlug(family.feature);

                    return (
                        <section key={family.id} id={family.id} aria-labelledby={`${family.id}-title`} className="k-card scroll-mt-24 p-6 sm:p-8">
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div>
                                    <h2 id={`${family.id}-title`} className="text-2xl font-bold" style={{ color: 'var(--k-ink)' }}>
                                        {family.name}
                                    </h2>
                                    <p className="mt-1 text-sm" style={{ color: 'var(--k-ink-2)' }}>
                                        {family.summary}
                                    </p>
                                </div>
                                {feature && (
                                    <Link href={featureHref(feature.slug)} className="k-mono shrink-0 transition-colors hover:text-[var(--k-cyan)]">
                                        Feature: {feature.title} →
                                    </Link>
                                )}
                            </div>
                            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                                {LANGUAGE_ORDER.map((language) => {
                                    const pkgs = family.packages.filter((p) => p.language === language);

                                    return (
                                        <div key={language} className="rounded-xl border p-4" style={{ borderColor: 'var(--k-hairline-2)', background: 'var(--k-bg-1)' }}>
                                            <p className="k-mono mb-3">
                                                {LANGUAGES[language].label} · {LANGUAGES[language].registry}
                                            </p>
                                            {pkgs.length === 0 ? (
                                                <p className="text-sm" style={{ color: 'var(--k-ink-4)' }}>
                                                    No {LANGUAGES[language].label} package.
                                                </p>
                                            ) : (
                                                <ul className="space-y-5">
                                                    {pkgs.map((pkg) => (
                                                        <li key={pkg.name}>
                                                            <a
                                                                href={registryUrl(pkg)}
                                                                className="break-all font-mono text-sm font-semibold transition-colors hover:text-[var(--k-cyan)]"
                                                                style={{ color: 'var(--k-ink)' }}
                                                            >
                                                                {pkg.name}
                                                            </a>
                                                            {pkg.role && <span className="k-mono ml-2">{pkg.role}</span>}
                                                            <CopyCommand command={installCommand(pkg)} className="mt-2 w-full px-3 py-2 text-xs" />
                                                            <div className="k-mono mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                                                <SmartLink href={pkg.docs} className="transition-colors hover:text-[var(--k-cyan)]">
                                                                    Docs{isExternal(pkg.docs) ? ' ↗' : ' →'}
                                                                </SmartLink>
                                                                <a href={repoUrl(pkg)} className="transition-colors hover:text-[var(--k-cyan)]">
                                                                    Source ↗
                                                                </a>
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </section>
                    );
                })}
            </div>

            <KineticFooter />
        </div>
    );
}
