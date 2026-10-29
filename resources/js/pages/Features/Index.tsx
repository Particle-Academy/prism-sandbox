import { Link } from '@inertiajs/react';
import { LanguageChips } from '../../components/features';
import { KineticFooter, KineticNav, Reveal } from '../../components/kinetic';
import { SearchPalette, useSearchPalette } from '../../components/SearchPalette';
import { FEATURES, featureHref, featureLanguages } from '../../data/ecosystem';

export default function FeaturesIndex({ version }: { version: string }) {
    const { open, setOpen } = useSearchPalette();

    return (
        <div className="k-page">
            <KineticNav version={version} onSearch={() => setOpen(true)} />
            <SearchPalette open={open} onClose={() => setOpen(false)} />

            <section className="mx-auto max-w-7xl px-6 pt-20 pb-6 sm:pt-28">
                <p className="k-mono mb-4">Features</p>
                <h1 className="max-w-3xl text-4xl font-extrabold sm:text-6xl" style={{ letterSpacing: '-0.04em', lineHeight: 1, color: 'var(--k-ink)' }}>
                    What you can build <span className="k-grad-text italic">with Prism.</span>
                </h1>
                <p className="mt-5 max-w-2xl text-lg" style={{ color: 'var(--k-ink-2)' }}>
                    Prism provides a common API for model requests. Its companion packages add agent sessions, storage, retrieval, external tools,
                    browser automation and observability, in PHP, TypeScript and Python.
                </p>
                <div className="mt-8 flex flex-wrap gap-3">
                    <Link href="/packages" className="k-btn k-btn--grad">
                        All packages →
                    </Link>
                    <Link href="/docs" className="k-btn k-btn--ghost">
                        Read the docs
                    </Link>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-6 py-12">
                <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURES.map((feature, i) => (
                        <li key={feature.slug}>
                            <Reveal delay={i * 40} className="h-full">
                                <Link href={featureHref(feature.slug)} className="k-card flex h-full flex-col p-6">
                                    <p className="k-mono mb-3">
                                        <span className="k-grad-text mr-2 text-sm font-bold tracking-normal">{String(i + 1).padStart(2, '0')}</span>
                                        {feature.category}
                                    </p>
                                    <h2 className="text-xl font-bold" style={{ color: 'var(--k-ink)' }}>
                                        {feature.title}
                                    </h2>
                                    <p className="mt-2 flex-1 text-sm leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                                        {feature.summary}
                                    </p>
                                    <LanguageChips languages={featureLanguages(feature)} className="mt-5" />
                                </Link>
                            </Reveal>
                        </li>
                    ))}
                </ul>
            </section>

            <KineticFooter />
        </div>
    );
}
