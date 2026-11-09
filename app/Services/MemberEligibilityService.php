<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

class MemberEligibilityService
{
    /**
     * @throws ValidationException
     */
    public function assertEligible(Member $member): void
    {
        if ($member->membership_status !== 'active') {
            throw ValidationException::withMessages([
                'member' => 'Anggota tidak aktif sehingga tidak dapat meminjam.',
            ]);
        }

        // ponytail: per-loan blocks (member_borrowing_blocks) arrive with overdue handling in Sprint 7.
        if ($member->borrowing_status !== 'eligible') {
            throw ValidationException::withMessages([
                'member' => 'Anggota sedang diblokir dari peminjaman.',
            ]);
        }
    }
}
