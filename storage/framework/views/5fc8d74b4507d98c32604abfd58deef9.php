<?php $__env->startSection('title', 'Daftar Inventaris'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><div class="eyebrow">ASSET DIRECTORY</div><h1>Daftar inventaris</h1><p class="muted">Jelajahi jenis barang dan pantau ketersediaan unit fisik ENT.</p></div>
    <?php if($canManage): ?><div class="actions"><details class="export-dropdown"><summary class="btn btn-secondary"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down"></i></summary><div class="export-menu"><a href="<?php echo e(route('reports.export.inventory')); ?>"><i class="fa-solid fa-file-csv"></i> CSV</a><a href="<?php echo e(route('reports.export.inventory',['format'=>'pdf'])); ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> PDF</a></div></details><a class="btn btn-secondary" href="<?php echo e(route('inventory-items.import')); ?>"><i class="fa-solid fa-file-import"></i> Import CSV</a><a class="btn btn-primary" href="<?php echo e(route('inventory-items.create')); ?>"><i class="fa-solid fa-plus"></i> Tambah inventaris</a></div><?php endif; ?>
</div>
<section class="panel">
    <div class="table-tools"><div><h2>Inventaris terdaftar</h2><p class="muted" style="font-size:11px;margin:4px 0 0"><?php echo e($items->total()); ?> jenis inventaris ditemukan</p></div>
        <form method="GET" data-live-filter class="actions inventory-filters"><div class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?php echo e(request('q')); ?>" placeholder="Cari kode atau nama..."></div>
            <select name="kategori" data-searchable-select data-search-placeholder="Cari kategori..." aria-label="Filter kategori" style="width:auto;min-width:145px"><option value="">Semua kategori</option><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category->id); ?>" <?php if(request('kategori')==$category->id): echo 'selected'; endif; ?>><?php echo e($category->nama); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
            <?php if($isManager): ?><select name="status" data-searchable-select data-search-placeholder="Cari status..." aria-label="Filter status" style="width:auto"><option value="">Semua status</option><option value="ACTIVE" <?php if(request('status')==='ACTIVE'): echo 'selected'; endif; ?>>Aktif</option><option value="INACTIVE" <?php if(request('status')==='INACTIVE'): echo 'selected'; endif; ?>>Nonaktif</option></select><?php endif; ?>
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button><a class="btn btn-ghost btn-sm" href="<?php echo e(route('inventory-items.index')); ?>">Reset</a>
        </form>
    </div>
    <?php if($canManage): ?>
    <form method="POST" action="<?php echo e(route('inventory-items.bulk-deactivate')); ?>" data-confirm="Nonaktifkan semua jenis inventaris yang dipilih? Unit yang sedang direservasi atau dipinjam akan membatalkan seluruh proses."><?php echo csrf_field(); ?>
    <div class="table-tools"><label style="display:flex;align-items:center;gap:8px;font-size:11px;color:var(--muted)"><input type="checkbox" data-select-all=".bulk-row" data-count-target="#bulkCount"> Pilih semua pada halaman</label><span class="muted" style="font-size:11px"><strong id="bulkCount">0</strong> dipilih</span><button class="btn btn-danger btn-sm" type="submit" id="bulkDeactivateButton" disabled><i class="fa-solid fa-box-archive"></i> Nonaktifkan terpilih</button></div>
    <?php endif; ?>
    <div class="inventory-grid">
    <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $total=(int)$item->total_units; $available=(int)$item->available_units; $borrowed=(int)$item->borrowed_units;
            $latestMaintenance=$item->units->flatMap(fn($unit)=>$unit->maintenanceRecords)->pluck('tanggal_pemeliharaan')->filter()->sortDesc()->first();
            $lastMaintained=$item->units->pluck('terakhir_dipelihara')->filter()->sortDesc()->first();
        ?>
        <article class="inventory-card">
            <div class="inventory-card-top"><span class="inventory-category"><?php echo e($item->category?->nama ?? 'Tanpa kategori'); ?></span><?php if($item->status==='ACTIVE'): ?><span class="status status-success">Aktif</span><?php else: ?><span class="status status-danger">Nonaktif</span><?php endif; ?></div>
            <div class="inventory-card-title"><div><span class="inventory-code"><?php echo e($item->kode_item); ?></span><h3><?php echo e($item->nama); ?></h3></div><?php if($canManage): ?><label class="bulk-check"><input class="bulk-row" type="checkbox" name="ids[]" value="<?php echo e($item->id); ?>" data-count-selection="#bulkCount" data-selector=".bulk-row" aria-label="Pilih <?php echo e($item->nama); ?>" <?php if($item->status!=='ACTIVE'): echo 'disabled'; endif; ?>></label><?php endif; ?></div>
            <div class="inventory-counts"><div><strong><?php echo e($total); ?></strong><span>Total unit</span></div><div class="available"><strong><?php echo e($available); ?></strong><span>Tersedia</span></div><div class="borrowed"><strong><?php echo e($borrowed); ?></strong><span>Dipinjam</span></div></div>
            <?php if($item->deskripsi): ?><p class="inventory-description"><?php echo e(\Illuminate\Support\Str::limit($item->deskripsi,110)); ?></p><?php else: ?><p class="inventory-description muted">Belum ada deskripsi inventaris.</p><?php endif; ?>
            <div class="inventory-card-footer"><span class="muted">Pemeliharaan: <?php echo e($latestMaintenance ? \Illuminate\Support\Carbon::parse($latestMaintenance)->format('d/m/Y') : ($lastMaintained ? \Illuminate\Support\Carbon::parse($lastMaintained)->format('d/m/Y') : 'Belum tercatat')); ?></span><div class="actions"><a class="btn btn-secondary btn-sm" href="<?php echo e(route('inventory-items.show',$item)); ?>">Detail</a>
                <?php if(auth()->user()->roles()->where('nama','USER')->exists()): ?>
                    <form method="POST" action="<?php echo e(route('loan-cart.add',$item)); ?>" class="quick-add-form" data-quick-add><?php echo csrf_field(); ?><input type="hidden" name="jumlah" value="1"><button class="btn btn-primary btn-sm" type="submit" <?php if($available<1 || $item->status!=='ACTIVE'): echo 'disabled'; endif; ?>><i class="fa-solid fa-cart-plus"></i> Tambah</button></form>
                <?php elseif($canManage): ?><a class="btn btn-ghost btn-sm" href="<?php echo e(route('inventory-items.edit',$item)); ?>" title="Edit inventaris"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
            </div></div>
        </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state"><i class="fa-solid fa-box-open"></i><strong>Inventaris tidak ditemukan</strong><span>Coba ubah kata kunci atau reset filter.</span></div><?php endif; ?>
    </div>
    <?php if($canManage): ?></form><?php endif; ?>
    <div class="pagination"><?php echo e($items->onEachSide(1)->links()); ?></div>
</section>
<div class="toast-stack" id="inventoryToast" aria-live="polite"></div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('styles'); ?>
<style>
.inventory-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.inventory-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:17px;display:flex;flex-direction:column;min-width:0;box-shadow:var(--shadow);transition:transform .18s,border-color .18s}.inventory-card:hover{transform:translateY(-2px);border-color:var(--teal)}.inventory-card-top,.inventory-card-title,.inventory-card-footer{display:flex;align-items:center;justify-content:space-between;gap:10px}.inventory-category,.inventory-code{font-size:10px;color:var(--muted);font-weight:700}.inventory-card-title{align-items:flex-start;margin:12px 0}.inventory-card-title h3{font-size:16px;line-height:1.35;margin:4px 0 0}.bulk-check{padding:3px}.inventory-counts{display:grid;grid-template-columns:repeat(3,1fr);background:var(--surface-2);border:1px solid var(--line);border-radius:10px;padding:12px 6px}.inventory-counts>div{text-align:center;display:grid;gap:2px;border-right:1px solid var(--line)}.inventory-counts>div:last-child{border:0}.inventory-counts strong{font-size:22px;line-height:1.2}.inventory-counts span{font-size:10px;color:var(--muted)}.inventory-counts .available strong{color:#16A34A}.inventory-counts .borrowed strong{color:#D97706}.inventory-description{font-size:11px;line-height:1.6;min-height:36px;margin:12px 0;color:var(--muted)}.inventory-card-footer{margin-top:auto;padding-top:12px;border-top:1px solid var(--line);align-items:flex-end}.inventory-card-footer>span{font-size:10px}.quick-add-form{margin:0}.inventory-filters{justify-content:flex-end}@media(max-width:1100px){.inventory-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.inventory-grid{grid-template-columns:1fr}.inventory-card-footer{align-items:flex-start;flex-direction:column}.inventory-filters{width:100%}.inventory-filters .search-field{min-width:100%}}
</style>
<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
<script>
document.querySelectorAll('[data-quick-add]').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');const original=button.innerHTML;button.disabled=true;button.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i>';
try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Barang tidak dapat ditambahkan.');window.updateFloatingCart?.(result.cart_count);const toast=document.createElement('div');toast.className='alert';toast.textContent=`${result.message} Total keranjang: ${result.cart_count} unit.`;document.getElementById('inventoryToast').appendChild(toast);setTimeout(()=>toast.remove(),4000);}catch(error){const toast=document.createElement('div');toast.className='alert error';toast.textContent=error.message;document.getElementById('inventoryToast').appendChild(toast);setTimeout(()=>toast.remove(),5000);}finally{button.disabled=false;button.innerHTML=original;}}));
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\inventarisENT\resources\views/inventory-items/index.blade.php ENDPATH**/ ?>