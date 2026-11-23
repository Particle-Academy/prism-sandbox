<?php

declare(strict_types=1);

namespace App\Parity;

use DateTimeImmutable;

/**
 * Turns a stored snapshot into what the page draws, judged against NOW.
 *
 * Freshness is decided here, at render time, not when the snapshot was
 * written: a snapshot that was perfect a week ago is a week old today, and
 * only the clock at render time can say so. The rule that matters: green is
 * only ever drawn for data that was read successfully AND is fresh. Anything
 * else (failed, partial, stale, never read) caps the colour below green, and
 * a source with no data at all says "unknown".
 */
final class DashboardPresenter
{
    /** The collection runs every six hours; a day without one means it has stopped. */
    public const COLLECTION_MAX_AGE_HOURS = 24;

    /** The parity CI runs daily and on every push to main. */
    public const CI_MAX_AGE_HOURS = 48;

    /** prism-provider-watch's own threshold for an unreviewed baseline (PRISM_WATCH_BASELINE_MAX_AGE_DAYS). */
    public const BASELINE_MAX_AGE_DAYS = 2;

    private const PORT_GAP_CAUSES = ['no-runner'];

    private const CAUSE_LABELS = [
        'no-runner' => 'this language has no runner for the suite (a port gap)',
        'reference-limit' => 'the reference cannot express, or gets wrong, what it asks of the ports',
        'language-limit' => 'this language cannot express the row',
        'decision-pending' => 'the implementations disagree and closing it needs a cross-repo decision',
    ];

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    public function present(?array $snapshot, DateTimeImmutable $now): array
    {
        $collected = $this->collected($snapshot, $now);
        $fresh = $collected['state'] === 'fresh';

        return [
            'collected' => $collected,
            'parity' => $this->parity($snapshot['parity'] ?? null, $fresh, $now),
            'providers' => $this->providers($snapshot['providers'] ?? null, $fresh),
            'drift' => $this->drift($snapshot['drift'] ?? null, $fresh, $now),
            'mcp' => $this->mcp($snapshot['protocols']['mcp'] ?? null, $fresh),
            'acp' => $this->acp($snapshot['protocols']['acp'] ?? null, $fresh),
            'upstream' => $this->upstream($snapshot['upstream'] ?? null, $fresh, $snapshot['drift']['data']['baseline_committed_at'] ?? null),
            'upstream_prs' => $this->upstreamPulls($snapshot['upstream_prs'] ?? null, $fresh, $now),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function upstream(?array $section, bool $fresh, mixed $watcherBaseline): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        $int = fn (mixed $v): ?int => is_int($v) ? $v : null;
        $release = fn (mixed $r): ?array => is_array($r) && is_string($r['tag'] ?? null) ? ['tag' => $r['tag'], 'published_at' => is_string($r['published_at'] ?? null) ? $r['published_at'] : null] : null;

        $watched = [];
        foreach (['issues', 'pulls'] as $source) {
            $entry = $data['watched_open'][$source] ?? null;
            $watched[$source] = is_array($entry) && is_int($entry['count'] ?? null) ? ['count' => $entry['count'], 'lower_bound' => (bool) ($entry['lower_bound'] ?? true)] : null;
        }

        return [
            'feed' => $feed,
            // Green is only for current data; the page caps everything else.
            'green' => $feed['state'] === 'ok',
            'repo' => is_string($data['repo'] ?? null) ? $data['repo'] : (string) config('parity.upstream.repo'),
            'fork' => is_string($data['fork'] ?? null) ? $data['fork'] : (string) config('parity.upstream.fork'),
            'ahead' => $int($data['ahead'] ?? null),
            'behind' => $int($data['behind'] ?? null),
            'merge_base' => is_array($data['merge_base'] ?? null) ? ['sha' => substr((string) $data['merge_base']['sha'], 0, 7), 'date' => $data['merge_base']['date'] ?? null] : null,
            'upstream_release' => $release($data['upstream_release'] ?? null),
            'fork_release' => $release($data['fork_release'] ?? null),
            'releases_since' => is_array($data['releases_since'] ?? null) ? array_values(array_filter(array_map($release, $data['releases_since']))) : null,
            'opened_since' => [
                'issues' => $int($data['opened_since']['issues'] ?? null),
                'pulls' => $int($data['opened_since']['pulls'] ?? null),
            ],
            'watched_open' => $watched,
            'watched_as_of' => is_string($watcherBaseline) ? $watcherBaseline : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function upstreamPulls(?array $section, bool $fresh, DateTimeImmutable $now): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        if (! is_array($data)) {
            return ['feed' => $feed, 'green' => false, 'pulls' => [], 'counts' => null, 'score' => null, 'lower_bound' => false, 'stale_days' => null];
        }

        $staleDays = is_int($data['stale_days'] ?? null) ? $data['stale_days'] : 30;
        $counts = ['open' => 0, 'merged' => 0, 'closed' => 0];
        $pulls = [];
        foreach (is_array($data['pulls'] ?? null) ? $data['pulls'] : [] as $pull) {
            if (! is_array($pull) || ! isset($counts[$pull['state'] ?? ''])) {
                continue;
            }
            $counts[$pull['state']]++;
            $created = $this->time($pull['created_at'] ?? null);
            $updated = $this->time($pull['updated_at'] ?? null);
            $age = $created === null ? null : intdiv($this->hours($created, $now), 24);
            $pulls[] = [
                'number' => $pull['number'],
                'title' => $pull['title'],
                'url' => $pull['url'],
                'state' => $pull['state'],
                'draft' => $pull['draft'] ?? null,
                'review' => is_string($pull['review'] ?? null) ? $pull['review'] : null,
                'ci' => is_string($pull['ci'] ?? null) ? $pull['ci'] : null,
                'age_days' => $age,
                'last_activity' => $updated?->format(DATE_ATOM),
                'idle_days' => $updated === null ? null : intdiv($this->hours($updated, $now), 24),
                'merged_at' => $pull['merged_at'] ?? null,
                'stale' => $pull['state'] === 'open' && $age !== null && $age > $staleDays,
            ];
        }
        $decided = $counts['merged'] + $counts['closed'];

        return [
            'feed' => $feed,
            'green' => $feed['state'] === 'ok',
            'pulls' => $pulls,
            'counts' => $counts,
            // Of the PRs upstream has decided, how many it took.
            'score' => $decided === 0 ? null : ['value' => $counts['merged'], 'of' => $decided],
            'lower_bound' => (bool) ($data['lower_bound'] ?? false),
            'stale_days' => $staleDays,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    private function collected(?array $snapshot, DateTimeImmutable $now): array
    {
        $at = $this->time($snapshot['generated_at'] ?? null);
        if ($at === null) {
            return ['state' => 'unknown', 'at' => null, 'age_hours' => null, 'max_age_hours' => self::COLLECTION_MAX_AGE_HOURS];
        }
        $hours = $this->hours($at, $now);

        return [
            'state' => $hours <= self::COLLECTION_MAX_AGE_HOURS ? 'fresh' : 'stale',
            'at' => $at->format(DATE_ATOM),
            'age_hours' => $hours,
            'max_age_hours' => self::COLLECTION_MAX_AGE_HOURS,
        ];
    }

    /**
     * The state of a section's feed: ok, partial, stale, failed or unknown.
     *
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function feed(?array $section, bool $fresh): array
    {
        if (! is_array($section)) {
            return ['state' => 'unknown', 'fetched_at' => null, 'last_ok_at' => null, 'reads' => []];
        }

        $state = match ($section['status'] ?? null) {
            'ok' => $fresh ? 'ok' : 'stale',
            'partial' => $fresh ? 'partial' : 'stale',
            'stale' => 'stale',
            'failed' => 'failed',
            default => 'unknown',
        };
        if (! is_array($section['data'] ?? null)) {
            $state = $state === 'failed' ? 'failed' : 'unknown';
        }

        $reads = [];
        foreach (is_array($section['reads'] ?? null) ? $section['reads'] : [] as $read) {
            if (is_array($read) && is_string($read['url'] ?? null) && str_starts_with($read['url'], 'https://')) {
                $reads[] = [
                    'label' => (string) ($read['label'] ?? ''),
                    'url' => $read['url'],
                    'ok' => (bool) ($read['ok'] ?? false),
                    'error' => is_string($read['error'] ?? null) ? $read['error'] : null,
                ];
            }
        }

        return [
            'state' => $state,
            'fetched_at' => is_string($section['fetched_at'] ?? null) ? $section['fetched_at'] : null,
            'last_ok_at' => is_string($section['last_ok_at'] ?? null) ? $section['last_ok_at'] : null,
            'reads' => $reads,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function parity(?array $section, bool $fresh, DateTimeImmutable $now): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        if (! is_array($data)) {
            return ['feed' => $feed, 'ci' => ['state' => 'unknown'], 'families' => [], 'score' => null, 'gaps' => [], 'suites' => 0];
        }

        $ci = $this->ci($data['ci'] ?? null, $data['head']['sha'] ?? null, $now);
        $verified = $feed['state'] === 'ok' && $ci['state'] === 'ok';

        $suites = array_values(array_filter((array) ($data['suites'] ?? []), 'is_array'));
        $families = [];
        $scored = 0;
        $complete = 0;
        foreach ((array) ($data['families'] ?? []) as $family) {
            if (! is_array($family) || ! is_string($family['id'] ?? null)) {
                continue;
            }
            $mine = array_values(array_filter($suites, fn (array $s): bool => ($s['family'] ?? null) === $family['id']));
            $cells = [];
            foreach (SnapshotBuilder::LANGUAGES as $language) {
                $cell = $this->parityCell((array) ($family['cells'][$language] ?? []), $mine, $language, $verified, $family['reference'] ?? null);
                if ($cell['packages'] !== []) {
                    $scored++;
                    if (in_array($cell['state'], ['agree', 'unverified'], true)) {
                        $complete++;
                    }
                }
                $cells[$language] = $cell;
            }
            $families[] = ['id' => $family['id'], 'reference' => $family['reference'] ?? null, 'suites' => count($mine), 'cells' => $cells];
        }

        // Cross-family suites still count towards the total shown, so none goes missing.
        return [
            'feed' => $feed,
            'ci' => $ci,
            'families' => $families,
            'score' => $scored === 0 ? null : ['value' => $complete, 'of' => $scored],
            'gaps' => array_values(array_filter((array) ($data['gaps'] ?? []), 'is_array')),
            'suites' => count($suites),
            'cross_family' => array_values(array_map(fn (array $s): string => (string) $s['id'], array_filter($suites, fn (array $s): bool => ($s['family'] ?? null) === 'cross-family'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $cell
     * @param  list<array<string, mixed>>  $suites
     * @return array<string, mixed>
     */
    private function parityCell(array $cell, array $suites, string $language, bool $verified, mixed $reference): array
    {
        $packages = array_values(array_filter((array) ($cell['packages'] ?? []), 'is_array'));
        $target = is_string($cell['target'] ?? null) ? $cell['target'] : null;
        $base = ['packages' => $packages, 'target' => $target, 'reference' => $reference === $language];

        if ($packages === []) {
            return $base + ['state' => 'none', 'full' => 0, 'total' => 0, 'notes' => ['No published package in this language.']];
        }
        if ($suites === []) {
            return $base + ['state' => 'no-suite', 'full' => 0, 'total' => 0, 'notes' => ['No cross-language suite covers this family yet, so agreement is not measured.']];
        }

        $full = 0;
        $portGaps = [];
        $elsewhere = [];
        $withheld = 0;
        foreach ($suites as $suite) {
            $entry = $suite['implementations'][$language] ?? ['status' => 'absent'];
            $status = $entry['status'] ?? 'absent';
            if ($status === 'full') {
                $full++;

                continue;
            }
            if (($suite['detail'] ?? false) !== true) {
                $withheld++;

                continue;
            }
            $cause = $status === 'absent' ? 'no-runner' : ($entry['cause'] ?? null);
            $line = $suite['id'].': '.(self::CAUSE_LABELS[$cause] ?? 'not full, cause not recorded');
            if (in_array($cause, self::PORT_GAP_CAUSES, true)) {
                $portGaps[] = $line;
            } else {
                $elsewhere[] = $line;
            }
        }

        $total = count($suites);
        $notes = [$full.' of '.$total.' suites full in this language.'];
        array_push($notes, ...$portGaps, ...$elsewhere);
        if ($withheld > 0) {
            $notes[] = $withheld.' security '.($withheld === 1 ? 'corpus is' : 'corpora are').' not full here; the detail is not published on this page.';
        }

        $state = match (true) {
            $portGaps !== [] => 'port-gap',
            $withheld > 0 => 'withheld',
            $elsewhere !== [] => 'elsewhere',
            $verified => 'agree',
            default => 'unverified',
        };
        if ($state === 'unverified') {
            $notes[] = 'Every suite is full, but the parity CI verdict or the data behind it is not current, so this is not shown as agreeing.';
        }

        return $base + ['state' => $state, 'full' => $full, 'total' => $total, 'notes' => $notes];
    }

    /** @return array<string, mixed> */
    private function ci(mixed $run, mixed $head, DateTimeImmutable $now): array
    {
        if (! is_array($run)) {
            return ['state' => 'unknown'];
        }
        $at = $this->time($run['created_at'] ?? null);
        $base = [
            'conclusion' => $run['conclusion'] ?? null,
            'at' => $at?->format(DATE_ATOM),
            'url' => $run['url'] ?? null,
            'head_sha' => is_string($run['head_sha'] ?? null) ? substr($run['head_sha'], 0, 7) : null,
            'matches_head' => is_string($head) && ($run['head_sha'] ?? null) === $head,
        ];
        $state = match (true) {
            ($run['conclusion'] ?? null) !== 'success' => 'failed',
            $at === null || $this->hours($at, $now) > self::CI_MAX_AGE_HOURS => 'stale',
            ! $base['matches_head'] => 'stale',
            default => 'ok',
        };

        return ['state' => $state] + $base;
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function providers(?array $section, bool $fresh): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        $languages = is_array($data['languages'] ?? null) ? $data['languages'] : [];

        $reference = null;
        foreach ($languages as $language) {
            if (is_array($language) && ($language['reference'] ?? false) === true) {
                $reference = $language;
            }
        }
        if (! is_array($reference)) {
            return ['feed' => $feed, 'capabilities' => [], 'providers' => [], 'languages' => []];
        }

        $capabilities = array_values(array_filter((array) $reference['capabilities'], 'is_string'));
        $providers = array_keys((array) $reference['providers']);
        $green = $feed['state'] === 'ok';

        $views = [];
        foreach (SnapshotBuilder::LANGUAGES as $code) {
            $language = $languages[$code] ?? null;
            if (! is_array($language)) {
                $views[$code] = ['package' => null, 'version' => null, 'score' => null, 'rows' => []];

                continue;
            }
            $rows = [];
            $have = 0;
            foreach ($providers as $provider) {
                $entry = $language['providers'][$provider] ?? null;
                $cells = [];
                foreach ($capabilities as $capability) {
                    $state = match (true) {
                        // A missing provider is a gap only where the reference has the capability.
                        ! is_array($entry) => in_array($capability, (array) ($reference['providers'][$provider]['implements'] ?? []), true) ? 'not-ported' : 'no',
                        ($entry['status'] ?? null) !== 'ok' => 'unknown',
                        in_array($capability, (array) $entry['implements'], true) => $green ? 'yes' : 'yes-unverified',
                        default => 'no',
                    };
                    if (str_starts_with($state, 'yes')) {
                        $have++;
                    }
                    $cells[$capability] = $state;
                }
                $rows[] = ['provider' => $provider, 'cells' => $cells];
            }
            $possible = 0;
            foreach ($providers as $provider) {
                $possible += count(array_intersect($capabilities, (array) ($reference['providers'][$provider]['implements'] ?? [])));
            }
            $views[$code] = [
                'package' => $language['package'] ?? null,
                'version' => $language['version'] ?? null,
                'tag' => $language['tag'] ?? null,
                'reference' => ($language['reference'] ?? false) === true,
                'providers' => count((array) $language['providers']),
                'score' => $possible === 0 ? null : ['value' => $have, 'of' => $possible],
                'rows' => $rows,
            ];
        }

        return ['feed' => $feed, 'capabilities' => $capabilities, 'providers' => $providers, 'languages' => $views];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function drift(?array $section, bool $fresh, DateTimeImmutable $now): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        if (! is_array($data)) {
            return ['feed' => $feed, 'items' => [], 'baseline' => ['state' => 'unknown'], 'score' => null];
        }

        $items = array_values(array_filter((array) ($data['items'] ?? []), 'is_array'));
        $committed = $this->time($data['baseline_committed_at'] ?? null);
        $newest = $items[0]['date'] ?? null;
        $baseline = ['state' => 'unknown', 'committed_at' => $committed?->format(DATE_ATOM), 'age_days' => null, 'max_age_days' => self::BASELINE_MAX_AGE_DAYS];
        if ($committed !== null) {
            $days = intdiv($this->hours($committed, $now), 24);
            $driftSince = is_string($newest) && $newest > $committed->format('Y-m-d');
            $baseline['age_days'] = $days;
            $baseline['drift_since_commit'] = $driftSince;
            $baseline['state'] = $days > self::BASELINE_MAX_AGE_DAYS && $driftSince ? 'stale' : ($feed['state'] === 'ok' ? 'ok' : 'stale');
        }

        $recent = array_values(array_filter($items, fn (array $i): bool => is_string($i['date'] ?? null) && $i['date'] >= $now->modify('-30 days')->format('Y-m-d')));
        $closed = count(array_filter($recent, fn (array $i): bool => ($i['state'] ?? null) === 'closed'));

        return [
            'feed' => $feed,
            'items' => $items,
            'baseline' => $baseline,
            'score' => $recent === [] ? null : ['value' => $closed, 'of' => count($recent)],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function mcp(?array $section, bool $fresh): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        $required = is_array($data['required'] ?? null) ? array_values(array_filter($data['required'], 'is_string')) : [];
        $rows = [];
        $served = 0;
        $needed = 0;
        foreach (is_array($data['implementations'] ?? null) ? $data['implementations'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $versions = is_array($row['versions'] ?? null) ? $row['versions'] : null;
            $cells = [];
            foreach ($required as $version) {
                $cells[$version] = $versions === null ? 'unknown' : (in_array($version, $versions, true) ? ($feed['state'] === 'ok' ? 'yes' : 'yes-unverified') : 'no');
            }
            if (($row['role'] ?? null) === 'server') {
                $needed += count($required);
                $served += count(array_filter($cells, fn (string $c): bool => str_starts_with($c, 'yes')));
            }
            $rows[] = ['package' => $row['package'] ?? null, 'language' => $row['language'] ?? null, 'version' => $row['version'] ?? null, 'role' => $row['role'] ?? null, 'cells' => $cells];
        }

        return [
            'feed' => $feed,
            'required' => $required,
            'rule' => $data['rule'] ?? SnapshotBuilder::MCP_VERSIONS_REQUIRED,
            'rows' => $rows,
            'score' => $needed === 0 ? null : ['value' => $served, 'of' => $needed],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $section
     * @return array<string, mixed>
     */
    private function acp(?array $section, bool $fresh): array
    {
        $feed = $this->feed($section, $fresh);
        $data = in_array($feed['state'], ['unknown', 'failed'], true) ? null : ($section['data'] ?? null);
        $stable = is_array($data['stable'] ?? null) ? $data['stable'] : null;
        $preview = is_array($data['preview'] ?? null) ? $data['preview'] : null;
        $rows = [];
        $ok = 0;
        foreach (is_array($data['implementations'] ?? null) ? $data['implementations'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $speaks = is_int($row['speaks'] ?? null) ? $row['speaks'] : null;
            $state = match (true) {
                $speaks === null || $stable === null => 'unknown',
                $speaks >= $stable['major'] => $feed['state'] === 'ok' ? 'yes' : 'yes-unverified',
                default => 'no',
            };
            if (str_starts_with($state, 'yes')) {
                $ok++;
            }
            $rows[] = [
                'package' => $row['package'] ?? null,
                'language' => $row['language'] ?? null,
                'version' => $row['version'] ?? null,
                'speaks' => $speaks,
                'stable' => $state,
                'preview' => $preview === null || $speaks === null ? null : ($speaks >= $preview['major'] ? 'yes' : 'no'),
            ];
        }

        return [
            'feed' => $feed,
            'stable' => $stable,
            'preview' => $preview,
            'rows' => $rows,
            'score' => $rows === [] ? null : ['value' => $ok, 'of' => count($rows)],
        ];
    }

    private function time(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function hours(DateTimeImmutable $then, DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $then->getTimestamp(), 3600));
    }
}
