@php
  $routeName = request()->route()?->getName();
  $crumbs = [
    'inventory-items.create'=>['Inventaris','inventory-items.index','Tambah jenis barang'],
    'inventory-items.edit'=>['Inventaris','inventory-items.index','Edit inventaris'],
    'inventory-items.show'=>['Inventaris','inventory-items.index','Detail inventaris'],
    'inventory-units.create'=>['Unit Inventaris','inventory-units.index','Tambah unit'],
    'inventory-units.edit'=>['Unit Inventaris','inventory-units.index','Edit unit'],
    'inventory-units.import'=>['Unit Inventaris','inventory-units.index','Import CSV'],
    'members.create'=>['Manajemen Anggota','members.index','Tambah anggota'],
    'members.edit'=>['Manajemen Anggota','members.index','Edit anggota'],
    'members.show'=>['Manajemen Anggota','members.index','Detail anggota'],
    'members.import'=>['Manajemen Anggota','members.index','Import CSV'],
    'loan-requests.create'=>['Peminjaman','loan-requests.index','Ajukan peminjaman'],
    'loan-requests.show'=>['Peminjaman','loan-requests.index','Detail pengajuan'],
    'return-records.show'=>['Pengembalian','return-records.index','Detail pengembalian'],
    'maintenance-records.create'=>['Pemeliharaan','maintenance-records.index','Tambah catatan'],
    'announcements.create'=>['Pengumuman','announcements.index','Buat pengumuman'],
    'announcements.edit'=>['Pengumuman','announcements.index','Edit pengumuman'],
  ];
@endphp
@if(isset($crumbs[$routeName]))
  @php([$parentLabel,$parentRoute,$currentLabel]=$crumbs[$routeName])
  <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Workspace</a><i class="fa-solid fa-chevron-right"></i><a href="{{ route($parentRoute) }}">{{ $parentLabel }}</a><i class="fa-solid fa-chevron-right"></i><span aria-current="page">{{ $currentLabel }}</span></nav>
@endif
