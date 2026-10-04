<?php

namespace App\Policies;

use App\Models\SavingsGoal;
use App\Models\User;

class SavingsGoalPolicy
{
    public function view(User $user, SavingsGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function update(User $user, SavingsGoal $goal): bool
    {
        return $this->view($user, $goal);
    }

    public function delete(User $user, SavingsGoal $goal): bool
    {
        return $this->view($user, $goal);
    }
}
