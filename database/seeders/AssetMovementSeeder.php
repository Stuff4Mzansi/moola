<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Illuminate\Database\Seeder;

class AssetMovementSeeder extends Seeder
{
    public function run(): void
    {
        $asset = Asset::factory()->create(['liquidity' => 'immediate']);
        $asset->valuations()->create(['amount_cents' => 100000, 'date' => today()->toDateString()]);
        $goal = SavingsGoal::factory()->create(['user_id' => $asset->user_id, 'asset_id' => $asset->id, 'opening_cents' => 0]);
        SavingsContribution::factory()->create(['savings_goal_id' => $goal->id, 'asset_id' => $asset->id, 'money_origin' => 'new', 'amount_cents' => 10000, 'date' => today()->toDateString()]);
    }
}
