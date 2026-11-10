# Sprint 10 — P1 Close-out: Foto, Ekspor, Perpanjangan, Reset Password, Kalender, Maintenance

**Goal:** Menutup seluruh P1 PRD yang tersisa: bukti foto serah terima/pengembalian, ekspor laporan, perpanjangan pinjaman, reset password mandiri + notifikasi email, kalender ketersediaan, dan riwayat maintenance/biaya.

**Spec acuan:** PRD `AUTH-11`, `INV-12`, `BOR-20`, `OVR-11`, `DASH-10`, `RES-07`, `DMG-05`, `NOT-06`; spec §6–§13.

## Global Constraints

- Frontend hanya Bun; tanpa dependency baru.
- Semua schema baru lewat migration; timestamp baru `timestampsTz()`.
- Aturan inti tidak berubah: approval, blokir overdue, audit log, ketersediaan.
- Foto hanya jpg/jpeg/png/webp, maks 5 file × 5 MB per transaksi.

## Decisions

- **Foto** disimpan di disk `public` (`handovers/{loan}`, `returns/{return}`), path relatif di kolom jsonb. `handover_records.photos` baru; `return_inspections.photos` sudah ada dan dipakai. Ditampilkan di detail pengembalian sebagai pembanding kondisi.
- **Ekspor** satu halaman `/admin/reports` untuk `admin_korlab` + `wm`, empat jenis CSV: pinjaman, pengembalian, kerusakan, audit. Filter tanggal memakai tanggal transaksi/laporan masing-masing. UTF-8 BOM agar rapi di Excel.
- **Perpanjangan**: tabel `loan_extension_requests`, satu pengajuan `pending` per pinjaman (partial unique index). Hanya pinjaman `borrowed` dan sebelum tenggat. Approval wajib role admin/wm dan mengecek ulang ketersediaan untuk periode `[due_at lama, due_at baru]`; due baru harus setelah due lama. Approval memindahkan `due_at` dan mereset `due_reminder_sent_at`. Pengajuan setelah tenggat ditolak.
- **Reset password** memakai fitur Fortify (`resetPasswords`), bukan alur buatan sendiri. Email notifikasi dikirim lewat queue (`ShouldQueue`, after commit) dengan `NotificationMail`; in-app tetap sumber utama.
- **Kalender** menampilkan 3 bulan ke depan (90 hari) dari reservasi aktif + pinjaman berjalan. Hari ditandai penuh/sebagian berdasarkan jumlah unit terpakai terhadap total stok; tanpa aksi pemesanan dari kalender.
- **Maintenance**: kolom `cost` + `vendor` di `damage_reports`, diisi saat resolve. Riwayat 20 laporan terakhir per barang tampil di detail inventaris untuk admin.

---

### Task 1: Foto serah terima & pengembalian (INV-12)

- [x] Migration `handover_records.photos`; model + factory.
- [x] Upload maks 5 foto di checkout dan verifikasi pengembalian.
- [x] Tampilkan foto serah terima + hasil inspeksi di detail pengembalian.
- [x] Test `Storage::fake('public')`: foto tersimpan, validasi tipe/ukuran, non-admin 403.
- [x] Runbook: `storage:link` + backup `storage/app/public`.

### Task 2: Ekspor laporan CSV (DASH-10)

- [x] `ReportController` index + export 4 jenis, filter `from`/`to`.
- [x] Halaman `/admin/reports` + nav Laporan untuk admin/wm.
- [x] Test: admin & wm bisa unduh, user 403, isi CSV terfilter.

### Task 3: Perpanjangan pinjaman (BOR-20, OVR-11)

- [x] Migration + model + factory `loan_extension_requests`.
- [x] Form pengajuan di detail peminjaman user (sebelum tenggat, alasan + tanggal baru).
- [x] Halaman approval `/admin/extensions` untuk admin/wm.
- [x] Approval cek ketersediaan ulang, pindahkan due, reset pengingat, audit, notifikasi.
- [x] Test: pengajuan valid, setelah tenggat ditolak, approval mengubah due, konflik stok ditolak, role salah 403, pending ganda ditolak.

### Task 4: Reset password + notifikasi email (AUTH-11, NOT-06)

- [x] Aktifkan `Features::resetPasswords()` + view ForgotPassword/ResetPassword.
- [x] Link "Lupa password?" di login.
- [x] `NotificationMail` queued (after commit) dari `NotificationService`.
- [x] Test: kirim link reset, reset token valid mengubah password, email notifikasi terkirim.
- [x] Runbook: konfigurasi MAIL + queue worker.

### Task 5: Kalender ketersediaan (RES-07)

- [x] Prop `schedule` di detail inventaris: reservasi aktif + pinjaman berjalan 90 hari.
- [x] Kalender 3 bulan di `inventory/Show.vue` (penuh/sebagian/kosong).
- [x] Test: schedule berisi reservasi dan pinjaman, hanya rentang berjalan.

### Task 6: Riwayat maintenance + biaya (DMG-05)

- [x] Migration `cost` + `vendor`; model, request, resolve.
- [x] UI resolve mengisi biaya/vendor; riwayat per barang di detail inventaris.
- [x] Test: biaya/vendor tersimpan, riwayat tampil untuk admin.

### Task 7: Verifikasi

Hasil: 217 tests (215 pass, 2 skip, 956 assertions), Pint, PHPStan, `bun run check`, `vue-tsc`, build, 5/5 E2E, `migrate:fresh --seed` — semua hijau.


- [x] `php artisan test`, `composer lint:check`, `composer types:check`.
- [x] `bun run check`, `bun run types:check`, `bun run build`.
- [x] `bun run e2e`, `php artisan migrate:fresh --seed`.
- [x] Update runbook + commit.

---

## Review Focus

- Foto tidak boleh bisa diunggah role selain admin_korlab; path tidak boleh keluar dari disk.
- Approval perpanjangan tidak boleh double-book dan tidak boleh jalan untuk pinjaman yang sudah overdue.
- Email gagal tidak boleh menggagalkan transaksi utama.
- Kalender tidak boleh membocorkan data peminjam (hanya rentang + jumlah).
