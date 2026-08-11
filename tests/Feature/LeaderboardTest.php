<?php

namespace Tests\Feature;

use App\Models\Contributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use LaravelFunLab\Facades\LFL;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_contributors_ranked_by_total_xp(): void
    {
        $alice = Contributor::create(['github_id' => '1', 'github_username' => 'alice']);
        $bob = Contributor::create(['github_id' => '2', 'github_username' => 'bob']);

        LFL::award('pr-accepted-xp')->to($alice)->amount(100)->because('test')->save();
        LFL::award('issue-xp')->to($bob)->amount(10)->because('test')->save();

        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Leaderboard')
                // Driven by config, so it tracks the silos rather than a literal.
                ->has('silos', count(config('gamification.metrics')))
                ->has('rows', 2)
                ->where('rows.0.github_username', 'alice')
                ->where('rows.0.rank', 1)
                ->where('rows.0.total_xp', 100)
                ->where('rows.1.github_username', 'bob')
                ->where('rows.1.rank', 2));
    }

    public function test_it_ranks_on_the_weighted_group_not_raw_xp(): void
    {
        $bulk = Contributor::create(['github_id' => '3', 'github_username' => 'bulk']);
        $merger = Contributor::create(['github_id' => '4', 'github_username' => 'merger']);

        // More raw XP, all of it in the lightest silo (commit-xp, weight 1.0).
        LFL::award('commit-xp')->to($bulk)->amount(90)->because('test')->save();

        // Less raw XP, but in the heaviest silo (pr-accepted-xp, weight 5.0).
        LFL::award('pr-accepted-xp')->to($merger)->amount(50)->because('test')->save();

        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // 50 x 5.0 = 250 beats 90 x 1.0 = 90, even though 90 > 50 raw.
                ->where('rows.0.github_username', 'merger')
                ->where('rows.0.score', 250)
                ->where('rows.0.total_xp', 50)
                ->where('rows.1.github_username', 'bulk')
                ->where('rows.1.score', 90)
                ->where('rows.1.total_xp', 90));
    }

    public function test_it_renders_empty_when_there_are_no_contributions(): void
    {
        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Leaderboard')->has('rows', 0));
    }
}
