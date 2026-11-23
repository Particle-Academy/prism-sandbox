<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Parity\PublicAllowRule;
use App\Parity\SnapshotBuilder;
use App\Parity\SnapshotStore;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public parity dashboard (/parity).
 *
 * Two properties carry the weight, so most of this file is about them:
 *
 *  1. NOTHING PRIVATE REACHES THE PAGE. The fixture below deliberately mixes
 *     a hidden package, a record marked "NO public detail", a private gap, an
 *     open row on a security corpus with its cause, a pull request carrying the
 *     drift label and an issue with a hand-written title. Every one of those has
 *     a unique canary string, and the test asserts that no canary appears
 *     anywhere in the props the page receives or the HTML it renders.
 *  2. NOTHING STALE OR UNREAD IS DRAWN GREEN. A missing snapshot, a failed
 *     source and an old snapshot are each shown as unknown or stale.
 */
class ParityDashboardTest extends TestCase
{
    private const NOW = '2026-11-23T12:00:00Z';

    private const HEAD = '42566c0a4d4378ca8595d7c9029a0ec38c1c0633';

    /** Every string that must never reach the page. */
    private const CANARIES = [
        'CANARY-HIDDEN-PACKAGE',
        'CANARY-NO-PUBLIC-DETAIL',
        'CANARY-PRIVATE-GAP',
        'CANARY-SECURITY-GAP',
        'CANARY-SECURITY-CAUSE',
        'CANARY-PR-TITLE',
        'CANARY-FREEFORM-TITLE',
        'CANARY-ISSUE-BODY',
        'G-999',
        'G-998',
        'G-997',
        // Upstream: a security PR, a security-labelled PR, a PR with a private
        // marker, an issue in the PR search, a PR in another repository, a draft
        // release and the text of release notes and PR bodies.
        'CANARY-SECURITY-PR',
        'CANARY-LABELLED-PR',
        'CANARY-PRIVATE-PR',
        'CANARY-ISSUE-IN-SEARCH',
        'CANARY-OTHER-REPO',
        'CANARY-PR-BODY',
        'CANARY-RELEASE-NOTES',
        'v9.9.9',
    ];

    private string $snapshotPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotPath = sys_get_temp_dir().'/parity-dashboard-test-'.bin2hex(random_bytes(6)).'.json';
        $this->app->instance(SnapshotStore::class, new SnapshotStore($this->snapshotPath));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->snapshotPath);

        parent::tearDown();
    }

    public function test_the_allow_rule_admits_only_public_published_unmarked_records(): void
    {
        $public = ['name' => 'a', 'language' => 'php', 'family' => 'core', 'repository' => 'https://github.com/Particle-Academy/prism', 'catalog' => 'public', 'published' => true];

        $this->assertTrue(PublicAllowRule::allowsPackage($public));
        $this->assertFalse(PublicAllowRule::allowsPackage(['catalog' => 'hidden'] + $public));
        $this->assertFalse(PublicAllowRule::allowsPackage(['published' => false] + $public));
        $this->assertFalse(PublicAllowRule::allowsPackage(['note' => 'NO public detail until fixed'] + $public));
        $this->assertFalse(PublicAllowRule::allowsPackage(['private' => true] + $public));
        $this->assertFalse(PublicAllowRule::allowsPackage(['repository' => 'https://github.com/someone-else/prism'] + $public));
        $this->assertFalse(PublicAllowRule::allowsPackage(['repository' => 'https://github.com/Particle-Academy/..'] + $public), 'A repository name is a URL path segment.');
        $this->assertFalse(PublicAllowRule::allowsPackage(['catalog' => null] + $public), 'An absent catalog field is not public.');
        $this->assertFalse(PublicAllowRule::allowsPackage(['notes' => ['Tracked as a private advisory.']] + $public));
        // A feature that merely contains the word is not a marker: this exact
        // name once hid both browser ports from the board.
        $this->assertTrue(PublicAllowRule::allowsPackage(['surface' => ['features' => ['private-address-refused']]] + $public));

        $this->assertFalse(PublicAllowRule::allowsSuiteDetail(['id' => 's', 'kind' => 'security-corpus']));
        $this->assertTrue(PublicAllowRule::allowsSuiteDetail(['id' => 's', 'kind' => 'response-parse']));
        $this->assertFalse(PublicAllowRule::allowsSuiteDetail(['id' => 's']), 'A suite of unknown kind does not show its detail.');

        $gap = ['id' => 'G-1', 'summary' => 'x', 'affects' => ['open-suite/row-1']];
        $this->assertTrue(PublicAllowRule::allowsLedgerGap($gap, ['open-suite' => true]));
        $this->assertFalse(PublicAllowRule::allowsLedgerGap($gap, ['open-suite' => false]));
        $this->assertFalse(PublicAllowRule::allowsLedgerGap($gap, []), 'A gap on a suite this page does not know is out.');
        $this->assertFalse(PublicAllowRule::allowsLedgerGap(['id' => 'GHSA-1'] + $gap, ['open-suite' => true]));
    }

    public function test_no_private_item_from_the_fixture_reaches_the_page(): void
    {
        $this->fakeSources();
        $this->collect();

        $response = $this->get('/parity')->assertOk();
        $props = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html = (string) $response->getContent();

        foreach (self::CANARIES as $canary) {
            $this->assertStringNotContainsString($canary, $props, "{$canary} reached the page props.");
            $this->assertStringNotContainsString($canary, $html, "{$canary} reached the rendered page.");
        }

        // The filter is not just dropping everything: the public items are there.
        $this->assertStringContainsString('G-125', $props);
        $this->assertStringContainsString('particle-academy/prism-browser', $props);
        $this->assertStringContainsString('"number":162', $props);
        // The hidden family is not a row at all.
        $this->assertStringNotContainsString('secret-family', $props);
        // Our public upstream PRs are there.
        $this->assertStringContainsString('"number":1045', $props);
        $this->assertStringContainsString('"number":1047', $props);
    }

    public function test_upstream_drift_and_our_pull_requests_are_read_from_public_sources(): void
    {
        $this->fakeSources();
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.upstream.feed.state', 'ok')
            ->where('dashboard.upstream.green', true)
            ->where('dashboard.upstream.ahead', 296)
            ->where('dashboard.upstream.behind', 3)
            ->where('dashboard.upstream.merge_base.sha', '385022b')
            ->where('dashboard.upstream.upstream_release.tag', 'v0.104.0')
            ->where('dashboard.upstream.fork_release.tag', 'v0.152.0')
            ->where('dashboard.upstream.releases_since', [
                ['tag' => 'v0.104.0', 'published_at' => '2026-11-20T10:00:00Z'],
                ['tag' => 'v0.103.0', 'published_at' => '2026-11-07T23:33:27Z'],
            ])
            ->where('dashboard.upstream.opened_since', ['issues' => 7, 'pulls' => 4])
            ->where('dashboard.upstream.watched_open.pulls', ['count' => 2, 'lower_bound' => false])
            ->where('dashboard.upstream_prs.feed.state', 'ok')
            ->where('dashboard.upstream_prs.counts', ['open' => 1, 'merged' => 1, 'closed' => 0])
            ->where('dashboard.upstream_prs.pulls.0.number', 1047)
            ->where('dashboard.upstream_prs.pulls.1.number', 1045)
            ->where('dashboard.upstream_prs.pulls.1.review', 'changes-requested')
            ->where('dashboard.upstream_prs.pulls.1.ci', 'awaiting-approval')
            ->where('dashboard.upstream_prs.pulls.1.stale', true)
            ->where('dashboard.upstream_prs.pulls.1.age_days', 32));
    }

    public function test_one_unreadable_author_search_makes_the_pull_request_card_unknown_not_short(): void
    {
        config(['parity.upstream.pr_authors' => ['wishborn', 'second-author']]);
        $this->fakeSources();
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.upstream_prs.feed.state', 'failed')
            ->where('dashboard.upstream_prs.counts', null));
    }

    public function test_a_security_corpus_that_is_not_full_shows_as_withheld_without_its_cause(): void
    {
        $this->fakeSources();
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->component('Parity')
            ->where('dashboard.parity.families.1.id', 'browser')
            ->where('dashboard.parity.families.1.cells.ts.state', 'withheld')
            ->where('dashboard.parity.families.1.cells.php.state', 'agree')
            ->where('dashboard.parity.families.0.cells.py.state', 'port-gap'));
    }

    public function test_fresh_complete_data_is_the_only_data_drawn_green(): void
    {
        $this->fakeSources();
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.collected.state', 'fresh')
            ->where('dashboard.parity.feed.state', 'ok')
            ->where('dashboard.parity.ci.state', 'ok')
            ->where('dashboard.providers.languages.php.rows.0.cells.text', 'yes')
            ->where('dashboard.providers.languages.ts.rows.1.cells.text', 'not-ported')
            ->where('dashboard.mcp.rows.0.cells.2026-07-28', 'yes')
            ->where('dashboard.acp.rows.0.stable', 'yes'));
    }

    public function test_an_old_snapshot_is_stale_and_never_green(): void
    {
        $this->fakeSources();
        $this->collect('2026-11-19T12:00:00Z');

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.collected.state', 'stale')
            ->where('dashboard.parity.feed.state', 'stale')
            ->where('dashboard.parity.families.1.cells.php.state', 'unverified')
            ->where('dashboard.providers.languages.php.rows.0.cells.text', 'yes-unverified')
            ->where('dashboard.mcp.rows.0.cells.2026-07-28', 'yes-unverified')
            ->where('dashboard.acp.rows.0.stable', 'yes-unverified'));
    }

    public function test_a_failing_parity_ci_caps_every_cell_below_green(): void
    {
        $this->fakeSources(ciConclusion: 'failure');
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.parity.ci.state', 'failed')
            ->where('dashboard.parity.families.1.cells.php.state', 'unverified'));
    }

    public function test_no_snapshot_renders_unknown_everywhere(): void
    {
        $this->get('/parity')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Parity')
            ->where('dashboard.collected.state', 'unknown')
            ->where('dashboard.parity.feed.state', 'unknown')
            ->where('dashboard.parity.families', [])
            ->where('dashboard.providers.feed.state', 'unknown')
            ->where('dashboard.drift.feed.state', 'unknown')
            ->where('dashboard.mcp.feed.state', 'unknown')
            ->where('dashboard.acp.feed.state', 'unknown'));
    }

    public function test_unreadable_sources_show_unknown_not_ok(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.collected.state', 'fresh')
            ->where('dashboard.parity.feed.state', 'failed')
            ->where('dashboard.parity.families', [])
            ->where('dashboard.providers.feed.state', 'failed')
            ->where('dashboard.drift.feed.state', 'failed')
            ->where('dashboard.mcp.feed.state', 'failed')
            ->where('dashboard.acp.feed.state', 'failed')
            ->where('dashboard.upstream.feed.state', 'failed')
            ->where('dashboard.upstream.behind', null)
            ->where('dashboard.upstream_prs.feed.state', 'failed')
            ->where('dashboard.upstream_prs.counts', null));
    }

    public function test_a_failed_collection_keeps_the_last_data_and_marks_it_stale(): void
    {
        $this->fakeSources();
        $this->collect();

        // A later collection where everything is down.
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstances();
        Http::fake(['*' => Http::response('down', 503)]);
        $this->collect();

        $this->get('/parity')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.parity.feed.state', 'stale')
            ->where('dashboard.parity.feed.last_ok_at', '2026-11-23T12:00:00+00:00')
            ->where('dashboard.parity.families.1.cells.php.state', 'unverified')
            ->where('dashboard.drift.feed.state', 'stale'));
    }

    public function test_the_page_itself_never_fetches(): void
    {
        Http::fake();

        $this->get('/parity')->assertOk();

        Http::assertNothingSent();
    }

    public function test_the_page_has_its_own_head_and_is_in_the_sitemap(): void
    {
        $html = (string) $this->get('/parity')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Parity dashboard — Prism</title>', $html);
        $this->assertStringContainsString('rel="canonical" href="https://ai.particle.academy/parity"', $html);
        $this->assertStringContainsString('<loc>https://ai.particle.academy/parity</loc>', (string) $this->get('/sitemap.xml')->getContent());
    }

    private function collect(string $at = self::NOW): void
    {
        $snapshot = $this->app->make(SnapshotBuilder::class)->build(new DateTimeImmutable($at));
        $this->app->make(SnapshotStore::class)->write($snapshot);

        // The page judges freshness against its own clock: pin it.
        $this->travelTo(new DateTimeImmutable(self::NOW));
    }

    private function fakeSources(string $ciConclusion = 'success'): void
    {
        $raw = 'raw.githubusercontent.com/Particle-Academy';
        $api = 'api.github.com/repos';
        $public = fn (string $family, string $language, string $name, string $repo, string $version, array $extra = []): array => [
            'name' => $name, 'language' => $language, 'family' => $family, 'version' => $version, 'registry' => 'x',
            'repository' => 'https://github.com/Particle-Academy/'.$repo, 'catalog' => 'public', 'published' => true, 'status' => 'published',
        ] + $extra;
        $core = fn (array $providers, array $caps): array => ['surface' => ['providers' => $providers, 'capabilities' => $caps]];

        $manifest = [
            'parity_target' => ['families' => [
                'core' => ['implementations' => ['php' => ['status' => 'shipped'], 'ts' => ['status' => 'in-progress'], 'py' => ['status' => 'in-progress']]],
                'browser' => ['implementations' => ['php' => ['status' => 'shipped']]],
            ]],
            'family_references' => ['core' => 'prism', 'browser' => 'prism-browser', 'secret-family' => 'secret-pkg'],
            'packages' => [
                'prism' => $public('core', 'php', 'particle-academy/prism', 'prism', '1.0.0', $core(['openai', 'anthropic'], ['text', 'embeddings']) + ['surface_paths' => ['providers' => 'src/Providers']]),
                'prism-ts' => $public('core', 'ts', '@particle-academy/prism', 'prism-ts', '0.1.0', $core(['openai'], ['text']) + ['surface_paths' => ['providers' => 'src/providers']]),
                'prism-py' => $public('core', 'py', 'prism-ai-core', 'prism-py', '0.1.0', $core(['openai'], ['text']) + ['surface_paths' => ['providers' => 'src/prism/providers']]),
                'prism-browser' => $public('browser', 'php', 'particle-academy/prism-browser', 'prism-browser', '0.4.0'),
                'prism-browser-ts' => $public('browser', 'ts', '@particle-academy/prism-browser', 'prism-browser-ts', '0.3.0'),
                'prism-mcp-server-ts' => $public('mcp-server', 'ts', '@particle-academy/prism-mcp-server', 'prism-mcp-server-ts', '0.7.0'),
                'prism-acp' => $public('acp', 'php', 'particle-academy/prism-acp', 'prism-acp', '0.1.2'),
                // Not public: never shown, and its family is not a row.
                'secret-pkg' => ['catalog' => 'hidden'] + $public('secret-family', 'php', 'CANARY-HIDDEN-PACKAGE', 'secret', '0.0.1'),
                // Public in name, but marked: never shown.
                'marked-pkg' => ['note' => 'CANARY-NO-PUBLIC-DETAIL: NO public detail'] + $public('browser', 'py', 'marked/pkg', 'marked', '0.0.1'),
            ],
        ];

        $suites = [
            'core-open' => ['id' => 'core-open', 'kind' => 'response-parse', 'reference' => 'php', 'implementations' => [
                'php' => ['status' => 'full', 'runner' => 'prism-parity:runners/php/runner.php'],
                'ts' => ['status' => 'full', 'runner' => 'prism-ts:conformance/runner.mjs'],
                'py' => ['status' => 'partial', 'cause' => 'no-runner', 'runner' => ''],
            ]],
            'browser-guard' => ['id' => 'browser-guard', 'kind' => 'security-corpus', 'reference' => 'php', 'implementations' => [
                'php' => ['status' => 'full', 'runner' => 'prism-browser:tests/X.php'],
                'ts' => ['status' => 'partial', 'cause' => 'reference-limit', 'cause_note' => 'CANARY-SECURITY-CAUSE', 'runner' => 'prism-browser-ts:test/x.ts'],
                'py' => ['status' => 'full', 'runner' => ''],
            ]],
        ];

        $ledger = [
            'cross_checked' => [['runner' => 'runners/php/runner.php'], ['runner' => '../prism-ts/conformance/runner.mjs']],
            'gaps' => [
                ['id' => 'G-125', 'summary' => 'A public, published gap.', 'affects' => ['core-open']],
                ['id' => 'G-999', 'summary' => 'CANARY-SECURITY-GAP on a security corpus.', 'affects' => ['browser-guard/row-1']],
                ['id' => 'G-998', 'summary' => 'CANARY-NO-PUBLIC-DETAIL. NO public detail.', 'affects' => ['core-open']],
                ['id' => 'G-997', 'summary' => 'CANARY-PRIVATE-GAP', 'private' => true, 'affects' => ['core-open']],
            ],
        ];

        $issue = fn (int $n, string $title, array $extra = []): array => [
            'number' => $n, 'title' => $title, 'state' => 'closed', 'closed_at' => '2026-11-21T13:04:06Z',
            'html_url' => 'https://github.com/Particle-Academy/prism/issues/'.$n, 'labels' => [['name' => 'provider-watch']],
            'body' => 'CANARY-ISSUE-BODY',
        ] + $extra;

        $phpProvider = fn (string $class, array $methods): string => "<?php\nclass {$class} {\n".implode("\n", array_map(fn ($m) => "    public function {$m}(Request \$r): Response {}", $methods))."\n}\n";

        Http::fake([
            "{$api}/Particle-Academy/prism-parity/commits/main" => Http::response(['sha' => self::HEAD, 'commit' => ['committer' => ['date' => '2026-11-23T10:00:00Z']]]),
            "{$raw}/prism-parity/".self::HEAD.'/manifest/packages.json' => Http::response($manifest),
            "{$api}/Particle-Academy/prism-parity/contents/suites*" => Http::response(array_map(fn ($id) => ['type' => 'dir', 'name' => $id], array_keys($suites))),
            "{$raw}/prism-parity/".self::HEAD.'/suites/core-open/manifest.json' => Http::response($suites['core-open']),
            "{$raw}/prism-parity/".self::HEAD.'/suites/browser-guard/manifest.json' => Http::response($suites['browser-guard']),
            "{$raw}/prism-parity/".self::HEAD.'/parity/ledger.json' => Http::response($ledger),
            "{$api}/Particle-Academy/prism-parity/actions/workflows/alignment.yml/runs*" => Http::response(['workflow_runs' => [
                ['head_branch' => 'main', 'event' => 'schedule', 'status' => 'completed', 'conclusion' => $ciConclusion, 'head_sha' => self::HEAD, 'created_at' => '2026-11-23T08:00:00Z', 'html_url' => 'https://github.com/Particle-Academy/prism-parity/actions/runs/1'],
                // A newer run for another branch must not be taken as main's verdict.
                ['head_branch' => 'feature', 'event' => 'push', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => str_repeat('a', 40), 'created_at' => '2026-11-23T09:00:00Z'],
            ]]),
            "{$api}/Particle-Academy/prism/contents/src/Providers*" => Http::response([['type' => 'dir', 'name' => 'OpenAI'], ['type' => 'dir', 'name' => 'Anthropic']]),
            "{$raw}/prism/v1.0.0/src/Providers/OpenAI/OpenAI.php" => Http::response($phpProvider('OpenAI', ['text', 'embeddings'])),
            "{$raw}/prism/v1.0.0/src/Providers/Anthropic/Anthropic.php" => Http::response($phpProvider('Anthropic', ['text'])),
            "{$raw}/prism-ts/v0.1.0/src/providers/openai/openai.ts" => Http::response("class OpenAI {\n  override async text(r) {}\n}\n"),
            "{$raw}/prism-py/v0.1.0/src/prism/providers/openai/provider.py" => Http::response("class OpenAI:\n    def text(self, request):\n        pass\n"),
            "{$api}/Particle-Academy/prism/issues*" => Http::response([
                $issue(162, 'Provider drift: 7 contract change(s) (+26 catalogue) — 2026-11-21'),
                $issue(161, 'Provider drift: 13 catalogue change(s), no contract changes — 2026-11-20'),
                $issue(160, 'CANARY-PR-TITLE', ['pull_request' => ['url' => 'x']]),
                $issue(159, 'CANARY-FREEFORM-TITLE: something a person wrote'),
            ]),
            "{$api}/Particle-Academy/prism-provider-watch/commits*" => Http::response([['commit' => ['committer' => ['date' => '2026-11-15T00:36:59Z']]]]),
            "{$api}/modelcontextprotocol/modelcontextprotocol/contents/schema" => Http::response(array_map(fn ($v) => ['type' => 'dir', 'name' => $v], ['2024-11-05', '2025-03-26', '2025-06-18', '2025-11-25', '2026-07-28', 'draft'])),
            "{$raw}/prism-mcp-server-ts/v0.7.0/protocol-versions.json" => Http::response(['versions' => ['2026-07-28', '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05']]),
            "{$api}/agentclientprotocol/agent-client-protocol/releases*" => Http::response([
                ['tag_name' => 'schema-v2.0.0-beta.3', 'prerelease' => true],
                ['tag_name' => 'schema-v1.34.1', 'prerelease' => false],
            ]),
            "{$raw}/prism-acp/v0.1.2/src/Agent/AcpAgent.php" => Http::response("<?php\nfinal class AcpAgent { public const PROTOCOL_VERSION = 1; }\n"),

            // Upstream drift.
            "{$api}/prism-php/prism/compare/*" => Http::response([
                'ahead_by' => 296, 'behind_by' => 3,
                'merge_base_commit' => ['sha' => '385022b'.str_repeat('0', 33), 'commit' => ['committer' => ['date' => '2026-11-07T23:15:09Z']]],
            ]),
            "{$api}/prism-php/prism/releases*" => Http::response([
                ['tag_name' => 'v0.104.0', 'published_at' => '2026-11-20T10:00:00Z', 'draft' => false, 'prerelease' => false, 'name' => 'CANARY-RELEASE-NOTES', 'body' => 'CANARY-RELEASE-NOTES'],
                ['tag_name' => 'v9.9.9', 'published_at' => '2026-11-22T10:00:00Z', 'draft' => true, 'prerelease' => false],
                ['tag_name' => 'v0.103.0', 'published_at' => '2026-11-07T23:33:27Z', 'draft' => false, 'prerelease' => false],
                ['tag_name' => 'v0.102.0', 'published_at' => '2026-10-16T14:00:09Z', 'draft' => false, 'prerelease' => false],
            ]),
            "{$api}/Particle-Academy/prism/releases/latest" => Http::response(['tag_name' => 'v0.152.0', 'published_at' => '2026-11-23T19:33:59Z', 'draft' => false, 'body' => 'CANARY-RELEASE-NOTES']),
            "{$raw}/prism-provider-watch/main/state/upstream.json" => Http::response(['sources' => [
                'issues' => ['ok' => true, 'data' => ['entries' => [['id' => '1052', 'title' => '#1052 x']]]],
                'pulls' => ['ok' => true, 'data' => ['entries' => [['id' => '1054', 'title' => '#1054 x'], ['id' => '1053', 'title' => '#1053 x']]]],
            ]]),

            // Searches: issues and pulls since the merge base, and our PRs by author.
            'api.github.com/search/issues*' => function ($request) {
                $url = urldecode($request->url());
                if (str_contains($url, 'author:second-author')) {
                    return Http::response('rate limited', 403);
                }
                if (str_contains($url, 'author:wishborn')) {
                    // Overrides first: `+` keeps the LEFT operand's keys.
                    $pr = fn (int $n, string $title, string $state, ?string $merged, array $extra = []): array => $extra + [
                        'number' => $n, 'title' => $title, 'state' => $state, 'created_at' => '2026-10-21T19:00:00Z', 'updated_at' => '2026-11-07T22:41:56Z',
                        'html_url' => 'https://github.com/prism-php/prism/pull/'.$n, 'pull_request' => ['merged_at' => $merged], 'labels' => [], 'body' => 'CANARY-PR-BODY',
                    ];

                    return Http::response(['total_count' => 7, 'incomplete_results' => false, 'items' => [
                        $pr(1047, 'feat(tool): decode an object argument sent as JSON', 'closed', '2026-11-07T22:41:56Z'),
                        $pr(1045, 'Stop fetching media URLs implicitly', 'open', null),
                        $pr(1060, 'Fix a security hole CANARY-SECURITY-PR', 'open', null),
                        $pr(1061, 'Tidy CANARY-LABELLED-PR', 'open', null, ['labels' => [['name' => 'security']]]),
                        $pr(1062, 'CANARY-PRIVATE-PR: NO public detail', 'open', null),
                        ['pull_request' => null] + $pr(1063, 'CANARY-ISSUE-IN-SEARCH', 'open', null),
                        ['html_url' => 'https://github.com/someone/else/pull/1064'] + $pr(1064, 'CANARY-OTHER-REPO', 'open', null),
                    ]]);
                }

                return Http::response(['total_count' => str_contains($url, 'is:issue') ? 7 : 4, 'incomplete_results' => false, 'items' => []]);
            },
            "{$api}/prism-php/prism/pulls/1045" => Http::response(['head' => ['sha' => str_repeat('b', 40)], 'draft' => false, 'body' => 'CANARY-PR-BODY']),
            "{$api}/prism-php/prism/pulls/1045/reviews*" => Http::response([
                ['state' => 'APPROVED', 'user' => ['login' => 'a']],
                ['state' => 'COMMENTED', 'user' => ['login' => 'b'], 'body' => 'CANARY-PR-BODY'],
                ['state' => 'CHANGES_REQUESTED', 'user' => ['login' => 'b']],
            ]),
            "{$api}/prism-php/prism/actions/runs*" => Http::response(['workflow_runs' => [
                ['status' => 'completed', 'conclusion' => 'success'],
                ['status' => 'completed', 'conclusion' => 'action_required'],
            ]]),
        ]);
    }
}
