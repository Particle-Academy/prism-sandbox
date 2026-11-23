<?php

declare(strict_types=1);

namespace App\Parity;

/**
 * What the public parity dashboard is ALLOWED to show.
 *
 * This is an allow rule, not a deny list: a record is shown only when it
 * positively meets every condition below. Anything that does not, including
 * anything this code has never seen before, is left out. The page is public,
 * so a wrong guess has to fall towards showing less.
 *
 * What is never read at all: the envelope's private gap register. The sources
 * are public repositories only (see PublicSources), and even within them:
 *
 *  - a package must be catalogued `public` AND published to a registry;
 *  - a suite's gap DETAIL is shown only for suites that are not security
 *    corpora, because an open row on a security corpus can describe a
 *    weakness that is not fixed yet;
 *  - a ledger gap must carry a plain G-/F- id and touch only suites whose
 *    detail is shown;
 *  - a drift issue is shown as the numbers in its machine-written title, never
 *    its body, and only when the title has the exact generated shape;
 *  - and any record carrying a private marker anywhere inside it is out.
 */
final class PublicAllowRule
{
    /** Keys whose truthy presence marks a record as not for publication. */
    private const PRIVATE_KEYS = ['private', 'embargo', 'embargoed', 'confidential', 'security_advisory', 'advisory'];

    /**
     * Text that marks a record as not for publication wherever it appears.
     *
     * Explicit markers only. The bare word "private" is NOT one: the catalog
     * names a browser feature `private-address-refused`, and treating that as
     * a marker silently dropped two published packages from the board, the
     * same way a dashboard built from one workspace's installs once missed a
     * whole family. A record that is private says so structurally (catalog,
     * a `private` key) or with one of these phrases.
     */
    private const PRIVATE_TEXT = '/no public detail|\[private\]|\bprivate (?:advisory|item|issue|note|detail|gap)s?\b|\bembargo(?:ed)?\b|\bconfidential\b|\bGHSA-[a-z0-9]{4}-/i';

    /** The repository name becomes a URL path segment, so it may not start with a dot (no `..`). */
    private const GITHUB_REPOSITORY = '#^https://github\.com/Particle-Academy/([A-Za-z0-9][A-Za-z0-9._-]*)$#';

    /** The shapes prism-provider-watch writes as an issue title. Anything else is not its output. */
    private const DRIFT_TITLES = [
        'contract' => '/^Provider drift: (?<contract>\d+) contract change\(s\)(?: \(\+(?<catalogue>\d+) catalogue\))? — (?<date>\d{4}-\d{2}-\d{2})$/u',
        'catalogue' => '/^Provider drift: (?<catalogue>\d+) catalogue change\(s\), no contract changes — (?<date>\d{4}-\d{2}-\d{2})$/u',
        'unclassified' => '/^Provider drift: (?<total>\d+) change\(s\) — (?<date>\d{4}-\d{2}-\d{2})$/u',
    ];

    public static function hasPrivateMarker(mixed $value): bool
    {
        if (is_string($value)) {
            return preg_match(self::PRIVATE_TEXT, $value) === 1;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $lower = strtolower($key);
                if (in_array($lower, self::PRIVATE_KEYS, true) && $item !== false && $item !== null) {
                    return true;
                }
                if (in_array($lower, ['visibility', 'catalog'], true) && $item !== 'public') {
                    return true;
                }
            }
            if (self::hasPrivateMarker($item)) {
                return true;
            }
        }

        return false;
    }

    /** The repository name for an allowed package, or null when the package may not be shown. */
    public static function packageRepository(mixed $package): ?string
    {
        if (! is_array($package)
            || ($package['catalog'] ?? null) !== 'public'
            || ($package['published'] ?? null) !== true
            || ! is_string($package['name'] ?? null)
            || ! is_string($package['language'] ?? null)
            || ! is_string($package['family'] ?? null)
            || ! is_string($package['repository'] ?? null)
            || preg_match(self::GITHUB_REPOSITORY, $package['repository'], $m) !== 1
            || self::hasPrivateMarker($package)) {
            return null;
        }

        return $m[1];
    }

    public static function allowsPackage(mixed $package): bool
    {
        return self::packageRepository($package) !== null;
    }

    /** Whether WHY a suite is not full may be shown, as opposed to only that it is not. */
    public static function allowsSuiteDetail(mixed $suite): bool
    {
        return is_array($suite)
            && is_string($suite['id'] ?? null)
            && is_string($suite['kind'] ?? null)
            && $suite['kind'] !== 'security-corpus'
            && ! self::hasPrivateMarker($suite);
    }

    /**
     * @param  array<string, bool>  $detailSuites  suite id => whether its detail is allowed
     */
    public static function allowsLedgerGap(mixed $gap, array $detailSuites): bool
    {
        if (! is_array($gap)
            || ! is_string($gap['id'] ?? null)
            || preg_match('/^[GF]-\d{1,4}$/', $gap['id']) !== 1
            || ! is_string($gap['summary'] ?? null)
            || ! is_array($gap['affects'] ?? null)
            || $gap['affects'] === []
            || self::hasPrivateMarker($gap)) {
            return false;
        }

        foreach ($gap['affects'] as $affected) {
            $suite = is_string($affected) ? explode('/', $affected, 2)[0] : null;
            if ($suite === null || ($detailSuites[$suite] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * The publishable fields of a drift issue, or null when it may not be shown.
     *
     * @return array{number: int, url: string, date: string, severity: string, contract: int|null, catalogue: int|null, state: string, closed_at: string|null}|null
     */
    public static function driftIssue(mixed $issue): ?array
    {
        if (! is_array($issue)
            || array_key_exists('pull_request', $issue)
            || ! is_int($issue['number'] ?? null)
            || ! is_string($issue['title'] ?? null)
            || ! in_array($issue['state'] ?? null, ['open', 'closed'], true)
            || ($issue['html_url'] ?? null) !== 'https://github.com/Particle-Academy/prism/issues/'.$issue['number']) {
            return null;
        }

        $labels = array_map(fn ($l): mixed => is_array($l) ? ($l['name'] ?? null) : $l, (array) ($issue['labels'] ?? []));
        if (! in_array('provider-watch', $labels, true)) {
            return null;
        }

        foreach (self::DRIFT_TITLES as $severity => $pattern) {
            if (preg_match($pattern, $issue['title'], $m) === 1) {
                return [
                    'number' => $issue['number'],
                    'url' => $issue['html_url'],
                    'date' => $m['date'],
                    'severity' => $severity,
                    'contract' => isset($m['contract']) && $m['contract'] !== '' ? (int) $m['contract'] : ($severity === 'catalogue' ? 0 : null),
                    'catalogue' => isset($m['catalogue']) && $m['catalogue'] !== '' ? (int) $m['catalogue'] : ($severity === 'contract' ? 0 : null),
                    'state' => $issue['state'],
                    'closed_at' => is_string($issue['closed_at'] ?? null) ? $issue['closed_at'] : null,
                ];
            }
        }

        return null;
    }
}
