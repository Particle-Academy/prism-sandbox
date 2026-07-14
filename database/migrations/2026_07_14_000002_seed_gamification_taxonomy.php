<?php

use Database\Seeders\FunLabSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The taxonomy is idempotent (upsert-by-slug); seeding it from a
        // migration deploys it in environments where `db:seed` is not run.
        (new FunLabSeeder)->run();
    }

    public function down(): void
    {
        // The gamification catalog is shared reference data; leave it in place
        // on rollback rather than orphaning any awards that reference it.
    }
};
