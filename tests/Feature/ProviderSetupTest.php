<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ProviderSetup\CoreProviderConfig;
use App\ProviderSetup\ProviderGuides;
use Tests\TestCase;

class ProviderSetupTest extends TestCase
{
    public function test_every_installed_provider_has_a_guide_without_deferred_exceptions(): void
    {
        $configured = array_keys((new CoreProviderConfig)->providers());
        $guides = (new ProviderGuides)->all();
        $covered = array_keys($guides);
        $this->assertSame(count($covered), count(array_unique($covered)));
        sort($configured);
        sort($covered);
        $this->assertSame($configured, $covered, 'Every installed provider needs a guide.');
        $this->assertCount(18, $guides);
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
            foreach ($guide['steps'] as $step) {
                $this->assertNotEmpty($step['title']);
                $this->assertNotEmpty($step['body']);
                $this->assertDoesNotMatchRegularExpression('/^Step\s+\d+$/i', $step['title']);
            }
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
        $this->post('/providers/setup/openai', ['key' => 'OWNED_NO_INPUT_CANARY'])->assertStatus(405);
    }

    public function test_the_catalog_never_uses_resolved_application_credential_values(): void
    {
        config(['prism.providers.openai.api_key' => 'OWNED_SETUP_SECRET_CANARY']);

        $response = $this->get('/providers/setup/openai')->assertOk();

        $this->assertStringNotContainsString('OWNED_SETUP_SECRET_CANARY', json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR));
    }
}
