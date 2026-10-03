<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->where('role', UserRole::SuperAdmin->value)->first();

        if ($owner === null) {
            return;
        }

        foreach (['Streaming service' => 15900, 'Cloud storage' => 3999, 'Music service' => 6999] as $name => $amountCents) {
            $owner->subscriptions()->firstOrCreate(['name' => $name], [
                'amount_cents' => $amountCents,
                'currency' => 'ZAR',
                'billing_frequency' => 'monthly',
                'next_billing_date' => now()->addDays(7)->toDateString(),
                'status' => 'active',
                'category' => 'Entertainment',
            ]);
        }
    }
}
