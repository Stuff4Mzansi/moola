<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Household;
use App\Domain\NetWorth\Enums\NetWorthAccountType;
use App\Domain\NetWorth\Models\NetWorthAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NetWorthAccount> */
final class NetWorthAccountFactory extends Factory
{
    protected $model = NetWorthAccount::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->randomElement(['Savings', 'Home', 'Vehicle', 'Credit card']),
            'type' => fake()->randomElement(NetWorthAccountType::cases()),
            'current_balance_minor' => fake()->numberBetween(0, 50_000_000),
            'is_archived' => false,
        ];
    }
}
