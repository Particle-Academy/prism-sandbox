<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ProviderSetup\CoreProviderConfig;
use App\ProviderSetup\ProviderGuides;
use Tests\TestCase;

class ProviderSetupTest extends TestCase
{
    public function test_provider_coverage_matches_the_installed_config_with_an_explicit_second_slice(): void
    {
        $configured = array_keys((new CoreProviderConfig)->providers());
        $guides = (new ProviderGuides)->all();
        $pending = json_decode(file_get_contents(resource_path('providers/coverage.json')), true, flags: JSON_THROW_ON_ERROR)['pending'];
        $covered = [...array_keys($guides), ...$pending];
        $this->assertSame(count($covered), count(array_unique($covered)));
        sort($configured);
        sort($covered);
        $this->assertSame($configured, $covered, 'Every installed provider needs a guide or the explicitly approved second slice.');
        $this->assertCount(8, $guides);
        $this->assertCount(10, $pending);
    }

    public function test_every_setup_page_contains_only_public_source_derived_props(): void
    {
        $this->get('/providers/setup')->assertOk()->assertViewHas('page');
        $guides = (new ProviderGuides)->all();
        foreach ($guides as $id => $guide) {
            $response = $this->get('/providers/setup/'.$id)->assertOk();
            $props = $response->viewData('page')['props'];
            $this->assertSame($id, $props['provider']['id']);
            $this->assertNull($props['provider']['verifiedOn']);
            $this->assertNotEmpty($props['provider']['sources']);
            $this->assertStringContainsString('/providers/setup/'.$id, $props['seo']['canonical']);
            foreach ($guide['environment'] as $variable) {
                $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $variable['name']);
                if ($variable['sensitive']) {
                    $this->assertNull($variable['default']);
                }
            }
            $this->assertSame($guide['portSupport']['ts'], $guide['portEnvironment']['typescript'] !== []);
            $this->assertSame($guide['portSupport']['py'], $guide['portEnvironment']['python'] !== []);
        }
        $this->get('/providers/setup/notaprovider')->assertNotFound();
        $this->get('/providers/setup/vertex')->assertNotFound();
    }

    public function test_the_catalog_never_uses_resolved_application_credential_values(): void
    {
        config(['prism.providers.openai.api_key' => 'OWNED_SETUP_SECRET_CANARY']);

        $response = $this->get('/providers/setup/openai')->assertOk();

        $this->assertStringNotContainsString('OWNED_SETUP_SECRET_CANARY', json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR));
    }
}
