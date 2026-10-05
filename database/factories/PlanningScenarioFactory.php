<?php

namespace Database\Factories;

use App\Models\PlanningScenario;
use App\Models\User;
use App\PlanningScenarios;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningScenario>
 */
class PlanningScenarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'kind' => 'debt',
            'inputs' => app(PlanningScenarios::class)->defaults('debt'),
        ];
    }

    public function liquidity(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => 'liquidity', 'inputs' => app(PlanningScenarios::class)->defaults('liquidity')]);
    }

    public function subscriptions(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => 'subscriptions', 'inputs' => app(PlanningScenarios::class)->defaults('subscriptions')]);
    }
}
