import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type IndexEntry = { title: string; link: string; section: string; text: string };

/** Cmd+K search palette over the docs index. */
export function SearchPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [index, setIndex] = useState<IndexEntry[] | null>(null);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open && index === null) {
            fetch('/docs-index.json')
                .then((r) => r.json())
                .then(setIndex)
                .catch(() => setIndex([]));
        }
        if (open) {
            setQuery('');
            setActive(0);
            setTimeout(() => inputRef.current?.focus(), 30);
        }
    }, [open, index]);

    const results = useMemo(() => {
        if (!index || query.trim().length < 2) return [];
        const terms = query.toLowerCase().split(/\s+/).filter(Boolean);

        return index
            .map((entry) => {
                const title = entry.title.toLowerCase();
                const body = entry.text.toLowerCase();
                let score = 0;
                for (const term of terms) {
                    if (title.includes(term)) score += 10;
                    else if (body.includes(term)) score += 2;
                    else return null;
                }
                return { entry, score };
            })
            .filter((r): r is { entry: IndexEntry; score: number } => r !== null)
            .sort((a, b) => b.score - a.score)
            .slice(0, 10)
            .map((r) => r.entry);
    }, [index, query]);

    useEffect(() => setActive(0), [results.length]);

    if (!open) return null;

    const go = (link: string) => {
        onClose();
        router.visit(link);
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]"
            style={{ background: 'rgba(7,7,11,0.7)', backdropFilter: 'blur(4px)' }}
            onClick={onClose}
            role="dialog"
            aria-modal
        >
            <div
                className="k-card w-full max-w-xl overflow-hidden shadow-2xl"
                onClick={(e) => e.stopPropagation()}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') onClose();
                    if (e.key === 'ArrowDown') setActive((a) => Math.min(a + 1, results.length - 1));
                    if (e.key === 'ArrowUp') setActive((a) => Math.max(a - 1, 0));
                    if (e.key === 'Enter' && results[active]) go(results[active].link);
                }}
            >
                <input
                    ref={inputRef}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search the docs…"
                    className="w-full border-b bg-transparent px-5 py-4 text-base outline-none"
                    style={{ borderColor: 'var(--k-hairline)', color: 'var(--k-ink)' }}
                />
                <ul className="max-h-[50vh] overflow-y-auto">
                    {results.map((entry, i) => (
                        <li key={entry.link}>
                            <button
                                type="button"
                                onMouseEnter={() => setActive(i)}
                                onClick={() => go(entry.link)}
                                className="flex w-full items-baseline justify-between gap-4 px-5 py-3 text-left"
                                style={{ background: i === active ? 'var(--k-bg-1)' : 'transparent' }}
                            >
                                <span style={{ color: i === active ? 'var(--k-cyan)' : 'var(--k-ink)' }}>{entry.title}</span>
                                <span className="k-mono shrink-0">{entry.section}</span>
                            </button>
                        </li>
                    ))}
                    {query.trim().length >= 2 && results.length === 0 && (
                        <li className="px-5 py-6 text-sm" style={{ color: 'var(--k-ink-3)' }}>
                            {index === null ? 'Loading index…' : 'No matches.'}
                        </li>
                    )}
                </ul>
            </div>
        </div>
    );
}

/** Global ⌘K / Ctrl+K binding. */
export function useSearchPalette() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return { open, setOpen };
}
