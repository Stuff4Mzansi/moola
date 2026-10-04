<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 30)->default('custom');
            $table->unsignedBigInteger('target_cents');
            $table->unsignedBigInteger('opening_cents')->default(0);
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->unsignedBigInteger('monthly_cents')->default(0);
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('savings_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->uuid('request_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->date('date');
            $table->string('notes', 255)->nullable();
            $table->string('budget_period_name', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('budget_transactions', function (Blueprint $table): void {
            $table->foreignId('savings_goal_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('savings_goal_id');
        });
        Schema::dropIfExists('savings_contributions');
        Schema::dropIfExists('savings_goals');
    }
};
