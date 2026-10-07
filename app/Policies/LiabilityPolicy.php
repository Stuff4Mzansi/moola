<?php

namespace App\Policies;

use App\Models\Liability;
use App\Models\User;

class LiabilityPolicy
{
    public function view(User $user, Liability $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function update(User $user, Liability $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Liability $record): bool
    {
        return $this->view($user, $record);
    }
}
