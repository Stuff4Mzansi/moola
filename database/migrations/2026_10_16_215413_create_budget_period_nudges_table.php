<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_period_nudges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nudge_key', 80);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();
            $table->unique(['budget_period_id', 'user_id', 'nudge_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_period_nudges');
    }
};
