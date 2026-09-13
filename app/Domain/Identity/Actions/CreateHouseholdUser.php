<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\AuditEvent;
use App\Domain\Identity\Models\HouseholdMembership;
use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CreateHouseholdUser
{
    public function handle(User $administrator, string $name, string $email, string $temporaryPassword): User
    {
        if (! $administrator->is_enabled || ! $administrator->is_administrator) {
            throw new DomainException('Only an enabled administrator can create household users.');
        }

        $householdId = $administrator->memberships()
            ->whereNull('removed_at')
            ->value('household_id');

        if ($householdId === null) {
            throw new DomainException('The administrator is not an active household member.');
        }

        $emailNormalized = mb_strtolower(trim($email));
        $validated = Validator::validate([
            'name' => trim($name),
            'email' => $emailNormalized,
            'password' => $temporaryPassword,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email_normalized'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        return DB::transaction(function () use ($validated, $emailNormalized, $householdId, $administrator): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $emailNormalized,
                'email_normalized' => $emailNormalized,
                'password' => $validated['password'],
                'is_enabled' => true,
                'must_change_password' => true,
                'is_administrator' => false,
            ]);

            HouseholdMembership::query()->create([
                'household_id' => $householdId,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);

            AuditEvent::query()->create([
                'household_id' => $householdId,
                'actor_user_id' => $administrator->id,
                'event_type' => 'user.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => ['permissions' => []],
                'occurred_at' => now(),
            ]);

            return $user;
        });
    }
}
