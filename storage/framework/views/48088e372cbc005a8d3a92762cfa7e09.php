<?php $__env->startSection('title', 'Detail Peminjaman'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <div class="eyebrow">LOAN MANAGEMENT</div>
        <h1>Detail Peminjaman</h1>
        <p class="sub">Informasi pengajuan, barang, keputusan, dan riwayat status.</p>
    </div>
    <a href="<?php echo e(route('loan-requests.index')); ?>" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke daftar
    </a>
</div>

<?php if(session('success')): ?>
    <div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Periksa kembali data yang dimasukkan.</strong>
        <ul class="mt-2">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
<?php endif; ?>

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Informasi pengajuan</h2>
            <p class="muted">Ringkasan identitas dan jadwal peminjaman.</p>
        </div>
        <span class="status <?php echo e($loan->status === 'PENDING' ? 'status-warning' : (in_array($loan->status, ['REJECTED', 'CANCELLED']) ? 'status-danger' : (in_array($loan->status, ['APPROVED', 'BORROWED']) ? 'status-success' : 'status-info'))); ?>">
            <?php echo e($loan->pendingCancellationRequest ? 'PERMOHONAN PEMBATALAN' : str_replace('_', ' ', $loan->status)); ?>

        </span>
    </div>

    <div class="form-grid">
        <div><span class="muted">Kode pengajuan</span><p><strong><?php echo e($loan->kode_pengajuan); ?></strong></p></div>
        <div><span class="muted">Pengaju</span><p><?php echo e($loan->user?->member?->nama ?? $loan->user?->username ?? '—'); ?></p></div>
        <div><span class="muted">Kategori</span><p><?php echo e($loan->kategori === 'URGENT' ? 'Mendadak' : 'Terjadwal'); ?></p></div>
        <div><span class="muted">Waktu mulai</span><p><?php echo e($loan->waktu_mulai?->format('d M Y H:i') ?? '—'); ?></p></div>
        <div><span class="muted">Waktu selesai</span><p><?php echo e($loan->waktu_selesai?->format('d M Y H:i') ?? '—'); ?></p></div>
        <div style="grid-column:1/-1"><span class="muted">Catatan peminjam</span><p><?php echo e($loan->catatan ?: '—'); ?></p></div>
        <?php if($loan->status === 'REJECTED'): ?>
            <div style="grid-column:1/-1">
                <span class="muted">Alasan penolakan</span>
                <p><?php echo e($loan->alasan_penolakan ?: '—'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if($loan->pendingCancellationRequest): ?>
<section class="card cancellation-pending">
    <div class="section-heading"><div><h2>Permohonan pembatalan sedang ditinjau</h2><p class="muted">Pengajuan masih berstatus APPROVED dan reservasi tetap berlaku sampai Korlab mengambil keputusan.</p></div><span class="status status-warning">Menunggu Korlab</span></div>
    <p><strong>Alasan pengguna:</strong> <?php echo e($loan->pendingCancellationRequest->reason); ?></p>
    <?php if(auth()->user()->roles()->where('nama', 'ADMIN')->exists()): ?>
    <form method="POST" action="<?php echo e(route('loan-requests.cancellation-review', $loan)); ?>" class="form-stack">
        <?php echo csrf_field(); ?>
        <div class="field"><label for="review_reason">Catatan keputusan <span class="required">*</span></label><textarea id="review_reason" name="review_reason" rows="3" minlength="5" maxlength="2000" required placeholder="Tuliskan alasan persetujuan atau penolakan agar pemohon memahami keputusan Korlab..."></textarea></div>
        <div class="actions"><button class="btn btn-primary" name="decision" value="approve" type="submit" onclick="return confirm('Setujui pembatalan dan lepaskan seluruh unit yang direservasi?')"><i class="fa-solid fa-check"></i> Setujui pembatalan</button><button class="btn btn-danger" name="decision" value="reject" type="submit" onclick="return confirm('Tolak permohonan pembatalan? Pengajuan akan tetap APPROVED.')"><i class="fa-solid fa-xmark"></i> Tolak permohonan</button></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if($loan->cancellationRequests->isNotEmpty()): ?>
<section class="card"><h2>Riwayat permohonan pembatalan</h2><div class="activity-list">
<?php $__currentLoopData = $loan->cancellationRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cancellation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="activity-item"><div><strong><?php echo e($cancellation->status === 'PENDING' ? 'Menunggu keputusan' : ($cancellation->status === 'APPROVED' ? 'Disetujui' : 'Ditolak')); ?></strong><p class="muted">Diajukan oleh <?php echo e($cancellation->requester?->member?->nama ?? $cancellation->requester?->username ?? 'Pengguna'); ?> · <?php echo e($cancellation->created_at?->format('d/m/Y H:i')); ?></p><p>Alasan: <?php echo e($cancellation->reason); ?></p><?php if($cancellation->review_reason): ?><p class="muted">Catatan Korlab: <?php echo e($cancellation->review_reason); ?></p><?php endif; ?></div><span class="status <?php echo e($cancellation->status === 'PENDING' ? 'status-warning' : ($cancellation->status === 'APPROVED' ? 'status-success' : 'status-danger')); ?>"><?php echo e($cancellation->status); ?></span></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div></section>
<?php endif; ?>

<section class="card">
    <h2>Barang yang diajukan</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Barang</th><th>Jumlah</th><th>Unit dialokasikan</th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $loan->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($detail->inventoryItem?->nama ?? 'Inventaris tidak ditemukan'); ?></td>
                        <td><?php echo e($detail->jumlah); ?> unit</td>
                        <td>
                            <?php $__empty_2 = true; $__currentLoopData = $detail->allocatedUnits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allocation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                <span class="status status-info"><?php echo e($allocation->inventoryUnit?->kode_unit ?? '—'); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                <span class="muted">Belum dialokasikan</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="3" class="muted">Tidak ada detail barang.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>


<?php if($loan->user_id === auth()->id() && in_array($loan->status, ['PENDING', 'APPROVED']) && !$loan->pendingCancellationRequest): ?>
<section class="card cancellation-card">
    <h2><?php echo e($loan->status === 'APPROVED' ? 'Ajukan pembatalan' : 'Batalkan pengajuan'); ?></h2>
    <?php if($loan->status === 'APPROVED'): ?>
        <p class="muted">Pengajuan sudah disetujui. Pembatalan harus dimohonkan kepada Korlab dan tidak langsung mengubah status pengajuan.</p>
    <?php else: ?>
        <p class="muted">Pengajuan masih menunggu persetujuan sehingga dapat dibatalkan langsung.</p>
    <?php endif; ?>
    <form method="POST" action="<?php echo e(route('loan-requests.cancel', $loan)); ?>" class="form-stack" onsubmit="return confirm('<?php echo e($loan->status === 'APPROVED' ? 'Kirim permohonan pembatalan kepada Korlab?' : 'Batalkan pengajuan ini?'); ?>')">
        <?php echo csrf_field(); ?>
        <div class="field"><label for="alasan_pembatalan"><?php echo e($loan->status === 'APPROVED' ? 'Alasan permohonan pembatalan' : 'Alasan (opsional)'); ?> <?php if($loan->status === 'APPROVED'): ?><span class="required">*</span><?php endif; ?></label><textarea id="alasan_pembatalan" name="alasan_pembatalan" rows="3" minlength="<?php echo e($loan->status === 'APPROVED' ? 5 : 0); ?>" maxlength="2000" <?php echo e($loan->status === 'APPROVED' ? 'required' : ''); ?> placeholder="Jelaskan alasan pembatalan..."><?php echo e(old('alasan_pembatalan')); ?></textarea><?php $__errorArgs = ['alasan_pembatalan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
        <div><button class="btn btn-danger" type="submit"><i class="fa-solid fa-ban"></i> <?php echo e($loan->status === 'APPROVED' ? 'Kirim permohonan pembatalan' : 'Batalkan pengajuan'); ?></button></div>
    </form>
</section>
<?php endif; ?>

<?php if(auth()->user()->roles()->where('nama', 'ADMIN')->exists() && $loan->status === 'APPROVED' && !$loan->pendingCancellationRequest): ?>
<section class="card cancellation-card"><h2>Pembatalan oleh Korlab</h2><p class="muted">Korlab dapat membatalkan pengajuan yang sudah disetujui selama barang belum diserahkan. Alasan wajib dicatat.</p><form method="POST" action="<?php echo e(route('loan-requests.cancel-admin', $loan)); ?>" class="form-stack" onsubmit="return confirm('Batalkan pengajuan APPROVED dan lepaskan reservasi unit?')"><?php echo csrf_field(); ?><div class="field"><label for="admin_alasan_pembatalan">Alasan pembatalan <span class="required">*</span></label><textarea id="admin_alasan_pembatalan" name="alasan_pembatalan" rows="3" minlength="5" maxlength="2000" required placeholder="Tuliskan alasan pembatalan oleh Korlab..."></textarea><?php $__errorArgs = ['alasan_pembatalan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div><button class="btn btn-danger" type="submit"><i class="fa-solid fa-ban"></i> Batalkan pengajuan</button></form></section>
<?php endif; ?>

<?php if($loan->status === 'BORROWED' && !$loan->returnRecord && $loan->user_id === auth()->id()): ?>
<section class="card return-request-card">
    <div class="section-heading">
        <div>
            <h2>Ajukan Pengembalian</h2>
            <p class="muted">Tentukan kapan barang akan dikembalikan. Jika lebih awal dari jadwal, sistem akan meminta konfirmasi.</p>
        </div>
        <span class="status status-info">Dapat diajukan</span>
    </div>

    <div class="return-sop-note"><strong><i class="fa-solid fa-clipboard-list"></i> Sebelum mengajukan pengembalian</strong><p>Periksa kondisi dan kelengkapan barang, tentukan waktu pengembalian, lalu sertakan foto bila diperlukan. Pengembalian baru selesai setelah diperiksa dan diverifikasi Korlab.</p><a href="<?php echo e(route('sop')); ?>#sop-pengembalian" target="_blank" rel="noopener">Baca SOP pengembalian <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
    <?php if($errors->has('waktu_pengembalian_diminta') || $errors->has('konfirmasi_pengembalian_awal') || $errors->has('photos') || $errors->has('photos.0')): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Periksa kembali data pengembalian.</strong>
            <ul>
                <?php $__currentLoopData = $errors->get('waktu_pengembalian_diminta'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php $__currentLoopData = $errors->get('konfirmasi_pengembalian_awal'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('return-records.store', $loan)); ?>" enctype="multipart/form-data" id="returnRequestForm" class="form-stack">
        <?php echo csrf_field(); ?>
        <div class="form-grid">
            <div>
                <span class="muted">Jadwal pengembalian semula</span>
                <p><strong><?php echo e($loan->waktu_selesai?->format('d M Y, H:i')); ?> WIB</strong></p>
            </div>
            <div class="field">
                <label for="waktu_pengembalian_diminta">Waktu pengembalian yang direncanakan <span class="required">*</span></label>
                <input type="datetime-local" id="waktu_pengembalian_diminta" name="waktu_pengembalian_diminta"
                       min="<?php echo e($loan->waktu_mulai?->format('Y-m-d\\TH:i')); ?>"
                       value="<?php echo e(old('waktu_pengembalian_diminta', $loan->waktu_selesai?->format('Y-m-d\\TH:i'))); ?>" required>
                <?php $__errorArgs = ['waktu_pengembalian_diminta'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <div id="earlyReturnNotice" class="early-return-notice" hidden>
            <strong><i class="fa-solid fa-circle-info"></i> Pengembalian lebih awal</strong>
            <p>Waktu yang kamu pilih lebih awal dari jadwal semula. Sistem akan mencatat tanggal dan jam yang kamu pilih sebagai waktu pengembalian yang direncanakan.</p>
            <label class="confirm-early">
                <input type="checkbox" name="konfirmasi_pengembalian_awal" value="1" <?php if(old('konfirmasi_pengembalian_awal')): echo 'checked'; endif; ?>>
                Saya mengonfirmasi pengembalian lebih awal pada tanggal dan waktu tersebut.
            </label>
            <?php $__errorArgs = ['konfirmasi_pengembalian_awal'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="field">
            <label for="catatan_pengguna">Catatan tambahan (opsional)</label>
            <textarea id="catatan_pengguna" name="catatan_pengguna" rows="3" maxlength="3000" placeholder="Informasi kondisi barang atau catatan untuk Korlab..."><?php echo e(old('catatan_pengguna')); ?></textarea>
        </div>
        <div class="field">
            <label for="photos">Foto kondisi seluruh barang yang dikembalikan <span class="required">*</span></label>
            <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <small class="muted">Wajib melampirkan minimal 1 foto dan maksimal 10 foto. Pilih seluruh foto barang sekaligus sebelum mengirim pengajuan. Format JPG, PNG, atau WEBP; maksimal 5 MB per foto.</small>
            <?php $__errorArgs = ['photos'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['photos.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div>
            <button class="btn btn-primary" type="submit" onclick="return confirm('Kirim pengajuan pengembalian sesuai tanggal dan waktu yang dipilih?')">
                <i class="fa-solid fa-rotate-left"></i> Kirim pengajuan pengembalian
            </button>
        </div>
    </form>
</section>
<?php elseif($loan->returnRecord): ?>
<section class="card">
    <h2>Status pengembalian</h2>
    <div class="form-grid">
        <div><span class="muted">Status</span><p><span class="status status-info"><?php echo e(str_replace('_', ' ', $loan->returnRecord->status)); ?></span></p></div>
        <div><span class="muted">Waktu pengembalian yang diminta</span><p><strong><?php echo e($loan->returnRecord->waktu_pengembalian_diminta?->format('d M Y, H:i') ?? '—'); ?> WIB</strong></p></div>
        <div><span class="muted">Pengajuan dikirim</span><p><?php echo e($loan->returnRecord->waktu_pengajuan?->format('d M Y, H:i') ?? '—'); ?></p></div>
        <?php if($loan->returnRecord->waktu_pengembalian_diminta && $loan->returnRecord->waktu_pengembalian_diminta->lt($loan->waktu_selesai)): ?>
            <div style="grid-column:1/-1" class="early-return-notice">
                <strong>Pengembalian lebih awal dari jadwal</strong>
                <p>Jadwal semula: <?php echo e($loan->waktu_selesai?->format('d M Y, H:i')); ?> WIB. Waktu yang diminta: <?php echo e($loan->returnRecord->waktu_pengembalian_diminta->format('d M Y, H:i')); ?> WIB.</p>
            </div>
        <?php endif; ?>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(route('return-records.show', $loan->returnRecord)); ?>">Lihat detail pengembalian</a>
</section>
<?php endif; ?>

<?php if($loan->statusHistories->isNotEmpty()): ?>
<section class="card">
    <h2>Riwayat status</h2>
    <div class="activity-list">
        <?php $__currentLoopData = $loan->statusHistories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $history): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="activity-item">
                <div>
                    <strong><?php echo e(str_replace('_', ' ', $history->status_sesudahnya)); ?></strong>
                    <p class="muted"><?php echo e($history->changedBy?->member?->nama ?? $history->changedBy?->username ?? 'Sistem'); ?></p>
                </div>
                <span class="muted"><?php echo e($history->waktu_perubahan?->format('d/m/Y H:i') ?? $history->created_at?->format('d/m/Y H:i')); ?></span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>
<?php endif; ?>

<?php if(auth()->user()->roles()->where('nama', 'ADMIN')->exists() && $loan->status === 'APPROVED' && !$loan->pendingCancellationRequest): ?>
<section class="card">
    <h2>Penyerahan Barang</h2>
    <p class="muted">Pengajuan sudah disetujui dan unit telah direservasi. Setelah barang benar-benar diserahkan kepada pengguna, konfirmasikan tindakan berikut.</p>
    <div class="alert alert-info" role="status">
        Status saat ini: <strong>APPROVED</strong>. Unit masih <strong>RESERVED</strong> dan belum dihitung sebagai barang yang sedang dipinjam.
    </div>
    <form method="POST" action="<?php echo e(route('loan-requests.mark-borrowed', $loan)); ?>"
          onsubmit="return confirm('Konfirmasi bahwa seluruh barang pada pengajuan ini sudah diserahkan kepada peminjam? Status pengajuan dan unit akan berubah menjadi BORROWED.')">
        <?php echo csrf_field(); ?>
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-hand-holding"></i> Konfirmasi Penyerahan Barang
        </button>
    </form>
</section>
<?php endif; ?>

<?php if(auth()->user()->roles()->where('nama', 'ADMIN')->exists() && $loan->status === 'PENDING'): ?>
<section class="card">
    <h2>Tindakan Korlab</h2>
    <p class="muted">Periksa ketersediaan dan detail barang sebelum mengambil keputusan.</p>

    <div class="actions" style="align-items:flex-start;flex-wrap:wrap">
        <form method="POST" action="<?php echo e(route('loan-requests.approve', $loan)); ?>"
              onsubmit="return confirm('Setujui pengajuan ini?')">
            <?php echo csrf_field(); ?>
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-check"></i> Setujui pengajuan
            </button>
        </form>
    </div>

    <form method="POST" action="<?php echo e(route('loan-requests.cancel-admin', $loan)); ?>" class="form-stack" style="margin-top:18px" onsubmit="return confirm('Batalkan pengajuan PENDING ini?')">
        <?php echo csrf_field(); ?>
        <div class="field"><label for="admin_cancel_pending_reason">Alasan pembatalan oleh Korlab <span class="required">*</span></label><textarea id="admin_cancel_pending_reason" name="alasan_pembatalan" rows="3" minlength="5" maxlength="2000" required placeholder="Tuliskan alasan pembatalan..."></textarea></div>
        <button class="btn btn-danger" type="submit"><i class="fa-solid fa-ban"></i> Batalkan pengajuan</button>
    </form>

    <form method="POST" action="<?php echo e(route('loan-requests.reject', $loan)); ?>" class="form-stack"
          style="margin-top:18px"
          onsubmit="return confirm('Tolak pengajuan ini dengan alasan yang sudah diisi?')">
        <?php echo csrf_field(); ?>
        <div class="field">
            <label for="alasan_penolakan">Alasan penolakan <span class="required">*</span></label>
            <textarea id="alasan_penolakan" name="alasan_penolakan" rows="4" minlength="5" maxlength="2000"
                      required placeholder="Tuliskan alasan penolakan secara jelas..."><?php echo e(old('alasan_penolakan')); ?></textarea>
            <?php $__errorArgs = ['alasan_penolakan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <small class="field-error"><?php echo e($message); ?></small>
            <?php else: ?>
                <small class="muted">Wajib diisi, minimal 5 karakter. Alasan akan ditampilkan kepada pemohon.</small>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div>
            <button class="btn btn-danger" type="submit">
                <i class="fa-solid fa-xmark"></i> Tolak pengajuan
            </button>
        </div>
    </form>
</section>
<?php endif; ?>

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
    const scheduled = <?php echo json_encode($loan->waktu_selesai?->format('Y-m-d\\TH:i'), 15, 512) ?>;
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\inventarisENT\resources\views/loan-requests/show.blade.php ENDPATH**/ ?>