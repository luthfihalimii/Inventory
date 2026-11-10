<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCheckoutRequest;
use App\Models\Asset;
use App\Models\Item;
use App\Models\LoanTransaction;
use App\Models\Reservation;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\PhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('q');
        $search = is_string($search) ? trim($search) : '';

        $transactions = LoanTransaction::query()
            ->where('status', 'reserved')
            ->with([
                'borrowRequest:id,reference_no,pickup_at,return_at',
                'member:id,name,nrp',
            ])
            ->when($search !== '', fn ($query) => $query->whereHas(
                'borrowRequest',
                fn ($query) => $query->where('reference_no', 'ilike', "%{$search}%"),
            ))
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/checkout/Index', [
            'transactions' => $transactions,
            'filters' => ['q' => $search],
        ]);
    }

    public function show(LoanTransaction $loanTransaction): Response
    {
        $loanTransaction->load([
            'borrowRequest:id,reference_no,pickup_at,return_at,status,usage_location,purpose',
            'member:id,name,nrp',
            'member.division:id,name',
            'reservations' => fn ($query) => $query
                ->where('status', 'active')
                ->with([
                    'item:id,code,name,tracking_method',
                    'asset:id,item_id,asset_code,condition,status',
                ])
                ->orderBy('item_id')
                ->orderBy('id'),
        ]);

        return Inertia::render('admin/checkout/Show', [
            'transaction' => [
                ...$loanTransaction->only(['id', 'status', 'checked_out_at', 'due_at']),
                'reference_no' => $loanTransaction->borrowRequest->reference_no,
                'pickup_at' => $loanTransaction->borrowRequest->pickup_at,
                'return_at' => $loanTransaction->borrowRequest->return_at,
                'usage_location' => $loanTransaction->borrowRequest->usage_location,
                'member' => [
                    ...$loanTransaction->member->only(['name', 'nrp']),
                    'division' => $loanTransaction->member->division?->name,
                ],
                'lines' => $loanTransaction->reservations->map(fn (Reservation $reservation) => [
                    'reservation_id' => $reservation->id,
                    'item_id' => $reservation->item_id,
                    'code' => $reservation->item?->code,
                    'name' => $reservation->item?->name,
                    'tracking_method' => $reservation->item?->tracking_method,
                    'quantity' => $reservation->quantity,
                    'asset' => $reservation->asset
                        ? $reservation->asset->only(['id', 'asset_code', 'condition'])
                        : null,
                ]),
            ],
            'canCheckout' => $loanTransaction->status === 'reserved'
                && $loanTransaction->borrowRequest->status === 'approved'
                && now()->lt($loanTransaction->borrowRequest->return_at),
        ]);
    }

    /**
     * Read-only token validation. Never changes state.
     */
    public function scan(Request $request, LoanTransaction $loanTransaction): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);
        $token = trim($data['token']);

        $loanTransaction->loadMissing('borrowRequest');

        if ($loanTransaction->status !== 'reserved' || $loanTransaction->borrowRequest->status !== 'approved') {
            return $this->invalid('Transaksi tidak siap checkout.');
        }

        $reservations = $loanTransaction->reservations()
            ->where('status', 'active')
            ->get();

        $asset = Asset::query()
            ->where('qr_token', $token)
            ->with('item:id,code,name,tracking_method')
            ->first();

        if ($asset) {
            $line = $reservations->firstWhere('asset_id', $asset->id)
                ?? $reservations->first(fn (Reservation $reservation) => $reservation->asset_id !== null
                    && $reservation->item_id === $asset->item_id);

            if (! $line) {
                return $this->invalid('Unit bukan bagian dari pengajuan ini.');
            }

            if ($asset->status !== 'available') {
                return $this->invalid("Unit tidak tersedia ({$asset->status}).");
            }

            $conflict = Reservation::query()
                ->where('asset_id', $asset->id)
                ->where('status', 'active')
                ->whereKeyNot($line->id)
                ->where('start_at', '<', $line->end_at)
                ->where('end_at', '>', $line->start_at)
                ->exists();

            if ($conflict) {
                return $this->invalid('Unit sudah direservasi untuk periode lain.');
            }

            return response()->json([
                'valid' => true,
                'type' => 'asset',
                'reservation_id' => $line->id,
                'swap' => $line->asset_id !== $asset->id,
                'asset' => $asset->only(['id', 'asset_code', 'condition']),
                'item' => $asset->item?->only(['id', 'code', 'name']),
            ]);
        }

        $item = Item::query()->where('qr_token', $token)->first();

        if ($item) {
            $line = $reservations->first(fn (Reservation $reservation) => $reservation->item_id === $item->id
                && $reservation->asset_id === null);

            if (! $line) {
                return $this->invalid('Barang bukan bagian dari pengajuan ini.');
            }

            return response()->json([
                'valid' => true,
                'type' => 'item',
                'reservation_id' => $line->id,
                'quantity' => max(0, $line->quantity - $line->quantity_fulfilled),
                'item' => $item->only(['id', 'code', 'name']),
            ]);
        }

        return $this->invalid('Token tidak dikenali.');
    }

    public function store(
        StoreCheckoutRequest $request,
        LoanTransaction $loanTransaction,
        AuditLogger $audit,
        NotificationService $notifications,
        PhotoStorage $photoStorage,
    ): RedirectResponse {
        $user = $request->user();
        $lines = $request->lines();
        $reason = $request->reason();
        $checklist = $request->checklist();
        $notes = $request->notes();
        $photoFiles = $request->file('photos', []);
        $photoFiles = is_array($photoFiles) ? $photoFiles : [];

        DB::transaction(function () use ($lines, $reason, $checklist, $notes, $user, $loanTransaction, $audit, $photoStorage, $photoFiles): void {
            $loan = LoanTransaction::whereKey($loanTransaction->id)->lockForUpdate()->firstOrFail();
            $borrowRequest = $loan->borrowRequest()->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'reserved' || $borrowRequest->status !== 'approved') {
                throw ValidationException::withMessages(['lines' => 'Transaksi tidak siap checkout.']);
            }

            if (now()->gte($borrowRequest->return_at)) {
                throw ValidationException::withMessages([
                    'lines' => 'Jadwal pengembalian sudah lewat; batalkan pengajuan lalu ajukan ulang.',
                ]);
            }

            $reservations = Reservation::query()
                ->where('borrow_request_id', $borrowRequest->id)
                ->where('status', 'active')
                ->orderBy('item_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $items = Item::query()
                ->whereIn('id', $reservations->pluck('item_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $submitted = collect($lines)->keyBy('reservation_id');
            $delivered = [];
            $scanResults = [];
            $isPartial = false;
            $isSwap = false;

            foreach ($submitted as $reservationId => $line) {
                $reservation = $reservations->get($reservationId);

                if (! $reservation) {
                    throw ValidationException::withMessages([
                        'lines' => 'Reservasi tidak cocok dengan transaksi ini.',
                    ]);
                }

                $item = $items[$reservation->item_id];

                if ($item->tracking_method === 'serialized') {
                    if ((int) $line['quantity'] !== 1) {
                        throw ValidationException::withMessages([
                            'lines' => "Unit {$item->name} harus diserahkan satu per satu.",
                        ]);
                    }

                    $asset = Asset::query()
                        ->whereKey($line['asset_id'] ?? 0)
                        ->where('item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $asset) {
                        throw ValidationException::withMessages([
                            'lines' => "Unit untuk {$item->name} tidak valid.",
                        ]);
                    }

                    if ($asset->status !== 'available') {
                        throw ValidationException::withMessages([
                            'lines' => "Unit {$asset->asset_code} tidak tersedia ({$asset->status}).",
                        ]);
                    }

                    if ($reservation->asset_id !== $asset->id) {
                        $conflict = Reservation::query()
                            ->where('asset_id', $asset->id)
                            ->where('status', 'active')
                            ->whereKeyNot($reservation->id)
                            ->where('start_at', '<', $reservation->end_at)
                            ->where('end_at', '>', $reservation->start_at)
                            ->exists();

                        if ($conflict) {
                            throw ValidationException::withMessages([
                                'lines' => "Unit {$asset->asset_code} sudah direservasi untuk periode lain.",
                            ]);
                        }

                        $isSwap = true;
                    }

                    $reservation->update([
                        'asset_id' => $asset->id,
                        'quantity_fulfilled' => 1,
                        'status' => 'fulfilled',
                    ]);

                    $asset->update(['status' => 'borrowed']);

                    $delivered[] = [
                        'item_id' => $item->id,
                        'asset_id' => $asset->id,
                        'quantity' => 1,
                        'condition_out' => $line['condition_out'],
                        'notes' => $line['notes'] ?? null,
                    ];
                    $scanResults[] = ['asset_id' => $asset->id, 'item_id' => $item->id];

                    continue;
                }

                $actual = (int) $line['quantity'];

                if ($actual > $reservation->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => "Jumlah {$item->name} melebihi yang direservasi.",
                    ]);
                }

                if ($actual < $reservation->quantity) {
                    $isPartial = true;
                }

                $reservation->update([
                    'quantity_fulfilled' => $actual,
                    'status' => 'fulfilled',
                ]);

                $delivered[] = [
                    'item_id' => $item->id,
                    'asset_id' => null,
                    'quantity' => $actual,
                    'condition_out' => $line['condition_out'],
                    'notes' => $line['notes'] ?? null,
                ];
                $scanResults[] = ['item_id' => $item->id, 'quantity' => $actual];
            }

            foreach ($reservations as $reservation) {
                if (! $submitted->has($reservation->id)) {
                    $reservation->update(['status' => 'released']);
                    $isPartial = true;
                }
            }

            if ($delivered === []) {
                throw ValidationException::withMessages([
                    'lines' => 'Minimal satu unit atau quantity harus diserahkan.',
                ]);
            }

            if (($isPartial || $isSwap) && $reason === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Alasan wajib diisi untuk partial checkout atau tukar unit.',
                ]);
            }

            foreach ($delivered as $row) {
                $loan->items()->create($row);
            }

            $loan->handover()->create([
                'officer_user_id' => $user->id,
                'borrower_member_id' => $loan->member_id,
                'handed_at' => now(),
                'checklist' => $checklist,
                'scan_results' => $scanResults,
                'photos' => $photoStorage->store($photoFiles, "handovers/{$loan->id}") ?: null,
                'borrower_confirmation_method' => 'on_site',
                'borrower_confirmed_at' => now(),
                // Spec §7.4: partial checkout and unit swaps require a recorded reason.
                'notes' => $reason === null
                    ? $notes
                    : trim("Alasan: {$reason}".($notes === null ? '' : "\n{$notes}")),
            ]);

            $loan->update(['status' => 'borrowed', 'checked_out_at' => now()]);

            $audit->record(
                'loan.checked_out',
                $loan,
                [
                    'lines' => count($delivered),
                    'partial' => $isPartial,
                    'swap' => $isSwap,
                    'reason' => $reason,
                ],
                ['status' => 'reserved'],
                ['status' => 'borrowed'],
            );
        });

        $notifications->notifyMember(
            $loanTransaction->member_id,
            'loan.checked_out',
            'Barang sudah diserahkan',
            'Barang pinjaman sudah diserahkan. Simpan baik-baik dan kembalikan sebelum tenggat.',
            ['loan_transaction_id' => $loanTransaction->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Barang diserahkan.']);

        return redirect()->route('admin.checkout.index');
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['valid' => false, 'message' => $message], 422);
    }
}
