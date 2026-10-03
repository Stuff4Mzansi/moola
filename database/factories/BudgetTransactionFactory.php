<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetTransaction> */
class BudgetTransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'budget_category_id' => null, 'budget_commitment_id' => null, 'amount_cents' => 15000, 'date' => now()->toDateString(), 'description' => fake()->word()];
    }
}
