<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelFunLab\Models\GamedMetric;
use Tests\TestCase;

/**
 * A silo added to config/gamification.php has no catalog row until the seeder
 * runs again, and migrations only run once. `commit-xp` shipped in that state:
 * it appeared in config and on the leaderboard, LFL::award() threw for every
 * commit, XpAwarder swallowed the throw to protect the webhook, and the
 * backfill reported skips while awarding nothing at all.
 */
class BackfillTaxonomyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_catalog_covers_every_configured_silo(): void
    {
        $configured = array_keys((array) config('gamification.metrics'));
        $seeded = GamedMetric::query()->pluck('slug')->all();

        $this->assertSame(
            [],
            array_values(array_diff($configured, $seeded)),
            'A configured silo has no lfl_gamed_metrics row, so it can never award. '
            .'Add a migration that re-runs FunLabSeeder.',
        );
    }

    public function test_the_backfill_refuses_to_run_with_a_silo_missing_from_the_catalog(): void
    {
        GamedMetric::query()->where('slug', 'commit-xp')->delete();

        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism'], '--dry-run' => true])
            ->expectsOutputToContain('Missing gamification catalog rows for: commit-xp')
            ->assertExitCode(1);
    }

    public function test_it_proceeds_when_the_catalog_is_complete(): void
    {
        // No network call is made before the guard, so a complete catalog gets
        // past it; the run itself is exercised elsewhere.
        $this->artisan('gamification:backfill', ['repos' => ['Particle-Academy/prism'], '--dry-run' => true])
            ->doesntExpectOutputToContain('Missing gamification catalog rows');
    }
}
