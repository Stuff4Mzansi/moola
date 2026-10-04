<?php

namespace Database\Factories;

use App\Models\LiquidityPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiquidityPreference> */
class LiquidityPreferenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'budget_ids' => [], 'horizon' => 30, 'buffer_cents' => 0, 'essential_cents' => 0, 'variable_cents' => 0, 'income_first' => false];
    }
}
