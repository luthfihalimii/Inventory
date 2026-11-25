<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\LoanDetail;
use App\Models\LoanDetailUnit;
use App\Models\LoanRequest;
use App\Models\LoanCancellationRequest;
use App\Models\LoanStatusHistory;
use App\Models\User;
use App\Notifications\LoanRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = $user->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();

        $baseQuery = LoanRequest::with([
            'user.member',
            'details.inventoryItem',
            'pendingCancellationRequest',
        ])->latest();

        if (! $isManager) {
            $baseQuery->where('user_id', $user->id);
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $baseQuery->where(function ($query) use ($search) {
                $query->where('kode_pengajuan', 'like', '%' . $search . '%')
                    ->orWhereHas('user.member', fn ($memberQuery) => $memberQuery
                        ->where('nama', 'like', '%' . $search . '%')
                        ->orWhere('kode', 'like', '%' . $search . '%'));
            });
        }

        if ($request->filled('status')) {
            if ($request->query('status') === 'CANCELLATION_REQUESTED') {
                $baseQuery->where('status', 'APPROVED')->whereHas('pendingCancellationRequest');
            } else {
                $baseQuery->where('status', $request->query('status'));
            }
        }

        if ($request->filled('kategori')) {
            $baseQuery->where('kategori', $request->query('kategori'));
        }

        if ($request->filled('from')) {
            $baseQuery->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $baseQuery->whereDate('created_at', '<=', $request->date('to'));
        }

        // Transaksi yang masih berada dalam alur operasional.
        $ongoingRequests = (clone $baseQuery)
            ->whereIn('status', ['PENDING', 'APPROVED', 'BORROWED', 'RETURN_PENDING', 'RETURN_ISSUE'])
            ->paginate(10, ['*'], 'ongoing_page')
            ->withQueryString();

        // Success history hanya berisi transaksi yang pengembaliannya sudah diverifikasi.
        $completedRequests = (clone $baseQuery)
            ->where('status', 'COMPLETED')
            ->paginate(10, ['*'], 'completed_page')
            ->withQueryString();

        // Transaksi yang tidak berlanjut tetap ditampilkan agar riwayat tidak hilang.
        $closedRequests = (clone $baseQuery)
            ->whereIn('status', ['REJECTED', 'CANCELLED'])
            ->paginate(10, ['*'], 'closed_page')
            ->withQueryString();

        return view('loan-requests.index', [
            'ongoingRequests' => $ongoingRequests,
            'completedRequests' => $completedRequests,
            'closedRequests' => $closedRequests,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $request->session()->get('loan_cart', []);
        if (empty($cart)) return redirect()->route('loan-cart.index')->with('error', 'Keranjang masih kosong.');
        if (! $request->session()->has('loan_sop_acceptance')) return redirect()->route('loan-cart.index')->with('error', 'Baca dan setujui SOP peminjaman sebelum checkout.');
        return view('loan-requests.create', [
            'items' => InventoryItem::where('status', 'ACTIVE')->whereIn('id', array_keys($cart))
                ->with(['units:id,inventory_item_id,kode_unit,status,kondisi'])
                ->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sopAcceptance = $request->session()->get('loan_sop_acceptance');
        if (! is_array($sopAcceptance) || empty($sopAcceptance['version']) || empty($sopAcceptance['accepted_at'])) {
            throw ValidationException::withMessages(['sop' => 'Persetujuan SOP belum tercatat. Kembali ke keranjang dan setujui SOP terlebih dahulu.']);
        }

        $cart = collect($request->session()->get('loan_cart', []))->map(fn ($qty) => (int) $qty)->sortKeys();
        $data = $request->validate([
            'kategori' => ['required', Rule::in(['URGENT', 'SCHEDULED'])],
            // Pengajuan URGENT boleh masuk meskipun waktu mulai sudah lewat,
            // selama waktu selesai belum lewat. SCHEDULED diperiksa H-2 di bawah.
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date', 'after:waktu_mulai', 'after:now'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        // Keranjang session menjadi sumber data resmi untuk item dan kuantitas.
        // Form checkout tidak mengirim items[] sehingga validasi wajib items
        // sebelumnya selalu gagal dengan pesan validation.required.
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Keranjang kosong. Tambahkan barang terlebih dahulu sebelum mengirim pengajuan.',
            ]);
        }

        $data['items'] = $cart->map(fn ($quantity, $inventoryItemId) => [
            'inventory_item_id' => (int) $inventoryItemId,
            'jumlah' => (int) $quantity,
        ])->values()->all();

        foreach ($data['items'] as $row) {
            if ($row['inventory_item_id'] < 1 || $row['jumlah'] < 1) {
                throw ValidationException::withMessages([
                    'items' => 'Data barang di keranjang tidak valid. Perbarui keranjang dan coba kembali.',
                ]);
            }
        }

        if (
            $data['kategori'] === 'SCHEDULED'
            && now()->addDays(2)->gt(\Illuminate\Support\Carbon::parse($data['waktu_mulai']))
        ) {
            throw ValidationException::withMessages([
                'waktu_mulai' => 'Peminjaman terjadwal harus diajukan minimal H-2.',
            ]);
        }

        $loan = DB::transaction(function () use ($request, $data, $sopAcceptance) {
            $itemIds = collect($data['items'])->pluck('inventory_item_id')->unique();

            if (
                InventoryItem::whereIn('id', $itemIds)->where('status', 'ACTIVE')->count()
                !== $itemIds->count()
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Terdapat inventaris yang tidak aktif.',
                ]);
            }


            $loan = LoanRequest::create([
                'kode_pengajuan' => 'ENT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'user_id' => $request->user()->id,
                'kategori' => $data['kategori'],
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'catatan' => $data['catatan'] ?? null,
                'status' => 'PENDING',
            ]);

            foreach ($data['items'] as $item) {
                $inventoryItem = InventoryItem::whereKey($item['inventory_item_id'])->lockForUpdate()->firstOrFail();
                $availableCount = InventoryUnit::where('inventory_item_id', $inventoryItem->id)
                    ->where('status', 'AVAILABLE')->where('kondisi', '!=', 'DAMAGED')->lockForUpdate()->get(['id'])->count();
                if ($item['jumlah'] > $availableCount) {
                    throw ValidationException::withMessages([
                        'items' => "Ketersediaan {$inventoryItem->nama} berubah. Saat ini hanya tersedia {$availableCount} unit. Sesuaikan keranjang.",
                    ]);
                }
                $loan->details()->create([
                    'inventory_item_id' => $item['inventory_item_id'],
                    'jumlah' => $item['jumlah'],
                ]);
            }

            $this->recordStatus($loan, null, 'PENDING', $request->user()->id);

            return $loan;
        });

        $this->writeAudit('CREATE', 'LoanRequest', $loan->id, null, [
            'kode_pengajuan' => $loan->kode_pengajuan,
            'kategori' => $loan->kategori,
            'status' => 'PENDING',
            'persetujuan_sop' => $sopAcceptance,
        ]);

        $this->notifyUsersWithRole('ADMIN', new LoanRequestNotification(
            'Pengajuan peminjaman baru',
            'Pengajuan ' . $loan->kode_pengajuan . ' menunggu pemeriksaan dan persetujuan Korlab.',
            'loan_submitted',
            $loan->id
        ));

        $request->session()->forget(['loan_cart', 'loan_sop_acceptance']);
        return redirect()->route('loan-requests.show', $loan)
            ->with('success', 'Pengajuan peminjaman berhasil dikirim.');
    }

    public function show(Request $request, LoanRequest $loanRequest): View
    {
        $isManager = $request->user()->roles()
            ->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();

        abort_unless($isManager || $loanRequest->user_id === $request->user()->id, 403);

        return view('loan-requests.show', [
            'loan' => $loanRequest->load([
                'user',
                'decisionMaker',
                    'returnRecord',
                'details.inventoryItem',
                'details.allocatedUnits.inventoryUnit',
                'details.allocationHistory.inventoryUnit',
                'statusHistories.changedBy',
                'comments.user',
                'cancellationRequests.requester',
                'cancellationRequests.reviewer',
                'pendingCancellationRequest',
            ]),
        ]);
    }

    /**
     * ADMIN menyetujui pengajuan.
     * Unit AVAILABLE dialokasikan terlebih dahulu.
     * Untuk URGENT, Korlab dapat memilih unit RESERVED melalui override_unit_ids[].
     */
    public function approve(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($this->hasRole($request, 'ADMIN'), 403);

        $data = $request->validate([
            'override_unit_ids' => ['nullable', 'array'],
            'override_unit_ids.*' => [
                'required', 'integer', 'distinct', 'exists:inventory_units,id',
            ],
        ]);

        if ($loanRequest->kategori !== 'URGENT' && ! empty($data['override_unit_ids'])) {
            throw ValidationException::withMessages([
                'override_unit_ids' => 'Pengalihan unit RESERVED hanya diperbolehkan untuk pengajuan URGENT.',
            ]);
        }

        $affectedLoans = [];
        $newLoanOwnerId = null;

        DB::transaction(function () use (
            $request,
            $loanRequest,
            $data,
            &$affectedLoans,
            &$newLoanOwnerId
        ) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'PENDING') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan PENDING yang dapat disetujui.',
                ]);
            }

            $newLoanOwnerId = $loan->user_id;
            $selectedIds = collect($data['override_unit_ids'] ?? [])->map(fn ($id) => (int) $id);
            $usedOverrideIds = collect();

            $details = $loan->details()->with('inventoryItem')->orderBy('id')->get();

            foreach ($details as $detail) {
                $needed = (int) $detail->jumlah;

                $availableUnits = InventoryUnit::where('inventory_item_id', $detail->inventory_item_id)
                    ->where('status', 'AVAILABLE')
                    ->where('kondisi', '!=', 'DAMAGED')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->limit($needed)
                    ->get();

                foreach ($availableUnits as $unit) {
                    $this->createActiveAllocation($detail, $unit);
                    $unit->update(['status' => 'RESERVED']);
                }

                $remaining = $needed - $availableUnits->count();

                if ($remaining > 0) {
                    if ($loan->kategori !== 'URGENT') {
                        throw ValidationException::withMessages([
                            'availability' => "Unit tersedia untuk {$detail->inventoryItem->nama} tidak mencukupi.",
                        ]);
                    }

                    $reservedUnits = InventoryUnit::where('inventory_item_id', $detail->inventory_item_id)
                        ->whereIn('id', $selectedIds)
                        ->where('status', 'RESERVED')
                        ->where('kondisi', '!=', 'DAMAGED')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($reservedUnits->count() !== $remaining) {
                        throw ValidationException::withMessages([
                            'override_unit_ids' =>
                                "Pilih tepat {$remaining} unit RESERVED untuk {$detail->inventoryItem->nama}.",
                        ]);
                    }

                    foreach ($reservedUnits as $unit) {
                        $oldAllocation = LoanDetailUnit::where('inventory_unit_id', $unit->id)
                            ->where('status', 'ACTIVE')
                            ->lockForUpdate()
                            ->first();

                        if (! $oldAllocation) {
                            throw ValidationException::withMessages([
                                'override_unit_ids' => "Unit {$unit->kode_unit} tidak memiliki alokasi aktif yang valid.",
                            ]);
                        }

                        $oldDetail = LoanDetail::whereKey($oldAllocation->loan_detail_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $oldLoan = LoanRequest::whereKey($oldDetail->loan_request_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $alreadyAffectedInThisApproval = isset($affectedLoans[$oldLoan->id]);

                        if (
                            $oldLoan->id === $loan->id
                            || (
                                $oldLoan->status !== 'APPROVED'
                                && ! ($oldLoan->status === 'NEEDS_ADJUSTMENT' && $alreadyAffectedInThisApproval)
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'override_unit_ids' =>
                                    "Unit {$unit->kode_unit} tidak berasal dari reservasi yang dapat dialihkan.",
                            ]);
                        }

                        $oldAllocation->update([
                            'status' => 'REALLOCATED',
                            'released_by' => $request->user()->id,
                            'released_at' => now(),
                            'release_reason' => 'Dialihkan untuk pengajuan URGENT ' . $loan->kode_pengajuan,
                        ]);

                        if ($oldLoan->status === 'APPROVED') {
                            $previousStatus = $oldLoan->status;
                            $oldLoan->update(['status' => 'NEEDS_ADJUSTMENT']);

                            $this->recordStatus(
                                $oldLoan,
                                $previousStatus,
                                'NEEDS_ADJUSTMENT',
                                $request->user()->id
                            );

                            $affectedLoans[$oldLoan->id] = [
                                'user_id' => $oldLoan->user_id,
                                'loan_request_id' => $oldLoan->id,
                                'kode_pengajuan' => $oldLoan->kode_pengajuan,
                                'unit_dialihkan' => [$unit->kode_unit],
                            ];

                        } elseif ($alreadyAffectedInThisApproval) {
                            $affectedLoans[$oldLoan->id]['unit_dialihkan'][] = $unit->kode_unit;
                        }

                        $this->createActiveAllocation($detail, $unit);
                        $usedOverrideIds->push((int) $unit->id);
                    }
                }
            }

            if ($usedOverrideIds->sort()->values()->all() !== $selectedIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'override_unit_ids' =>
                        'Ada unit pilihan yang tidak diperlukan atau tidak sesuai dengan barang yang diajukan.',
                ]);
            }

            foreach ($affectedLoans as $affectedLoanId => $affected) {
                $this->writeAudit(
                    'URGENT_REALLOCATION_IMPACT',
                    'LoanRequest',
                    (int) $affectedLoanId,
                    ['status' => 'APPROVED'],
                    [
                        'status' => 'NEEDS_ADJUSTMENT',
                        'unit_dialihkan' => $affected['unit_dialihkan'] ?? [],
                        'pengajuan_urgent' => $loan->kode_pengajuan,
                    ]
                );
            }

            $previousStatus = $loan->status;
            $loan->update([
                'status' => 'APPROVED',
                'diputuskan_oleh' => $request->user()->id,
                'waktu_keputusan' => now(),
            ]);

            $this->recordStatus($loan, $previousStatus, 'APPROVED', $request->user()->id);

            $this->writeAudit('APPROVE', 'LoanRequest', $loan->id,
                ['status' => 'PENDING'],
                [
                    'status' => 'APPROVED',
                    'unit_reserved' => true,
                    'unit_override_ids' => $usedOverrideIds->all(),
                ]
            );
        });

        foreach ($affectedLoans as $affected) {
            User::find($affected['user_id'])?->notify(new LoanRequestNotification(
                'Reservasi barang berubah',
                'Sebagian unit pada pengajuan ' . $affected['kode_pengajuan']
                    . ' dialihkan untuk kebutuhan urgent. Silakan koordinasikan penyesuaian dengan Korlab.',
                'reservation_reallocated',
                $affected['loan_request_id']
            ));
        }

        if ($newLoanOwnerId) {
            User::find($newLoanOwnerId)?->notify(new LoanRequestNotification(
                'Pengajuan disetujui',
                'Pengajuan ' . $loanRequest->kode_pengajuan . ' telah disetujui dan barang direservasi.',
                'loan_approved',
                $loanRequest->id
            ));
        }

        return back()->with('success', 'Pengajuan disetujui dan unit berhasil direservasi.');
    }

    public function resolveAdjustment(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($this->hasRole($request, 'ADMIN'), 403);

        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['required', 'array'],
            'allocations.*.*' => [
                'required', 'integer', 'distinct', 'exists:inventory_units,id',
            ],
        ]);

        DB::transaction(function () use ($request, $loanRequest, $data) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'NEEDS_ADJUSTMENT') {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan tidak sedang membutuhkan penyesuaian.',
                ]);
            }

            $allSelected = collect($data['allocations'])->flatten()->map(fn ($id) => (int) $id);
            if ($allSelected->count() !== $allSelected->unique()->count()) {
                throw ValidationException::withMessages([
                    'allocations' => 'Satu unit tidak boleh dipilih lebih dari satu kali.',
                ]);
            }

            $usedIds = collect();

            foreach ($loan->details()->with('inventoryItem')->orderBy('id')->get() as $detail) {
                $activeCount = $detail->allocatedUnits()->count();
                $shortage = (int) $detail->jumlah - $activeCount;
                $selectedForDetail = collect($data['allocations'][$detail->id] ?? [])
                    ->map(fn ($id) => (int) $id);

                if ($shortage < 0 || $selectedForDetail->count() !== $shortage) {
                    throw ValidationException::withMessages([
                        "allocations.{$detail->id}" =>
                            "Pilih tepat {$shortage} unit pengganti untuk {$detail->inventoryItem->nama}.",
                    ]);
                }

                $units = InventoryUnit::where('inventory_item_id', $detail->inventory_item_id)
                    ->whereIn('id', $selectedForDetail)
                    ->where('status', 'AVAILABLE')
                    ->where('kondisi', '!=', 'DAMAGED')
                    ->lockForUpdate()
                    ->get();

                if ($units->count() !== $shortage) {
                    throw ValidationException::withMessages([
                        "allocations.{$detail->id}" =>
                            'Unit pengganti harus tersedia dan sesuai dengan jenis inventaris.',
                    ]);
                }

                foreach ($units as $unit) {
                    $this->createActiveAllocation($detail, $unit);
                    $unit->update(['status' => 'RESERVED']);
                    $usedIds->push((int) $unit->id);
                }
            }

            if ($usedIds->sort()->values()->all() !== $allSelected->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'allocations' => 'Ada unit pilihan yang tidak digunakan atau tidak sesuai.',
                ]);
            }

            foreach ($loan->details as $detail) {
                if ($detail->allocatedUnits()->count() !== (int) $detail->jumlah) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Alokasi pengganti belum memenuhi seluruh kebutuhan pengajuan.',
                    ]);
                }
            }

            $oldStatus = $loan->status;
            $loan->update(['status' => 'APPROVED']);
            $this->recordStatus($loan, $oldStatus, 'APPROVED', $request->user()->id);

            $this->writeAudit('RESOLVE_ADJUSTMENT', 'LoanRequest', $loan->id,
                ['status' => 'NEEDS_ADJUSTMENT'],
                ['status' => 'APPROVED', 'unit_pengganti' => $usedIds->all()]
            );
        });

        $loanRequest->user?->notify(new LoanRequestNotification(
            'Penyesuaian pengajuan selesai',
            'Alokasi barang untuk pengajuan ' . $loanRequest->kode_pengajuan . ' telah dilengkapi kembali.',
            'loan_adjustment_resolved',
            $loanRequest->id
        ));

        return back()->with('success', 'Penyesuaian alokasi berhasil diselesaikan.');
    }

    public function reject(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($this->hasRole($request, 'ADMIN'), 403);

        $data = $request->validate([
            'alasan_penolakan' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $loanRequest, $data) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'PENDING') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan PENDING yang dapat ditolak.',
                ]);
            }

            $oldStatus = $loan->status;
            $loan->update([
                'status' => 'REJECTED',
                'diputuskan_oleh' => $request->user()->id,
                'waktu_keputusan' => now(),
                'alasan_penolakan' => $data['alasan_penolakan'],
            ]);

            $this->recordStatus($loan, $oldStatus, 'REJECTED', $request->user()->id);

            $this->writeAudit('REJECT', 'LoanRequest', $loan->id,
                ['status' => $oldStatus],
                ['status' => 'REJECTED', 'alasan_penolakan' => $data['alasan_penolakan']]
            );
        });

        $loanRequest->user?->notify(new LoanRequestNotification(
            'Pengajuan ditolak',
            'Pengajuan ' . $loanRequest->kode_pengajuan . ' ditolak. Alasan: ' . $data['alasan_penolakan'],
            'loan_rejected',
            $loanRequest->id
        ));

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function markBorrowed(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($this->hasRole($request, 'ADMIN'), 403);

        DB::transaction(function () use ($request, $loanRequest) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan APPROVED yang dapat diserahkan.',
                ]);
            }

            if (LoanCancellationRequest::where('loan_request_id', $loan->id)->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Penyerahan ditahan karena ada permohonan pembatalan yang masih menunggu keputusan Korlab.',
                ]);
            }

            foreach ($loan->details()->with('allocatedUnits')->get() as $detail) {
                $allocations = $detail->allocatedUnits;

                if ($allocations->count() !== (int) $detail->jumlah) {
                    throw ValidationException::withMessages([
                        'allocation' => 'Jumlah alokasi belum sesuai. Periksa penyesuaian pengajuan.',
                    ]);
                }

                foreach ($allocations as $allocation) {
                    $unit = InventoryUnit::whereKey($allocation->inventory_unit_id)
                        ->lockForUpdate()->firstOrFail();

                    if ($unit->status !== 'RESERVED') {
                        throw ValidationException::withMessages([
                            'allocation' => "Unit {$unit->kode_unit} tidak lagi berstatus RESERVED.",
                        ]);
                    }

                    $unit->update(['status' => 'BORROWED']);
                }
            }

            $oldStatus = $loan->status;
            $loan->update(['status' => 'BORROWED']);
            $this->recordStatus($loan, $oldStatus, 'BORROWED', $request->user()->id);

            $this->writeAudit('MARK_BORROWED', 'LoanRequest', $loan->id,
                ['status' => 'APPROVED'],
                ['status' => 'BORROWED']
            );
        });

        $loanRequest->user?->notify(new LoanRequestNotification(
            'Barang telah diserahkan',
            'Barang untuk pengajuan ' . $loanRequest->kode_pengajuan . ' telah dicatat diserahkan.',
            'loan_borrowed',
            $loanRequest->id
        ));

        return back()->with('success', 'Penyerahan fisik barang berhasil dicatat.');
    }

    /**
     * ADMIN dapat membatalkan pengajuan APPROVED sebelum penyerahan fisik.
     * Alasan wajib dicatat dan semua unit RESERVED dilepas dengan jejak alokasi.
     */
    public function cancelApproved(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($this->hasRole($request, 'ADMIN'), 403);

        $data = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $loanRequest, $data) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if (! in_array($loan->status, ['PENDING', 'APPROVED'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan hanya dapat dibatalkan oleh Korlab saat masih PENDING atau APPROVED dan barang belum diserahkan.',
                ]);
            }

            if (LoanCancellationRequest::where('loan_request_id', $loan->id)->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Permohonan pembatalan dari pengguna sedang menunggu keputusan. Proses melalui panel verifikasi permohonan tersebut.',
                ]);
            }

            foreach ($loan->details()->with('allocatedUnits')->get() as $detail) {
                foreach ($detail->allocatedUnits as $allocation) {
                    $unit = InventoryUnit::whereKey($allocation->inventory_unit_id)
                        ->lockForUpdate()->firstOrFail();

                    if ($unit->status !== 'RESERVED') {
                        throw ValidationException::withMessages([
                            'allocation' => "Unit {$unit->kode_unit} tidak lagi berstatus RESERVED. Pembatalan perlu diperiksa manual.",
                        ]);
                    }

                    $allocation->update([
                        'status' => 'RELEASED',
                        'released_by' => $request->user()->id,
                        'released_at' => now(),
                        'release_reason' => $data['alasan_pembatalan'],
                    ]);

                    $unit->update(['status' => 'AVAILABLE']);
                    $unit->synchronizeOperationalStatus();
                }
            }

            $oldStatus = $loan->status;
            $loan->update([
                'status' => 'CANCELLED',
                'alasan_pembatalan' => $data['alasan_pembatalan'],
                'dibatalkan_oleh' => $request->user()->id,
                'waktu_pembatalan' => now(),
            ]);

            $this->recordStatus($loan, $oldStatus, 'CANCELLED', $request->user()->id);
            $this->writeAudit('CANCEL_BY_ADMIN', 'LoanRequest', $loan->id,
                ['status' => $oldStatus],
                ['status' => 'CANCELLED', 'alasan_pembatalan' => $data['alasan_pembatalan']]
            );
        });

        $loanRequest->user?->notify(new LoanRequestNotification(
            'Pengajuan dibatalkan oleh Korlab',
            'Pengajuan ' . $loanRequest->kode_pengajuan . ' dibatalkan oleh Korlab. Alasan: ' . $data['alasan_pembatalan'],
            'loan_approved_cancelled',
            $loanRequest->id
        ));

        return back()->with('success', 'Pengajuan berhasil dibatalkan oleh Korlab. Reservasi unit yang terkait telah dilepas bila ada.');
    }

    /**
     * USER dapat membatalkan PENDING secara langsung. Untuk APPROVED, user
     * hanya dapat mengirim permohonan yang harus diputuskan ADMIN/Korlab.
     */
    public function cancel(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($loanRequest->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'alasan_pembatalan' => ['nullable', 'string', 'min:5', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($request, $loanRequest, $data) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loan->status === 'PENDING') {
                if ($loan->details()->whereHas('allocatedUnits', fn ($q) => $q->where('status', 'ACTIVE'))->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Pengajuan sedang memiliki alokasi aktif. Hubungi Korlab untuk bantuan pembatalan.',
                    ]);
                }
                $loan->update([
                    'status' => 'CANCELLED',
                    'alasan_pembatalan' => $data['alasan_pembatalan'] ?? null,
                    'dibatalkan_oleh' => $request->user()->id,
                    'waktu_pembatalan' => now(),
                ]);
                $this->recordStatus($loan, 'PENDING', 'CANCELLED', $request->user()->id);
                $this->writeAudit('CANCEL', 'LoanRequest', $loan->id, ['status' => 'PENDING'], [
                    'status' => 'CANCELLED', 'alasan_pembatalan' => $data['alasan_pembatalan'] ?? null,
                ]);
                return 'cancelled';
            }

            if ($loan->status !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'status' => 'Pembatalan hanya dapat dilakukan saat pengajuan masih menunggu persetujuan atau sudah disetujui tetapi belum diserahkan.',
                ]);
            }

            if (LoanCancellationRequest::where('loan_request_id', $loan->id)->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Permohonan pembatalan untuk pengajuan ini masih menunggu keputusan Korlab.',
                ]);
            }

            $reason = trim((string) ($data['alasan_pembatalan'] ?? ''));
            if (strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'alasan_pembatalan' => 'Tuliskan alasan pembatalan minimal 5 karakter.',
                ]);
            }

            $cancellation = LoanCancellationRequest::create([
                'loan_request_id' => $loan->id,
                'requested_by' => $request->user()->id,
                'reason' => $reason,
                'status' => 'PENDING',
            ]);
            $this->writeAudit('REQUEST_CANCELLATION', 'LoanRequest', $loan->id, null, [
                'cancellation_request_id' => $cancellation->id,
                'status' => 'CANCELLATION_REQUESTED',
                'reason' => $reason,
            ]);
            return 'requested';
        });

        if ($result === 'requested') {
            $this->notifyUsersWithRole('ADMIN', new LoanRequestNotification(
                'Permohonan pembatalan pengajuan',
                'Pengajuan ' . $loanRequest->kode_pengajuan . ' meminta pembatalan dan menunggu keputusan Korlab.',
                'loan_cancellation_requested', $loanRequest->id
            ));
            return back()->with('success', 'Permohonan pembatalan berhasil dikirim. Pengajuan tetap disetujui sampai Korlab mengambil keputusan.');
        }

        $this->notifyUsersWithRole('ADMIN', new LoanRequestNotification(
            'Pengajuan peminjaman dibatalkan pengguna',
            'Pengajuan ' . $loanRequest->kode_pengajuan . ' dibatalkan oleh pengaju sebelum disetujui.',
            'loan_cancelled_by_user', $loanRequest->id
        ));
        return back()->with('success', 'Pengajuan berhasil dibatalkan.');
    }

    public function reviewCancellation(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($this->hasRole($request, 'ADMIN'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $cancellation = DB::transaction(function () use ($request, $loanRequest, $data) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();
            $cancellation = LoanCancellationRequest::where('loan_request_id', $loan->id)
                ->where('status', 'PENDING')->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'APPROVED') {
                throw ValidationException::withMessages(['status' => 'Pengajuan tidak lagi berstatus APPROVED sehingga permohonan tidak dapat diproses.']);
            }

            if ($data['decision'] === 'reject') {
                $cancellation->update([
                    'status' => 'REJECTED', 'reviewed_by' => $request->user()->id,
                    'review_reason' => $data['review_reason'], 'reviewed_at' => now(),
                ]);
                $this->writeAudit('REJECT_CANCELLATION', 'LoanRequest', $loan->id, null, [
                    'cancellation_request_id' => $cancellation->id,
                    'status' => 'CANCELLATION_REJECTED', 'reason' => $data['review_reason'],
                ]);
                return $cancellation;
            }

            foreach ($loan->details()->with('allocatedUnits')->get() as $detail) {
                foreach ($detail->allocatedUnits->where('status', 'ACTIVE') as $allocation) {
                    $unit = InventoryUnit::whereKey($allocation->inventory_unit_id)->lockForUpdate()->firstOrFail();
                    if ($unit->status !== 'RESERVED') {
                        throw ValidationException::withMessages([
                            'allocation' => "Unit {$unit->kode_unit} tidak lagi berstatus RESERVED. Pembatalan belum dapat diproses; hubungi pengelola sistem.",
                        ]);
                    }
                    $allocation->update([
                        'status' => 'RELEASED', 'released_by' => $request->user()->id,
                        'released_at' => now(), 'release_reason' => 'Permohonan pengguna disetujui: ' . $data['review_reason'],
                    ]);
                    $unit->update(['status' => 'AVAILABLE']);
                    $unit->synchronizeOperationalStatus();
                }
            }

            $cancellation->update([
                'status' => 'APPROVED', 'reviewed_by' => $request->user()->id,
                'review_reason' => $data['review_reason'], 'reviewed_at' => now(),
            ]);
            $loan->update([
                'status' => 'CANCELLED', 'alasan_pembatalan' => $cancellation->reason,
                'dibatalkan_oleh' => $cancellation->requested_by, 'waktu_pembatalan' => now(),
            ]);
            $this->recordStatus($loan, 'APPROVED', 'CANCELLED', $request->user()->id);
            $this->writeAudit('APPROVE_CANCELLATION', 'LoanRequest', $loan->id, ['status' => 'APPROVED'], [
                'status' => 'CANCELLED', 'cancellation_request_id' => $cancellation->id,
                'reason' => $cancellation->reason, 'review_reason' => $data['review_reason'],
            ]);
            return $cancellation;
        });

        $loanRequest->user?->notify(new LoanRequestNotification(
            $data['decision'] === 'approve' ? 'Pembatalan pengajuan disetujui' : 'Permohonan pembatalan ditolak',
            $data['decision'] === 'approve'
                ? 'Pengajuan ' . $loanRequest->kode_pengajuan . ' telah dibatalkan. ' . $data['review_reason']
                : 'Permohonan pembatalan ' . $loanRequest->kode_pengajuan . ' ditolak. Pengajuan tetap berlaku. Alasan: ' . $data['review_reason'],
            'loan_cancellation_' . $data['decision'], $loanRequest->id
        ));

        return back()->with('success', $data['decision'] === 'approve'
            ? 'Permohonan pembatalan disetujui. Reservasi unit telah dilepas.'
            : 'Permohonan pembatalan ditolak. Status pengajuan tetap APPROVED.');
    }

    private function createActiveAllocation(LoanDetail $detail, InventoryUnit $unit): LoanDetailUnit
    {
        return LoanDetailUnit::create([
            'loan_detail_id' => $detail->id,
            'inventory_unit_id' => $unit->id,
            'status' => 'ACTIVE',
        ]);
    }

    private function recordStatus(
        LoanRequest $loan,
        ?string $oldStatus,
        string $newStatus,
        ?int $userId
    ): void {
        LoanStatusHistory::create([
            'loan_request_id' => $loan->id,
            'status_sebelumnya' => $oldStatus,
            'status_sesudahnya' => $newStatus,
            'diubah_oleh' => $userId,
            'waktu_perubahan' => now(),
        ]);
    }

    private function hasRole(Request $request, string $role): bool
    {
        return $request->user()->roles()->where('nama', $role)->exists();
    }
}
