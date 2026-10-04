<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetNotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetNotificationPreference> */
class BudgetNotificationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return ['budget_id' => Budget::factory(), 'user_id' => User::factory(), 'muted_types' => []];
    }
}
