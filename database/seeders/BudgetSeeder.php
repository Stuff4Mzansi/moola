<?php

namespace Database\Seeders;

use App\BudgetWorkspace;
use App\Models\Budget;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('role', UserRole::SuperAdmin->value)->first();
        if ($owner === null || Budget::query()->where('user_id', $owner->id)->where('name', 'Example budget')->exists()) {
            return;
        }
        $budget = Budget::factory()->create(['user_id' => $owner->id, 'name' => 'Example budget']);
        $period = $budget->periods()->create(['name' => now()->format('M Y'), 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()]);
        $period->categories()->create(['name' => 'Subscriptions', 'kind' => 'subscriptions', 'allocated_cents' => null]);
        $period->categories()->create(['name' => 'Other', 'kind' => 'other', 'allocated_cents' => 0]);
        $food = $period->categories()->create(['name' => 'Food', 'kind' => 'custom', 'allocated_cents' => 300000]);
        $period->incomes()->create(['name' => 'Expected salary', 'expected_cents' => 2000000, 'received_cents' => 0]);
        $period->transactions()->create(['budget_category_id' => $food->id, 'amount_cents' => 10000, 'date' => now()->toDateString(), 'description' => 'Example expense']);
        app(BudgetWorkspace::class)->sync($period, true);
    }
}
