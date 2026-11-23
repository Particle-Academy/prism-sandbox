<?php

declare(strict_types=1);

namespace App\Parity;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Reads the public sources and turns them into one snapshot for the dashboard.
 *
 * Every number on the page comes from here, and nothing here is typed in by
 * hand: the families, languages, providers, capabilities and versions are
 * whatever the published catalog and released source say on the day. Each
 * section records the addresses it read and whether each read worked, and a
 * section whose source could not be read says `failed` or `partial`. It never
 * comes back empty-and-ok, because an empty list reads as "nothing wrong".
 *
 * The catalog is prism-parity's manifest, the WHOLE manifest. Cards are never
 * built from what one checkout happens to install. A dashboard that did that
 * once missed a whole family.
 */
final class SnapshotBuilder
{
    public const SCHEMA = 1;

    public const LANGUAGES = ['php', 'ts', 'py'];

    /**
     * The standing rule for our MCP servers (Wish, 2026-11-14): serve the five
     * newest official MCP schema versions. prism-provider-watch checks the same
     * thing. This is the rule, not data, which is why it is a constant.
     */
    public const MCP_VERSIONS_REQUIRED = 5;

    private const PARITY_REPO = 'Particle-Academy/prism-parity';

    /** How each language declares a provider capability. A language convention, not data. */
    private const PROVIDER_METHODS = [
        'php' => '/public function (?<m>[a-zA-Z]+)\(/',
        'ts' => '/override\s+(?:async\s+)?\*?\s*(?<m>[a-zA-Z]+)\s*\(/',
        'py' => '/^\s+def (?<m>[a-z_]+)\(self/m',
    ];

    /** Method name => capability. `audio` is the entry point that dispatches to the two speech methods. */
    private const METHOD_CAPABILITIES = [
        'text' => 'text',
        'structured' => 'structured',
        'stream' => 'stream',
        'embeddings' => 'embeddings',
        'images' => 'images',
        'moderation' => 'moderation',
        'textToSpeech' => 'textToSpeech',
        'text_to_speech' => 'textToSpeech',
        'speechToText' => 'speechToText',
        'speech_to_text' => 'speechToText',
        'fim' => 'fim',
        'batch' => 'batch',
        'uploadFile' => 'files',
        'upload_file' => 'files',
    ];

    /** Where each language's ACP agent declares the protocol version it speaks. */
    private const ACP_VERSION_FILES = [
        'php' => ['src/Agent/AcpAgent.php', '/const PROTOCOL_VERSION = (\d+);/'],
        'ts' => ['src/acp/agent.ts', '/export const PROTOCOL_VERSION = (\d+);/'],
    ];

    public function __construct(private readonly PublicSources $sources) {}

    /** @return array<string, mixed> */
    public function build(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable;
        $at = $now->format(DateTimeInterface::ATOM);

        $catalog = $this->catalog($at);
        $manifest = $catalog['manifest'];
        unset($catalog['manifest']);

        return [
            'schema' => self::SCHEMA,
            'generated_at' => $at,
            'parity' => $this->parity($catalog, $manifest, $at),
            'providers' => $this->providers($manifest, $at),
            'drift' => $this->drift($at),
            'protocols' => $this->protocols($manifest, $at),
            'upstream' => (new UpstreamCollector($this->sources))->upstream($at),
            'upstream_prs' => (new UpstreamCollector($this->sources))->pulls($at),
        ];
    }

    /**
     * The catalog, pinned to one prism-parity commit so every file in the
     * parity section is read from the same tree.
     *
     * @return array{ref: string, head: array<string, mixed>|null, reads: list<array<string, mixed>>, manifest: array<string, mixed>|null}
     */
    private function catalog(string $at): array
    {
        $reads = [];
        $head = $this->sources->get(PublicSources::API.'/repos/'.self::PARITY_REPO.'/commits/main');
        $reads[] = $this->read('prism-parity main', $head);
        $sha = is_string($head['json']['sha'] ?? null) && preg_match('/^[0-9a-f]{40}$/', $head['json']['sha']) === 1 ? $head['json']['sha'] : null;
        $ref = $sha ?? 'main';

        $manifest = $this->sources->get(PublicSources::RAW.'/'.self::PARITY_REPO.'/'.$ref.'/manifest/packages.json');
        $reads[] = $this->read('Package catalog (manifest/packages.json)', $manifest);

        return [
            'ref' => $ref,
            'head' => $sha === null ? null : [
                'sha' => $sha,
                'date' => is_string($head['json']['commit']['committer']['date'] ?? null) ? $head['json']['commit']['committer']['date'] : null,
            ],
            'reads' => $reads,
            'manifest' => $manifest['ok'] && is_array($manifest['json']) && is_array($manifest['json']['packages'] ?? null) ? $manifest['json'] : null,
        ];
    }

    /**
     * @param  array{ref: string, head: array<string, mixed>|null, reads: list<array<string, mixed>>}  $catalog
     * @param  array<string, mixed>|null  $manifest
     * @return array<string, mixed>
     */
    private function parity(array $catalog, ?array $manifest, string $at): array
    {
        $reads = $catalog['reads'];
        if ($manifest === null) {
            return $this->section('failed', $at, $reads, null);
        }

        $ref = $catalog['ref'];
        $packages = $this->allowedPackages($manifest);

        $listing = $this->sources->get(PublicSources::API.'/repos/'.self::PARITY_REPO.'/contents/suites?ref='.rawurlencode($ref));
        $reads[] = $this->read('Suite list (suites/)', $listing);
        $suiteIds = [];
        foreach (is_array($listing['json']) && array_is_list($listing['json']) ? $listing['json'] : [] as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'dir' && is_string($item['name'] ?? null) && preg_match('/^[a-z0-9-]+$/', $item['name']) === 1) {
                $suiteIds[] = $item['name'];
            }
        }
        sort($suiteIds);

        $urls = [];
        foreach ($suiteIds as $id) {
            $urls[$id] = PublicSources::RAW.'/'.self::PARITY_REPO.'/'.$ref.'/suites/'.$id.'/manifest.json';
        }
        $fetched = $this->sources->getMany($urls);

        $ledger = $this->sources->get(PublicSources::RAW.'/'.self::PARITY_REPO.'/'.$ref.'/parity/ledger.json');

        $families = $this->familyIds($manifest, $packages);
        $sharedRunnerFamily = $this->sharedRunnerFamily($ledger['json'], $manifest);
        $suites = [];
        $unreadSuites = 0;
        foreach ($suiteIds as $id) {
            $result = $fetched[$id] ?? null;
            $suite = is_array($result['json'] ?? null) ? $result['json'] : null;
            if ($suite === null || ! is_array($suite['implementations'] ?? null)) {
                $unreadSuites++;

                continue;
            }
            $suites[] = $this->suite($id, $suite, $manifest, $families, $sharedRunnerFamily);
        }
        $suitesUrl = 'https://github.com/'.self::PARITY_REPO.'/tree/'.$ref.'/suites';
        if ($unreadSuites > 0) {
            $reads[] = ['label' => 'Suite manifests', 'url' => $suitesUrl, 'ok' => false, 'error' => $unreadSuites.' of '.count($suiteIds).' could not be read'];
        } elseif ($suiteIds !== []) {
            $reads[] = ['label' => 'Suite manifests', 'url' => $suitesUrl, 'ok' => true, 'error' => null];
        }

        $reads[] = $this->read('Parity ledger (parity/ledger.json)', $ledger);
        $detail = [];
        foreach ($suites as $suite) {
            $detail[$suite['id']] = $suite['detail'];
        }
        $gaps = [];
        foreach (is_array($ledger['json']['gaps'] ?? null) ? $ledger['json']['gaps'] : [] as $gap) {
            if (PublicAllowRule::allowsLedgerGap($gap, $detail)) {
                $gaps[] = [
                    'id' => $gap['id'],
                    'summary' => $gap['summary'],
                    'affects' => array_values(array_unique(array_map(fn (string $a): string => explode('/', $a, 2)[0], $gap['affects']))),
                ];
            }
        }

        $runs = $this->sources->get(PublicSources::API.'/repos/'.self::PARITY_REPO.'/actions/workflows/alignment.yml/runs?branch=main&status=completed&per_page=20');
        $reads[] = $this->read('Parity CI on main (alignment.yml, which calls the cross-check)', $runs);

        $complete = $listing['ok'] && $unreadSuites === 0 && $suiteIds !== [] && $ledger['ok'] && $catalog['head'] !== null;

        return $this->section($complete ? 'ok' : 'partial', $at, $reads, [
            'ref' => $ref,
            'head' => $catalog['head'],
            'families' => array_map(fn (string $family): array => $this->family($family, $manifest, $packages), $families),
            'suites' => $suites,
            'gaps' => $gaps,
            'ci' => $runs['ok'] ? $this->latestRun($runs['json']) : null,
        ]);
    }

    /**
     * Allowed packages, keyed by their manifest key.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, array<string, mixed>>
     */
    private function allowedPackages(array $manifest): array
    {
        $allowed = [];
        foreach ((array) $manifest['packages'] as $key => $package) {
            $repository = PublicAllowRule::packageRepository($package);
            if (is_string($key) && $repository !== null) {
                $allowed[$key] = $package + ['repository_name' => $repository];
            }
        }

        return $allowed;
    }

    /**
     * Every family the catalog targets or publishes, in the catalog's own order.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array<string, mixed>>  $packages
     * @return list<string>
     */
    private function familyIds(array $manifest, array $packages): array
    {
        $ids = [];
        foreach (array_keys((array) ($manifest['parity_target']['families'] ?? [])) as $family) {
            if (is_string($family) && ! str_starts_with($family, '$')) {
                $ids[$family] = true;
            }
        }
        foreach ($packages as $package) {
            $ids[$package['family']] = true;
        }

        return array_keys($ids);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array<string, mixed>>  $packages
     * @return array<string, mixed>
     */
    private function family(string $family, array $manifest, array $packages): array
    {
        $referenceKey = $manifest['family_references'][$family] ?? null;
        $reference = is_string($referenceKey) ? ($packages[$referenceKey] ?? null) : null;
        $targets = (array) ($manifest['parity_target']['families'][$family]['implementations'] ?? []);

        $cells = [];
        foreach (self::LANGUAGES as $language) {
            $mine = array_values(array_filter($packages, fn (array $p): bool => $p['family'] === $family && $p['language'] === $language));
            $target = is_array($targets[$language] ?? null) && is_string($targets[$language]['status'] ?? null) ? $targets[$language]['status'] : null;
            $cells[$language] = [
                'packages' => array_map(fn (array $p): array => [
                    'name' => $p['name'],
                    'version' => is_string($p['version'] ?? null) ? $p['version'] : null,
                    'registry' => is_string($p['registry'] ?? null) ? $p['registry'] : null,
                    'repository' => $p['repository_name'],
                ], $mine),
                'target' => $target,
            ];
        }

        return [
            'id' => $family,
            'reference' => is_array($reference) ? $reference['language'] : null,
            'cells' => $cells,
        ];
    }

    /**
     * @param  array<string, mixed>  $suite
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $families
     * @return array<string, mixed>
     */
    private function suite(string $id, array $suite, array $manifest, array $families, ?string $sharedRunnerFamily): array
    {
        $detail = PublicAllowRule::allowsSuiteDetail($suite + ['id' => $id]);
        $implementations = [];
        foreach (self::LANGUAGES as $language) {
            $entry = $suite['implementations'][$language] ?? null;
            if (! is_array($entry)) {
                $implementations[$language] = ['status' => 'absent', 'cause' => null];

                continue;
            }
            $full = ($entry['status'] ?? null) === 'full';
            $implementations[$language] = [
                'status' => $full ? 'full' : 'not-full',
                // WHY a row is not full is shown only where the suite's detail is allowed.
                'cause' => ! $full && $detail && is_string($entry['cause'] ?? null) ? $entry['cause'] : null,
            ];
        }

        return [
            'id' => $id,
            'title' => is_string($suite['title'] ?? null) && ! PublicAllowRule::hasPrivateMarker($suite['title']) ? $suite['title'] : null,
            'kind' => is_string($suite['kind'] ?? null) ? $suite['kind'] : null,
            'reference' => is_string($suite['reference'] ?? null) ? $suite['reference'] : null,
            'family' => $this->suiteFamily($id, $suite, $manifest, $families, $sharedRunnerFamily),
            'detail' => $detail,
            'implementations' => $implementations,
        ];
    }

    /**
     * The family the ledger's cross-checked runners belong to, when they all
     * belong to one. Those runners (prism-parity's runners/ plus the ports'
     * conformance runners) drive the core packages. Reading it from the ledger
     * rather than assuming it keeps a renamed runner from silently moving
     * suites between families.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function sharedRunnerFamily(mixed $ledger, array $manifest): ?string
    {
        $found = [];
        foreach (is_array($ledger['cross_checked'] ?? null) ? $ledger['cross_checked'] : [] as $entry) {
            $runner = is_array($entry) && is_string($entry['runner'] ?? null) ? $entry['runner'] : null;
            if ($runner !== null && preg_match('#^[.][.]/([A-Za-z0-9._-]+)/#', $runner, $m) === 1) {
                $package = $manifest['packages'][$m[1]] ?? null;
                if (PublicAllowRule::allowsPackage($package)) {
                    $found[$package['family']] = true;
                }
            }
        }

        return count($found) === 1 ? (string) array_key_first($found) : null;
    }

    /**
     * Which family a suite belongs to, from the repositories its runners live in.
     *
     * @param  array<string, mixed>  $suite
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $families
     */
    private function suiteFamily(string $id, array $suite, array $manifest, array $families, ?string $sharedRunnerFamily): string
    {
        $shared = false;
        foreach (self::LANGUAGES as $language) {
            $runner = $suite['implementations'][$language]['runner'] ?? null;
            if (! is_string($runner) || ! str_contains($runner, ':')) {
                continue;
            }
            [$repository, $path] = explode(':', $runner, 2);
            $shared = $shared || ($repository === 'prism-parity' && str_starts_with($path, 'runners/'));
            $package = $manifest['packages'][$repository] ?? null;
            if ($repository !== 'prism-parity' && PublicAllowRule::allowsPackage($package) && in_array($package['family'], $families, true)) {
                return $package['family'];
            }
        }

        // Run by the shared conformance runners the ledger cross-checks: their family.
        if ($shared && $sharedRunnerFamily !== null) {
            return $sharedRunnerFamily;
        }

        // A suite run only by prism-parity's own tooling: fall back to its name.
        $best = null;
        foreach ($families as $family) {
            if (str_starts_with($id, $family.'-') && ($best === null || strlen($family) > strlen($best))) {
                $best = $family;
            }
        }

        return $best ?? 'cross-family';
    }

    /**
     * The newest completed run on main that main itself triggered. Selected by
     * branch and event, never "the latest run", because the latest run can be
     * a different subject's verdict.
     *
     * @return array<string, mixed>|null
     */
    private function latestRun(mixed $json): ?array
    {
        $best = null;
        foreach (is_array($json['workflow_runs'] ?? null) ? $json['workflow_runs'] : [] as $run) {
            if (! is_array($run) || ($run['head_branch'] ?? null) !== 'main' || ! in_array($run['event'] ?? null, ['push', 'schedule'], true)
                || ($run['status'] ?? null) !== 'completed' || ! is_string($run['created_at'] ?? null)) {
                continue;
            }
            if ($best === null || strcmp($run['created_at'], $best['created_at']) > 0) {
                $best = $run;
            }
        }

        if ($best === null) {
            return null;
        }

        $url = is_string($best['html_url'] ?? null) && str_starts_with($best['html_url'], 'https://github.com/'.self::PARITY_REPO.'/actions/runs/') ? $best['html_url'] : null;

        return [
            'conclusion' => is_string($best['conclusion'] ?? null) ? $best['conclusion'] : null,
            'head_sha' => is_string($best['head_sha'] ?? null) ? $best['head_sha'] : null,
            'created_at' => $best['created_at'],
            'event' => $best['event'],
            'url' => $url,
        ];
    }

    /**
     * Provider x capability, per language, read from each core package's
     * RELEASED source: the tag of the version the catalog says is published.
     * Main can hold work that has not shipped, and this page shows what a
     * consumer can install.
     *
     * @param  array<string, mixed>|null  $manifest
     * @return array<string, mixed>
     */
    private function providers(?array $manifest, string $at): array
    {
        if ($manifest === null) {
            return $this->section('failed', $at, [['label' => 'Package catalog', 'url' => PublicSources::RAW.'/'.self::PARITY_REPO.'/main/manifest/packages.json', 'ok' => false, 'error' => 'catalog unreadable']], null);
        }

        $packages = array_filter($this->allowedPackages($manifest), fn (array $p): bool => $p['family'] === 'core');
        $referenceKey = $manifest['family_references']['core'] ?? null;
        $reads = [];
        $languages = [];
        $complete = true;

        foreach (self::LANGUAGES as $language) {
            $key = array_key_first(array_filter($packages, fn (array $p): bool => $p['language'] === $language));
            if ($key === null) {
                $languages[$language] = null;

                continue;
            }
            $package = $packages[$key];
            $result = $this->providerSurface($language, $package, $reads);
            $complete = $complete && $result['complete'];
            unset($result['complete']);
            $languages[$language] = $result + ['reference' => $key === $referenceKey];
        }

        return $this->section($complete ? 'ok' : 'partial', $at, $reads, ['languages' => $languages]);
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  list<array<string, mixed>>  $reads
     * @return array<string, mixed>
     */
    private function providerSurface(string $language, array $package, array &$reads): array
    {
        $repository = 'Particle-Academy/'.$package['repository_name'];
        $version = is_string($package['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/', $package['version']) === 1 ? $package['version'] : null;
        $declaredProviders = array_values(array_filter((array) ($package['surface']['providers'] ?? []), fn ($p): bool => is_string($p) && preg_match('/^[a-z0-9]+$/', $p) === 1));
        $capabilities = array_values(array_filter((array) ($package['surface']['capabilities'] ?? []), 'is_string'));
        $path = is_string($package['surface_paths']['providers'] ?? null) && preg_match('#^[A-Za-z0-9_/-]+$#', $package['surface_paths']['providers']) === 1 ? $package['surface_paths']['providers'] : null;

        $base = ['package' => $package['name'], 'version' => $version, 'tag' => $version === null ? null : 'v'.$version, 'capabilities' => $capabilities, 'providers' => []];
        if ($version === null || $path === null) {
            $reads[] = ['label' => $package['name'].' providers', 'url' => 'https://github.com/'.$repository, 'ok' => false, 'error' => 'no released version or provider path in the catalog'];

            return $base + ['complete' => false];
        }
        $tag = 'v'.$version;

        $files = [];
        if ($language === 'php') {
            // PHP directories are StudlyCase; the catalog names providers in lowercase.
            $listing = $this->sources->get(PublicSources::API.'/repos/'.$repository.'/contents/'.$path.'?ref='.rawurlencode($tag));
            $reads[] = $this->read($package['name'].' '.$tag.' provider directories', $listing);
            $dirs = [];
            foreach (is_array($listing['json']) && array_is_list($listing['json']) ? $listing['json'] : [] as $item) {
                if (is_array($item) && ($item['type'] ?? null) === 'dir' && is_string($item['name'] ?? null) && preg_match('/^[A-Za-z0-9]+$/', $item['name']) === 1) {
                    $dirs[strtolower($item['name'])] = $item['name'];
                }
            }
            foreach ($declaredProviders as $provider) {
                if (isset($dirs[$provider])) {
                    $files[$provider] = $path.'/'.$dirs[$provider].'/'.$dirs[$provider].'.php';
                }
            }
        } else {
            foreach ($declaredProviders as $provider) {
                $files[$provider] = $language === 'ts' ? "{$path}/{$provider}/{$provider}.ts" : "{$path}/{$provider}/provider.py";
            }
        }

        $fetched = $this->sources->getMany(array_map(fn (string $file): string => PublicSources::RAW.'/'.$repository.'/'.$tag.'/'.$file, $files));
        $providers = [];
        $failed = 0;
        foreach ($declaredProviders as $provider) {
            $result = $fetched[$provider] ?? null;
            if (! is_array($result) || ! $result['ok'] || ! is_string($result['body'])) {
                $failed++;
                $providers[$provider] = ['status' => 'unknown', 'implements' => []];

                continue;
            }
            preg_match_all(self::PROVIDER_METHODS[$language], $result['body'], $m);
            $implements = [];
            foreach ($m['m'] as $method) {
                if (isset(self::METHOD_CAPABILITIES[$method])) {
                    $implements[self::METHOD_CAPABILITIES[$method]] = true;
                }
            }
            if (isset($implements['textToSpeech']) || isset($implements['speechToText'])) {
                $implements['audio'] = true;
            }
            $providers[$provider] = ['status' => 'ok', 'implements' => array_keys($implements)];
        }
        $reads[] = [
            'label' => $package['name'].' '.$tag.' provider classes',
            'url' => 'https://github.com/'.$repository.'/tree/'.$tag.'/'.$path,
            'ok' => $failed === 0,
            'error' => $failed === 0 ? null : $failed.' of '.count($declaredProviders).' provider files could not be read',
        ];

        return ['providers' => $providers, 'complete' => $failed === 0 && (! isset($listing) || $listing['ok'])] + $base;
    }

    /**
     * Provider API drift, as prism-provider-watch publishes it: one issue per
     * day with drift, filed on Particle-Academy/prism, plus the date its
     * committed baseline last advanced (the watcher reviews against that).
     *
     * @return array<string, mixed>
     */
    private function drift(string $at): array
    {
        $issues = $this->sources->get(PublicSources::API.'/repos/Particle-Academy/prism/issues?labels=provider-watch&state=all&sort=created&direction=desc&per_page=30');
        $baseline = $this->sources->get(PublicSources::API.'/repos/Particle-Academy/prism-provider-watch/commits?path=state&per_page=1');
        $reads = [$this->read('Drift issues (label provider-watch)', $issues), $this->read('Watcher baseline commits (state/)', $baseline)];

        if (! $issues['ok']) {
            return $this->section('failed', $at, $reads, null);
        }

        $items = [];
        foreach (is_array($issues['json']) && array_is_list($issues['json']) ? $issues['json'] : [] as $issue) {
            $allowed = PublicAllowRule::driftIssue($issue);
            if ($allowed !== null) {
                $items[] = $allowed;
            }
        }
        usort($items, fn (array $a, array $b): int => $b['number'] <=> $a['number']);

        $commit = is_array($baseline['json']) && array_is_list($baseline['json']) ? ($baseline['json'][0] ?? null) : null;
        $committedAt = is_array($commit) && is_string($commit['commit']['committer']['date'] ?? null) ? $commit['commit']['committer']['date'] : null;

        return $this->section($baseline['ok'] && $committedAt !== null ? 'ok' : 'partial', $at, $reads, [
            'items' => $items,
            'baseline_committed_at' => $committedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $manifest
     * @return array<string, mixed>
     */
    private function protocols(?array $manifest, string $at): array
    {
        $packages = $manifest === null ? [] : $this->allowedPackages($manifest);

        return [
            'mcp' => $this->mcp($packages, $at, $manifest !== null),
            'acp' => $this->acp($packages, $at, $manifest !== null),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $packages
     * @return array<string, mixed>
     */
    private function mcp(array $packages, string $at, bool $catalogRead): array
    {
        $schema = $this->sources->get(PublicSources::API.'/repos/modelcontextprotocol/modelcontextprotocol/contents/schema');
        $reads = [$this->read('Official MCP schema versions', $schema)];

        $official = [];
        foreach (is_array($schema['json']) && array_is_list($schema['json']) ? $schema['json'] : [] as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'dir' && is_string($item['name'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['name']) === 1) {
                $official[] = $item['name'];
            }
        }
        rsort($official, SORT_STRING);
        $required = array_slice($official, 0, self::MCP_VERSIONS_REQUIRED);

        $servers = array_filter($packages, fn (array $p): bool => $p['family'] === 'mcp-server');
        $urls = [];
        foreach ($servers as $key => $server) {
            if (is_string($server['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/', $server['version']) === 1) {
                $urls[$key] = PublicSources::RAW.'/Particle-Academy/'.$server['repository_name'].'/v'.$server['version'].'/protocol-versions.json';
            }
        }
        $fetched = $this->sources->getMany($urls);
        $rows = [];
        $complete = $schema['ok'] && $required !== [] && $catalogRead;
        foreach ($servers as $key => $server) {
            $result = $fetched[$key] ?? null;
            $versions = is_array($result) && $result['ok'] && is_array($result['json']['versions'] ?? null) && array_is_list($result['json']['versions'])
                ? array_values(array_filter($result['json']['versions'], fn ($v): bool => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1))
                : null;
            $complete = $complete && $versions !== null;
            $reads[] = $this->read($server['name'].' protocol-versions.json', $result ?? ['ok' => false, 'url' => 'https://github.com/Particle-Academy/'.$server['repository_name'], 'error' => 'no released version']);
            $rows[] = [
                'package' => $server['name'],
                'language' => $server['language'],
                'version' => is_string($server['version'] ?? null) ? $server['version'] : null,
                'role' => 'server',
                'versions' => $versions,
            ];
        }
        foreach (array_filter($packages, fn (array $p): bool => $p['family'] === 'mcp') as $client) {
            $rows[] = [
                'package' => $client['name'],
                'language' => $client['language'],
                'version' => is_string($client['version'] ?? null) ? $client['version'] : null,
                'role' => 'client',
                // Only the catalog's declared protocol: clients publish no version list to check.
                'versions' => is_string($client['protocol'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $client['protocol']) === 1 ? [$client['protocol']] : null,
            ];
        }

        return $this->section($complete ? 'ok' : ($schema['ok'] ? 'partial' : 'failed'), $at, $reads, [
            'required' => $required,
            'rule' => self::MCP_VERSIONS_REQUIRED,
            'implementations' => $rows,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $packages
     * @return array<string, mixed>
     */
    private function acp(array $packages, string $at, bool $catalogRead): array
    {
        $releases = $this->sources->get(PublicSources::API.'/repos/agentclientprotocol/agent-client-protocol/releases?per_page=50');
        $reads = [$this->read('ACP schema releases', $releases)];

        $stable = null;
        $preview = null;
        foreach (is_array($releases['json']) && array_is_list($releases['json']) ? $releases['json'] : [] as $release) {
            if (! is_array($release) || ! is_string($release['tag_name'] ?? null)
                || preg_match('/^schema-v(\d+)\.(\d+)\.(\d+)(-[0-9A-Za-z.]+)?$/', $release['tag_name'], $m) !== 1
                || ($release['draft'] ?? false) === true) {
                continue;
            }
            $isPreview = ($release['prerelease'] ?? false) === true || ($m[4] ?? '') !== '';
            $entry = ['major' => (int) $m[1], 'tag' => $release['tag_name'], 'semver' => [(int) $m[1], (int) $m[2], (int) $m[3]]];
            if ($isPreview) {
                if ($preview === null || $entry['semver'] > $preview['semver']) {
                    $preview = $entry;
                }
            } elseif ($stable === null || $entry['semver'] > $stable['semver']) {
                $stable = $entry;
            }
        }
        if ($preview !== null && $stable !== null && $preview['major'] <= $stable['major']) {
            $preview = null;
        }

        $agents = array_filter($packages, fn (array $p): bool => $p['family'] === 'acp' && isset(self::ACP_VERSION_FILES[$p['language']]));
        $urls = [];
        foreach ($agents as $key => $agent) {
            if (is_string($agent['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/', $agent['version']) === 1) {
                $urls[$key] = PublicSources::RAW.'/Particle-Academy/'.$agent['repository_name'].'/v'.$agent['version'].'/'.self::ACP_VERSION_FILES[$agent['language']][0];
            }
        }
        $fetched = $this->sources->getMany($urls);
        $rows = [];
        $complete = $releases['ok'] && $stable !== null && $catalogRead;
        foreach ($agents as $key => $agent) {
            $result = $fetched[$key] ?? null;
            $speaks = is_array($result) && $result['ok'] && is_string($result['body']) && preg_match(self::ACP_VERSION_FILES[$agent['language']][1], $result['body'], $m) === 1 ? (int) $m[1] : null;
            $complete = $complete && $speaks !== null;
            $reads[] = $this->read($agent['name'].' protocol version (released source)', $result ?? ['ok' => false, 'url' => 'https://github.com/Particle-Academy/'.$agent['repository_name'], 'error' => 'no released version']);
            $rows[] = [
                'package' => $agent['name'],
                'language' => $agent['language'],
                'version' => is_string($agent['version'] ?? null) ? $agent['version'] : null,
                'speaks' => $speaks,
            ];
        }

        $strip = fn (?array $e): ?array => $e === null ? null : ['major' => $e['major'], 'tag' => $e['tag']];

        return $this->section($complete ? 'ok' : ($releases['ok'] ? 'partial' : 'failed'), $at, $reads, [
            'stable' => $strip($stable),
            'preview' => $strip($preview),
            'implementations' => $rows,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $reads
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private function section(string $status, string $at, array $reads, ?array $data): array
    {
        return ['status' => $status, 'fetched_at' => $at, 'last_ok_at' => $status === 'ok' ? $at : null, 'reads' => $reads, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{label: string, url: string, ok: bool, error: string|null}
     */
    private function read(string $label, array $result): array
    {
        return ['label' => $label, 'url' => (string) ($result['url'] ?? ''), 'ok' => (bool) ($result['ok'] ?? false), 'error' => is_string($result['error'] ?? null) ? $result['error'] : null];
    }
}
