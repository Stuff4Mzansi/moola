<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Transactions\Enums\TransactionType;
use App\Domain\Transactions\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
final class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'entered_by_user_id' => User::factory(),
            'budget_subcategory_id' => null,
            'type' => TransactionType::Expense,
            'amount_minor' => fake()->numberBetween(100, 100_000),
            'transacted_on' => fake()->date(),
            'description' => fake()->words(3, true),
        ];
    }
}
