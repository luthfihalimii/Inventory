<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('wm');
    }

    public function manageMember(User $user, Member $member): bool
    {
        return $user->hasRole('wm');
    }
}
