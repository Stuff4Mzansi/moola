<?php

namespace Database\Factories;

use App\Models\BudgetCategory;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetCategory> */
class BudgetCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'name' => fake()->word(), 'allocated_cents' => 10000, 'kind' => 'custom'];
    }
}
