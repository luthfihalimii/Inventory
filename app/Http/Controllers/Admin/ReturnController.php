<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyReturnRequest;
use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\ReturnInspection;
use App\Models\ReturnInspectionItem;
use App\Models\ReturnRequest;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\OverdueService;
use App\Services\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'pending');

        return Inertia::render('admin/return/Index', [
            'requests' => ReturnRequest::query()
                ->with([
                    'loanTransaction:id,borrow_request_id,status,due_at',
                    'loanTransaction.borrowRequest:id,reference_no',
                    'loanTransaction.member:id,name,nrp',
                    'requester:id,name',
                ])
                ->when(
                    in_array($status, ['pending', 'verified', 'cancelled'], true),
                    fn ($query) => $query->where('status', $status),
                )
                ->latest('requested_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['status' => is_string($status) ? $status : ''],
        ]);
    }

    public function show(ReturnRequest $returnRequest): Response
    {
        $returnRequest->load([
            'loanTransaction:id,borrow_request_id,member_id,status,checked_out_at,due_at',
            'loanTransaction.borrowRequest:id,reference_no,pickup_at,return_at',
            'loanTransaction.member:id,name,nrp',
            'loanTransaction.member.division:id,name',
            'loanTransaction.items.item:id,code,name,tracking_method',
            'loanTransaction.items.asset:id,item_id,asset_code,condition,status',
            'loanTransaction.handover',
            'inspection.items',
        ]);

        return Inertia::render('admin/return/Show', [
            'request' => [
                ...$returnRequest->only(['id', 'status', 'requested_at', 'notes', 'cancelled_reason', 'verified_at']),
                'loan' => [
                    'id' => $returnRequest->loanTransaction->id,
                    'status' => $returnRequest->loanTransaction->status,
                    'checked_out_at' => $returnRequest->loanTransaction->checked_out_at,
                    'due_at' => $returnRequest->loanTransaction->due_at,
                    'reference_no' => $returnRequest->loanTransaction->borrowRequest?->reference_no,
                    'member' => [
                        ...$returnRequest->loanTransaction->member->only(['name', 'nrp']),
                        'division' => $returnRequest->loanTransaction->member->division?->name,
                    ],
                ],
                'items' => $returnRequest->loanTransaction->items->map(fn (LoanItem $loanItem) => [
                    'id' => $loanItem->id,
                    'code' => $loanItem->item?->code,
                    'name' => $loanItem->item?->name,
                    'tracking_method' => $loanItem->item?->tracking_method,
                    'quantity' => $loanItem->quantity,
                    'quantity_returned' => $loanItem->quantity_returned,
                    'condition_out' => $loanItem->condition_out,
                    'asset' => $loanItem->asset
                        ? $loanItem->asset->only(['id', 'asset_code', 'condition'])
                        : null,
                ]),
                'inspection' => $returnRequest->inspection?->items->map(fn (ReturnInspectionItem $item) => [
                    'loan_item_id' => $item->loan_item_id,
                    'condition' => $item->condition,
                    'quantity_returned' => $item->quantity_returned,
                    'notes' => $item->notes,
                ]),
                'inspectionPhotos' => $returnRequest->inspection->photos ?? [],
                'handoverPhotos' => $returnRequest->loanTransaction->handover->photos ?? [],
            ],
            'canVerify' => $returnRequest->status === 'pending',
        ]);
    }

    public function verify(
        VerifyReturnRequest $request,
        ReturnRequest $returnRequest,
        AuditLogger $audit,
        OverdueService $overdue,
        NotificationService $notifications,
        PhotoStorage $photoStorage,
    ): RedirectResponse {
        $user = $request->user();
        $rows = $request->items();
        $notes = $request->notes();
        $photoFiles = $request->file('photos', []);
        $photoFiles = is_array($photoFiles) ? $photoFiles : [];

        /** @var array{0: int, 1: int, 2: bool} $notify */
        $notify = DB::transaction(function () use ($rows, $notes, $user, $returnRequest, $audit, $overdue, $photoStorage, $photoFiles): array {
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'items' => 'Pengembalian ini sudah diproses.',
                ]);
            }

            $loan = LoanTransaction::whereKey($locked->loan_transaction_id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'return_pending_verification') {
                throw ValidationException::withMessages([
                    'items' => 'Transaksi tidak sedang menunggu verifikasi.',
                ]);
            }

            $loanItems = $loan->items()
                ->with('item:id,code,name,tracking_method')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $grouped = [];

            foreach ($rows as $row) {
                $grouped[$row['loan_item_id']][] = $row;
            }

            foreach ($grouped as $loanItemId => $group) {
                if (! $loanItems->has($loanItemId)) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada baris inspeksi yang bukan milik transaksi ini.',
                    ]);
                }

                $loanItem = $loanItems[$loanItemId];
                $total = array_sum(array_column($group, 'quantity_returned'));
                $remaining = $loanItem->quantity - $loanItem->quantity_returned;

                // Partial returns are allowed: unreturned quantity stays open on the loan.
                if ($total > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "Sisa {$loanItem->item?->name} hanya {$remaining}, bukan {$total}.",
                    ]);
                }
            }

            $inspection = ReturnInspection::create([
                'return_request_id' => $locked->id,
                'officer_user_id' => $user->id,
                'inspected_at' => now(),
                'notes' => $notes,
                'photos' => $photoStorage->store($photoFiles, "returns/{$locked->id}") ?: null,
            ]);

            foreach ($rows as $row) {
                ReturnInspectionItem::create([
                    'return_inspection_id' => $inspection->id,
                    'loan_item_id' => $row['loan_item_id'],
                    'condition' => $row['condition'],
                    'quantity_returned' => $row['quantity_returned'],
                    'notes' => $row['notes'] ?? null,
                ]);

                $this->applyCondition(
                    $loanItems[$row['loan_item_id']],
                    $row['condition'],
                    (int) $row['quantity_returned'],
                    $loan,
                    $user->id,
                    $audit,
                    $row['notes'],
                );

                $loanItems[$row['loan_item_id']]
                    ->increment('quantity_returned', (int) $row['quantity_returned']);
            }

            $fullyReturned = ! $loan->items()
                ->whereColumn('quantity_returned', '<', 'quantity')
                ->exists();

            $locked->update(['status' => 'verified', 'verified_at' => now()]);

            if ($fullyReturned) {
                $loan->update(['status' => 'completed', 'returned_at' => now(), 'completed_at' => now()]);

                // §9.4: close the overdue block for this loan; eligible only when nothing else is open.
                $overdue->releaseForLoan($loan, 'Transaksi selesai dan diverifikasi.');
            } else {
                // The member still holds units, so the block (if any) stays until everything is back.
                $loan->update(['status' => $loan->overdue_at ? 'overdue' : 'borrowed']);

                $audit->record(
                    'loan.partially_returned',
                    $loan,
                    ['reference_no' => $loan->borrowRequest?->reference_no],
                    ['status' => 'return_pending_verification'],
                    ['status' => $loan->status],
                );
            }

            $audit->record(
                'return.verified',
                $locked,
                [
                    'loan_transaction_id' => $loan->id,
                    'items' => array_map(fn (array $row) => [
                        'loan_item_id' => $row['loan_item_id'],
                        'condition' => $row['condition'],
                        'quantity' => $row['quantity_returned'],
                    ], $rows),
                ],
                ['status' => 'pending'],
                ['status' => 'verified'],
            );

            if ($fullyReturned) {
                $audit->record(
                    'loan.completed',
                    $loan,
                    ['reference_no' => $loan->borrowRequest?->reference_no],
                    ['status' => 'return_pending_verification'],
                    ['status' => 'completed'],
                );
            }

            return [$loan->member_id, $loan->id, $fullyReturned];
        });

        [$memberId, $loanId, $fullyReturned] = $notify;

        $notifications->notifyMember(
            $memberId,
            $fullyReturned ? 'return.verified' : 'return.partially_verified',
            $fullyReturned ? 'Pengembalian diverifikasi' : 'Pengembalian sebagian diverifikasi',
            $fullyReturned
                ? 'Transaksi selesai. Jika tidak ada pinjaman terlambat lain, status peminjaman kembali normal.'
                : 'Barang yang sudah kembali dicatat. Sisa pinjaman masih berjalan; kembalikan sisanya lalu ajukan pengembalian lagi.',
            ['loan_transaction_id' => $loanId, 'return_request_id' => $returnRequest->id],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $fullyReturned
                ? 'Pengembalian diverifikasi.'
                : 'Pengembalian sebagian diverifikasi; sisa pinjaman masih terbuka.',
        ]);

        return redirect()->route('admin.returns.index');
    }

    public function cancel(
        Request $request,
        ReturnRequest $returnRequest,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        DB::transaction(function () use ($data, $returnRequest, $audit): void {
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'reason' => 'Pengembalian ini sudah diproses.',
                ]);
            }

            $loan = LoanTransaction::whereKey($locked->loan_transaction_id)->lockForUpdate()->firstOrFail();

            $locked->update(['status' => 'cancelled', 'cancelled_reason' => $data['reason']]);
            $loan->update(['status' => $loan->overdue_at ? 'overdue' : 'borrowed']);

            $audit->record(
                'return.cancelled',
                $locked,
                ['reason' => $data['reason']],
                ['status' => 'pending'],
                ['status' => 'cancelled'],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengembalian dibatalkan.']);

        return redirect()->route('admin.returns.index');
    }

    private function applyCondition(
        LoanItem $loanItem,
        string $condition,
        int $quantity,
        LoanTransaction $loan,
        int $officerUserId,
        AuditLogger $audit,
        ?string $notes = null,
    ): void {
        if ($loanItem->asset_id) {
            $asset = Asset::whereKey($loanItem->asset_id)->lockForUpdate()->firstOrFail();

            if ($condition === 'good') {
                // A unit reported damaged from the inventory screen must not become bookable again
                // just because it came back in one piece.
                $hasOpenReport = DamageReport::query()
                    ->where('asset_id', $asset->id)
                    ->where('status', 'open')
                    ->exists();

                $asset->update($hasOpenReport
                    ? ['status' => 'maintenance']
                    : ['status' => 'available', 'condition' => 'good']);

                return;
            }

            $asset->update(match ($condition) {
                'damaged' => ['status' => 'damaged', 'condition' => 'damaged'],
                'lost' => ['status' => 'lost', 'condition' => 'damaged'],
                default => ['status' => 'maintenance', 'condition' => 'damaged'],
            });

            $this->openDamageReport($loan, $loanItem, $condition, 1, $asset->id, $officerUserId, $audit, $notes);

            return;
        }

        if ($condition === 'good') {
            return;
        }

        $this->openDamageReport($loan, $loanItem, $condition, $quantity, null, $officerUserId, $audit, $notes);
    }

    private function openDamageReport(
        LoanTransaction $loan,
        LoanItem $loanItem,
        string $condition,
        int $quantity,
        ?int $assetId,
        int $officerUserId,
        AuditLogger $audit,
        ?string $notes = null,
    ): void {
        $report = DamageReport::create([
            'loan_transaction_id' => $loan->id,
            'loan_item_id' => $loanItem->id,
            'item_id' => $loanItem->item_id,
            'asset_id' => $assetId,
            'member_id' => $loan->member_id,
            'type' => match ($condition) {
                'lost' => 'loss',
                'incomplete' => 'incomplete',
                default => 'damage',
            },
            'quantity' => $quantity,
            'description' => trim("Inspeksi pengembalian: kondisi {$condition}. ".($notes ?? '')),
            'status' => 'open',
            'reported_by_user_id' => $officerUserId,
            'reported_at' => now(),
        ]);

        $audit->record(
            'damage.reported',
            $report,
            [
                'item_id' => $loanItem->item_id,
                'asset_id' => $assetId,
                'quantity' => $quantity,
                'condition' => $condition,
            ],
        );
    }
}
