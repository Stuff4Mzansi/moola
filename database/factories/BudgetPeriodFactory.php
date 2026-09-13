<?php

namespace Database\Factories;

use App\Domain\Budgeting\Enums\BudgetPeriodState;
use App\Domain\Budgeting\Models\BudgetPeriod;
use App\Domain\Identity\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<BudgetPeriod> */
final class BudgetPeriodFactory extends Factory
{
    protected $model = BudgetPeriod::class;

    public function definition(): array
    {
        $startsOn = Carbon::parse(fake()->dateTimeBetween('-6 months', '+1 month'));

        return [
            'household_id' => Household::factory(),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->copy()->addMonth()->subDay()->toDateString(),
            'expected_income_minor' => fake()->numberBetween(100_000, 10_000_000),
            'state' => BudgetPeriodState::Draft,
            'finalized_at' => null,
        ];
    }
}
