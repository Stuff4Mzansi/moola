<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_recurring_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('category_name', 100);
            $table->unsignedBigInteger('amount_cents');
            $table->string('billing_frequency');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('budget_recurring_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_recurring_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->date('scheduled_date');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedTinyInteger('history_months')->default(0);
            $table->unsignedInteger('history_payments')->default(0);
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->unique(['budget_period_id', 'budget_recurring_expense_id', 'scheduled_date'], 'recurring_period_expense_date_unique');
        });
        Schema::table('budget_transactions', function (Blueprint $table): void {
            $table->foreignId('budget_recurring_charge_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('budget_recurring_charge_id');
        });
        Schema::dropIfExists('budget_recurring_charges');
        Schema::dropIfExists('budget_recurring_expenses');
    }
};
