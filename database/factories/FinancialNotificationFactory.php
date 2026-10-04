<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use App\Models\FinancialNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialNotification> */
class FinancialNotificationFactory extends Factory
{
    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'budget_id' => fn (array $attributes): int => BudgetPeriod::query()->findOrFail($attributes['budget_period_id'])->budget_id, 'user_id' => fn (array $attributes): int => BudgetPeriod::query()->findOrFail($attributes['budget_period_id'])->budget->user_id, 'event_key' => fake()->uuid(), 'type' => 'budget_limit', 'title' => 'Budget nearing limit', 'message' => 'Review your spending.', 'tab' => 'overview'];
    }
}
