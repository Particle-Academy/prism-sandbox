<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_request_budgets', function (Blueprint $table): void {
            $table->string('provider', 32);
            $table->string('window', 16);
            $table->timestamp('period_started_at');
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
            $table->primary(['provider', 'window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_request_budgets');
    }
};
