<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetRecurringCharge> */
class BudgetRecurringChargeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'budget_recurring_expense_id' => BudgetRecurringExpense::factory(), 'name' => fake()->word(), 'scheduled_date' => now()->toDateString(), 'amount_cents' => 10000];
    }
}
