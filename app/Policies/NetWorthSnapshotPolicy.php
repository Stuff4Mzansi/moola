<?php

namespace App\Policies;

use App\Models\NetWorthSnapshot;
use App\Models\User;

class NetWorthSnapshotPolicy
{
    public function view(User $user, NetWorthSnapshot $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function update(User $user, NetWorthSnapshot $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, NetWorthSnapshot $record): bool
    {
        return $this->view($user, $record);
    }
}
