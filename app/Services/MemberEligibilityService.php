<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

class MemberEligibilityService
{
    public function __construct(private readonly OverdueService $overdue) {}

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

        // Scheduler lag must not be a loophole: flag newly overdue loans before deciding.
        $this->overdue->syncMember($member);
        $member->refresh();

        if ($member->borrowing_status !== 'eligible' || $this->overdue->hasActiveBlock($member)) {
            throw ValidationException::withMessages([
                'member' => 'Anggota sedang diblokir karena pinjaman terlambat.',
            ]);
        }
    }

    /**
     * Human-readable block summary for the request form.
     *
     * @return array<int, array{reference_no: string|null, due_at: string|null}>
     */
    public function activeBlockSummary(Member $member): array
    {
        return $this->overdue->activeBlocksFor($member)
            ->map(fn ($loan) => [
                'reference_no' => $loan->borrowRequest?->reference_no,
                'due_at' => $loan->due_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
