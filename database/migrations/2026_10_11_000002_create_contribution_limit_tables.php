<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_limit_buckets', function (Blueprint $table): void {
            $table->char('bucket_hash', 64)->primary();
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
        });
        Schema::create('contribution_limit_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 64);
            $table->char('event_hash', 64);
            $table->date('event_day');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['contributor_id', 'metric', 'event_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_limit_decisions');
        Schema::dropIfExists('contribution_limit_buckets');
    }
};
