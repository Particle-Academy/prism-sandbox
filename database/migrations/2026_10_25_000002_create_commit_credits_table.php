<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The identities of every commit that has earned commit XP, so the same work
 * is credited once even when it lands in more than one repository under a
 * different SHA (absorbed into the fork by cherry-pick, or squash-merged
 * upstream). See App\Support\CommitCredits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->char('identity_hash', 64);
            $table->string('identity', 100);
            $table->text('event_key');
            $table->timestamps();
            $table->unique(['contributor_id', 'identity_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commit_credits');
    }
};
