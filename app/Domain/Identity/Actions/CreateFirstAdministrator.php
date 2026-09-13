<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Capability;
use App\Domain\Identity\Models\AuditEvent;
use App\Domain\Identity\Models\Household;
use App\Domain\Identity\Models\HouseholdMembership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\Models\InstallationSetting;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CreateFirstAdministrator
{
    public function handle(string $name, string $email, string $password, string $householdName): User
    {
        $emailNormalized = mb_strtolower(trim($email));
        $validated = Validator::validate([
            'name' => trim($name),
            'email' => $emailNormalized,
            'password' => $password,
            'household_name' => trim($householdName),
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
            'household_name' => ['required', 'string', 'max:120'],
        ]);

        return DB::transaction(function () use ($validated, $emailNormalized): User {
            if (User::query()->exists()) {
                throw new DomainException('The first administrator has already been created.');
            }

            $household = Household::query()->create(['name' => $validated['household_name']]);
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $emailNormalized,
                'email_normalized' => $emailNormalized,
                'password' => $validated['password'],
                'is_enabled' => true,
                'must_change_password' => false,
                'is_administrator' => true,
            ]);

            HouseholdMembership::query()->create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);

            foreach (Capability::cases() as $capability) {
                Permission::query()->create([
                    'household_id' => $household->id,
                    'user_id' => $user->id,
                    'capability' => $capability,
                ]);
            }

            InstallationSetting::query()->forceCreate([
                'id' => 1,
                'household_id' => $household->id,
                'currency_code' => 'ZAR',
                'locale' => 'en_ZA',
                'timezone' => 'Africa/Johannesburg',
                'budget_start_day' => 25,
                'backup_retention_count' => 10,
            ]);

            AuditEvent::query()->create([
                'household_id' => $household->id,
                'actor_user_id' => $user->id,
                'event_type' => 'administrator.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => ['source' => 'browser_setup'],
                'occurred_at' => now(),
            ]);

            return $user;
        });
    }
}
