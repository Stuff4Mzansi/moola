<?php

namespace Database\Seeders;

use App\Models\LiquidityPreference;
use Illuminate\Database\Seeder;

class LiquidityPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        LiquidityPreference::factory()->create();
    }
}
