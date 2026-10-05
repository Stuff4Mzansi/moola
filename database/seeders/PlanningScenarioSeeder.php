<?php

namespace Database\Seeders;

use App\Models\PlanningScenario;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlanningScenarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create();
        PlanningScenario::factory()->for($user, 'owner')->create(['name' => 'Extra monthly debt payment', 'inputs' => ['extra' => '500.00', 'strategy' => 'avalanche']]);
        PlanningScenario::factory()->liquidity()->for($user, 'owner')->create(['name' => 'Cash-flow safety check']);
        PlanningScenario::factory()->subscriptions()->for($user, 'owner')->create(['name' => 'Subscription review']);
    }
}
