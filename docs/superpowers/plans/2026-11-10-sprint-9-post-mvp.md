# Sprint 9 — Post-MVP: Impor Inventaris, Pengingat H-1, Pengembalian Sebagian, Batch QR

**Goal:** Menutup empat gap operasional yang muncul setelah MVP: memindahkan inventaris awal tanpa input satu-satu, mengingatkan tenggat sehari sebelumnya, mencatat pengembalian sebagian, dan mencetak label QR sekaligus.

**Spec acuan:** PRD `BOR-20`/`OVR-10`/`RET-12`/`INV-12` (P1), spec §7.6, §12, §13.

## Global Constraints

- Frontend hanya Bun (`bun run`), tanpa dependency baru.
- Tidak mengubah aturan inti: approval, blokir overdue, dan audit log tetap.
- Semua perubahan schema lewat migration baru; data lama di-backfill.

## Decisions

- **Impor CSV**: satu file CSV ber-header, atomik (satu baris salah = batal semua). Kolom wajib: `code,name,category,location,tracking_method`. Opsional: `quantity_total,price,acquired_at,status,notes,asset_codes`. Kategori/lokasi dicocokkan berdasarkan nama (case-insensitive) atau kode lokasi. `asset_codes` dipisah `;`/`|` untuk barang serialized. Tanpa dependency baru — `fgetcsv`.
- **Pengingat H-1**: kolom `due_reminder_sent_at` di `loan_transactions` (idempoten, bukan hitung notifikasi). Command baru `inventory:due-reminders`, dijadwalkan harian 08:00 Asia/Jakarta. Hanya pinjaman `borrowed` yang jatuh tempo besok; in-app notification lewat `NotificationService`.
- **Pengembalian sebagian**: kolom `quantity_returned` di `loan_items`. Verifikasi menerima jumlah ≤ sisa; transaksi selesai hanya jika semua baris terpenuhi. Baris yang belum kembali kembali berstatus `borrowed`/`overdue` dan blokir tetap aktif. Backfill: pinjaman `completed` dianggap sudah kembali penuh.
- **Batch QR**: halaman QR yang sama menerima `items[]`; item index punya checkbox + tombol cetak. Batch dibatasi 100 barang.

---

### Task 1: Pengembalian sebagian (RET-12)

**Files:** migration `add_quantity_returned_to_loan_items`, `LoanItem`, `ReturnController`, `VerifyReturnRequest` (bila perlu), `admin/return/Show.vue`, `ReturnTest`.

- [x] Migration + model + factory.
- [x] Validasi: total per baris ≤ sisa; baris boleh tidak dikirim (tidak kembali).
- [x] `quantity_returned` di-increment; complete hanya jika semua baris penuh.
- [x] Blokir dilepas hanya saat transaksi selesai.
- [x] UI menampilkan sisa dan prefills sesuai sisa.
- [x] Test: parsial tetap terbuka, kelebihan kembali ditolak, pelunasan menutup transaksi.

### Task 2: Pengingat H-1 (OVR-10)

**Files:** migration `add_due_reminder_sent_at_to_loan_transactions`, `LoanTransaction`, command `SendDueRemindersCommand`, `routes/console.php`, test.

- [x] Kolom penanda idempoten.
- [x] Command + notifikasi in-app.
- [x] Jadwal harian 08:00.
- [x] Test: dikirim sekali, tidak untuk pinjaman jauh/terlambat.

### Task 3: Impor inventaris CSV

**Files:** `ImportItemsRequest`, `ItemController@import`, route, form di `admin/inventory/items/Index.vue`, test.

- [x] Parser header + validasi baris, atomik.
- [x] Buat item + unit (`asset_codes`) + QR token.
- [x] Audit per item dibuat.
- [x] Test: sukses, kategori tidak dikenal membatalkan semua, kode duplikat ditolak, non-admin 403.

### Task 4: Batch cetak QR

**Files:** `ItemController@qr`/`qrBatch`, route, `Qr.vue`, item index, test.

- [x] Route batch + validasi maksimal 100 id.
- [x] Checkbox dan tombol cetak di item index.
- [x] `Qr.vue` merender banyak barang.
- [x] Test akses + isi halaman.

### Task 5: Verifikasi

- [x] `php artisan test`, `composer lint:check`, `composer types:check`.
- [x] `bun run check`, `bun run types:check`, `bun run build`.
- [x] `bun run e2e` (regresi alur kritis).
- [x] `php artisan migrate:fresh --seed`.
- [x] Commit.

---

## Review Focus

- Partial return tidak boleh melepas blokir sebelum semua barang kembali.
- Pengingat tidak boleh dobel saat scheduler jalan ulang.
- Impor tidak boleh meninggalkan data setengah jadi.
- Batch QR tidak boleh bocor ke role selain `admin_korlab`.

---

## Completion Notes

### Rulings

- **Ruling:** Impor CSV hanya lewat satu form di halaman kelola barang, tanpa endpoint unduh template. Header ditulis di UI. Alasan: template statis menambah route + file yang mudah basi; header di UI selalu cocok dengan parser. Biaya bila salah: pengguna menyalin header manual sekali.
- **Ruling:** Impor atomik dan menolak seluruh file bila ada satu baris salah, termasuk duplikat kode/unit. Alasan: impor setengah jadi lebih berbahaya daripada gagal total; admin bisa perbaiki CSV lalu ulang. Biaya bila salah: file 500 baris dengan satu typo harus diulang setelah diperbaiki (tetap satu kali submit).
- **Ruling:** Pencocokan kategori/lokasi memakai nama case-insensitive; lokasi juga menerima kode. Alasan: CSV hasil export spreadsheet biasanya berisi nama, bukan id. Biaya bila salah: nama kategori yang sama beda ejaan dianggap tidak dikenal dan file ditolak.
- **Ruling:** Pengembalian sebagian memakai kolom `quantity_returned` per `loan_items`, bukan tabel status per item. Alasan: semua laporan kondisi sudah per baris `loan_item`; satu counter cukup untuk sisa, dan backfill transaksi lama mudah. Biaya bila salah: bila nanti butuh status per unit serialized yang berbeda-beda, perlu tabel detail tambahan.
- **Ruling:** Transaksi selesai hanya jika semua `loan_items.quantity_returned == quantity`; blokir hanya dilepas saat itu. Alasan: mempertahankan aturan §9.4 — anggota masih memegang barang, jadi tidak boleh bebas mengajukan pinjaman baru. Biaya bila salah: anggota tetap terblokir sampai barang terakhir diverifikasi, sesuai aturan.
- **Ruling:** Pengingat H-1 memakai penanda `due_reminder_sent_at`, bukan mengecek riwayat notifikasi. Alasan: idempoten tanpa memindai JSON `data`, dan tetap benar walau notifikasi gagal dikirim (percobaan berikutnya tidak mengulang di hari yang sama). Biaya bila salah: notifikasi yang gagal permanen tidak di-retry otomatis.
- **Ruling:** Pengingat dijadwalkan harian 08:00, bukan tiap jam. Alasan: pinjaman UKM dibuat pada jam kerja; 08:00 adalah waktu baca notifikasi yang wajar. Biaya bila salah: pinjaman yang dibuat setelah 08:00 untuk tenggat besok tidak mendapat pengingat hari itu.
- **Ruling:** Batch QR memakai halaman `Qr.vue` yang sama dengan prop `items[]`, maksimal 100 barang per cetak. Alasan: satu komponen, satu layout cetak, tidak ada duplikasi. Biaya bila salah: label >100 barang harus dicetak dua kali.

### Hasil verifikasi

- `php artisan test`: 194 test, 192 pass, 2 skip, 833 assertion.
- `composer lint:check`, `composer types:check`: bersih.
- `bun run check`, `bun run types:check`, `bun run build`: bersih.
- `bun run e2e`: 5 spec lulus (regresi alur kritis).
- `php artisan migrate:fresh --seed`: sukses.
