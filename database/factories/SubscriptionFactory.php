<?php

namespace Database\Factories;

use App\BillingFrequency;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
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
            'name' => fake()->company(),
            'amount_cents' => fake()->numberBetween(100, 50000),
            'currency' => 'ZAR',
            'billing_frequency' => BillingFrequency::Monthly,
            'next_billing_date' => now()->addDays(7)->toDateString(),
            'status' => SubscriptionStatus::Active,
            'category' => 'Entertainment',
            'website' => null,
            'notes' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => SubscriptionStatus::Paused]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => SubscriptionStatus::Cancelled]);
    }
}
