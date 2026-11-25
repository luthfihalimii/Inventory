<?php

namespace App\Http\Controllers;

use App\Models\InventoryUnit;
use App\Models\LoanRequest;
use App\Models\LoanStatusHistory;
use App\Models\ReturnChecklistResult;
use App\Models\ReturnPhoto;
use App\Models\ReturnRecord;
use App\Models\ReturnUnitInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReturnRecordController extends Controller
{
    public function index(Request $request): View
    {
        $query = ReturnRecord::with(['loanRequest.user.member', 'applicant.member', 'verifier.member'])->latest();
        $isManager = $request->user()->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();
        if (! $isManager) $query->where('diajukan_oleh', $request->user()->id);
        if ($request->filled('q')) $query->whereHas('loanRequest', fn($q) => $q->where('kode_pengajuan', 'like', '%' . $request->q . '%'));
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from')) $query->whereDate('waktu_pengajuan', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('waktu_pengajuan', '<=', $request->date('to'));
        return view('return-records.index', ['returns' => $query->paginate(20)->withQueryString()]);
    }

    public function store(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        abort_unless($loanRequest->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'waktu_pengembalian_diminta' => ['required', 'date'],
            'konfirmasi_pengembalian_awal' => ['nullable'],
            'catatan_pengguna' => ['nullable', 'string', 'max:3000'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photos.required' => 'Foto barang yang dikembalikan wajib dilampirkan.',
            'photos.array' => 'Pilih foto barang dalam satu kali pengiriman.',
            'photos.min' => 'Lampirkan minimal satu foto kondisi barang sebelum mengirim pengembalian.',
            'photos.max' => 'Maksimal 10 foto dapat dikirim dalam satu pengajuan pengembalian.',
            'photos.*.image' => 'Semua lampiran harus berupa file gambar.',
            'photos.*.mimes' => 'Format foto yang didukung: JPG, JPEG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
        ]);

        $requestedReturnAt = \Illuminate\Support\Carbon::parse($data['waktu_pengembalian_diminta']);
        if ($requestedReturnAt->lt($loanRequest->waktu_mulai)) {
            throw ValidationException::withMessages([
                'waktu_pengembalian_diminta' => 'Waktu pengembalian tidak boleh sebelum waktu mulai peminjaman.',
            ]);
        }
        if ($requestedReturnAt->gt($loanRequest->waktu_selesai)) {
            throw ValidationException::withMessages([
                'waktu_pengembalian_diminta' => 'Waktu pengembalian tidak boleh melewati jadwal selesai peminjaman.',
            ]);
        }
        if ($requestedReturnAt->lt(now()->startOfMinute())) {
            throw ValidationException::withMessages([
                'waktu_pengembalian_diminta' => 'Waktu pengembalian harus sekarang atau waktu yang akan datang.',
            ]);
        }
        $isEarlyReturn = $requestedReturnAt->lt($loanRequest->waktu_selesai);
        if ($isEarlyReturn && ! $request->boolean('konfirmasi_pengembalian_awal')) {
            throw ValidationException::withMessages([
                'konfirmasi_pengembalian_awal' => 'Konfirmasi pengembalian lebih awal wajib dicentang.',
            ]);
        }

        $returnRecord = DB::transaction(function () use ($request, $loanRequest, $data) {
            $loan = LoanRequest::whereKey($loanRequest->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== 'BORROWED') {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan harus berstatus BORROWED untuk mengajukan pengembalian.',
                ]);
            }

            if ($loan->returnRecord()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ini sudah memiliki catatan pengembalian.',
                ]);
            }

            $details = $loan->details()->with('allocatedUnits.inventoryUnit')->get();
            $activeAllocations = $details->flatMap(fn($detail) => $detail->allocatedUnits);
            $expectedUnitCount = (int) $details->sum('jumlah');
            $activeUnitCount = $activeAllocations->count();

            if ($activeUnitCount < 1 || $activeUnitCount !== $expectedUnitCount) {
                throw ValidationException::withMessages([
                    'allocation' => 'Tidak ditemukan alokasi unit aktif pada pengajuan ini.',
                ]);
            }

            if ($activeAllocations->contains(fn($allocation) => $allocation->inventoryUnit?->status !== 'BORROWED')) {
                throw ValidationException::withMessages([
                    'allocation' => 'Seluruh unit dalam pengajuan harus berstatus BORROWED sebelum pengembalian diajukan.',
                ]);
            }

            $record = ReturnRecord::create([
                'loan_request_id' => $loan->id,
                'diajukan_oleh' => $request->user()->id,
                'waktu_pengajuan' => now(),
                'waktu_pengembalian_diminta' => $data['waktu_pengembalian_diminta'],
                'status' => 'SUBMITTED',
                'catatan_pengguna' => $data['catatan_pengguna'] ?? null,
            ]);

            foreach ($request->file('photos', []) as $photo) {
                $path = $photo->store('return-photos', 'local');
                ReturnPhoto::create([
                    'return_record_id' => $record->id,
                    'file_path' => $path,
                ]);
            }

            $oldStatus = $loan->status;
            $loan->update(['status' => 'RETURN_PENDING']);

            LoanStatusHistory::create([
                'loan_request_id' => $loan->id,
                'status_sebelumnya' => $oldStatus,
                'status_sesudahnya' => 'RETURN_PENDING',
                'diubah_oleh' => $request->user()->id,
                'waktu_perubahan' => now(),
            ]);

            return $record;
        });

        $this->writeAudit('SUBMIT_RETURN', 'ReturnRecord', $returnRecord->id, null, [
            'loan_request_id' => $loanRequest->id,
            'status' => 'SUBMITTED',
        ]);

        $this->notifyUsersWithRole('ADMIN', new \App\Notifications\LoanRequestNotification(
            'Pengajuan pengembalian baru',
            'Pengembalian untuk pengajuan ' . $loanRequest->kode_pengajuan . ' menunggu pemeriksaan Korlab.',
            'return_submitted',
            $loanRequest->id
        ));

        return back()->with('success', 'Pengajuan pengembalian berhasil dikirim dan menunggu pemeriksaan Admin.');
    }

    public function show(Request $request, ReturnRecord $returnRecord): View
    {
        $user = $request->user();
        $isManager = $user->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();

        abort_unless($isManager || $returnRecord->diajukan_oleh === $user->id, 403);

        return view('return-records.show', [
            'return' => $returnRecord->load([
                'loanRequest.details.inventoryItem',
                'loanRequest.details.allocatedUnits.inventoryUnit',
                'applicant',
                'verifier',
                'photos',
                'checklistResults',
                'unitInspections.inventoryUnit',
            ]),
        ]);
    }

    public function photo(Request $request, ReturnPhoto $returnPhoto)
    {
        $record = $returnPhoto->returnRecord()->with('loanRequest')->firstOrFail();
        $user = $request->user();
        $isManager = $user->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();
        abort_unless($isManager || $record->diajukan_oleh === $user->id, 403);
        abort_unless(Storage::disk('local')->exists($returnPhoto->file_path), 404);
        $path = Storage::disk('local')->path($returnPhoto->file_path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        return response()->file($path, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * ADMIN memeriksa kondisi masing-masing unit.
     * units[UNIT_ID][kondisi] = GOOD|MINOR_DAMAGE|DAMAGED
     * units[UNIT_ID][catatan] = catatan pemeriksaan
     * hasil = VERIFIED|ISSUE_FOUND
     *
     * ISSUE_FOUND mengarantina unit dengan status MAINTENANCE sampai
     * pemeriksaan ulang diselesaikan. Status kondisi fisik tetap dicatat terpisah.
     */
    public function verify(Request $request, ReturnRecord $returnRecord): RedirectResponse
    {
        abort_unless($request->user()->roles()->where('nama', 'ADMIN')->exists(), 403);

        $data = $request->validate([
            'catatan_admin' => ['nullable', 'string', 'max:3000'],
            'hasil' => ['required', Rule::in(['VERIFIED', 'ISSUE_FOUND'])],
            'checklist' => ['nullable', 'array'],
            'checklist.*.nama_pemeriksaan' => ['required', 'string', 'max:150'],
            'checklist.*.is_checked' => ['required', 'boolean'],
            'checklist.*.catatan' => ['nullable', 'string', 'max:2000'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.kondisi' => ['required', Rule::in(InventoryUnit::CONDITIONS)],
            'units.*.catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldAudit = null;

        DB::transaction(function () use ($request, $returnRecord, $data, &$oldAudit) {
            $record = ReturnRecord::whereKey($returnRecord->id)->lockForUpdate()->firstOrFail();
            $loan = LoanRequest::whereKey($record->loan_request_id)->lockForUpdate()->firstOrFail();

            $isFirstInspection = $record->status === 'SUBMITTED' && $loan->status === 'RETURN_PENDING';
            $isReinspection = $record->status === 'ISSUE_FOUND' && $loan->status === 'RETURN_ISSUE';

            if (! $isFirstInspection && ! $isReinspection) {
                throw ValidationException::withMessages([
                    'status' => 'Pengembalian ini tidak berada dalam status yang dapat diperiksa atau diperiksa ulang.',
                ]);
            }

            $allocations = $loan->details()
                ->with(['allocatedUnits' => function ($query) {
                    $query->with('inventoryUnit')->lockForUpdate();
                }])
                ->get()
                ->flatMap(fn($detail) => $detail->allocatedUnits)
                ->filter(fn($allocation) => $allocation->inventoryUnit !== null)
                ->values();

            $expectedIds = $allocations->pluck('inventory_unit_id')->map(fn($id) => (string) $id)->sort()->values();
            $submittedIds = collect(array_keys($data['units']))->map(fn($id) => (string) $id)->sort()->values();

            if ($expectedIds->all() !== $submittedIds->all()) {
                throw ValidationException::withMessages([
                    'units' => 'Kondisi setiap unit yang dialokasikan wajib diperiksa. Pastikan tidak ada unit yang terlewat atau ditambahkan.',
                ]);
            }

            $oldAudit = $record->toArray();
            $inspectionRound = ((int) $record->unitInspections()->max('pemeriksaan_ke')) + 1;

            foreach ($data['checklist'] ?? [] as $item) {
                ReturnChecklistResult::create([
                    'return_record_id' => $record->id,
                    'pemeriksaan_ke' => $inspectionRound,
                    'nama_pemeriksaan' => $item['nama_pemeriksaan'],
                    'is_checked' => $item['is_checked'],
                    'catatan' => $item['catatan'] ?? null,
                ]);
            }

            foreach ($allocations as $allocation) {
                $unit = InventoryUnit::whereKey($allocation->inventory_unit_id)
                    ->lockForUpdate()->firstOrFail();
                $inspection = $data['units'][$unit->id];

                ReturnUnitInspection::create([
                    'return_record_id' => $record->id,
                    'inventory_unit_id' => $unit->id,
                    'pemeriksaan_ke' => $inspectionRound,
                    'kondisi_ditemukan' => $inspection['kondisi'],
                    'catatan' => $inspection['catatan'] ?? null,
                ]);

                $unit->update(['kondisi' => $inspection['kondisi']]);

                if ($data['hasil'] === 'ISSUE_FOUND') {
                    // Unit dikarantina; tidak boleh dipinjam lagi sebelum masalah selesai.
                    $unit->update(['status' => 'MAINTENANCE']);
                }
            }

            $oldLoanStatus = $loan->status;
            $newLoanStatus = $data['hasil'] === 'VERIFIED' ? 'COMPLETED' : 'RETURN_ISSUE';

            $record->update([
                'diverifikasi_oleh' => $request->user()->id,
                'waktu_verifikasi' => now(),
                'status' => $data['hasil'],
                'catatan_admin' => $data['catatan_admin'] ?? null,
            ]);

            $loan->update(['status' => $newLoanStatus]);

            if ($data['hasil'] === 'VERIFIED') {
                foreach ($allocations as $allocation) {
                    $allocation->update(['status' => 'COMPLETED']);
                    $unit = InventoryUnit::whereKey($allocation->inventory_unit_id)
                        ->lockForUpdate()->firstOrFail();
                    $unit->synchronizeOperationalStatus();
                }
            }

            LoanStatusHistory::create([
                'loan_request_id' => $loan->id,
                'status_sebelumnya' => $oldLoanStatus,
                'status_sesudahnya' => $newLoanStatus,
                'diubah_oleh' => $request->user()->id,
                'waktu_perubahan' => now(),
            ]);
        });

        $this->writeAudit('VERIFY_RETURN', 'ReturnRecord', $returnRecord->id, $oldAudit, [
            'hasil' => $data['hasil'],
            'catatan_admin' => $data['catatan_admin'] ?? null,
        ]);

        $loanCode = $returnRecord->loanRequest()->value('kode_pengajuan');
        $returnRecord->fresh()->applicant?->notify(new \App\Notifications\LoanRequestNotification(
            $data['hasil'] === 'VERIFIED' ? 'Pengembalian telah diverifikasi' : 'Pengembalian memerlukan pemeriksaan ulang',
            $data['hasil'] === 'VERIFIED'
                ? 'Pengembalian untuk pengajuan ' . $loanCode . ' telah diverifikasi.'
                : 'Pengembalian untuk pengajuan ' . $loanCode . ' memiliki temuan yang perlu ditindaklanjuti.',
            $data['hasil'] === 'VERIFIED' ? 'return_verified' : 'return_issue_found',
            $returnRecord->loan_request_id
        ));

        $message = $data['hasil'] === 'VERIFIED'
            ? 'Pengembalian berhasil diverifikasi. Kondisi dan status setiap unit telah diperbarui.'
            : 'Masalah pengembalian dicatat. Unit dikarantina dan menunggu pemeriksaan ulang.';

        return back()->with('success', $message);
    }
}
