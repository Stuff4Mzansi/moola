<?php

namespace Database\Factories;

use App\Models\Liability;
use App\Models\LiabilityValuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiabilityValuation> */
class LiabilityValuationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['liability_id' => Liability::factory(), 'amount_cents' => 100000, 'date' => now()->toDateString()];
    }
}
