<?php

namespace App\Policies;

use App\Models\User;

class MemberPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('wm');
    }
}
