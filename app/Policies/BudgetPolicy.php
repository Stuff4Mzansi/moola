<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function view(User $user, Budget $budget): bool
    {
        return $budget->memberRole($user) !== null;
    }

    public function update(User $user, Budget $budget): bool
    {
        return in_array($budget->memberRole($user), ['owner', 'editor'], true);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->isAdmin();
    }

    public function share(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
