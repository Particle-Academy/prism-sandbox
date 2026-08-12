<?php

use Database\Seeders\FunLabSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // config/gamification.php is the source of truth for the silos, but the
        // catalog rows LFL awards against are seeded by a migration — and a
        // migration only ever runs once. So `commit-xp`, added to the config
        // long after 2026_07_14_000002 had already run, existed in config and
        // on the leaderboard while having no row in lfl_gamed_metrics.
        //
        // LFL::award() then threw for every commit, XpAwarder swallowed it to
        // protect the webhook, and the silo silently awarded nothing at all.
        //
        // The seeder is an idempotent upsert-by-slug, so re-running it adds
        // what is missing and touches nothing else. Add one of these whenever a
        // silo is introduced.
        (new FunLabSeeder)->run();
    }

    public function down(): void
    {
        // Shared reference data — leave it rather than orphaning awards.
    }
};
