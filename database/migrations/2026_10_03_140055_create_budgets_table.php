<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('scope')->default('personal');
            $table->boolean('include_subscriptions')->default(true);
            $table->string('repeat_cycle')->default('none');
            $table->date('anchor_date');
            $table->timestamps();
        });
        Schema::create('budget_members', function (Blueprint $table): void {
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->primary(['budget_id', 'user_id']);
        });
        Schema::create('budget_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['budget_id', 'start_date', 'end_date']);
        });
        Schema::create('budget_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('allocated_cents')->nullable();
            $table->string('kind')->default('custom');
            $table->timestamps();
        });
        Schema::create('budget_incomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('expected_cents');
            $table->unsignedBigInteger('received_cents')->default(0);
            $table->date('received_date')->nullable();
            $table->timestamps();
        });
        Schema::create('budget_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('scheduled_date');
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->unique(['budget_period_id', 'subscription_id', 'scheduled_date']);
        });
        Schema::create('budget_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_commitment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('budget_commitments');
        Schema::dropIfExists('budget_incomes');
        Schema::dropIfExists('budget_categories');
        Schema::dropIfExists('budget_periods');
        Schema::dropIfExists('budget_members');
        Schema::dropIfExists('budgets');
    }
};
