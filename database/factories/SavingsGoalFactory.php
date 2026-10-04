<?php

namespace Database\Factories;

use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavingsGoal> */
class SavingsGoalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->words(2, true), 'kind' => 'custom', 'target_cents' => 100000, 'opening_cents' => 0, 'start_date' => now()->startOfMonth()->toDateString(), 'target_date' => now()->addMonths(5)->endOfMonth()->toDateString(), 'monthly_cents' => 0];
    }
}
