<?php

namespace Tests\Feature;

use App\Models\Contributor;
use App\Support\ContributionLimits;
use App\Support\XpAwarder;
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

    public function test_it_splits_out_the_points_earned_upstream(): void
    {
        config()->set('gamification.upstream', ['prism-php/prism']);

        $both = Contributor::create(['github_id' => '5', 'github_username' => 'both']);
        $ours = Contributor::create(['github_id' => '6', 'github_username' => 'ours']);

        // Upstream: a merged PR (25 x 5.0 = 125). Ours: an issue (5 x 2.0 = 10).
        XpAwarder::award($both, 'pr-accepted-xp', 25, 'test', 'pr-merged:UP_1', context: ContributionLimits::context('prism-php/prism'));
        XpAwarder::award($both, 'issue-xp', 5, 'test', 'issue:OURS_1', context: ContributionLimits::context('Particle-Academy/prism'));
        XpAwarder::award($ours, 'issue-xp', 5, 'test', 'issue:OURS_2', context: ContributionLimits::context('Particle-Academy/prism'));

        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('upstream', ['prism-php/prism'])
                ->where('rows.0.github_username', 'both')
                ->where('rows.0.score', 135)
                ->where('rows.0.upstream', 125)
                ->where('rows.1.github_username', 'ours')
                ->where('rows.1.upstream', 0));
    }

    public function test_it_shows_every_contributor_not_just_the_top_fifty(): void
    {
        foreach (range(1, 60) as $i) {
            $contributor = Contributor::create(['github_id' => (string) (1000 + $i), 'github_username' => "c{$i}"]);
            LFL::award('issue-xp')->to($contributor)->amount($i)->because('test')->save();
        }

        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 60)
                ->where('rows.0.github_username', 'c60')
                ->where('rows.59.github_username', 'c1'));
    }

    public function test_the_github_placeholder_account_is_not_a_contributor(): void
    {
        $placeholder = Contributor::create(['github_id' => '7', 'github_username' => 'invalid-email-address']);
        LFL::award('commit-xp')->to($placeholder)->amount(10)->because('test')->save();

        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
    }

    public function test_it_renders_empty_when_there_are_no_contributions(): void
    {
        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Leaderboard')->has('rows', 0));
    }
}
