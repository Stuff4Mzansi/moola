<?php

namespace Database\Seeders;

use App\Models\BudgetPeriod;
use Illuminate\Database\Seeder;

class BudgetGroupSeeder extends Seeder
{
    public function run(): void
    {
        $period = BudgetPeriod::query()->first();
        if ($period === null) {
            return;
        }
        foreach (['Needs' => 5000, 'Wants' => 3000, 'Savings' => 2000] as $name => $percentage) {
            $period->groups()->firstOrCreate(['name' => $name], ['percentage_basis_points' => $percentage]);
        }
    }
}
