<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_award_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 64);
            $table->char('event_hash', 64);
            $table->text('event_key');
            $table->timestamps();
            $table->unique(['contributor_id', 'metric', 'event_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_award_claims');
    }
};
