<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanExtensionRequest;
use App\Models\LoanTransaction;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExtensionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/extensions/Index', [
            'extensions' => LoanExtensionRequest::query()
                ->with([
                    'loan:id,borrow_request_id,member_id,status,due_at',
                    'loan.borrowRequest:id,reference_no',
                    'loan.member:id,name,nrp',
                    'requester:id,name',
                    'decider:id,name',
                ])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function approve(
        Request $request,
        LoanExtensionRequest $extension,
        AvailabilityService $availability,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        $notes = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ])['decision_notes'] ?? null;

        /** @var array{member_id: int, loan_id: int, due_at: string} $result */
        $result = DB::transaction(function () use ($extension, $request, $notes, $availability, $audit): array {
            $locked = LoanExtensionRequest::whereKey($extension->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['extension' => 'Pengajuan ini sudah diproses.']);
            }

            $loan = LoanTransaction::whereKey($locked->loan_transaction_id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'borrowed' || $loan->due_at === null) {
                throw ValidationException::withMessages([
                    'extension' => 'Hanya pinjaman aktif yang bisa diperpanjang.',
                ]);
            }

            if (now()->gte($loan->due_at)) {
                throw ValidationException::withMessages([
                    'extension' => 'Tenggat sudah terlewat; perpanjangan tidak bisa disetujui.',
                ]);
            }

            if ($locked->requested_due_at->lessThanOrEqualTo($loan->due_at)) {
                throw ValidationException::withMessages([
                    'extension' => 'Tenggat baru tidak valid.',
                ]);
            }

            $loan->load('items.item');

            foreach ($loan->items as $line) {
                $remaining = $line->quantity - $line->quantity_returned;

                if ($remaining < 1) {
                    continue;
                }

                $available = $availability->availableForPeriod(
                    $line->item,
                    $loan->due_at,
                    $locked->requested_due_at,
                );

                if ($available < $remaining) {
                    throw ValidationException::withMessages([
                        'extension' => "Stok {$line->item?->name} tidak cukup untuk periode perpanjangan (tersedia {$available}, dibutuhkan {$remaining}).",
                    ]);
                }
            }

            $loan->update([
                'due_at' => $locked->requested_due_at,
                'due_reminder_sent_at' => null,
            ]);

            $locked->update([
                'status' => 'approved',
                'decided_by_user_id' => $request->user()->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ]);

            $audit->record(
                'loan.extension_approved',
                $locked,
                ['loan_transaction_id' => $loan->id],
                ['due_at' => null],
                ['due_at' => $locked->requested_due_at->toIso8601String()],
            );

            return [
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
                'due_at' => $locked->requested_due_at->toDateTimeString(),
            ];
        });

        $notifications->notifyMember(
            $result['member_id'],
            'loan.extension_approved',
            'Perpanjangan disetujui',
            "Tenggat baru: {$result['due_at']}.",
            ['loan_transaction_id' => $result['loan_id'], 'extension_id' => $extension->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perpanjangan disetujui.']);

        return back();
    }

    public function reject(
        Request $request,
        LoanExtensionRequest $extension,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        $notes = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ])['decision_notes'] ?? null;

        $memberId = DB::transaction(function () use ($extension, $request, $notes, $audit): int {
            $locked = LoanExtensionRequest::whereKey($extension->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['extension' => 'Pengajuan ini sudah diproses.']);
            }

            $locked->update([
                'status' => 'rejected',
                'decided_by_user_id' => $request->user()->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ]);

            $audit->record(
                'loan.extension_rejected',
                $locked,
                ['loan_transaction_id' => $locked->loan_transaction_id],
                ['status' => 'pending'],
                ['status' => 'rejected'],
            );

            return LoanTransaction::whereKey($locked->loan_transaction_id)->value('member_id');
        });

        $notifications->notifyMember(
            (int) $memberId,
            'loan.extension_rejected',
            'Perpanjangan ditolak',
            $notes ?? 'Pengajuan perpanjangan tidak disetujui. Kembalikan barang sesuai tenggat.',
            ['loan_transaction_id' => $extension->loan_transaction_id, 'extension_id' => $extension->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perpanjangan ditolak.']);

        return back();
    }
}
