<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetValuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetValuation> */
class AssetValuationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['asset_id' => Asset::factory(), 'amount_cents' => 100000, 'date' => now()->toDateString()];
    }
}
