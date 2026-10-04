<?php

namespace Database\Factories;

use App\Models\NetWorthSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NetWorthSnapshot> */
class NetWorthSnapshotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'date' => now()->toDateString(), 'assets_cents' => 100000, 'debts_cents' => 20000, 'net_worth_cents' => 80000, 'details' => ['assets' => [], 'debts' => [], 'excluded' => 0]];
    }
}
