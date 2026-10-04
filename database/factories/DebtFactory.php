<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Debt> */
class DebtFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->word().' loan', 'opening_balance_cents' => 100000, 'balance_date' => now()->startOfMonth()->toDateString(), 'annual_rate_basis_points' => 1800, 'minimum_payment_cents' => 10000, 'due_anchor' => now()->addDays(5)->toDateString()];
    }
}
