<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Household;
use App\Domain\Subscriptions\Enums\RecurrenceUnit;
use App\Domain\Subscriptions\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'budget_subcategory_id' => null,
            'name' => fake()->company(),
            'amount_minor' => fake()->numberBetween(500, 50_000),
            'recurrence_unit' => RecurrenceUnit::Month,
            'recurrence_interval' => 1,
            'anchor_day' => 1,
            'occurs_on_last_day' => false,
            'next_charge_on' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'is_active' => true,
        ];
    }
}
