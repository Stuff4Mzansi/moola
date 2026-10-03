<?php

namespace Database\Factories;

use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetIncome> */
class BudgetIncomeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'name' => 'Salary', 'expected_cents' => 2000000, 'received_cents' => 0, 'received_date' => null];
    }
}
