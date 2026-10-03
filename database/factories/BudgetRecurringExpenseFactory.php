<?php

namespace Database\Factories;

use App\BillingFrequency;
use App\Models\Budget;
use App\Models\BudgetRecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetRecurringExpense> */
class BudgetRecurringExpenseFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_id' => Budget::factory(), 'name' => fake()->word(), 'category_name' => 'Other', 'amount_cents' => 10000, 'billing_frequency' => BillingFrequency::Monthly, 'start_date' => now()->startOfMonth(), 'is_active' => true];
    }
}
