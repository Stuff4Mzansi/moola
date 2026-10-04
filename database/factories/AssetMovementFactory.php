<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetMovement> */
class AssetMovementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['asset_id' => Asset::factory(), 'amount_cents' => 10000, 'date' => now()->toDateString()];
    }
}
