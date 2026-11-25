# Backend Patch Notes — Inventaris ENT

## Cakupan

Patch ini memperbarui fondasi backend sesuai keputusan sistem. Source Blade tidak tersedia pada paket sumber yang diperiksa, sehingga penyesuaian Views dan Routes belum dilakukan.

## Perubahan utama

1. Data identitas dipindahkan ke tabel `members`; tabel `users` menjadi akun login dengan `member_id`, `username`, `password`, dan status akun.
2. Satu anggota dapat memiliki beberapa akun, tetapi satu akun hanya memiliki satu role.
3. Akun USER masuk menggunakan NRP anggota. Akun ADMIN dan SUPER_ADMIN masuk menggunakan username nonnumerik.
4. Akun ADMIN dan SUPER_ADMIN dibatasi satu akun aktif per role melalui validasi transaksi dan unique generated key pada `manager_terms`.
5. Jabatan kosong untuk anggota baru otomatis diarahkan ke posisi `Tidak Ada`. Seeder juga mengisi data anggota lama yang masih null.
6. Penonaktifan anggota menonaktifkan seluruh akun terhubung, mengubah remember token, mencabut sesi database, dan menutup masa jabatan pengelola aktif.
7. Pergantian pemegang akun ADMIN/SUPER_ADMIN menggunakan akun yang sama, mengganti password, mencabut sesi lama, dan membuat catatan `manager_terms` baru. Method backend tersedia di `MemberController::rotateManagerAccount` (masa jabatan baru dicatat sejak waktu rotasi); route dan form belum ditambahkan.
8. Audit log menyimpan `manager_term_id` agar aktivitas akun pengelola dapat ditelusuri menurut masa jabatan.
9. Logout menghancurkan sesi. Session idle default 120 menit dan `expire_on_close=false` agar penutupan browser tidak dianggap logout.
10. Inventaris tidak dapat mengganti jenis barang setelah memiliki riwayat peminjaman; barang yang masih direservasi/dipinjam tidak dapat dinonaktifkan.
11. Pengajuan APPROVED dapat dibatalkan Admin sebelum serah terima, wajib alasan, melepas reservasi, mencatat histori dan memberi notifikasi. Method `LoanRequestController::cancelApproved` tersedia; route belum ditambahkan.
12. Pengembalian memeriksa seluruh unit sekaligus. Setiap putaran pemeriksaan dan checklist disimpan dengan nomor pemeriksaan, bukan menimpa hasil sebelumnya.
13. Notifikasi pengajuan baru dan pengembalian baru dikirim ke ADMIN; hasil verifikasi pengembalian dikirim ke USER.
14. Pengumuman terjadwal tidak tampil di Dashboard sebelum waktu publikasi.
15. Timezone default aplikasi menjadi `Asia/Jakarta`.
16. UserFactory diselaraskan dengan skema Members/Users.
17. Perintah `php artisan ent:create-super-admin` disediakan untuk membuat akun SUPER_ADMIN pertama secara interaktif tanpa password bawaan di source code.

## Cara menerapkan

**Backup database terlebih dahulu.** Migrasi pemisahan Members/Users memindahkan kolom identitas dari `users` ke `members` dan tidak dapat di-rollback otomatis karena satu anggota dapat memiliki beberapa akun.

Setelah backup, periksa `.env` lokal dan pastikan `APP_TIMEZONE=Asia/Jakarta`, `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, dan `SESSION_EXPIRE_ON_CLOSE=false`. File `.env` tidak disertakan dalam ZIP dan tidak ditimpa.

Setelah backup:

```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed
php artisan ent:create-super-admin
```

Jangan gunakan `php artisan migrate:fresh` pada database yang berisi data.

Jika migrasi melaporkan lebih dari satu akun ADMIN atau SUPER_ADMIN, hentikan proses dan tentukan akun pengelola yang dipertahankan sebelum mencoba migrasi kembali.

## Penyesuaian Views/Routes yang masih diperlukan

1. Form login: input `identity` dengan label NRP/Username. Controller masih menerima nama input lama `nrp` untuk kompatibilitas sementara.
2. Form anggota: kirim data anggota dan `accounts[]` (role, username untuk pengelola, password). Saat edit, akun baru dikirim melalui `new_accounts[]`.
3. Tambahkan route/form untuk `MemberController::rotateManagerAccount`.
4. Tambahkan route/form Admin untuk `LoanRequestController::cancelApproved` dengan field `alasan_pembatalan`.
5. Perbarui halaman anggota untuk menampilkan relasi `Member -> accounts -> roles` dan riwayat `managerTerms`.
6. Perbarui tampilan pemeriksaan pengembalian agar riwayat dikelompokkan berdasarkan `pemeriksaan_ke`.

## Retensi data semesteran

Patch ini tidak membuat penghapusan otomatis data transaksi. Histori audit, peminjaman, pengembalian, dan inspeksi tidak boleh dihapus tanpa proses arsip/retensi yang ditetapkan. File foto/lampiran adalah kandidat utama untuk kebijakan pemindahan atau pembersihan storage.

## Pengujian

Seluruh file PHP pada `app`, `database`, `routes`, `bootstrap`, dan `config` telah lulus `php -l`. Pengujian runtime migration dan PHPUnit belum dapat dilakukan pada lingkungan pemeriksaan karena ekstensi PHP DOM dan PDO SQLite tidak tersedia. Jalankan migrasi pada salinan database terlebih dahulu sebelum menerapkan ke database utama.
