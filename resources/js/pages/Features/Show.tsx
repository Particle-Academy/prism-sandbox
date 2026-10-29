import { Link } from '@inertiajs/react';
import { CodeSample, Inline, LanguageChips, SectionHeading } from '../../components/features';
import { CopyCommand, KineticFooter, KineticNav, Reveal, SmartLink } from '../../components/kinetic';
import { SearchPalette, useSearchPalette } from '../../components/SearchPalette';
import {
    LANGUAGES,
    LANGUAGE_ORDER,
    featureBySlug,
    featureHref,
    featureLanguages,
    featurePackages,
    installCommand,
    isExternal,
    registryUrl,
    repoUrl,
    type Package,
} from '../../data/ecosystem';

function packageLabel(pkg: Package): string {
    return LANGUAGES[pkg.language].label + (pkg.role ? ` · ${pkg.role}` : '');
}

export default function FeatureShow({ version, slug }: { version: string; slug: string }) {
    const { open, setOpen } = useSearchPalette();
    const feature = featureBySlug(slug);

    // The server only renders slugs listed in config/features.php; this guards a
    // slug that is listed there but missing here.
    if (!feature) {
        return (
            <div className="k-page">
                <KineticNav version={version} />
                <p className="mx-auto max-w-3xl px-6 py-32 text-center" style={{ color: 'var(--k-ink-2)' }}>
                    This feature page is not available. <Link href="/features" className="k-grad-text">See every feature →</Link>
                </p>
                <KineticFooter />
            </div>
        );
    }

    const packages = featurePackages(feature);
    const languages = featureLanguages(feature);
    const primaryDocs = feature.docs[0];
    const related = feature.related.map(featureBySlug).filter((f) => f !== undefined);

    return (
        <div className="k-page">
            <KineticNav version={version} onSearch={() => setOpen(true)} />
            <SearchPalette open={open} onClose={() => setOpen(false)} />

            {/* 1. Breadcrumb */}
            <nav aria-label="Breadcrumb" className="mx-auto max-w-7xl px-6 pt-10">
                <ol className="k-mono flex flex-wrap items-center gap-2">
                    <li>
                        <Link href="/features" className="transition-colors hover:text-[var(--k-cyan)]">
                            Features
                        </Link>
                    </li>
                    <li aria-hidden>/</li>
                    <li aria-current="page" style={{ color: 'var(--k-ink-2)' }}>
                        {feature.title}
                    </li>
                </ol>
            </nav>

            {/* 2. Hero */}
            <section className="relative">
                <div
                    className="pointer-events-none absolute -top-24 right-[6%] size-[28rem] rounded-full opacity-15 blur-3xl"
                    style={{ background: 'radial-gradient(circle, #1fd4ff, transparent 65%)' }}
                    aria-hidden
                />
                <div className="relative mx-auto max-w-7xl px-6 pt-10 pb-16 sm:pt-14">
                    <p className="k-mono mb-5">Feature · {feature.category}</p>
                    <h1 className="max-w-4xl text-4xl font-extrabold sm:text-6xl" style={{ letterSpacing: '-0.04em', lineHeight: 1, color: 'var(--k-ink)' }}>
                        {feature.title}
                    </h1>
                    <div className="mt-6 max-w-2xl space-y-3 text-lg" style={{ color: 'var(--k-ink-2)' }}>
                        {feature.pitch.map((sentence) => (
                            <p key={sentence}>
                                <Inline text={sentence} />
                            </p>
                        ))}
                    </div>
                    <LanguageChips languages={languages} className="mt-7" />
                    <div className="mt-8 flex flex-wrap gap-3">
                        <SmartLink href={primaryDocs.href} className="k-btn k-btn--grad">
                            Read the docs →
                        </SmartLink>
                        <Link href={`/packages#${feature.families[0]}`} className="k-btn k-btn--ghost">
                            View packages
                        </Link>
                        <a href={repoUrl(packages[0])} className="k-btn k-btn--ghost">
                            GitHub
                        </a>
                    </div>
                    <ul className="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        {packages.map((pkg) => (
                            <li key={pkg.name + pkg.language}>
                                <a href={registryUrl(pkg)} className="font-mono transition-colors hover:text-[var(--k-cyan)]" style={{ color: 'var(--k-ink-3)' }}>
                                    {pkg.name}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            {/* 3. Capabilities */}
            <section className="k-hairline-top" aria-labelledby="capabilities">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <SectionHeading eyebrow="Capabilities" title="What it does" id="capabilities" />
                    <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {feature.capabilities.map((capability, i) => (
                            <Reveal key={capability.title} delay={i * 50} className="h-full min-w-0">
                                <div className="k-card h-full p-6">
                                    <p className="k-grad-text mb-3 font-mono text-sm font-bold">{String(i + 1).padStart(2, '0')}</p>
                                    <h3 className="font-semibold" style={{ color: 'var(--k-ink)' }}>
                                        {capability.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed" style={{ color: 'var(--k-ink-3)' }}>
                                        <Inline text={capability.body} />
                                    </p>
                                    {capability.code && <CodeSample snippet={capability.code} />}
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </section>

            {/* 4. How it fits */}
            <section className="k-hairline-top" style={{ background: 'var(--k-bg-1)' }} aria-labelledby="fits">
                <div className="mx-auto grid max-w-7xl gap-10 px-6 py-20 lg:grid-cols-[1.1fr_.9fr]">
                    <div>
                        <SectionHeading eyebrow="How it fits" title="Where it sits in the ecosystem" id="fits" />
                        <div className="mt-6 space-y-4 leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                            {feature.fits.map((paragraph) => (
                                <p key={paragraph}>
                                    <Inline text={paragraph} />
                                </p>
                            ))}
                        </div>
                    </div>
                    <div className="grid content-start gap-4 sm:grid-cols-3 lg:grid-cols-1">
                        {LANGUAGE_ORDER.filter((l) => languages.includes(l)).map((language) => (
                            <div key={language} className="k-card p-5">
                                <p className="k-mono mb-2">{LANGUAGES[language].label}</p>
                                <ul className="space-y-1.5">
                                    {packages
                                        .filter((p) => p.language === language)
                                        .map((pkg) => (
                                            <li key={pkg.name}>
                                                <a
                                                    href={registryUrl(pkg)}
                                                    className="break-all font-mono text-sm transition-colors hover:text-[var(--k-cyan)]"
                                                    style={{ color: 'var(--k-ink)' }}
                                                >
                                                    {pkg.name}
                                                </a>
                                            </li>
                                        ))}
                                </ul>
                            </div>
                        ))}
                        {feature.extraLinks?.map((link) => (
                            <SmartLink key={link.href} href={link.href} className="k-mono transition-colors hover:text-[var(--k-cyan)]">
                                {link.label} →
                            </SmartLink>
                        ))}
                    </div>
                </div>
            </section>

            {/* 5. Install */}
            <section className="k-hairline-top" aria-labelledby="install">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <SectionHeading eyebrow="Install" title="Add it to your project" id="install" />
                    <ol className="mt-10 space-y-6">
                        {packages.map((pkg, i) => (
                            <li key={pkg.name + pkg.language} className="flex gap-5">
                                <span className="k-grad-text w-8 shrink-0 pt-1 font-mono text-sm font-bold">{String(i + 1).padStart(2, '0')}</span>
                                <div className="min-w-0">
                                    <p className="mb-2 text-sm" style={{ color: 'var(--k-ink-2)' }}>
                                        <span className="font-mono" style={{ color: 'var(--k-ink)' }}>
                                            {pkg.name}
                                        </span>{' '}
                                        <span className="k-mono ml-1">{packageLabel(pkg)}</span>
                                    </p>
                                    <CopyCommand command={installCommand(pkg)} />
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {/* 6. The packages */}
            <section className="k-hairline-top" style={{ background: 'var(--k-bg-1)' }} aria-labelledby="packages">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <SectionHeading eyebrow="The packages" title="On the registries" id="packages" />
                    <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {packages.map((pkg) => (
                            <div key={pkg.name + pkg.language} className="k-card flex flex-col p-6">
                                <p className="k-mono mb-2">
                                    {packageLabel(pkg)} · {LANGUAGES[pkg.language].registry}
                                </p>
                                <p className="break-all font-mono font-semibold" style={{ color: 'var(--k-ink)' }}>
                                    {pkg.name}
                                </p>
                                <div className="k-mono mt-5 flex flex-wrap gap-x-4 gap-y-2">
                                    <a href={registryUrl(pkg)} className="transition-colors hover:text-[var(--k-cyan)]">
                                        {LANGUAGES[pkg.language].registry} ↗
                                    </a>
                                    <SmartLink href={pkg.docs} className="transition-colors hover:text-[var(--k-cyan)]">
                                        Docs{isExternal(pkg.docs) ? ' ↗' : ' →'}
                                    </SmartLink>
                                    <a href={repoUrl(pkg)} className="transition-colors hover:text-[var(--k-cyan)]">
                                        Source ↗
                                    </a>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* 7. Closing CTA */}
            <section className="k-hairline-top">
                <div className="mx-auto max-w-7xl px-6 py-20">
                    <div className="grid gap-10 lg:grid-cols-2">
                        <div>
                            <p className="k-mono mb-3">Next</p>
                            <h2 className="text-3xl font-bold sm:text-4xl" style={{ letterSpacing: '-0.03em', color: 'var(--k-ink)' }}>
                                Read the <span className="k-grad-text italic">docs.</span>
                            </h2>
                            <ul className="mt-6 space-y-2">
                                {feature.docs.map((doc) => (
                                    <li key={doc.href}>
                                        <SmartLink href={doc.href} className="transition-colors hover:text-[var(--k-cyan)]" style={{ color: 'var(--k-ink-2)' }}>
                                            {doc.label}
                                            {isExternal(doc.href) ? ' ↗' : ' →'}
                                        </SmartLink>
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <p className="k-mono mb-4">Related features</p>
                            <div className="grid gap-4">
                                {related.map((other) => (
                                    <Link key={other.slug} href={featureHref(other.slug)} className="k-card block p-5">
                                        <p className="k-mono mb-1">{other.category}</p>
                                        <p className="font-semibold" style={{ color: 'var(--k-ink)' }}>
                                            {other.title}
                                        </p>
                                        <p className="mt-1 text-sm" style={{ color: 'var(--k-ink-3)' }}>
                                            {other.summary}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <KineticFooter />
        </div>
    );
}
