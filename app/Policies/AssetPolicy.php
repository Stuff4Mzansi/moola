<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function view(User $user, Asset $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function update(User $user, Asset $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Asset $record): bool
    {
        return $this->view($user, $record);
    }
}
