<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DebtPayment> */
class DebtPaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['debt_id' => Debt::factory(), 'amount_cents' => 10000, 'interest_cents' => 1000, 'date' => now()->toDateString()];
    }
}
