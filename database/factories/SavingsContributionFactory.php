<?php

namespace Database\Factories;

use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavingsContribution> */
class SavingsContributionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['savings_goal_id' => SavingsGoal::factory(), 'amount_cents' => 10000, 'date' => now()->toDateString()];
    }
}
