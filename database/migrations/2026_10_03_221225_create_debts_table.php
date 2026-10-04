<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('creditor', 100)->nullable();
            $table->unsignedBigInteger('opening_balance_cents');
            $table->date('balance_date');
            $table->unsignedInteger('annual_rate_basis_points')->default(0);
            $table->unsignedBigInteger('minimum_payment_cents');
            $table->date('due_anchor');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('debt_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->uuid('request_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('interest_cents')->default(0);
            $table->date('date');
            $table->string('notes', 255)->nullable();
            $table->string('budget_period_name', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        foreach (['budget_recurring_expenses', 'budget_recurring_charges'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            });
        }
        Schema::table('budget_transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('interest_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('budget_transactions', function (Blueprint $table): void {
            $table->dropColumn('interest_cents');
        });
        foreach (['budget_recurring_charges', 'budget_recurring_expenses'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('debt_id');
            });
        }
        Schema::dropIfExists('debt_payments');
        Schema::dropIfExists('debts');
    }
};
