<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table): void {
            $table->id();
            $table->string('github_id')->unique();
            $table->string('github_username')->index();
            $table->string('avatar_url')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributors');
    }
};
