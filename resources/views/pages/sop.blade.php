@extends('layouts.app')
@section('title', 'SOP Peminjaman')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">PANDUAN PEMINJAMAN</div><h1>SOP Peminjaman Inventaris</h1><p class="muted">Ketentuan pengajuan, penggunaan, dan pengembalian barang inventaris ENT.</p></div>
    <button class="btn btn-secondary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak SOP</button>
</div>
<div class="panel"><h2><i class="fa-solid fa-circle-info"></i> 1. Ketentuan umum</h2><ol>
<li>Pengajuan dilakukan melalui akun USER milik anggota yang bersangkutan.</li>
<li>Akun ADMIN/Korlab dan SUPER_ADMIN/WM tidak digunakan untuk mengajukan peminjaman. Pengelola yang hendak meminjam wajib menggunakan akun USER.</li>
<li>Barang digunakan untuk kegiatan ENT sesuai kebutuhan dan tanggung jawab peminjam.</li>
<li>Peminjam bertanggung jawab menjaga barang dan seluruh kelengkapannya selama masa peminjaman.</li>
</ol></div>
<div class="panel"><h2><i class="fa-solid fa-cart-shopping"></i> 2. Pengajuan melalui keranjang</h2><ol>
<li>Buka Daftar Inventaris, pilih jenis barang, dan periksa kalender ketersediaan.</li>
<li>Tambahkan barang yang diperlukan ke keranjang. Satu keranjang dapat berisi beberapa jenis barang dengan jumlah unit berbeda.</li>
<li>Periksa kembali jenis barang dan kuantitas sebelum checkout.</li>
<li>Isi kategori pengajuan (terjadwal atau mendadak), waktu mulai dan selesai, serta keterangan tambahan bila diperlukan.</li>
<li>Satu checkout menghasilkan satu pengajuan yang memuat seluruh detail barang di keranjang.</li>
<li>Barang di dalam keranjang belum dipesan dan belum mengurangi ketersediaan unit.</li>
</ol></div>
<div class="panel"><h2><i class="fa-regular fa-calendar-check"></i> 3. Waktu dan ketersediaan</h2><ol>
<li>Pengajuan terjadwal diajukan paling lambat H-2 sebelum waktu penggunaan.</li>
<li>Pengajuan mendadak merupakan pengecualian H-2 dan diprioritaskan berdasarkan kebutuhan serta pertimbangan operasional ADMIN/Korlab.</li>
<li>Kalender menampilkan jadwal peminjaman yang telah disetujui. Pengajuan yang masih menunggu keputusan bukan reservasi final.</li>
<li>Ketersediaan unit diperiksa kembali saat pengajuan diproses. Unit yang sedang dipinjam, direservasi, rusak, atau dalam pemeliharaan tidak dapat dialokasikan.</li>
<li>Jadwal dan jumlah unit yang disetujui mengikuti hasil pemeriksaan ADMIN/Korlab.</li>
</ol></div>
<div class="panel"><h2><i class="fa-solid fa-clipboard-check"></i> 4. Persetujuan dan pengambilan</h2><ol>
<li>ADMIN/Korlab memeriksa dan memutuskan pengajuan.</li>
<li>Barang hanya boleh diambil setelah pengajuan disetujui.</li>
<li>Peminjam mengambil barang sesuai waktu dan lokasi yang tercatat.</li>
<li>Sebelum membawa barang, peminjam memeriksa kondisi dan kelengkapan bersama petugas. Ketidaksesuaian harus dilaporkan sebelum barang digunakan.</li>
</ol></div>
<div class="panel"><h2><i class="fa-solid fa-box-open"></i> 5. Penggunaan dan pengembalian</h2><ol>
<li>Gunakan barang sesuai peruntukan dan jangan memindahtangankannya tanpa koordinasi dengan ADMIN/Korlab.</li>
<li>Barang dikembalikan sesuai waktu selesai pada pengajuan.</li>
<li>Ajukan pengembalian melalui sistem dan sertakan foto kondisi barang sesuai instruksi pada formulir pengembalian.</li>
<li>Pengembalian belum selesai sebelum diperiksa dan diverifikasi ADMIN/Korlab.</li>
<li>Jika ditemukan kerusakan atau kelengkapan tidak sesuai, hasil pemeriksaan dicatat dan unit dapat ditahan dari status tersedia sampai tindak lanjut selesai.</li>
</ol></div>
<div class="panel" id="sop-pengembalian"><h2><i class="fa-solid fa-rotate-left"></i> 6. Prosedur pengembalian barang</h2><ol>
<li>Pastikan seluruh barang dan kelengkapannya siap dikembalikan sesuai waktu selesai pada pengajuan.</li>
<li>Buka detail pengajuan yang berstatus BORROWED, lalu pilih formulir Ajukan Pengembalian.</li>
<li>Tentukan waktu pengembalian yang direncanakan. Jika lebih awal dari jadwal semula, baca pemberitahuan dan berikan konfirmasi pada sistem.</li>
<li>Isi catatan kondisi barang atau informasi yang perlu diketahui Korlab. Foto kondisi barang dapat dilampirkan sesuai instruksi formulir.</li>
<li>Kirim pengajuan pengembalian melalui sistem. Status pengembalian akan menunggu pemeriksaan ADMIN/Korlab.</li>
<li>Serahkan barang beserta seluruh kelengkapannya kepada ADMIN/Korlab untuk diperiksa. Pengembalian belum dianggap selesai sebelum diverifikasi.</li>
<li>Jika ditemukan kerusakan atau kelengkapan tidak sesuai, hasil pemeriksaan dicatat dan unit dapat ditahan dari status tersedia sampai tindak lanjut selesai.</li>
</ol><p class="muted" style="font-size:11px">Urutan dan ketentuan di atas dirangkum dari bagian “Penggunaan dan pengembalian” serta “Keterlambatan dan kendala” pada draft SOP yang sudah ada.</p></div>
<div class="panel"><h2><i class="fa-solid fa-triangle-exclamation"></i> 7. Keterlambatan dan kendala</h2><ol>
<li>Jika berpotensi terlambat, segera hubungi ADMIN/Korlab sebelum batas pengembalian.</li>
<li>Perpanjangan waktu tidak berlaku otomatis dan harus mendapat persetujuan ADMIN/Korlab.</li>
<li>Kerusakan, kehilangan, atau kendala selama penggunaan harus segera dilaporkan melalui fitur Pelaporan Masalah dan disampaikan kepada ADMIN/Korlab.</li>
</ol></div>
<div class="alert" style="background:var(--blue-soft);color:var(--navy);padding:14px 16px;border-radius:11px"><strong>Catatan dokumen</strong><p style="margin:5px 0 0">Halaman ini merupakan draft panduan berdasarkan alur ENT Inventory. Ketentuan final, termasuk sanksi dan penanggung jawab, perlu ditinjau serta disahkan oleh pengurus ENT sebelum diberlakukan sebagai SOP resmi.</p></div>
@endsection
