<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('liquidity', 20)->default('unknown');
            $table->unsignedInteger('access_days')->nullable();
            $table->date('available_date')->nullable();
            $table->unsignedBigInteger('withdrawal_cost_cents')->default(0);
            $table->boolean('value_uncertain')->default(false);
        });
        Schema::table('budget_incomes', function (Blueprint $table): void {
            $table->date('expected_date')->nullable();
        });
        Schema::create('liquidity_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('budget_ids')->nullable();
            $table->unsignedInteger('horizon')->default(30);
            $table->unsignedBigInteger('buffer_cents')->default(0);
            $table->unsignedBigInteger('essential_cents')->default(0);
            $table->unsignedBigInteger('variable_cents')->default(0);
            $table->boolean('income_first')->default(false);
            $table->timestamps();
        });
        Schema::create('asset_reserves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('savings_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['asset_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_reserves');
        Schema::dropIfExists('liquidity_preferences');
        Schema::table('budget_incomes', function (Blueprint $table): void {
            $table->dropColumn('expected_date');
        });
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn(['liquidity', 'access_days', 'available_date', 'withdrawal_cost_cents', 'value_uncertain']);
        });
    }
};
