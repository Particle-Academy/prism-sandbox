import { LANGUAGES, type Language, type Snippet } from '../data/ecosystem';

/** PHP · TypeScript · Python chips, showing only the languages given. */
export function LanguageChips({ languages, className }: { languages: Language[]; className?: string }) {
    return (
        <ul className={'flex flex-wrap gap-2 ' + (className ?? '')} aria-label="Available in">
            {languages.map((language) => (
                <li
                    key={language}
                    className="k-mono rounded-full border px-3 py-1"
                    style={{ borderColor: 'var(--k-hairline-2)', color: 'var(--k-ink-2)' }}
                >
                    {LANGUAGES[language].label}
                </li>
            ))}
        </ul>
    );
}

const SNIPPET_LABEL: Record<Snippet['language'], string> = {
    php: 'PHP',
    ts: 'TypeScript',
    py: 'Python',
    bash: 'Shell',
};

/** A short, static code sample. Plain text: nothing here is rendered as HTML. */
export function CodeSample({ snippet }: { snippet: Snippet }) {
    return (
        <figure className="mt-4 overflow-hidden rounded-lg border" style={{ borderColor: 'var(--k-hairline-2)', background: 'var(--k-bg)' }}>
            <figcaption className="k-mono border-b px-3 py-1.5" style={{ borderColor: 'var(--k-hairline)' }}>
                {SNIPPET_LABEL[snippet.language]}
            </figcaption>
            <pre className="overflow-x-auto p-3 text-xs leading-relaxed" style={{ color: 'var(--k-ink-2)' }}>
                <code>{snippet.source}</code>
            </pre>
        </figure>
    );
}

/** Text with `backticked` spans shown as inline code. React escapes every part. */
export function Inline({ text }: { text: string }) {
    return (
        <>
            {text.split('`').map((part, i) =>
                i % 2 === 1 ? (
                    <code key={i} className="rounded px-1 py-0.5 font-mono text-[0.9em]" style={{ background: 'var(--k-bg-2)', color: 'var(--k-ink)' }}>
                        {part}
                    </code>
                ) : (
                    part
                ),
            )}
        </>
    );
}

/** Section heading in the Kinetic style: a mono eyebrow over a bold title. */
export function SectionHeading({ eyebrow, title, id }: { eyebrow: string; title: string; id?: string }) {
    return (
        <div>
            <p className="k-mono mb-3">{eyebrow}</p>
            <h2 id={id} className="text-3xl font-bold sm:text-4xl" style={{ letterSpacing: '-0.03em', color: 'var(--k-ink)' }}>
                {title}
            </h2>
        </div>
    );
}
