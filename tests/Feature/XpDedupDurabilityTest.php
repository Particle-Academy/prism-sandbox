<?php

namespace Tests\Feature;

use App\Models\Contributor;
use App\Support\AwardOutcome;
use App\Support\XpAwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The dedup cooldown used to live only in the cache. A `cache:clear` — which a
 * deploy step can easily run — wiped every cooldown, so the next backfill
 * re-awarded contributions that had already been granted and inflated the
 * leaderboard. The durable record in LFL's event log is now the authority.
 */
class XpDedupDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private function contributor(): Contributor
    {
        return Contributor::create(['github_id' => '4242', 'github_username' => 'octocat']);
    }

    public function test_it_does_not_re_award_after_the_cache_is_cleared(): void
    {
        $c = $this->contributor();

        $this->assertSame(AwardOutcome::Awarded, XpAwarder::award($c, 'commit-xp', 10, 'landed', 'commit:abc123'));
        $this->assertSame(10, $c->fresh()->getXpFor('commit-xp'));

        // The deploy-step scenario that caused the bug.
        Cache::flush();

        $this->assertSame(AwardOutcome::Duplicate, XpAwarder::award($c, 'commit-xp', 10, 'landed', 'commit:abc123'));
        $this->assertSame(10, $c->fresh()->getXpFor('commit-xp'));
    }

    public function test_a_repeat_backfill_after_a_cache_clear_awards_nothing(): void
    {
        $c = $this->contributor();
        $shas = ['commit:a1', 'commit:b2', 'commit:c3'];

        foreach ($shas as $sha) {
            XpAwarder::award($c, 'commit-xp', 10, 'landed', $sha);
        }
        $this->assertSame(30, $c->fresh()->getXpFor('commit-xp'));

        Cache::flush();

        foreach ($shas as $sha) {
            $this->assertSame(AwardOutcome::Duplicate, XpAwarder::award($c, 'commit-xp', 10, 'landed', $sha));
        }

        $this->assertSame(30, $c->fresh()->getXpFor('commit-xp'));
    }

    public function test_the_cache_is_repopulated_so_the_fast_path_returns(): void
    {
        $c = $this->contributor();
        XpAwarder::award($c, 'commit-xp', 10, 'landed', 'commit:abc123');

        Cache::flush();
        XpAwarder::award($c, 'commit-xp', 10, 'landed', 'commit:abc123');

        // Both assertions matter: the cache-only version also repopulates here,
        // but only after wrongly awarding a second time, so the XP total is
        // what actually distinguishes the fix.
        $this->assertTrue(Cache::has("xp:{$c->id}:commit-xp:commit:abc123"));
        $this->assertSame(10, $c->fresh()->getXpFor('commit-xp'));
    }

    public function test_a_genuinely_new_contribution_still_awards_after_a_clear(): void
    {
        $c = $this->contributor();
        XpAwarder::award($c, 'commit-xp', 10, 'landed', 'commit:abc123');

        Cache::flush();

        $this->assertSame(AwardOutcome::Awarded, XpAwarder::award($c, 'commit-xp', 10, 'landed', 'commit:different'));
        $this->assertSame(20, $c->fresh()->getXpFor('commit-xp'));
    }

    public function test_the_same_key_in_a_different_metric_is_not_confused(): void
    {
        $c = $this->contributor();
        XpAwarder::award($c, 'commit-xp', 10, 'landed', 'shared-id');

        Cache::flush();

        $this->assertSame(AwardOutcome::Awarded, XpAwarder::award($c, 'pr-comment-xp', 2, 'commented', 'shared-id'));
        $this->assertSame(10, $c->fresh()->getXpFor('commit-xp'));
        $this->assertSame(2, $c->fresh()->getXpFor('pr-comment-xp'));
    }

    public function test_one_contributors_award_does_not_dedup_another(): void
    {
        $a = $this->contributor();
        $b = Contributor::create(['github_id' => '5353', 'github_username' => 'hubot']);

        XpAwarder::award($a, 'commit-xp', 10, 'landed', 'commit:abc123');

        Cache::flush();

        $this->assertSame(AwardOutcome::Awarded, XpAwarder::award($b, 'commit-xp', 10, 'landed', 'commit:abc123'));
        $this->assertSame(10, $b->fresh()->getXpFor('commit-xp'));
    }
}
