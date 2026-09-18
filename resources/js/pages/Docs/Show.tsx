import { Head, Link } from '@inertiajs/react';
import { MultiSwitch, cn } from '@particle-academy/react-fancy';
import { useEffect, useState } from 'react';
import { KineticNav } from '../../components/kinetic';
import { SearchPalette, useSearchPalette } from '../../components/SearchPalette';

type NavItem = { text: string; link: string };
type NavGroup = { text: string; items: NavItem[] };
type TocEntry = { id: string; text: string; level: number };
type DocLanguage = 'php' | 'typescript' | 'python';
type Page = {
    title: string;
    html: string;
    toc: TocEntry[];
    prev: { text: string; link: string } | null;
    next: { text: string; link: string } | null;
    section: string | null;
    languages: DocLanguage[];
    defaultLanguage: DocLanguage | null;
};

const languageOptions: { label: string; value: DocLanguage }[] = [
    { label: 'PHP', value: 'php' },
    { label: 'TypeScript', value: 'typescript' },
    { label: 'Python', value: 'python' },
];

const languageStorageKey = 'prism-docs-language';

export default function Show({ page, nav, path, version }: { page: Page; nav: NavGroup[]; path: string; version: string }) {
    const { open, setOpen } = useSearchPalette();
    const [menuOpen, setMenuOpen] = useState(false);
    const [language, setLanguage] = useState<DocLanguage>(page.defaultLanguage ?? page.languages[0] ?? 'php');
    const current = '/' + path;

    useEffect(() => setMenuOpen(false), [path]);

    useEffect(() => {
        if (page.languages.length === 0) return;

        const stored = window.localStorage.getItem(languageStorageKey) as DocLanguage | null;
        const next = page.defaultLanguage ?? (stored && page.languages.includes(stored) ? stored : page.languages[0]);

        setLanguage(next);
        window.localStorage.setItem(languageStorageKey, next);
    }, [page.defaultLanguage, page.languages, path]);

    const selectLanguage = (next: DocLanguage) => {
        setLanguage(next);
        window.localStorage.setItem(languageStorageKey, next);
    };

    const description = page.title + ' — Prism documentation (' + version + '), the Laravel package for LLMs.';

    return (
        <div className="k-page">
            <Head>
                <title>{page.title}</title>
                <meta head-key="description" name="description" content={description} />
                <meta property="og:title" content={page.title + ' — Prism'} />
                <meta property="og:description" content={description} />
                <meta property="og:type" content="article" />
            </Head>

            <KineticNav version={version} onSearch={() => setOpen(true)} />
            <SearchPalette open={open} onClose={() => setOpen(false)} />

            <button
                type="button"
                onClick={() => setMenuOpen((o) => !o)}
                className="k-mono fixed bottom-5 right-5 z-40 rounded-full border px-4 py-2.5 backdrop-blur-md lg:hidden"
                style={{ borderColor: 'var(--k-hairline-2)', background: 'rgba(16,16,24,0.9)', color: 'var(--k-ink)' }}
            >
                {menuOpen ? 'Close' : 'Menu'}
            </button>

            <div className="mx-auto flex max-w-7xl gap-10 px-6">
                {/* Sidebar */}
                <aside
                    className={cn(
                        'fixed inset-0 z-30 overflow-y-auto px-8 pt-20 pb-24 lg:sticky lg:top-[57px] lg:z-auto lg:block lg:h-[calc(100vh-57px)] lg:w-60 lg:shrink-0 lg:px-0 lg:pt-8 lg:pb-10',
                        menuOpen ? 'block' : 'hidden',
                    )}
                    style={{ background: menuOpen ? 'var(--k-bg)' : undefined }}
                >
                    <nav>
                        {nav.map((group) => (
                            <div key={group.text} className="mb-7">
                                <p className="k-mono mb-2.5">{group.text}</p>
                                <ul className="space-y-0.5 border-l" style={{ borderColor: 'var(--k-hairline)' }}>
                                    {group.items.map((item) => {
                                        const active = item.link === current;
                                        // The upstream VitePress sidebar mixes in absolute links to
                                        // companion packages (Relay, Bedrock). Those are not pages we
                                        // render, so they must not be prefixed with /docs.
                                        const external = /^https?:\/\//.test(item.link);
                                        const className =
                                            '-ml-px block border-l py-1 pl-3.5 text-[0.83rem] transition-colors';
                                        const style = {
                                            borderColor: active ? 'var(--k-mag)' : 'transparent',
                                            color: active ? 'var(--k-ink)' : 'var(--k-ink-3)',
                                            fontWeight: active ? 600 : 400,
                                        };

                                        return (
                                            <li key={item.link}>
                                                {external ? (
                                                    <a
                                                        href={item.link}
                                                        target="_blank"
                                                        rel="noreferrer noopener"
                                                        className={className}
                                                        style={style}
                                                    >
                                                        {item.text} ↗
                                                    </a>
                                                ) : (
                                                    <Link href={'/docs' + item.link} className={className} style={style}>
                                                        {item.text}
                                                    </Link>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        ))}
                    </nav>
                </aside>

                {/* Content */}
                <main className="min-w-0 flex-1 py-10">
                    {page.section && <p className="k-mono mb-4">{page.section}</p>}
                    {page.languages.length > 1 && (
                        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 border-b pb-4" style={{ borderColor: 'var(--k-hairline)' }}>
                            <div>
                                <p className="text-sm font-semibold" style={{ color: 'var(--k-ink)' }}>
                                    Code language
                                </p>
                                <p className="text-xs" style={{ color: 'var(--k-ink-3)' }}>
                                    Updates every example on this page.
                                </p>
                            </div>
                            <MultiSwitch
                                aria-label="Code language"
                                size="sm"
                                list={languageOptions.filter((option) => page.languages.includes(option.value))}
                                value={language}
                                onValueChange={selectLanguage}
                            />
                        </div>
                    )}
                    <article className="doc-prose" data-language={language} dangerouslySetInnerHTML={{ __html: page.html }} />

                    <nav className="k-hairline-top mt-14 flex justify-between gap-4 pt-6 text-sm">
                        {page.prev ? (
                            <Link href={page.prev.link} className="k-card px-4 py-3 transition-colors hover:text-[var(--k-cyan)]" style={{ color: 'var(--k-ink-2)' }}>
                                <span className="k-mono block">← Previous</span>
                                {page.prev.text}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {page.next && (
                            <Link
                                href={page.next.link}
                                className="k-card px-4 py-3 text-right transition-colors hover:text-[var(--k-cyan)]"
                                style={{ color: 'var(--k-ink-2)' }}
                            >
                                <span className="k-mono block">Next →</span>
                                {page.next.text}
                            </Link>
                        )}
                    </nav>
                </main>

                {/* TOC */}
                <aside className="sticky top-[57px] hidden h-[calc(100vh-57px)] w-52 shrink-0 overflow-y-auto py-10 xl:block">
                    {page.toc.length > 1 && (
                        <>
                            <p className="k-mono mb-3">On this page</p>
                            <ul className="space-y-1.5 text-[0.8rem]">
                                {page.toc.map((entry) => (
                                    <li key={entry.id} style={{ paddingLeft: entry.level === 3 ? '0.9rem' : 0 }}>
                                        <a href={'#' + entry.id} className="transition-colors hover:text-[var(--k-cyan)]" style={{ color: 'var(--k-ink-3)' }}>
                                            {entry.text}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </aside>
            </div>
        </div>
    );
}
