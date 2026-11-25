@extends('layouts.app')
@section('title', 'Daftar Inventaris')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">ASSET DIRECTORY</div><h1>Daftar inventaris</h1><p class="muted">Jelajahi jenis barang dan pantau ketersediaan unit fisik ENT.</p></div>
    @if($canManage)<div class="actions"><details class="export-dropdown"><summary class="btn btn-secondary"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down"></i></summary><div class="export-menu"><a href="{{ route('reports.export.inventory') }}"><i class="fa-solid fa-file-csv"></i> CSV</a><a href="{{ route('reports.export.inventory',['format'=>'pdf']) }}" target="_blank"><i class="fa-solid fa-file-pdf"></i> PDF</a></div></details><a class="btn btn-secondary" href="{{ route('inventory-items.import') }}"><i class="fa-solid fa-file-import"></i> Import CSV</a><a class="btn btn-primary" href="{{ route('inventory-items.create') }}"><i class="fa-solid fa-plus"></i> Tambah inventaris</a></div>@endif
</div>
<section class="panel">
    <div class="table-tools"><div><h2>Inventaris terdaftar</h2><p class="muted" style="font-size:11px;margin:4px 0 0">{{ $items->total() }} jenis inventaris ditemukan</p></div>
        <form method="GET" data-live-filter class="actions inventory-filters"><div class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="{{ request('q') }}" placeholder="Cari kode atau nama..."></div>
            <select name="kategori" data-searchable-select data-search-placeholder="Cari kategori..." aria-label="Filter kategori" style="width:auto;min-width:145px"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('kategori')==$category->id)>{{ $category->nama }}</option>@endforeach</select>
            @if($isManager)<select name="status" data-searchable-select data-search-placeholder="Cari status..." aria-label="Filter status" style="width:auto"><option value="">Semua status</option><option value="ACTIVE" @selected(request('status')==='ACTIVE')>Aktif</option><option value="INACTIVE" @selected(request('status')==='INACTIVE')>Nonaktif</option></select>@endif
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button><a class="btn btn-ghost btn-sm" href="{{ route('inventory-items.index') }}">Reset</a>
        </form>
    </div>
    @if($canManage)
    <form method="POST" action="{{ route('inventory-items.bulk-deactivate') }}" data-confirm="Nonaktifkan semua jenis inventaris yang dipilih? Unit yang sedang direservasi atau dipinjam akan membatalkan seluruh proses.">@csrf
    <div class="table-tools"><label style="display:flex;align-items:center;gap:8px;font-size:11px;color:var(--muted)"><input type="checkbox" data-select-all=".bulk-row" data-count-target="#bulkCount"> Pilih semua pada halaman</label><span class="muted" style="font-size:11px"><strong id="bulkCount">0</strong> dipilih</span><button class="btn btn-danger btn-sm" type="submit" id="bulkDeactivateButton" disabled><i class="fa-solid fa-box-archive"></i> Nonaktifkan terpilih</button></div>
    @endif
    <div class="inventory-grid">
    @forelse($items as $item)
        @php
            $total=(int)$item->total_units; $available=(int)$item->available_units; $borrowed=(int)$item->borrowed_units;
            $latestMaintenance=$item->units->flatMap(fn($unit)=>$unit->maintenanceRecords)->pluck('tanggal_pemeliharaan')->filter()->sortDesc()->first();
            $lastMaintained=$item->units->pluck('terakhir_dipelihara')->filter()->sortDesc()->first();
        @endphp
        <article class="inventory-card">
            <div class="inventory-card-top"><span class="inventory-category">{{ $item->category?->nama ?? 'Tanpa kategori' }}</span>@if($item->status==='ACTIVE')<span class="status status-success">Aktif</span>@else<span class="status status-danger">Nonaktif</span>@endif</div>
            <div class="inventory-card-title"><div><span class="inventory-code">{{ $item->kode_item }}</span><h3>{{ $item->nama }}</h3></div>@if($canManage)<label class="bulk-check"><input class="bulk-row" type="checkbox" name="ids[]" value="{{ $item->id }}" data-count-selection="#bulkCount" data-selector=".bulk-row" aria-label="Pilih {{ $item->nama }}" @disabled($item->status!=='ACTIVE')></label>@endif</div>
            <div class="inventory-counts"><div><strong>{{ $total }}</strong><span>Total unit</span></div><div class="available"><strong>{{ $available }}</strong><span>Tersedia</span></div><div class="borrowed"><strong>{{ $borrowed }}</strong><span>Dipinjam</span></div></div>
            @if($item->deskripsi)<p class="inventory-description">{{ \Illuminate\Support\Str::limit($item->deskripsi,110) }}</p>@else<p class="inventory-description muted">Belum ada deskripsi inventaris.</p>@endif
            <div class="inventory-card-footer"><span class="muted">Pemeliharaan: {{ $latestMaintenance ? \Illuminate\Support\Carbon::parse($latestMaintenance)->format('d/m/Y') : ($lastMaintained ? \Illuminate\Support\Carbon::parse($lastMaintained)->format('d/m/Y') : 'Belum tercatat') }}</span><div class="actions"><a class="btn btn-secondary btn-sm" href="{{ route('inventory-items.show',$item) }}">Detail</a>
                @if(auth()->user()->roles()->where('nama','USER')->exists())
                    <form method="POST" action="{{ route('loan-cart.add',$item) }}" class="quick-add-form" data-quick-add>@csrf<input type="hidden" name="jumlah" value="1"><button class="btn btn-primary btn-sm" type="submit" @disabled($available<1 || $item->status!=='ACTIVE')><i class="fa-solid fa-cart-plus"></i> Tambah</button></form>
                @elseif($canManage)<a class="btn btn-ghost btn-sm" href="{{ route('inventory-items.edit',$item) }}" title="Edit inventaris"><i class="fa-solid fa-pen"></i></a>@endif
            </div></div>
        </article>
    @empty<div class="empty-state"><i class="fa-solid fa-box-open"></i><strong>Inventaris tidak ditemukan</strong><span>Coba ubah kata kunci atau reset filter.</span></div>@endforelse
    </div>
    @if($canManage)</form>@endif
    <div class="pagination">{{ $items->onEachSide(1)->links() }}</div>
</section>
<div class="toast-stack" id="inventoryToast" aria-live="polite"></div>
@endsection
@push('styles')
<style>
.inventory-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.inventory-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:17px;display:flex;flex-direction:column;min-width:0;box-shadow:var(--shadow);transition:transform .18s,border-color .18s}.inventory-card:hover{transform:translateY(-2px);border-color:var(--teal)}.inventory-card-top,.inventory-card-title,.inventory-card-footer{display:flex;align-items:center;justify-content:space-between;gap:10px}.inventory-category,.inventory-code{font-size:10px;color:var(--muted);font-weight:700}.inventory-card-title{align-items:flex-start;margin:12px 0}.inventory-card-title h3{font-size:16px;line-height:1.35;margin:4px 0 0}.bulk-check{padding:3px}.inventory-counts{display:grid;grid-template-columns:repeat(3,1fr);background:var(--surface-2);border:1px solid var(--line);border-radius:10px;padding:12px 6px}.inventory-counts>div{text-align:center;display:grid;gap:2px;border-right:1px solid var(--line)}.inventory-counts>div:last-child{border:0}.inventory-counts strong{font-size:22px;line-height:1.2}.inventory-counts span{font-size:10px;color:var(--muted)}.inventory-counts .available strong{color:#16A34A}.inventory-counts .borrowed strong{color:#D97706}.inventory-description{font-size:11px;line-height:1.6;min-height:36px;margin:12px 0;color:var(--muted)}.inventory-card-footer{margin-top:auto;padding-top:12px;border-top:1px solid var(--line);align-items:flex-end}.inventory-card-footer>span{font-size:10px}.quick-add-form{margin:0}.inventory-filters{justify-content:flex-end}@media(max-width:1100px){.inventory-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.inventory-grid{grid-template-columns:1fr}.inventory-card-footer{align-items:flex-start;flex-direction:column}.inventory-filters{width:100%}.inventory-filters .search-field{min-width:100%}}
</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('[data-quick-add]').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');const original=button.innerHTML;button.disabled=true;button.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i>';
try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Barang tidak dapat ditambahkan.');window.updateFloatingCart?.(result.cart_count);const toast=document.createElement('div');toast.className='alert';toast.textContent=`${result.message} Total keranjang: ${result.cart_count} unit.`;document.getElementById('inventoryToast').appendChild(toast);setTimeout(()=>toast.remove(),4000);}catch(error){const toast=document.createElement('div');toast.className='alert error';toast.textContent=error.message;document.getElementById('inventoryToast').appendChild(toast);setTimeout(()=>toast.remove(),5000);}finally{button.disabled=false;button.innerHTML=original;}}));
</script>
@endpush
