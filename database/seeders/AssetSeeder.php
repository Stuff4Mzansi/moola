<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetValuation;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        AssetValuation::factory()->for(Asset::factory())->create();
    }
}
