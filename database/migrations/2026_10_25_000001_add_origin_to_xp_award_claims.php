<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each award was earned, and how much it was worth, so the leaderboard
 * can split a contributor's points between this ecosystem and upstream from
 * one grouped query. Claims written before this carry nulls: those all came
 * from our own repositories, because upstream was never tracked until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xp_award_claims', function (Blueprint $table): void {
            $table->string('repo', 200)->nullable()->index();
            $table->unsignedInteger('xp')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('xp_award_claims', function (Blueprint $table): void {
            $table->dropIndex(['repo']);
            $table->dropColumn(['repo', 'xp']);
        });
    }
};
