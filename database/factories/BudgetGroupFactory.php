<?php

namespace Database\Factories;

use App\Models\BudgetGroup;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetGroup> */
class BudgetGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'name' => fake()->unique()->word(), 'percentage_basis_points' => null];
    }
}
