# ENT Inventory

**EEPIS News & Network Team Inventory Management System** — aplikasi internal untuk pengelolaan inventaris, peminjaman, pengembalian, anggota, pemeliharaan, pengumuman, dan audit aktivitas ENT.

## Stack

- Laravel 13
- PHP 8.3 atau lebih baru
- MySQL / MariaDB
- Blade, CSS responsif, dan JavaScript ringan
- Tailwind CSS 4 tetap tersedia pada toolchain Vite proyek; tampilan workspace menggunakan design system CSS bersama agar dapat berjalan tanpa kompilasi aset tambahan.

## Instalasi lokal (WAMP)

1. Ekstrak folder `inventarisENT` ke `C:\wamp64\www\inventarisENT`.
2. Pastikan versi PHP CLI dan Apache minimal PHP 8.3. Aktifkan ekstensi PHP yang dibutuhkan Laravel, terutama `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, dan `curl`.
3. Buat database MySQL, misalnya `inventaris_ent`.
4. Salin `.env.example` menjadi `.env`, lalu sesuaikan `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, dan `APP_URL`.
5. Jalankan perintah berikut dari folder proyek:

   ```bash
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```

6. Buat dua akun pengelola awal. Perintah ini hanya membuat akun jika role terkait belum memiliki akun:

   ```bash
   php artisan ent:create-admin
   php artisan ent:create-super-admin
   ```

7. Arahkan DocumentRoot Apache ke folder `public` proyek, atau gunakan virtual host WAMP yang menunjuk ke `C:\wamp64\www\inventarisENT\public`.
8. Buka URL lokal yang sudah ditetapkan di `APP_URL`.

> Jangan menjalankan `php artisan migrate:fresh` pada database yang berisi data penting. Perintah tersebut menghapus seluruh tabel dan data sebelum migrasi ulang. Untuk instalasi pertama gunakan `php artisan migrate --seed`.

## Role dan kewenangan

- **USER**: login menggunakan NRP; melihat inventaris; mengajukan peminjaman; melihat transaksi sendiri; mengajukan pengembalian; membaca pengumuman, SOP, dan notifikasi.
- **ADMIN / Korlab**: login menggunakan username; mengelola jenis dan unit inventaris, kategori, pemeliharaan, persetujuan peminjaman, verifikasi pengembalian, serta audit aktivitas akun sendiri.
- **SUPER_ADMIN / Webmaster**: login menggunakan username; mengelola anggota dan akun; mengelola pengumuman; memantau audit log seluruh sistem dan mengekspor data. Role ini tidak memperoleh endpoint persetujuan atau verifikasi transaksi.

## Import CSV

- Import unit inventaris dilakukan oleh ADMIN. Satu baris mewakili satu unit fisik dan `kode_item` harus cocok dengan jenis inventaris aktif.
- Import anggota dilakukan oleh SUPER_ADMIN. Satu baris mewakili satu anggota dan membuat akun USER berbasis NRP.
- Unduh template langsung dari halaman import. Baris duplikat dilewati. Jika ada baris tidak valid atau master data tidak ditemukan, seluruh batch dibatalkan tanpa penyimpanan parsial.
- CSV anggota berisi password awal dalam teks biasa. Gunakan password sementara yang unik, distribusikan dengan aman, dan hapus file CSV kerja setelah proses selesai. Akun USER diberi penanda wajib mengganti password dan notifikasi pengingat.

## Tampilan dan aksesibilitas

- Tema terang, gelap, dan mengikuti sistem; preferensi tema dan lebar sidebar disimpan pada browser.
- Sidebar dapat diciutkan pada desktop dan menjadi drawer pada layar kecil.
- Motion design menggunakan transisi ringan dan menghormati `prefers-reduced-motion`.
- Tabel memiliki header sticky, filter pencarian pada daftar utama, status badge, dan tampilan kosong.
- Sesi menampilkan peringatan sebelum batas waktu sesi berdasarkan `SESSION_LIFETIME`.
- Halaman laporan mendukung cetak browser / Save as PDF melalui dialog print. Ekspor data terstruktur tersedia sebagai CSV.

## Catatan operasional

- Reset password mandiri melalui email atau OTP tidak tersedia. Pengguna diarahkan menghubungi divisi Webmaster ENT.
- Data identitas anggota pada pengaturan akun bersifat baca-saja. Pengguna hanya dapat mengubah username dan password.
- Akun ADMIN dan SUPER_ADMIN adalah akun pengelola bersama dan tidak menerima pengingat password awal USER.
- Periksa kembali konfigurasi backup database dan storage sebelum digunakan pada lingkungan produksi.
