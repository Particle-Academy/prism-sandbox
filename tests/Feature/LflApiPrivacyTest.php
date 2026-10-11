<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LflApiPrivacyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('readEndpoints')]
    public function test_anonymous_reads_cannot_expose_a_private_opted_out_contributor(string $endpoint): void
    {
        $contributor = Contributor::create(['github_id' => 70001, 'github_username' => 'private-profile-fixture']);
        $profile = $contributor->getProfile();
        $profile->update(['visibility_settings' => ['public' => false], 'is_opted_in' => false]);

        $this->assertFalse($profile->fresh()->is_opted_in);
        $this->assertFalse($profile->fresh()->visibility_settings['public']);

        // Exercise the shipped config and real routes; do not disable the API in-test.
        $this->getJson('/api/lfl/'.$endpoint.'/App%5CModels%5CContributor/'.$contributor->id)
            ->assertNotFound()
            ->assertDontSee('private-profile-fixture')
            ->assertDontSee('total_xp');
    }

    public static function readEndpoints(): array
    {
        return [['profiles'], ['awards']];
    }
}
