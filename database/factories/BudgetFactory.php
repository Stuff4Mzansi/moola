<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Budget> */
class BudgetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->word().' budget', 'scope' => 'personal', 'include_subscriptions' => true, 'repeat_cycle' => 'none', 'anchor_date' => now()->startOfMonth()->toDateString()];
    }

    public function household(): static
    {
        return $this->state(fn (array $attributes): array => ['scope' => 'household', 'include_subscriptions' => false]);
    }
}
