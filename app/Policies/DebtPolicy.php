<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;

class DebtPolicy
{
    public function view(User $user, Debt $debt): bool
    {
        return $user->id === $debt->user_id;
    }

    public function update(User $user, Debt $debt): bool
    {
        return $this->view($user, $debt);
    }

    public function delete(User $user, Debt $debt): bool
    {
        return $this->view($user, $debt);
    }
}
