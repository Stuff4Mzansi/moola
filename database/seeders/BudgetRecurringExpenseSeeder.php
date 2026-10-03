<?php

namespace Database\Seeders;

use App\BillingFrequency;
use App\Models\Budget;
use Illuminate\Database\Seeder;

class BudgetRecurringExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $budget = Budget::query()->first();
        if ($budget !== null) {
            $budget->recurringExpenses()->firstOrCreate(['name' => 'Utilities'], ['category_name' => 'Other', 'amount_cents' => 100000, 'billing_frequency' => BillingFrequency::Monthly, 'start_date' => now()->startOfMonth()->toDateString(), 'is_active' => true]);
        }
    }
}
