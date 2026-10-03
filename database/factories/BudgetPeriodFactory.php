<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetPeriod> */
class BudgetPeriodFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_id' => Budget::factory(), 'name' => now()->format('M Y'), 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()];
    }
}
