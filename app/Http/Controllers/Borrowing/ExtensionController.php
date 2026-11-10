<?php

namespace App\Http\Controllers\Borrowing;

use App\Http\Controllers\Controller;
use App\Models\LoanTransaction;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExtensionController extends Controller
{
    public function store(Request $request, LoanTransaction $loanTransaction, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'requested_due_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'requested_due_at.required' => 'Tenggat baru wajib diisi.',
            'reason.required' => 'Alasan perpanjangan wajib diisi.',
        ]);

        abort_if($user->member_id !== $loanTransaction->member_id, 403);

        $requestedDue = Carbon::parse($data['requested_due_at']);

        DB::transaction(function () use ($loanTransaction, $requestedDue, $data, $user, $audit): void {
            $loan = LoanTransaction::whereKey($loanTransaction->id)->lockForUpdate()->firstOrFail();

            if ($loan->member_id !== $user->member_id) {
                abort(403);
            }

            if ($loan->status !== 'borrowed' || $loan->due_at === null || now()->gte($loan->due_at)) {
                throw ValidationException::withMessages([
                    'loan' => 'Perpanjangan hanya bisa diajukan sebelum tenggat dan saat barang masih dipinjam.',
                ]);
            }

            if ($requestedDue->lessThanOrEqualTo($loan->due_at)) {
                throw ValidationException::withMessages([
                    'requested_due_at' => 'Tenggat baru harus setelah tenggat saat ini.',
                ]);
            }

            if ($loan->extensionRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'loan' => 'Masih ada pengajuan perpanjangan yang menunggu keputusan.',
                ]);
            }

            $extension = $loan->extensionRequests()->create([
                'requested_by_user_id' => $user->id,
                'current_due_at' => $loan->due_at,
                'requested_due_at' => $requestedDue,
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);

            $audit->record(
                'loan.extension_requested',
                $extension,
                ['loan_transaction_id' => $loan->id],
                null,
                ['requested_due_at' => $requestedDue->toIso8601String()],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan perpanjangan terkirim.']);

        return back();
    }
}
