<?php $__env->startSection('title','Pengajuan Peminjaman'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <div class="eyebrow">LOAN MANAGEMENT</div>
        <h1><?php echo e(auth()->user()->roles()->where('nama','USER')->exists() ? 'Peminjaman saya' : 'Pengajuan peminjaman'); ?></h1>
        <p class="muted">Transaksi aktif, riwayat peminjaman selesai, dan pengajuan yang tidak dilanjutkan ditampilkan terpisah.</p>
    </div>
    <div class="actions">
        <?php if(auth()->user()->roles()->whereIn('nama',['ADMIN','SUPER_ADMIN'])->exists()): ?>
            <details class="export-dropdown">
                <summary class="btn btn-secondary"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="export-menu">
                    <a href="<?php echo e(route('reports.export.loans')); ?>"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="<?php echo e(route('reports.export.loans',['format'=>'pdf'])); ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                </div>
            </details>
        <?php else: ?>
            <button class="btn btn-secondary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak PDF</button>
        <?php endif; ?>
        <?php if(auth()->user()->roles()->where('nama','USER')->exists()): ?>
            <a class="btn btn-primary" href="<?php echo e(route('inventory-items.index')); ?>"><i class="fa-solid fa-plus"></i> Ajukan peminjaman</a>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <div class="table-tools">
        <div>
            <h2>Filter transaksi</h2>
            <p class="muted" style="font-size:11px;margin:4px 0 0">Hasil diperbarui otomatis saat filter berubah.</p>
        </div>
        <form method="GET" action="<?php echo e(route('loan-requests.index')); ?>" data-live-filter class="actions">
            <div class="search-field">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input name="q" value="<?php echo e(request('q')); ?>" placeholder="Cari kode pengajuan, kode nama, atau pemohon..." autocomplete="off">
            </div>
            <select name="status" data-searchable-select data-search-placeholder="Cari status..." style="width:auto">
                <option value="">Semua status</option>
                <?php $__currentLoopData = ['PENDING','APPROVED','CANCELLATION_REQUESTED','REJECTED','BORROWED','RETURN_PENDING','RETURN_ISSUE','COMPLETED','CANCELLED']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>><?php echo e($status === 'CANCELLATION_REQUESTED' ? 'PERMOHONAN PEMBATALAN' : str_replace('_',' ',$status)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <select name="kategori" data-searchable-select data-search-placeholder="Cari kategori..." style="width:auto">
                <option value="">Semua kategori</option>
                <option value="URGENT" <?php if(request('kategori') === 'URGENT'): echo 'selected'; endif; ?>>Mendesak</option>
                <option value="SCHEDULED" <?php if(request('kategori') === 'SCHEDULED'): echo 'selected'; endif; ?>>Terjadwal</option>
            </select>
            <label class="sr-only" for="loan-from">Dari tanggal</label>
            <input id="loan-from" type="date" name="from" value="<?php echo e(request('from')); ?>" aria-label="Dari tanggal">
            <label class="sr-only" for="loan-to">Sampai tanggal</label>
            <input id="loan-to" type="date" name="to" value="<?php echo e(request('to')); ?>" aria-label="Sampai tanggal">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            <a class="btn btn-ghost btn-sm" href="<?php echo e(route('loan-requests.index')); ?>">Reset</a>
        </form>
    </div>
</section>

<section class="panel">
    <div class="section-heading">
        <div>
            <h2><i class="fa-solid fa-arrows-rotate"></i> Transaksi berlangsung</h2>
            <p class="muted">Pengajuan yang masih berada dalam proses peminjaman atau pengembalian.</p>
        </div>
        <span class="status status-info"><?php echo e($ongoingRequests->total()); ?> transaksi</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Kode pengajuan</th><th>Pengaju</th><th>Kategori</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $ongoingRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $loan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><strong><?php echo e($loan->kode_pengajuan); ?></strong></td>
                    <td><?php echo e($loan->user?->member?->kode ?? '—'); ?> · <?php echo e($loan->user?->member?->nama ?? $loan->user?->username ?? '—'); ?></td>
                    <td><span class="status <?php echo e($loan->kategori === 'URGENT' ? 'status-warning' : 'status-info'); ?>"><?php echo e($loan->kategori === 'URGENT' ? 'Mendesak' : 'Terjadwal'); ?></span></td>
                    <td><?php echo e($loan->waktu_mulai?->format('d/m/Y H:i')); ?><div class="muted" style="font-size:10px">s.d. <?php echo e($loan->waktu_selesai?->format('d/m/Y H:i')); ?></div></td>
                    <td><span class="status <?php echo e($loan->pendingCancellationRequest ? 'status-warning' : ($loan->status === 'PENDING' ? 'status-warning' : ($loan->status === 'RETURN_ISSUE' ? 'status-danger' : 'status-info'))); ?>"><?php echo e($loan->pendingCancellationRequest ? 'PERMOHONAN PEMBATALAN' : str_replace('_',' ',$loan->status)); ?></span></td>
                    <td><a class="btn btn-secondary btn-sm" href="<?php echo e(route('loan-requests.show',$loan)); ?>" title="Detail pengajuan"><i class="fa-regular fa-eye"></i></a></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-clipboard-check"></i><strong>Tidak ada transaksi yang sedang berlangsung</strong><span>Pengajuan yang masih diproses akan muncul di bagian ini.</span></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination"><?php echo e($ongoingRequests->links()); ?></div>
</section>

<section class="panel success-history">
    <div class="section-heading">
        <div>
            <h2><i class="fa-solid fa-circle-check"></i> Success history</h2>
            <p class="muted">Riwayat transaksi yang pengembaliannya telah diverifikasi dan berstatus COMPLETED.</p>
        </div>
        <span class="status status-success"><?php echo e($completedRequests->total()); ?> selesai</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Kode pengajuan</th><th>Pengaju</th><th>Kategori</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $completedRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $loan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><strong><?php echo e($loan->kode_pengajuan); ?></strong></td>
                    <td><?php echo e($loan->user?->member?->kode ?? '—'); ?> · <?php echo e($loan->user?->member?->nama ?? $loan->user?->username ?? '—'); ?></td>
                    <td><span class="status <?php echo e($loan->kategori === 'URGENT' ? 'status-warning' : 'status-info'); ?>"><?php echo e($loan->kategori === 'URGENT' ? 'Mendesak' : 'Terjadwal'); ?></span></td>
                    <td><?php echo e($loan->waktu_mulai?->format('d/m/Y H:i')); ?><div class="muted" style="font-size:10px">s.d. <?php echo e($loan->waktu_selesai?->format('d/m/Y H:i')); ?></div></td>
                    <td><span class="status status-success">COMPLETED</span></td>
                    <td><a class="btn btn-secondary btn-sm" href="<?php echo e(route('loan-requests.show',$loan)); ?>" title="Lihat riwayat"><i class="fa-regular fa-eye"></i></a></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-box-open"></i><strong>Belum ada success history</strong><span>Transaksi akan masuk ke sini setelah pengembalian selesai diverifikasi oleh Korlab.</span></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination"><?php echo e($completedRequests->links()); ?></div>
</section>

<section class="panel">
    <div class="section-heading">
        <div>
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Riwayat tidak dilanjutkan</h2>
            <p class="muted">Pengajuan yang ditolak atau dibatalkan. Data tetap tersedia untuk keperluan penelusuran.</p>
        </div>
        <span class="status status-warning"><?php echo e($closedRequests->total()); ?> transaksi</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Kode pengajuan</th><th>Pengaju</th><th>Kategori</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $closedRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $loan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><strong><?php echo e($loan->kode_pengajuan); ?></strong></td>
                    <td><?php echo e($loan->user?->member?->kode ?? '—'); ?> · <?php echo e($loan->user?->member?->nama ?? $loan->user?->username ?? '—'); ?></td>
                    <td><?php echo e($loan->kategori === 'URGENT' ? 'Mendesak' : 'Terjadwal'); ?></td>
                    <td><?php echo e($loan->waktu_mulai?->format('d/m/Y H:i')); ?><div class="muted" style="font-size:10px">s.d. <?php echo e($loan->waktu_selesai?->format('d/m/Y H:i')); ?></div></td>
                    <td><span class="status status-danger"><?php echo e($loan->status); ?></span></td>
                    <td><a class="btn btn-secondary btn-sm" href="<?php echo e(route('loan-requests.show',$loan)); ?>" title="Lihat detail"><i class="fa-regular fa-eye"></i></a></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-folder-open"></i><strong>Tidak ada riwayat yang ditutup</strong><span>Pengajuan yang ditolak atau dibatalkan akan muncul di sini.</span></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination"><?php echo e($closedRequests->links()); ?></div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\inventarisENT\resources\views/loan-requests/index.blade.php ENDPATH**/ ?>