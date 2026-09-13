<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Household> */
final class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [
            'name' => fake()->lastName().' Household',
        ];
    }
}
