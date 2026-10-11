<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Docs\DocsRepository;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DocsPathSecurityTest extends TestCase
{
    private const OUTSIDE_CONTENT = 'DOCS-OUTSIDE-ROOT-FIXTURE';

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
        $this->fixture = sys_get_temp_dir().'/prism-docs-path-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/package/docs/public/nested', 0755, true);
        File::makeDirectory($this->fixture.'/package/docs/public-evil', 0755, true);
        File::makeDirectory($this->fixture.'/site/ports', 0755, true);
        File::put($this->fixture.'/outside.txt', self::OUTSIDE_CONTENT);
        File::put($this->fixture.'/outside.md', self::OUTSIDE_CONTENT);
        File::put($this->fixture.'/package/docs/public-evil/outside.txt', self::OUTSIDE_CONTENT);
        File::put($this->fixture.'/package/docs/public/nested/valid.txt', 'VALID-NESTED-ASSET');
        File::put($this->fixture.'/package/docs/guide.md', "# Safe guide\n\nInside the docs root.");

        // Override only the filesystem roots; use the real repository and HTTP route.
        $this->app->instance(DocsRepository::class, new class($this->fixture) extends DocsRepository
        {
            public function __construct(private string $fixture) {}

            public function basePath(): string
            {
                return $this->fixture.'/package/docs';
            }

            public function sitePath(): string
            {
                return $this->fixture.'/site';
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            File::deleteDirectory($this->fixture);
        } finally {
            parent::tearDown();
        }
    }

    public function test_the_exact_live_exploit_is_refused(): void
    {
        // Use the installed root and the exact URL that served this app's composer.json.
        $this->app->instance(DocsRepository::class, new DocsRepository);

        $this->assertAssetRefused('/docs-assets/.%5C./.%5C./.%5C./.%5C./.%5C./composer.json');
    }

    #[DataProvider('invalidAssetPaths')]
    public function test_invalid_decoded_asset_segments_are_refused(string $path): void
    {
        $this->assertAssetRefused('/docs-assets/'.$path);
    }

    public static function invalidAssetPaths(): array
    {
        return [
            'ordinary traversal' => ['../../../outside.txt'],
            'encoded dots' => ['%2e%2e/%2e%2e/%2e%2e/outside.txt'],
            'encoded separators' => ['%2e%2e%2f%2e%2e%2f%2e%2e%2foutside.txt'],
            'split dots' => ['.%5C./.%5C./.%5C./outside.txt'],
            'doubled split dots' => ['.%5C%5C./.%5C%5C./.%5C%5C./outside.txt'],
            'mixed traversal' => ['.%5C./%2e%2e/.%5c./outside.txt'],
            'exact parent segment' => ['..'],
            'current directory segment' => ['nested/./valid.txt'],
            'empty segment' => ['nested//valid.txt'],
            'leading empty segment' => ['%2Fnested/valid.txt'],
            'trailing empty segment' => ['nested/valid.txt%2F'],
            'backslash segment' => ['nested%5Cvalid.txt'],
            'NUL byte' => ['nested/valid.txt%00'],
        ];
    }

    public function test_a_valid_nested_asset_is_served_and_cacheable(): void
    {
        $response = $this->get('/docs-assets/nested/valid.txt')->assertOk();

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(
            realpath($this->fixture.'/package/docs/public/nested/valid.txt'),
            $response->baseResponse->getFile()->getRealPath(),
        );
        $this->assertSame('VALID-NESTED-ASSET', $response->streamedContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('86400', (string) $response->headers->getCacheControlDirective('max-age'));
    }

    public function test_a_missing_asset_root_fails_closed(): void
    {
        File::deleteDirectory($this->fixture.'/package/docs/public');

        $this->assertAssetRefused('/docs-assets/nested/valid.txt');
    }

    public function test_a_directory_is_not_served_as_an_asset(): void
    {
        $this->assertAssetRefused('/docs-assets/nested');
    }

    public function test_an_asset_symlink_outside_the_root_is_refused(): void
    {
        $this->createSymlink($this->fixture.'/outside.txt', $this->fixture.'/package/docs/public/escape.txt');

        $this->assertAssetRefused('/docs-assets/escape.txt');
    }

    public function test_an_asset_symlink_to_a_sibling_prefix_is_refused(): void
    {
        $this->createSymlink(
            $this->fixture.'/package/docs/public-evil/outside.txt',
            $this->fixture.'/package/docs/public/escape.txt',
        );

        $this->assertAssetRefused('/docs-assets/escape.txt');
    }

    public function test_a_docs_page_symlink_outside_its_root_is_refused(): void
    {
        $this->createSymlink($this->fixture.'/outside.md', $this->fixture.'/package/docs/escape.md');

        $response = $this->get('/docs/escape')->assertNotFound();

        $this->assertNotInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $response->assertDontSee(self::OUTSIDE_CONTENT);
    }

    public function test_a_port_fragment_symlink_outside_its_root_is_not_read(): void
    {
        $this->createSymlink($this->fixture.'/outside.md', $this->fixture.'/site/ports/guide.md');

        $response = $this->get('/docs/guide')->assertOk();
        $page = $response->viewData('page')['props']['page'];

        $this->assertStringNotContainsString(self::OUTSIDE_CONTENT, $page['html']);
        $this->assertSame([], $page['variants']);
    }

    public function test_docs_traversal_is_refused_even_without_the_route_constraint(): void
    {
        $this->app['router']->getRoutes()->getByName('docs.show')->where('path', '.*');

        $response = $this->get('/docs/.%5C./.%5C./outside')->assertNotFound();

        $this->assertNotInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $response->assertDontSee(self::OUTSIDE_CONTENT);
    }

    public function test_a_page_alias_cannot_escape_the_docs_roots(): void
    {
        config(['docs.page_aliases.guide' => '../../outside']);

        $response = $this->get('/docs/guide')->assertNotFound();

        $this->assertNotInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $response->assertDontSee(self::OUTSIDE_CONTENT);
    }

    public function test_a_port_mapping_cannot_escape_the_site_root(): void
    {
        config(['docs.port_guides.guide' => '../outside']);

        $response = $this->get('/docs/guide')->assertOk();
        $page = $response->viewData('page')['props']['page'];

        $this->assertStringNotContainsString(self::OUTSIDE_CONTENT, $page['html']);
        $this->assertSame([], $page['variants']);
    }

    public function test_a_valid_nested_port_fragment_still_renders(): void
    {
        File::put($this->fixture.'/site/ports/guide.md', "# Port guide\n\nVALID-PORT-FRAGMENT");

        $response = $this->get('/docs/guide')->assertOk();
        $page = $response->viewData('page')['props']['page'];

        $this->assertStringContainsString('VALID-PORT-FRAGMENT', $page['variants']['typescript']['html']);
        $this->assertStringContainsString('VALID-PORT-FRAGMENT', $page['variants']['python']['html']);
    }

    private function assertAssetRefused(string $uri): void
    {
        $response = $this->get($uri)->assertNotFound();

        // A BinaryFileResponse has no getContent() body: check both the selected
        // file channel and the rendered error body, so an empty body cannot hide a read.
        $this->assertNotInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $response->assertDontSee(self::OUTSIDE_CONTENT);
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('max-age'));
    }

    private function createSymlink(string $target, string $link): void
    {
        $target = str_replace('/', DIRECTORY_SEPARATOR, $target);
        $link = str_replace('/', DIRECTORY_SEPARATOR, $link);

        $created = @symlink($target, $link);

        // PHP cannot request Windows' unprivileged symlink creation flag.
        // PowerShell 7 can use it when Developer Mode is enabled.
        if (! $created && DIRECTORY_SEPARATOR === '\\') {
            $command = "New-Item -ItemType SymbolicLink -Path '".str_replace("'", "''", $link)
                ."' -Target '".str_replace("'", "''", $target)."' -ErrorAction Stop | Out-Null";
            $process = proc_open(
                ['pwsh.exe', '-NoProfile', '-NonInteractive', '-Command', $command],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );

            if (is_resource($process)) {
                foreach ($pipes as $pipe) {
                    stream_get_contents($pipe);
                    fclose($pipe);
                }
                $created = proc_close($process) === 0;
            }
        }

        if (! $created) {
            $this->markTestSkipped('Cannot create a symlink on this platform; symlink escape coverage was not executed.');
        }

        $this->assertTrue(is_link($link));
        $this->assertSame(realpath($target), realpath($link));
    }
}
