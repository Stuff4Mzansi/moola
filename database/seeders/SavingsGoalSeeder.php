<?php

namespace Database\Seeders;

use App\Models\SavingsGoal;
use Illuminate\Database\Seeder;

class SavingsGoalSeeder extends Seeder
{
    public function run(): void
    {
        SavingsGoal::factory()->create();
    }
}
