<?php

namespace Database\Factories;

use App\Domain\Budgeting\Enums\BudgetGroup;
use App\Domain\Budgeting\Models\BudgetSubcategory;
use App\Domain\Identity\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetSubcategory> */
final class BudgetSubcategoryFactory extends Factory
{
    protected $model = BudgetSubcategory::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'main_group_identifier' => fake()->randomElement(BudgetGroup::cases()),
            'label' => fake()->unique()->words(2, true),
            'position' => 0,
            'is_archived' => false,
            'rollover_enabled' => false,
        ];
    }
}
