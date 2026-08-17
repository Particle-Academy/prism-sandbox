<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The page ranks on weighted score, so it has to say how a score is reached.
 * The explainer is driven by the same config the awards use — if it were a
 * hand-written copy it would silently lie the first time a value changed.
 */
class LeaderboardScoringExplainerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_silo_carries_the_numbers_needed_to_explain_scoring(): void
    {
        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('silos', count(config('gamification.metrics')))
                ->has('silos.0', fn (AssertableInertia $silo) => $silo
                    ->hasAll(['slug', 'name', 'xp', 'weight'])
                    ->etc()));
    }

    public function test_the_published_numbers_match_the_awarding_config(): void
    {
        $silos = collect(
            $this->get('/leaderboard')->viewData('page')['props']['silos']
        )->keyBy('slug');

        foreach (config('gamification.metrics') as $slug => $meta) {
            $this->assertSame((int) $meta['xp'], $silos[$slug]['xp'], "xp drifted for {$slug}");
            $this->assertSame((float) $meta['weight'], $silos[$slug]['weight'], "weight drifted for {$slug}");
        }
    }
}
