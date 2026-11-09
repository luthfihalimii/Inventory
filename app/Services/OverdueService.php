<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OverdueService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ReservationService $reservations,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Flag every overdue loan and open its member block. Safe to run repeatedly.
     *
     * @return array{overdue: int, blocks: int}
     */
    public function markOverdue(): array
    {
        $now = now();

        $loans = LoanTransaction::query()
            ->whereIn('status', ['borrowed', 'return_pending_verification'])
            ->whereNull('overdue_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('id')
            ->get();

        $flagged = 0;

        foreach ($loans as $loan) {
            /** @var array{id: int, member_id: int}|null $notify */
            $notify = DB::transaction(function () use ($loan, $now): ?array {
                $locked = LoanTransaction::whereKey($loan->id)->lockForUpdate()->firstOrFail();

                // Re-check under the lock: a concurrent return may have completed it.
                if ($locked->overdue_at !== null || $locked->due_at === null || $locked->due_at->gte($now)) {
                    return null;
                }

                if (! in_array($locked->status, ['borrowed', 'return_pending_verification'], true)) {
                    return null;
                }

                $before = ['status' => $locked->status, 'overdue_at' => null];

                // A loan already awaiting verification keeps its status; only the timestamp is filled.
                $attributes = $locked->status === 'borrowed'
                    ? ['status' => 'overdue', 'overdue_at' => $now]
                    : ['overdue_at' => $now];

                $locked->update($attributes);

                $this->openBlock($locked, $now);

                $this->audit->record(
                    'loan.overdue',
                    $locked,
                    ['due_at' => $locked->due_at?->toIso8601String()],
                    $before,
                    ['status' => $locked->status, 'overdue_at' => $now->toIso8601String()],
                );

                return ['id' => $locked->id, 'member_id' => $locked->member_id];
            });

            if ($notify === null) {
                continue;
            }

            $flagged++;

            // §12: notification runs after commit, so a rollback never leaves a phantom notice.
            $this->notifications->notifyMember(
                $notify['member_id'],
                'loan.overdue',
                'Pinjaman terlambat',
                'Batas pengembalian sudah lewat. Pengajuan baru diblokir sampai barang dikembalikan dan diverifikasi.',
                ['loan_transaction_id' => $notify['id']],
            );
        }

        return [
            'overdue' => $flagged,
            'blocks' => MemberBorrowingBlock::whereNull('ended_at')->count(),
        ];
    }

    /**
     * Cancel approved-but-unclaimed requests whose pickup day has passed.
     *
     * @return int number of requests cancelled
     */
    public function expireUnclaimedReservations(): int
    {
        $expired = 0;

        $loans = LoanTransaction::query()
            ->where('status', 'reserved')
            ->whereHas('borrowRequest', fn ($query) => $query->where('status', 'approved'))
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            /** @var array{id: int, member_id: int}|null $notify */
            $notify = DB::transaction(function () use ($loan): ?array {
                $locked = LoanTransaction::whereKey($loan->id)->lockForUpdate()->firstOrFail();
                $borrowRequest = $locked->borrowRequest()->lockForUpdate()->first();

                if ($locked->status !== 'reserved' || $borrowRequest === null || $borrowRequest->status !== 'approved') {
                    return null;
                }

                if (now()->lt($this->pickupDeadline($borrowRequest))) {
                    return null;
                }

                $reason = 'Reservasi kedaluwarsa: barang tidak diambil sampai akhir tanggal pengambilan.';

                $this->reservations->release($borrowRequest);

                $locked->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);

                $borrowRequest->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);

                $this->audit->record(
                    'borrow_request.expired',
                    $borrowRequest,
                    ['reference_no' => $borrowRequest->reference_no],
                    ['status' => 'approved'],
                    ['status' => 'cancelled'],
                );

                return ['id' => $borrowRequest->id, 'member_id' => $borrowRequest->member_id];
            });

            if ($notify === null) {
                continue;
            }

            $expired++;

            // Expiry never blocks the member: the units were never handed over.
            $this->notifications->notifyMember(
                $notify['member_id'],
                'borrow_request.expired',
                'Pengajuan kedaluwarsa',
                'Barang tidak diambil sampai akhir tanggal pengambilan, jadi pengajuan dibatalkan otomatis.',
                ['borrow_request_id' => $notify['id']],
            );
        }

        return $expired;
    }

    /**
     * Synchronous guard for request submission: scheduler lag must not be a loophole.
     */
    public function syncMember(Member $member): void
    {
        $overdueIds = LoanTransaction::query()
            ->where('member_id', $member->id)
            ->whereIn('status', ['borrowed', 'return_pending_verification'])
            ->whereNull('overdue_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->pluck('id');

        foreach ($overdueIds as $loanId) {
            $flagged = DB::transaction(function () use ($loanId): bool {
                $locked = LoanTransaction::whereKey($loanId)->lockForUpdate()->first();

                if ($locked === null || $locked->overdue_at !== null || $locked->due_at === null || $locked->due_at->gte(now())) {
                    return false;
                }

                if (! in_array($locked->status, ['borrowed', 'return_pending_verification'], true)) {
                    return false;
                }

                $before = ['status' => $locked->status, 'overdue_at' => null];

                $locked->update($locked->status === 'borrowed'
                    ? ['status' => 'overdue', 'overdue_at' => now()]
                    : ['overdue_at' => now()]);

                $this->openBlock($locked, now());

                $this->audit->record(
                    'loan.overdue',
                    $locked,
                    ['source' => 'submit_check'],
                    $before,
                    ['status' => $locked->status, 'overdue_at' => now()->toIso8601String()],
                );

                return true;
            });

            if ($flagged) {
                $this->notifications->notifyMember(
                    $member->id,
                    'loan.overdue',
                    'Pinjaman terlambat',
                    'Batas pengembalian sudah lewat. Pengajuan baru diblokir sampai barang dikembalikan dan diverifikasi.',
                    ['loan_transaction_id' => $loanId],
                );
            }
        }
    }

    public function hasActiveBlock(Member $member): bool
    {
        return MemberBorrowingBlock::where('member_id', $member->id)
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * Close blocks for a finished loan and restore eligibility when nothing else is open.
     */
    public function releaseForLoan(LoanTransaction $loan, ?string $notes = null): void
    {
        $open = $loan->borrowingBlocks()->whereNull('ended_at')->get();

        if ($open->isEmpty()) {
            return;
        }

        foreach ($open as $block) {
            $block->update(['ended_at' => now(), 'notes' => $notes]);

            $this->audit->record(
                'member_block.released',
                $block,
                ['member_id' => $block->member_id, 'loan_transaction_id' => $loan->id],
                ['ended_at' => null],
                ['ended_at' => now()->toIso8601String()],
            );
        }

        // Lock the member row so concurrent verifications serialize: the loser re-reads the
        // already-committed release instead of leaving the member blocked forever.
        $member = Member::whereKey($loan->member_id)->lockForUpdate()->first();

        if ($member === null) {
            return;
        }

        $stillBlocked = MemberBorrowingBlock::where('member_id', $loan->member_id)
            ->whereNull('ended_at')
            ->exists();

        if ($stillBlocked) {
            return;
        }

        // A loan that went past due inside this scheduler window has no block yet, but the
        // member must stay blocked until it is returned too.
        $otherOverdue = LoanTransaction::where('member_id', $loan->member_id)
            ->whereKeyNot($loan->id)
            ->whereIn('status', ['borrowed', 'return_pending_verification'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->exists();

        if ($otherOverdue) {
            return;
        }

        $before = ['borrowing_status' => $member->borrowing_status];

        $member->update(['borrowing_status' => 'eligible']);

        if ($before['borrowing_status'] !== 'eligible') {
            $this->audit->record(
                'member.unblocked',
                $member,
                [],
                $before,
                ['borrowing_status' => 'eligible'],
            );
        }
    }

    /**
     * @return Collection<int, LoanTransaction>
     */
    public function activeBlocksFor(Member $member): Collection
    {
        return LoanTransaction::query()
            ->with('borrowRequest:id,reference_no')
            ->whereIn('id', MemberBorrowingBlock::where('member_id', $member->id)
                ->whereNull('ended_at')
                ->pluck('loan_transaction_id'))
            ->get();
    }

    /**
     * Last moment of the pickup day, in the app timezone.
     */
    public function pickupDeadline(BorrowRequest $borrowRequest): CarbonInterface
    {
        return $borrowRequest->pickup_at->copy()->endOfDay();
    }

    private function openBlock(LoanTransaction $loan, CarbonInterface $at): void
    {
        // Mark the member first so eligibility checks see the block even before the row exists.
        Member::whereKey($loan->member_id)->update(['borrowing_status' => 'borrowing_blocked']);

        $exists = MemberBorrowingBlock::where('loan_transaction_id', $loan->id)
            ->whereNull('ended_at')
            ->exists();

        if ($exists) {
            return;
        }

        MemberBorrowingBlock::create([
            'member_id' => $loan->member_id,
            'loan_transaction_id' => $loan->id,
            'reason' => 'Terlambat mengembalikan barang.',
            'started_at' => $at,
            'notes' => null,
        ]);
    }
}
