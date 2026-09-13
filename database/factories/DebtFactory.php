<?php

namespace Database\Factories;

use App\Domain\Debts\Models\Debt;
use App\Domain\Identity\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Debt> */
final class DebtFactory extends Factory
{
    protected $model = Debt::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->randomElement(['Credit card', 'Vehicle loan', 'Student loan']),
            'current_principal_minor' => fake()->numberBetween(10_000, 10_000_000),
            'apr_basis_points' => fake()->numberBetween(0, 3_500),
            'minimum_payment_minor' => fake()->numberBetween(1_000, 20_000),
            'planned_payment_minor' => fake()->numberBetween(2_000, 50_000),
            'opened_on' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'is_archived' => false,
        ];
    }
}
