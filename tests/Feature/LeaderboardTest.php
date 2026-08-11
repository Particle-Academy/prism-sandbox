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

    public function test_it_renders_empty_when_there_are_no_contributions(): void
    {
        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Leaderboard')->has('rows', 0));
    }
}
