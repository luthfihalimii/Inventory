<?php

namespace App\Http\Controllers\Borrowing;

use App\Http\Controllers\Controller;
use App\Models\LoanTransaction;
use App\Models\ReturnRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function store(Request $request, LoanTransaction $loanTransaction, AuditLogger $audit): RedirectResponse
    {
        // Either the member the units were issued to, or whoever filed the request on their behalf.
        $user = $request->user();

        $isBorrower = $user->member_id === $loanTransaction->member_id;
        $isCreator = $loanTransaction->borrowRequest()
            ->where('created_by_user_id', $user->id)
            ->exists();

        abort_unless($isBorrower || $isCreator, 403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($loanTransaction, $request, $data, $audit): void {
            $loan = LoanTransaction::whereKey($loanTransaction->id)->lockForUpdate()->firstOrFail();

            if (! in_array($loan->status, ['borrowed', 'overdue'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Transaksi ini tidak bisa diajukan pengembaliannya.',
                ]);
            }

            if ($loan->returnRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Pengembalian sudah diajukan.',
                ]);
            }

            $returnRequest = ReturnRequest::create([
                'loan_transaction_id' => $loan->id,
                'requested_by_user_id' => $request->user()->id,
                'requested_at' => now(),
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            $beforeStatus = $loan->status;

            $loan->update(['status' => 'return_pending_verification']);

            $audit->record(
                'return.requested',
                $returnRequest,
                ['loan_transaction_id' => $loan->id],
                ['status' => $beforeStatus],
                ['status' => 'pending'],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengembalian diajukan.']);

        return back();
    }
}
