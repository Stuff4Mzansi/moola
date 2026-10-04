<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetReserve;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetReserve> */
class AssetReserveFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['asset_id' => Asset::factory(), 'name' => 'Emergency reserve', 'kind' => 'emergency', 'amount_cents' => 10000];
    }
}
