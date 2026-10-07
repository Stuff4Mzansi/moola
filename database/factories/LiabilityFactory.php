<?php

namespace Database\Factories;

use App\Models\Liability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Liability> */
class LiabilityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->words(2, true), 'kind' => 'bank'];
    }
}
