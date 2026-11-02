<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ProviderSetup\CoreProviderConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProviderConfigSourceTest extends TestCase
{
    public function test_names_and_defaults_are_read_without_resolving_environment_values(): void
    {
        // Only an owned canary, never an actual credential variable.
        putenv('SETUP_TEST_CANARY=DO_NOT_RENDER_ENVIRONMENT_VALUES');

        try {
            $rows = (new CoreProviderConfig)->fromSource(<<<'PHP'
                <?php
                return ['providers' => ['sample' => [
                    'api_key' => env('SETUP_TEST_CANARY', 'DO_NOT_RENDER_SECRET_DEFAULT'),
                    'url' => env('SAMPLE_URL', 'https://example.com/v1'),
                    'timeout' => (int) env('SAMPLE_TIMEOUT', 1_024),
                    'enabled' => env('SAMPLE_ENABLED', false),
                    'site' => ['title' => env('SAMPLE_TITLE', null)],
                ]]];
                PHP);
        } finally {
            putenv('SETUP_TEST_CANARY');
        }

        $this->assertSame([
            ['option' => 'api_key', 'name' => 'SETUP_TEST_CANARY', 'default' => null, 'sensitive' => true],
            ['option' => 'url', 'name' => 'SAMPLE_URL', 'default' => 'https://example.com/v1', 'sensitive' => false],
            ['option' => 'timeout', 'name' => 'SAMPLE_TIMEOUT', 'default' => 1024, 'sensitive' => false],
            ['option' => 'enabled', 'name' => 'SAMPLE_ENABLED', 'default' => false, 'sensitive' => false],
            ['option' => 'site.title', 'name' => 'SAMPLE_TITLE', 'default' => null, 'sensitive' => false],
        ], $rows['sample']);
        $this->assertStringNotContainsString('DO_NOT_RENDER', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('unsupportedSources')]
    public function test_expressions_and_ambiguous_shapes_are_refused(string $source): void
    {
        $this->expectException(RuntimeException::class);

        (new CoreProviderConfig)->fromSource($source);
    }

    public static function unsupportedSources(): array
    {
        return [
            'other calls' => ["<?php return ['providers' => getenv('SETUP_TEST_CANARY')];"],
            'dynamic name' => ["<?php return ['providers' => ['sample' => ['key' => env(strtoupper('sample'))]]];"],
            'duplicate provider' => ["<?php return ['providers' => ['sample' => [], 'sample' => []]];"],
            'expression default' => ["<?php return ['providers' => ['sample' => ['key' => env('SAMPLE_KEY', file_get_contents('missing'))]]];"],
            'extra statement' => ["<?php return ['providers' => ['sample' => []]]; echo 'not executed';"],
            'missing providers' => ["<?php return ['other' => []];"],
            'oversized source' => ['<?php /*'.str_repeat('x', 262_144).'*/ return [];'],
        ];
    }
}
