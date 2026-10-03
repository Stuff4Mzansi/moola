<?php

namespace Database\Factories;

use App\Models\BudgetCommitment;
use App\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetCommitment> */
class BudgetCommitmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'subscription_id' => null, 'name' => 'Service', 'scheduled_date' => now()->toDateString(), 'amount_cents' => 15900, 'is_current' => true];
    }
}
