import { Badge, Card, Grid, Heading, Progress, Text, Tooltip } from '@particle-academy/react-fancy';
import type { ReactNode } from 'react';
import { KineticFooter, KineticNav } from '../components/kinetic';

/*
 * The public parity dashboard. Laid out after Fancy's f-dash: a grid of section
 * cards, each with a score, a progress bar and a status badge, then a dense grid
 * of per-item badges whose detail lives in a tooltip (and in the badge's
 * accessible name, so it is reachable without a pointer).
 *
 * Everything shown arrives from the server (DashboardPresenter), already judged
 * against the clock. This file only draws it, and the one rule it keeps is the
 * server's: green appears only where the server said the data is current.
 */

type Color = 'green' | 'amber' | 'red' | 'sky' | 'zinc' | 'violet';
type Score = { value: number; of: number } | null;
type Read = { label: string; url: string; ok: boolean; error: string | null };
type Feed = { state: 'ok' | 'partial' | 'stale' | 'failed' | 'unknown'; fetched_at: string | null; last_ok_at: string | null; reads: Read[] };

type PackageRef = { name: string; version: string | null; registry: string | null; repository: string };
type ParityCell = {
    state: 'agree' | 'unverified' | 'elsewhere' | 'withheld' | 'port-gap' | 'no-suite' | 'none';
    packages: PackageRef[];
    target: string | null;
    reference: boolean;
    full: number;
    total: number;
    notes: string[];
};
type Ci = { state: 'ok' | 'stale' | 'failed' | 'unknown'; conclusion?: string | null; at?: string | null; url?: string | null; head_sha?: string | null; matches_head?: boolean };
type ParityView = {
    feed: Feed;
    ci: Ci;
    families: { id: string; reference: string | null; suites: number; cells: Record<Language, ParityCell> }[];
    score: Score;
    gaps: { id: string; summary: string; affects: string[] }[];
    suites: number;
    cross_family?: string[];
};
type ProviderCell = 'yes' | 'yes-unverified' | 'no' | 'not-ported' | 'unknown';
type ProviderLanguage = {
    package: string | null;
    version: string | null;
    tag?: string | null;
    reference?: boolean;
    providers?: number;
    score: Score;
    rows: { provider: string; cells: Record<string, ProviderCell> }[];
};
type ProvidersView = { feed: Feed; capabilities: string[]; providers: string[]; languages: Partial<Record<Language, ProviderLanguage>> };
type DriftItem = { number: number; url: string; date: string; severity: 'contract' | 'catalogue' | 'unclassified'; contract: number | null; catalogue: number | null; state: 'open' | 'closed'; closed_at: string | null };
type DriftView = {
    feed: Feed;
    items: DriftItem[];
    baseline: { state: 'ok' | 'stale' | 'unknown'; committed_at?: string | null; age_days?: number | null; max_age_days?: number; drift_since_commit?: boolean };
    score: Score;
};
type ProtocolCell = 'yes' | 'yes-unverified' | 'no' | 'unknown';
type McpView = {
    feed: Feed;
    required: string[];
    rule: number;
    rows: { package: string; language: string; version: string | null; role: 'server' | 'client'; cells: Record<string, ProtocolCell> }[];
    score: Score;
};
type AcpView = {
    feed: Feed;
    stable: { major: number; tag: string } | null;
    preview: { major: number; tag: string } | null;
    rows: { package: string; language: string; version: string | null; speaks: number | null; stable: ProtocolCell; preview: 'yes' | 'no' | null }[];
    score: Score;
};
type Dashboard = {
    collected: { state: 'fresh' | 'stale' | 'unknown'; at: string | null; age_hours: number | null; max_age_hours: number };
    parity: ParityView;
    providers: ProvidersView;
    drift: DriftView;
    mcp: McpView;
    acp: AcpView;
};

type Language = 'php' | 'ts' | 'py';
const LANGUAGES: Language[] = ['php', 'ts', 'py'];
const LANGUAGE_LABEL: Record<string, string> = { php: 'PHP', ts: 'TypeScript', py: 'Python' };

/** ISO time as fixed UTC text: no locale and no clock, so the server and the browser render the same thing. */
function utc(iso: string | null | undefined): string {
    if (!iso) return 'unknown';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return 'unknown';

    return d.toISOString().slice(0, 16).replace('T', ' ') + ' UTC';
}

function ago(hours: number | null): string {
    if (hours === null) return 'unknown';
    if (hours < 1) return 'under an hour ago';
    if (hours < 48) return `${hours}h ago`;

    return `${Math.floor(hours / 24)} days ago`;
}

const FEED: Record<Feed['state'], { color: Color; label: string; detail: string }> = {
    ok: { color: 'green', label: 'current', detail: 'Every source for this card was read in the latest collection.' },
    partial: { color: 'amber', label: 'partial', detail: 'Some sources for this card could not be read. What could not be read shows as unknown.' },
    stale: { color: 'amber', label: 'stale', detail: 'This data is older than it should be. It is shown as last known, never as current.' },
    failed: { color: 'red', label: 'unknown', detail: 'The source could not be read and there is no earlier copy.' },
    unknown: { color: 'zinc', label: 'unknown', detail: 'Not collected yet.' },
};

/** A status badge whose detail is in a tooltip and in its accessible name. */
function Cell({ color, label, detail, solid = false }: { color: Color; label: ReactNode; detail: string; solid?: boolean }) {
    return (
        <Tooltip content={<span className="whitespace-pre-line">{detail}</span>}>
            <Badge color={color} variant={solid ? 'solid' : 'soft'} size="sm" tabIndex={0} aria-label={detail} className="cursor-help justify-center">
                {label}
            </Badge>
        </Tooltip>
    );
}

function FeedBadge({ feed }: { feed: Feed }) {
    const meta = FEED[feed.state];
    const when = feed.state === 'stale' && feed.last_ok_at ? `\nLast read successfully: ${utc(feed.last_ok_at)}` : '';

    return <Cell color={meta.color} label={meta.label} detail={`${meta.detail}\nCollected: ${utc(feed.fetched_at)}${when}`} solid />;
}

function ScoreLine({ score, label, green }: { score: Score; label: string; green: boolean }) {
    if (score === null) {
        return (
            <Text size="sm" color="muted">
                {label}: unknown
            </Text>
        );
    }
    const pct = score.of === 0 ? 0 : Math.round((score.value / score.of) * 100);

    return (
        <div className="space-y-1">
            <Text size="sm">
                <span className="font-semibold tabular-nums">
                    {score.value} / {score.of}
                </span>{' '}
                <span className="opacity-70">{label}</span>
            </Text>
            <Progress value={pct} max={100} size="sm" color={green && pct === 100 ? 'green' : pct >= 75 ? 'sky' : 'amber'} aria-label={`${label}: ${pct}%`} />
        </div>
    );
}

function Section({ title, description, feed, score, scoreLabel, wide = false, children }: { title: string; description: string; feed: Feed; score: Score; scoreLabel: string; wide?: boolean; children: ReactNode }) {
    return (
        <Card variant="outlined" padding="md" style={wide ? { gridColumn: '1 / -1' } : undefined}>
            <div className="flex flex-col gap-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Heading as="h2" size="lg" weight="bold">
                            {title}
                        </Heading>
                        <Text size="sm" color="muted" className="mt-1 max-w-3xl">
                            {description}
                        </Text>
                    </div>
                    <FeedBadge feed={feed} />
                </div>
                <div className="max-w-md">
                    <ScoreLine score={score} label={scoreLabel} green={feed.state === 'ok'} />
                </div>
                {children}
            </div>
        </Card>
    );
}

function Legend({ items }: { items: [Color, string, string][] }) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            {items.map(([color, label, detail]) => (
                <Cell key={label} color={color} label={label} detail={detail} />
            ))}
        </div>
    );
}

/* ---------------------------------------------------------------- parity */

const PARITY: Record<ParityCell['state'], { color: Color; label: (c: ParityCell) => string }> = {
    agree: { color: 'green', label: (c) => `agrees ${c.full}/${c.total}` },
    unverified: { color: 'amber', label: (c) => `${c.full}/${c.total} unverified` },
    elsewhere: { color: 'sky', label: (c) => `${c.full}/${c.total} · not port` },
    withheld: { color: 'violet', label: (c) => `${c.full}/${c.total} · withheld` },
    'port-gap': { color: 'red', label: (c) => `port gap ${c.full}/${c.total}` },
    'no-suite': { color: 'zinc', label: () => 'no suite' },
    none: { color: 'zinc', label: () => 'no package' },
};

function ParityCard({ view }: { view: ParityView }) {
    const ci = view.ci;
    const ciColor: Color = ci.state === 'ok' ? 'green' : ci.state === 'failed' ? 'red' : ci.state === 'stale' ? 'amber' : 'zinc';
    const ciDetail =
        ci.state === 'unknown'
            ? 'The parity CI verdict on main could not be read.'
            : `Newest completed run of the parity CI on main (alignment, which runs the cross-check): ${ci.conclusion ?? 'unknown'} at ${utc(ci.at)} for ${ci.head_sha ?? 'unknown'}.` +
              (ci.matches_head ? '' : '\nThat is not the current head of main, so it is not counted as current.') +
              (ci.state === 'stale' ? '\nOlder than two days or behind main: not counted as current.' : '');

    return (
        <Section
            wide
            title="Language parity"
            description="Every family in the published catalog, by language. A cell counts the cross-language suites for that family that run in that language, and its colour says whose gap any remainder is. A cell is green only when every suite is full and the parity CI on main is passing and current."
            feed={view.feed}
            score={view.score}
            scoreLabel="family × language cells with every suite full"
        >
            <div className="flex flex-wrap items-center gap-2">
                <Text size="sm" color="muted">
                    Parity CI on main:
                </Text>
                <Cell color={ciColor} label={ci.state === 'unknown' ? 'unknown' : `${ci.conclusion ?? 'unknown'} · ${ci.head_sha ?? ''}`} detail={ciDetail} solid />
                {ci.url && (
                    <a href={ci.url} className="text-sm underline opacity-70 hover:opacity-100" rel="noreferrer" target="_blank">
                        run
                    </a>
                )}
                <Text size="sm" color="muted">
                    · {view.suites} suites
                </Text>
            </div>

            {view.families.length === 0 ? (
                <Text color="muted">Unknown: the catalog could not be read.</Text>
            ) : (
                <div className="overflow-x-auto">
                    <Grid cols={4} gap="sm" responsive={false} className="min-w-[36rem] items-center">
                        <Text size="xs" weight="semibold" className="uppercase opacity-60">
                            Family
                        </Text>
                        {LANGUAGES.map((l) => (
                            <Text key={l} size="xs" weight="semibold" className="uppercase opacity-60">
                                {LANGUAGE_LABEL[l]}
                            </Text>
                        ))}
                        {view.families.map((family) => [
                            <div key={`${family.id}-name`}>
                                <Text weight="semibold">{family.id}</Text>
                                <Text size="xs" color="muted">
                                    {family.suites} {family.suites === 1 ? 'suite' : 'suites'}
                                    {family.reference ? ` · reference ${LANGUAGE_LABEL[family.reference] ?? family.reference}` : ''}
                                </Text>
                            </div>,
                            ...LANGUAGES.map((l) => {
                                const cell = family.cells[l];
                                const meta = PARITY[cell.state];
                                const pkg = cell.packages.map((p) => `${p.name}${p.version ? ' ' + p.version : ''}`).join(', ');
                                const detail = [
                                    `${family.id} · ${LANGUAGE_LABEL[l]}${cell.reference ? ' (reference)' : ''}`,
                                    pkg ? `Package: ${pkg}` : null,
                                    cell.target ? `Launch target status: ${cell.target}` : null,
                                    ...cell.notes,
                                ]
                                    .filter(Boolean)
                                    .join('\n');

                                return (
                                    <div key={`${family.id}-${l}`} className="flex flex-col items-start gap-0.5">
                                        <Cell color={meta.color} label={meta.label(cell)} detail={detail} />
                                        {pkg && (
                                            <Text size="xs" color="muted" className="truncate">
                                                {cell.packages[0].version ? `v${cell.packages[0].version}` : ''}
                                                {cell.reference ? ' · ref' : ''}
                                            </Text>
                                        )}
                                    </div>
                                );
                            }),
                        ])}
                    </Grid>
                </div>
            )}

            <Legend
                items={[
                    ['green', 'agrees', 'Every suite for the family is full in this language, and the parity CI on main is passing and current.'],
                    ['amber', 'unverified', 'Every suite is full, but the CI verdict or the data is not current, so agreement is not claimed.'],
                    ['red', 'port gap', 'This language has no runner for at least one suite: work for the port.'],
                    ['sky', 'not port', 'Not full, but the gap belongs to the reference or to what the language can express, not to the port.'],
                    ['violet', 'withheld', 'A security corpus is not full here. Why is not published on this page.'],
                    ['zinc', 'no suite / no package', 'Nothing to compare yet, or no published package in this language.'],
                ]}
            />

            {view.gaps.length > 0 && (
                <div className="space-y-2">
                    <Heading as="h3" size="sm" weight="semibold">
                        Known gaps, as published in the parity ledger
                    </Heading>
                    <ul className="space-y-1">
                        {view.gaps.map((gap) => (
                            <li key={gap.id} className="flex gap-2">
                                <Badge color="zinc" variant="outline" size="sm" className="shrink-0">
                                    {gap.id}
                                </Badge>
                                <Text size="sm">
                                    {gap.summary} <span className="opacity-60">({gap.affects.join(', ')})</span>
                                </Text>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {view.cross_family && view.cross_family.length > 0 && (
                <Text size="xs" color="muted">
                    Suites spanning several families, counted in the total but in no row: {view.cross_family.join(', ')}.
                </Text>
            )}
        </Section>
    );
}

/* ------------------------------------------------------------- providers */

const PROVIDER: Record<ProviderCell, { color: Color; label: string; text: string }> = {
    yes: { color: 'green', label: '✓', text: 'implemented in the released source' },
    'yes-unverified': { color: 'amber', label: '✓', text: 'implemented, as last read (the data is not current)' },
    no: { color: 'zinc', label: '–', text: 'not implemented by this provider' },
    'not-ported': { color: 'red', label: '×', text: 'the reference has this, but the provider is not ported to this language' },
    unknown: { color: 'zinc', label: '?', text: 'unknown: the provider source could not be read' },
};

function ProvidersCard({ view }: { view: ProvidersView }) {
    // The card's score is the ports against the reference: what a TypeScript or
    // Python user can call, out of what a PHP user can.
    const ports = LANGUAGES.map((l) => view.languages[l]).filter((l): l is ProviderLanguage => !!l && !!l.package && !l.reference);
    const known = ports.every((p) => p.score !== null) && ports.length > 0;
    const score: Score = known ? ports.reduce((s, p) => ({ value: s.value + p.score!.value, of: s.of + p.score!.of }), { value: 0, of: 0 }) : null;

    return (
        <Section
            wide
            title="Providers × capabilities"
            description="For each language, which capability each provider implements in the source of its latest published release. Rows and columns are the reference package's providers and capabilities, so a provider missing from a port is visible as a row."
            feed={view.feed}
            score={score}
            scoreLabel="of the reference's provider capabilities shipped by the TypeScript and Python ports"
        >
            {view.capabilities.length === 0 ? (
                <Text color="muted">Unknown: the reference package's provider surface could not be read.</Text>
            ) : (
                <div className="space-y-6">
                    {LANGUAGES.map((l) => {
                        const lang = view.languages[l];
                        if (!lang || !lang.package) {
                            return (
                                <Text key={l} color="muted">
                                    {LANGUAGE_LABEL[l]}: no published core package.
                                </Text>
                            );
                        }

                        return (
                            <div key={l} className="space-y-2">
                                <div className="flex flex-wrap items-end justify-between gap-3">
                                    <Heading as="h3" size="sm" weight="semibold">
                                        {LANGUAGE_LABEL[l]} · {lang.package} {lang.tag ?? ''}
                                        {lang.reference ? ' · reference' : ''}
                                    </Heading>
                                    <div className="w-64">
                                        <ScoreLine score={lang.score} label="of the reference's provider capabilities" green={view.feed.state === 'ok'} />
                                    </div>
                                </div>
                                <div className="overflow-x-auto">
                                    <Grid cols={view.capabilities.length + 1} gap="sm" responsive={false} className="min-w-[56rem] items-center">
                                        <span />
                                        {view.capabilities.map((c) => (
                                            <Text key={c} size="xs" className="truncate opacity-60" title={c}>
                                                {c}
                                            </Text>
                                        ))}
                                        {lang.rows.map((row) => [
                                            <Text key={`${row.provider}-name`} size="sm" weight="medium">
                                                {row.provider}
                                            </Text>,
                                            ...view.capabilities.map((c) => {
                                                const meta = PROVIDER[row.cells[c] ?? 'unknown'];

                                                return (
                                                    <Cell
                                                        key={`${row.provider}-${c}`}
                                                        color={meta.color}
                                                        label={meta.label}
                                                        detail={`${row.provider} · ${c} · ${LANGUAGE_LABEL[l]} ${lang.tag ?? ''}: ${meta.text}`}
                                                    />
                                                );
                                            }),
                                        ])}
                                    </Grid>
                                </div>
                            </div>
                        );
                    })}
                    <Legend
                        items={(['yes', 'yes-unverified', 'no', 'not-ported', 'unknown'] as ProviderCell[]).map((k) => [PROVIDER[k].color, PROVIDER[k].label, PROVIDER[k].text])}
                    />
                </div>
            )}
        </Section>
    );
}

/* ----------------------------------------------------------------- drift */

function DriftCard({ view }: { view: DriftView }) {
    const b = view.baseline;
    const baselineColor: Color = b.state === 'ok' ? 'green' : b.state === 'stale' ? 'amber' : 'zinc';
    const baselineDetail =
        b.state === 'unknown'
            ? 'When the watcher baseline was last reviewed could not be read.'
            : `The watcher compares against a committed baseline that advances only after someone reviews what changed. Last committed ${utc(b.committed_at)} (${b.age_days} days ago).` +
              (b.state === 'stale' ? `\nDrift has been reported since then and the baseline is older than ${b.max_age_days} days: the watcher reports this as stale.` : '');

    return (
        <Section
            title="Provider API drift"
            description="What prism-provider-watch reported, from the issues it files on Particle-Academy/prism. A contract change touches an API we integrate; a catalogue change is a model or price moving."
            feed={view.feed}
            score={view.score}
            scoreLabel="drift reports in the last 30 days closed"
        >
            <div className="flex flex-wrap items-center gap-2">
                <Text size="sm" color="muted">
                    Watcher baseline:
                </Text>
                <Cell color={baselineColor} label={b.state === 'unknown' ? 'unknown' : `${b.state} · ${b.age_days}d`} detail={baselineDetail} solid />
            </div>
            {view.items.length === 0 ? (
                <Text color="muted">{view.feed.state === 'ok' ? 'No drift reports published.' : 'Unknown: the drift reports could not be read.'}</Text>
            ) : (
                <ul className="space-y-1.5">
                    {view.items.slice(0, 14).map((item) => {
                        const sev: Color = item.severity === 'contract' && (item.contract ?? 0) > 0 ? 'amber' : 'zinc';
                        const sevLabel =
                            item.severity === 'contract'
                                ? `${item.contract} contract${item.catalogue ? ` · +${item.catalogue} catalogue` : ''}`
                                : item.severity === 'catalogue'
                                  ? `${item.catalogue} catalogue`
                                  : 'unclassified';
                        const closed = item.state === 'closed';
                        const stateColor: Color = closed ? (view.feed.state === 'ok' ? 'green' : 'amber') : 'red';

                        return (
                            <li key={item.number} className="flex flex-wrap items-center gap-2">
                                <Text size="sm" className="w-24 tabular-nums">
                                    {item.date}
                                </Text>
                                <Cell
                                    color={sev}
                                    label={sevLabel}
                                    detail={item.severity === 'contract' ? 'Contract changes touch an API Prism integrates; each one is triaged against the source.' : 'Catalogue changes only: models or prices moved.'}
                                />
                                <Cell
                                    color={stateColor}
                                    label={item.state}
                                    detail={closed ? `Closed ${utc(item.closed_at)}.` : 'Open: not triaged yet.'}
                                />
                                <a href={item.url} className="text-sm underline opacity-70 hover:opacity-100" rel="noreferrer" target="_blank">
                                    #{item.number}
                                </a>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Section>
    );
}

/* ------------------------------------------------------------- protocols */

const PROTOCOL: Record<ProtocolCell, { color: Color; label: string; text: string }> = {
    yes: { color: 'green', label: '✓', text: 'served' },
    'yes-unverified': { color: 'amber', label: '✓', text: 'served, as last read (the data is not current)' },
    no: { color: 'red', label: '×', text: 'not served' },
    unknown: { color: 'zinc', label: '?', text: 'unknown: could not be read' },
};

function ProtocolsCard({ mcp, acp }: { mcp: McpView; acp: AcpView }) {
    return (
        <Section
            title="Protocol coverage"
            description={`MCP: the standing rule is that our servers serve the ${mcp.rule} newest official schema versions, read from the specification repository. Clients declare one version in the catalog. ACP: the protocol version each released agent speaks, against the newest stable schema release.`}
            feed={worst(mcp.feed, acp.feed)}
            score={mcp.score}
            scoreLabel={`MCP server × required-version pairs served`}
        >
            <div className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                    <Heading as="h3" size="sm" weight="semibold">
                        MCP
                    </Heading>
                    <FeedBadge feed={mcp.feed} />
                </div>
                {mcp.required.length === 0 ? (
                    <Text color="muted">Unknown: the official schema versions could not be read.</Text>
                ) : (
                    <div className="overflow-x-auto">
                        <Grid cols={mcp.required.length + 1} gap="sm" responsive={false} className="min-w-[28rem] items-center">
                            <span />
                            {mcp.required.map((v) => (
                                <Text key={v} size="xs" className="opacity-60">
                                    {v}
                                </Text>
                            ))}
                            {mcp.rows.map((row) => [
                                <div key={`${row.package}-name`}>
                                    <Text size="sm" weight="medium" className="truncate">
                                        {row.package}
                                    </Text>
                                    <Text size="xs" color="muted">
                                        {row.role} · {LANGUAGE_LABEL[row.language] ?? row.language} {row.version ? `v${row.version}` : ''}
                                    </Text>
                                </div>,
                                ...mcp.required.map((v) => {
                                    const state = row.cells[v] ?? 'unknown';
                                    // A client declares one version; a version it does not declare is not a gap.
                                    const meta = row.role === 'client' && state === 'no' ? { color: 'zinc' as Color, label: '–', text: 'not the version this client declares' } : PROTOCOL[state];

                                    return <Cell key={`${row.package}-${v}`} color={meta.color} label={meta.label} detail={`${row.package} · MCP ${v}: ${meta.text}`} />;
                                }),
                            ])}
                        </Grid>
                    </div>
                )}
            </div>

            <div className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                    <Heading as="h3" size="sm" weight="semibold">
                        ACP
                    </Heading>
                    <FeedBadge feed={acp.feed} />
                </div>
                <Text size="sm" color="muted">
                    Newest stable schema: {acp.stable ? `v${acp.stable.major} (${acp.stable.tag})` : 'unknown'}
                    {acp.preview ? ` · preview: v${acp.preview.major} (${acp.preview.tag})` : ''}
                </Text>
                {acp.rows.length === 0 ? (
                    <Text color="muted">Unknown: no ACP agent could be read.</Text>
                ) : (
                    <ul className="space-y-1.5">
                        {acp.rows.map((row) => {
                            const meta = PROTOCOL[row.stable];

                            return (
                                <li key={row.package} className="flex flex-wrap items-center gap-2">
                                    <Text size="sm" weight="medium">
                                        {row.package}
                                    </Text>
                                    <Text size="xs" color="muted">
                                        {LANGUAGE_LABEL[row.language] ?? row.language} {row.version ? `v${row.version}` : ''}
                                    </Text>
                                    <Cell
                                        color={meta.color}
                                        label={row.speaks === null ? '?' : `speaks v${row.speaks}`}
                                        detail={`${row.package}: ${row.speaks === null ? 'protocol version unknown' : `speaks ACP v${row.speaks}`}; newest stable is ${acp.stable ? 'v' + acp.stable.major : 'unknown'}: ${meta.text}.`}
                                    />
                                    {row.preview === 'no' && acp.preview && (
                                        <Cell color="sky" label={`v${acp.preview.major} preview: not yet`} detail={`ACP v${acp.preview.major} is a prerelease (${acp.preview.tag}). Not served yet; not a gap against the stable protocol.`} />
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </Section>
    );
}

const ORDER: Feed['state'][] = ['ok', 'partial', 'stale', 'unknown', 'failed'];
function worst(a: Feed, b: Feed): Feed {
    return ORDER.indexOf(a.state) >= ORDER.indexOf(b.state) ? a : b;
}

/* --------------------------------------------------------------- sources */

function SourcesCard({ feeds }: { feeds: [string, Feed][] }) {
    return (
        <Card variant="outlined" padding="md" style={{ gridColumn: '1 / -1' }}>
            <div className="space-y-3">
                <Heading as="h2" size="lg" weight="bold">
                    Sources
                </Heading>
                <Text size="sm" color="muted">
                    Every address read in the latest collection. All are public; nothing here is read with a credential.
                </Text>
                {feeds.map(([name, feed]) => (
                    <div key={name} className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Text size="sm" weight="semibold">
                                {name}
                            </Text>
                            <FeedBadge feed={feed} />
                        </div>
                        {feed.reads.length === 0 ? (
                            <Text size="xs" color="muted">
                                Not collected yet.
                            </Text>
                        ) : (
                            <ul className="space-y-0.5">
                                {feed.reads.map((read) => (
                                    <li key={read.label + read.url} className="flex flex-wrap items-center gap-2">
                                        <Badge color={read.ok ? 'zinc' : 'red'} variant="soft" size="sm">
                                            {read.ok ? 'read' : 'failed'}
                                        </Badge>
                                        <a href={read.url} className="text-xs underline opacity-70 hover:opacity-100" rel="noreferrer" target="_blank">
                                            {read.label}
                                        </a>
                                        {read.error && (
                                            <Text size="xs" color="danger">
                                                {read.error}
                                            </Text>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ))}
            </div>
        </Card>
    );
}

/* ------------------------------------------------------------------ page */

export default function Parity({ version, dashboard }: { version: string; dashboard: Dashboard }) {
    const c = dashboard.collected;
    const collectedColor: Color = c.state === 'fresh' ? 'green' : c.state === 'stale' ? 'amber' : 'zinc';
    const collectedLabel = c.state === 'unknown' ? 'not collected yet' : c.state === 'stale' ? `stale · collected ${ago(c.age_hours)}` : `collected ${ago(c.age_hours)}`;
    const collectedDetail =
        c.state === 'unknown'
            ? 'No collection has run yet, so every card shows unknown.'
            : `Last collection: ${utc(c.at)}. Collected every six hours; older than ${c.max_age_hours} hours counts as stale, and nothing stale is drawn green.`;

    return (
        <div className="k-page">
            <KineticNav version={version} />

            <section className="mx-auto max-w-7xl px-6 pt-20 pb-6 sm:pt-28">
                <p className="k-mono mb-4">Parity · languages, providers, drift, protocols</p>
                <h1 className="max-w-3xl text-4xl font-extrabold sm:text-6xl" style={{ letterSpacing: '-0.04em', lineHeight: 1, color: 'var(--k-ink)' }}>
                    Where Prism agrees, <span className="k-grad-text italic">and where it does not.</span>
                </h1>
                <p className="mt-5 max-w-2xl text-lg" style={{ color: 'var(--k-ink-2)' }}>
                    Read from public sources: the parity catalog and suites, released package source, the provider watcher's reports and the protocol specifications.
                    A source that could not be read says unknown.
                </p>
                <div className="mt-6 flex items-center gap-2">
                    <Cell color={collectedColor} label={collectedLabel} detail={collectedDetail} solid />
                </div>
            </section>

            <div className="mx-auto max-w-7xl px-6 pb-20">
                <Grid cols={2} gap="lg">
                    <ParityCard view={dashboard.parity} />
                    <ProvidersCard view={dashboard.providers} />
                    <DriftCard view={dashboard.drift} />
                    <ProtocolsCard mcp={dashboard.mcp} acp={dashboard.acp} />
                    <SourcesCard
                        feeds={[
                            ['Language parity', dashboard.parity.feed],
                            ['Providers × capabilities', dashboard.providers.feed],
                            ['Provider API drift', dashboard.drift.feed],
                            ['MCP', dashboard.mcp.feed],
                            ['ACP', dashboard.acp.feed],
                        ]}
                    />
                </Grid>
            </div>

            <KineticFooter />
        </div>
    );
}
