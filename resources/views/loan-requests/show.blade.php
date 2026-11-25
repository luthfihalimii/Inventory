@extends('layouts.app')

@section('title', 'Detail Peminjaman')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">LOAN MANAGEMENT</div>
        <h1>Detail Peminjaman</h1>
        <p class="sub">Informasi pengajuan, barang, keputusan, dan riwayat status.</p>
    </div>
    <a href="{{ route('loan-requests.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke daftar
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Periksa kembali data yang dimasukkan.</strong>
        <ul class="mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Informasi pengajuan</h2>
            <p class="muted">Ringkasan identitas dan jadwal peminjaman.</p>
        </div>
        <span class="status {{ $loan->status === 'PENDING' ? 'status-warning' : (in_array($loan->status, ['REJECTED', 'CANCELLED']) ? 'status-danger' : (in_array($loan->status, ['APPROVED', 'BORROWED']) ? 'status-success' : 'status-info')) }}">
            {{ $loan->pendingCancellationRequest ? 'PERMOHONAN PEMBATALAN' : str_replace('_', ' ', $loan->status) }}
        </span>
    </div>

    <div class="form-grid">
        <div><span class="muted">Kode pengajuan</span><p><strong>{{ $loan->kode_pengajuan }}</strong></p></div>
        <div><span class="muted">Pengaju</span><p>{{ $loan->user?->member?->nama ?? $loan->user?->username ?? '—' }}</p></div>
        <div><span class="muted">Kategori</span><p>{{ $loan->kategori === 'URGENT' ? 'Mendadak' : 'Terjadwal' }}</p></div>
        <div><span class="muted">Waktu mulai</span><p>{{ $loan->waktu_mulai?->format('d M Y H:i') ?? '—' }}</p></div>
        <div><span class="muted">Waktu selesai</span><p>{{ $loan->waktu_selesai?->format('d M Y H:i') ?? '—' }}</p></div>
        <div style="grid-column:1/-1"><span class="muted">Catatan peminjam</span><p>{{ $loan->catatan ?: '—' }}</p></div>
        @if($loan->status === 'REJECTED')
            <div style="grid-column:1/-1">
                <span class="muted">Alasan penolakan</span>
                <p>{{ $loan->alasan_penolakan ?: '—' }}</p>
            </div>
        @endif
    </div>
</section>

@if($loan->pendingCancellationRequest)
<section class="card cancellation-pending">
    <div class="section-heading"><div><h2>Permohonan pembatalan sedang ditinjau</h2><p class="muted">Pengajuan masih berstatus APPROVED dan reservasi tetap berlaku sampai Korlab mengambil keputusan.</p></div><span class="status status-warning">Menunggu Korlab</span></div>
    <p><strong>Alasan pengguna:</strong> {{ $loan->pendingCancellationRequest->reason }}</p>
    @if(auth()->user()->roles()->where('nama', 'ADMIN')->exists())
    <form method="POST" action="{{ route('loan-requests.cancellation-review', $loan) }}" class="form-stack">
        @csrf
        <div class="field"><label for="review_reason">Catatan keputusan <span class="required">*</span></label><textarea id="review_reason" name="review_reason" rows="3" minlength="5" maxlength="2000" required placeholder="Tuliskan alasan persetujuan atau penolakan agar pemohon memahami keputusan Korlab..."></textarea></div>
        <div class="actions"><button class="btn btn-primary" name="decision" value="approve" type="submit" onclick="return confirm('Setujui pembatalan dan lepaskan seluruh unit yang direservasi?')"><i class="fa-solid fa-check"></i> Setujui pembatalan</button><button class="btn btn-danger" name="decision" value="reject" type="submit" onclick="return confirm('Tolak permohonan pembatalan? Pengajuan akan tetap APPROVED.')"><i class="fa-solid fa-xmark"></i> Tolak permohonan</button></div>
    </form>
    @endif
</section>
@endif

@if($loan->cancellationRequests->isNotEmpty())
<section class="card"><h2>Riwayat permohonan pembatalan</h2><div class="activity-list">
@foreach($loan->cancellationRequests as $cancellation)
<div class="activity-item"><div><strong>{{ $cancellation->status === 'PENDING' ? 'Menunggu keputusan' : ($cancellation->status === 'APPROVED' ? 'Disetujui' : 'Ditolak') }}</strong><p class="muted">Diajukan oleh {{ $cancellation->requester?->member?->nama ?? $cancellation->requester?->username ?? 'Pengguna' }} · {{ $cancellation->created_at?->format('d/m/Y H:i') }}</p><p>Alasan: {{ $cancellation->reason }}</p>@if($cancellation->review_reason)<p class="muted">Catatan Korlab: {{ $cancellation->review_reason }}</p>@endif</div><span class="status {{ $cancellation->status === 'PENDING' ? 'status-warning' : ($cancellation->status === 'APPROVED' ? 'status-success' : 'status-danger') }}">{{ $cancellation->status }}</span></div>
@endforeach
</div></section>
@endif

<section class="card">
    <h2>Barang yang diajukan</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Barang</th><th>Jumlah</th><th>Unit dialokasikan</th></tr>
            </thead>
            <tbody>
                @forelse($loan->details as $detail)
                    <tr>
                        <td>{{ $detail->inventoryItem?->nama ?? 'Inventaris tidak ditemukan' }}</td>
                        <td>{{ $detail->jumlah }} unit</td>
                        <td>
                            @forelse($detail->allocatedUnits as $allocation)
                                <span class="status status-info">{{ $allocation->inventoryUnit?->kode_unit ?? '—' }}</span>
                            @empty
                                <span class="muted">Belum dialokasikan</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Tidak ada detail barang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>


@if($loan->user_id === auth()->id() && in_array($loan->status, ['PENDING', 'APPROVED']) && !$loan->pendingCancellationRequest)
<section class="card cancellation-card">
    <h2>{{ $loan->status === 'APPROVED' ? 'Ajukan pembatalan' : 'Batalkan pengajuan' }}</h2>
    @if($loan->status === 'APPROVED')
        <p class="muted">Pengajuan sudah disetujui. Pembatalan harus dimohonkan kepada Korlab dan tidak langsung mengubah status pengajuan.</p>
    @else
        <p class="muted">Pengajuan masih menunggu persetujuan sehingga dapat dibatalkan langsung.</p>
    @endif
    <form method="POST" action="{{ route('loan-requests.cancel', $loan) }}" class="form-stack" onsubmit="return confirm('{{ $loan->status === 'APPROVED' ? 'Kirim permohonan pembatalan kepada Korlab?' : 'Batalkan pengajuan ini?' }}')">
        @csrf
        <div class="field"><label for="alasan_pembatalan">{{ $loan->status === 'APPROVED' ? 'Alasan permohonan pembatalan' : 'Alasan (opsional)' }} @if($loan->status === 'APPROVED')<span class="required">*</span>@endif</label><textarea id="alasan_pembatalan" name="alasan_pembatalan" rows="3" minlength="{{ $loan->status === 'APPROVED' ? 5 : 0 }}" maxlength="2000" {{ $loan->status === 'APPROVED' ? 'required' : '' }} placeholder="Jelaskan alasan pembatalan...">{{ old('alasan_pembatalan') }}</textarea>@error('alasan_pembatalan')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div><button class="btn btn-danger" type="submit"><i class="fa-solid fa-ban"></i> {{ $loan->status === 'APPROVED' ? 'Kirim permohonan pembatalan' : 'Batalkan pengajuan' }}</button></div>
    </form>
</section>
@endif

@if(auth()->user()->roles()->where('nama', 'ADMIN')->exists() && $loan->status === 'APPROVED' && !$loan->pendingCancellationRequest)
<section class="card cancellation-card"><h2>Pembatalan oleh Korlab</h2><p class="muted">Korlab dapat membatalkan pengajuan yang sudah disetujui selama barang belum diserahkan. Alasan wajib dicatat.</p><form method="POST" action="{{ route('loan-requests.cancel-admin', $loan) }}" class="form-stack" onsubmit="return confirm('Batalkan pengajuan APPROVED dan lepaskan reservasi unit?')">@csrf<div class="field"><label for="admin_alasan_pembatalan">Alasan pembatalan <span class="required">*</span></label><textarea id="admin_alasan_pembatalan" name="alasan_pembatalan" rows="3" minlength="5" maxlength="2000" required placeholder="Tuliskan alasan pembatalan oleh Korlab..."></textarea>@error('alasan_pembatalan')<small class="field-error">{{ $message }}</small>@enderror</div><button class="btn btn-danger" type="submit"><i class="fa-solid fa-ban"></i> Batalkan pengajuan</button></form></section>
@endif

@if($loan->status === 'BORROWED' && !$loan->returnRecord && $loan->user_id === auth()->id())
<section class="card return-request-card">
    <div class="section-heading">
        <div>
            <h2>Ajukan Pengembalian</h2>
            <p class="muted">Tentukan kapan barang akan dikembalikan. Jika lebih awal dari jadwal, sistem akan meminta konfirmasi.</p>
        </div>
        <span class="status status-info">Dapat diajukan</span>
    </div>

    <div class="return-sop-note"><strong><i class="fa-solid fa-clipboard-list"></i> Sebelum mengajukan pengembalian</strong><p>Periksa kondisi dan kelengkapan barang, tentukan waktu pengembalian, lalu sertakan foto bila diperlukan. Pengembalian baru selesai setelah diperiksa dan diverifikasi Korlab.</p><a href="{{ route('sop') }}#sop-pengembalian" target="_blank" rel="noopener">Baca SOP pengembalian <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
    @if($errors->has('waktu_pengembalian_diminta') || $errors->has('konfirmasi_pengembalian_awal') || $errors->has('photos') || $errors->has('photos.0'))
        <div class="alert alert-danger" role="alert">
            <strong>Periksa kembali data pengembalian.</strong>
            <ul>
                @foreach($errors->get('waktu_pengembalian_diminta') as $error)<li>{{ $error }}</li>@endforeach
                @foreach($errors->get('konfirmasi_pengembalian_awal') as $error)<li>{{ $error }}</li>@endforeach
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('return-records.store', $loan) }}" enctype="multipart/form-data" id="returnRequestForm" class="form-stack">
        @csrf
        <div class="form-grid">
            <div>
                <span class="muted">Jadwal pengembalian semula</span>
                <p><strong>{{ $loan->waktu_selesai?->format('d M Y, H:i') }} WIB</strong></p>
            </div>
            <div class="field">
                <label for="waktu_pengembalian_diminta">Waktu pengembalian yang direncanakan <span class="required">*</span></label>
                <input type="datetime-local" id="waktu_pengembalian_diminta" name="waktu_pengembalian_diminta"
                       min="{{ $loan->waktu_mulai?->format('Y-m-d\\TH:i') }}"
                       value="{{ old('waktu_pengembalian_diminta', $loan->waktu_selesai?->format('Y-m-d\\TH:i')) }}" required>
                @error('waktu_pengembalian_diminta')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div id="earlyReturnNotice" class="early-return-notice" hidden>
            <strong><i class="fa-solid fa-circle-info"></i> Pengembalian lebih awal</strong>
            <p>Waktu yang kamu pilih lebih awal dari jadwal semula. Sistem akan mencatat tanggal dan jam yang kamu pilih sebagai waktu pengembalian yang direncanakan.</p>
            <label class="confirm-early">
                <input type="checkbox" name="konfirmasi_pengembalian_awal" value="1" @checked(old('konfirmasi_pengembalian_awal'))>
                Saya mengonfirmasi pengembalian lebih awal pada tanggal dan waktu tersebut.
            </label>
            @error('konfirmasi_pengembalian_awal')<small class="field-error">{{ $message }}</small>@enderror
        </div>

        <div class="field">
            <label for="catatan_pengguna">Catatan tambahan (opsional)</label>
            <textarea id="catatan_pengguna" name="catatan_pengguna" rows="3" maxlength="3000" placeholder="Informasi kondisi barang atau catatan untuk Korlab...">{{ old('catatan_pengguna') }}</textarea>
        </div>
        <div class="field">
            <label for="photos">Foto kondisi seluruh barang yang dikembalikan <span class="required">*</span></label>
            <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <small class="muted">Wajib melampirkan minimal 1 foto dan maksimal 10 foto. Pilih seluruh foto barang sekaligus sebelum mengirim pengajuan. Format JPG, PNG, atau WEBP; maksimal 5 MB per foto.</small>
            @error('photos')<small class="field-error">{{ $message }}</small>@enderror
            @error('photos.*')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div>
            <button class="btn btn-primary" type="submit" onclick="return confirm('Kirim pengajuan pengembalian sesuai tanggal dan waktu yang dipilih?')">
                <i class="fa-solid fa-rotate-left"></i> Kirim pengajuan pengembalian
            </button>
        </div>
    </form>
</section>
@elseif($loan->returnRecord)
<section class="card">
    <h2>Status pengembalian</h2>
    <div class="form-grid">
        <div><span class="muted">Status</span><p><span class="status status-info">{{ str_replace('_', ' ', $loan->returnRecord->status) }}</span></p></div>
        <div><span class="muted">Waktu pengembalian yang diminta</span><p><strong>{{ $loan->returnRecord->waktu_pengembalian_diminta?->format('d M Y, H:i') ?? '—' }} WIB</strong></p></div>
        <div><span class="muted">Pengajuan dikirim</span><p>{{ $loan->returnRecord->waktu_pengajuan?->format('d M Y, H:i') ?? '—' }}</p></div>
        @if($loan->returnRecord->waktu_pengembalian_diminta && $loan->returnRecord->waktu_pengembalian_diminta->lt($loan->waktu_selesai))
            <div style="grid-column:1/-1" class="early-return-notice">
                <strong>Pengembalian lebih awal dari jadwal</strong>
                <p>Jadwal semula: {{ $loan->waktu_selesai?->format('d M Y, H:i') }} WIB. Waktu yang diminta: {{ $loan->returnRecord->waktu_pengembalian_diminta->format('d M Y, H:i') }} WIB.</p>
            </div>
        @endif
    </div>
    <a class="btn btn-secondary" href="{{ route('return-records.show', $loan->returnRecord) }}">Lihat detail pengembalian</a>
</section>
@endif

@if($loan->statusHistories->isNotEmpty())
<section class="card">
    <h2>Riwayat status</h2>
    <div class="activity-list">
        @foreach($loan->statusHistories as $history)
            <div class="activity-item">
                <div>
                    <strong>{{ str_replace('_', ' ', $history->status_sesudahnya) }}</strong>
                    <p class="muted">{{ $history->changedBy?->member?->nama ?? $history->changedBy?->username ?? 'Sistem' }}</p>
                </div>
                <span class="muted">{{ $history->waktu_perubahan?->format('d/m/Y H:i') ?? $history->created_at?->format('d/m/Y H:i') }}</span>
            </div>
        @endforeach
    </div>
</section>
@endif

@if(auth()->user()->roles()->where('nama', 'ADMIN')->exists() && $loan->status === 'APPROVED' && !$loan->pendingCancellationRequest)
<section class="card">
    <h2>Penyerahan Barang</h2>
    <p class="muted">Pengajuan sudah disetujui dan unit telah direservasi. Setelah barang benar-benar diserahkan kepada pengguna, konfirmasikan tindakan berikut.</p>
    <div class="alert alert-info" role="status">
        Status saat ini: <strong>APPROVED</strong>. Unit masih <strong>RESERVED</strong> dan belum dihitung sebagai barang yang sedang dipinjam.
    </div>
    <form method="POST" action="{{ route('loan-requests.mark-borrowed', $loan) }}"
          onsubmit="return confirm('Konfirmasi bahwa seluruh barang pada pengajuan ini sudah diserahkan kepada peminjam? Status pengajuan dan unit akan berubah menjadi BORROWED.')">
        @csrf
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-hand-holding"></i> Konfirmasi Penyerahan Barang
        </button>
    </form>
</section>
@endif

@if(auth()->user()->roles()->where('nama', 'ADMIN')->exists() && $loan->status === 'PENDING')
<section class="card">
    <h2>Tindakan Korlab</h2>
    <p class="muted">Periksa ketersediaan dan detail barang sebelum mengambil keputusan.</p>

    <div class="actions" style="align-items:flex-start;flex-wrap:wrap">
        <form method="POST" action="{{ route('loan-requests.approve', $loan) }}"
              onsubmit="return confirm('Setujui pengajuan ini?')">
            @csrf
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-check"></i> Setujui pengajuan
            </button>
        </form>
    </div>

    <form method="POST" action="{{ route('loan-requests.cancel-admin', $loan) }}" class="form-stack" style="margin-top:18px" onsubmit="return confirm('Batalkan pengajuan PENDING ini?')">
        @csrf
        <div class="field"><label for="admin_cancel_pending_reason">Alasan pembatalan oleh Korlab <span class="required">*</span></label><textarea id="admin_cancel_pending_reason" name="alasan_pembatalan" rows="3" minlength="5" maxlength="2000" required placeholder="Tuliskan alasan pembatalan..."></textarea></div>
        <button class="btn btn-danger" type="submit"><i class="fa-solid fa-ban"></i> Batalkan pengajuan</button>
    </form>

    <form method="POST" action="{{ route('loan-requests.reject', $loan) }}" class="form-stack"
          style="margin-top:18px"
          onsubmit="return confirm('Tolak pengajuan ini dengan alasan yang sudah diisi?')">
        @csrf
        <div class="field">
            <label for="alasan_penolakan">Alasan penolakan <span class="required">*</span></label>
            <textarea id="alasan_penolakan" name="alasan_penolakan" rows="4" minlength="5" maxlength="2000"
                      required placeholder="Tuliskan alasan penolakan secara jelas...">{{ old('alasan_penolakan') }}</textarea>
            @error('alasan_penolakan')
                <small class="field-error">{{ $message }}</small>
            @else
                <small class="muted">Wajib diisi, minimal 5 karakter. Alasan akan ditampilkan kepada pemohon.</small>
            @enderror
        </div>
        <div>
            <button class="btn btn-danger" type="submit">
                <i class="fa-solid fa-xmark"></i> Tolak pengajuan
            </button>
        </div>
    </form>
</section>
@endif

<style>
    .section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .section-heading h2{margin:0 0 4px}
    .section-heading p{margin:0}
    .form-stack{display:flex;flex-direction:column;gap:12px}
    .field-error{display:block;color:#b42318;font-weight:600;margin-top:6px}
    .required{color:#b42318}
    .activity-list{display:flex;flex-direction:column;gap:12px}
    .activity-item{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid var(--line)}
    .activity-item:last-child{border-bottom:0}
    .activity-item p{margin:4px 0 0}
    @media(max-width:640px){.section-heading,.activity-item{flex-direction:column}}
.cancellation-card{border-color:#efc5c5}.cancellation-pending{border-color:#e9c77c;background:linear-gradient(135deg,var(--surface),var(--surface-2))}.return-sop-note{padding:13px 15px;border:1px solid var(--line);border-radius:12px;background:var(--surface-2);margin:12px 0}.return-sop-note p{margin:5px 0;font-size:12px;color:var(--muted)}.return-sop-note a{font-size:12px;font-weight:700}.early-return-notice{padding:14px 16px;border:1px solid #f0b44d;background:#fff8e6;border-radius:12px;color:#854d0e}
    .early-return-notice p{margin:6px 0 10px}
    .confirm-early{display:flex;align-items:flex-start;gap:8px;font-weight:600}
    .confirm-early input{margin-top:3px}
    .return-request-card{border-color:#b8d8f3}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('waktu_pengembalian_diminta');
    const notice = document.getElementById('earlyReturnNotice');
    const checkbox = notice?.querySelector('input[name="konfirmasi_pengembalian_awal"]');
    const scheduled = @json($loan->waktu_selesai?->format('Y-m-d\\TH:i'));
    if (!input || !notice || !scheduled) return;
    const updateEarlyReturnNotice = () => {
        const early = input.value !== '' && input.value < scheduled;
        notice.hidden = !early;
        if (checkbox) {
            checkbox.required = early;
            if (!early) checkbox.checked = false;
        }
    };
    input.addEventListener('input', updateEarlyReturnNotice);
    input.addEventListener('change', updateEarlyReturnNotice);
    updateEarlyReturnNotice();
});
</script>
@endsection
