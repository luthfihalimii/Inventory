<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('wm');
    }

    public function manageUser(User $user, User $target): bool
    {
        return $user->hasRole('wm');
    }
}
