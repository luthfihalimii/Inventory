# Runbook Operasional — ENT Inventory

Panduan deploy, backup/restore, stock opname data awal, dan pelatihan untuk pilot UKM Jurnalistik.
Spec acuan: `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §12, §13.1, §14 Sprint 8.

---

## 1. Prasyarat

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.5 | Ekstensi `pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `bcmath` |
| PostgreSQL | 18 | Role aplikasi butuh hak `CREATE` di schema `public` |
| Bun | 1.4 | Hanya untuk build aset. **Jangan** pakai npm/yarn/pnpm |
| Composer | 2.x | `--no-dev --optimize-autoloader` saat produksi |

Role database aplikasi harus bisa membuat extension `btree_gist` (dipakai constraint anti-overlap
reservasi). Jika role aplikasi bukan superuser, buat sekali sebagai superuser:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

Migration `2026_11_09_000018_create_reservations_table.php` menjalankan statement yang sama, jadi
tanpa extension ini `migrate` akan gagal.

## 2. Environment wajib

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...              # php artisan key:generate --show
APP_URL=https://inventaris.example.org
APP_TIMEZONE=Asia/Jakarta       # jangan diubah: H-2 & expiry dihitung di zona ini

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=ent_inventory
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
LOG_CHANNEL=stderr               # log ke stdout, sesuai §12
LOG_LEVEL=warning

# Email notifikasi (reset password + notifikasi transaksi).
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=inventaris@example.org
MAIL_FROM_NAME="ENT Inventory"
```

## 3. Deploy

Urutan ini diasumsikan dijalankan dari root repo di server.

```bash
composer install --no-dev --optimize-autoloader
bun install --frozen-lockfile
bun run build

php artisan migrate --force

# Seed referensi produksi (role, divisi, kategori, lokasi, SOP aktif).
# JANGAN jalankan `db:seed` polos: DatabaseSeeder memanggil DemoUserSeeder yang membuat
# akun demo berpassword "password".
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=DivisionSeeder --force
php artisan db:seed --class=CategorySeeder --force
php artisan db:seed --class=LocationSeeder --force
php artisan db:seed --class=SopSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan storage:link
```

Catatan:

- `SopSeeder` wajib. Halaman `/peminjaman/baru` memakai `firstOrFail()` pada SOP aktif; tanpa seed,
  pengajuan mengembalikan 404.
- `storage:link` wajib agar foto serah terima/pengembalian dapat diakses dari `/storage/...`.
  Pastikan `storage/app/public` writable oleh user php-fpm.
- `migrate:fresh` **hanya** untuk DB lokal/E2E. Jangan pernah di produksi.
- Perubahan env → `php artisan config:cache` ulang, lalu restart worker + scheduler.

### Topologi container (§12)

| Container | Proses |
|---|---|
| `web` | nginx + php-fpm (aplikasi) |
| `postgres` | PostgreSQL 18 |
| `scheduler` | `php artisan schedule:work` |
| `queue` | `php artisan queue:work --sleep=3 --tries=3` |

`scheduler` dan `queue` **terpisah** dari `web`. Jangan mengandalkan cron di container web.

- Scheduler menjalankan `inventory:overdue` tiap 10 menit dan `inventory:due-reminders` tiap
  pukul 08:00 (keduanya idempotent, `withoutOverlapping`).
  Bisa juga lewat cron: `* * * * * php artisan schedule:run`.
- Queue worker **wajib** hidup: notifikasi email (`NotificationMail`) dikirim lewat queue
  setelah transaksi commit. Tanpa worker, email tidak terkirim tetapi transaksi utama tetap
  berhasil (in-app notification tetap tersimpan).
- `MAIL_*` harus benar sebelum go-live; reset password mandiri bergantung pada pengiriman email.
  Uji dengan `php artisan tinker` → `Mail::raw('tes', fn ($m) => $m->to('admin@example.org')->subject('Tes'));`.

Health check: `GET /up` (HTTP 200). Arahkan monitoring ke endpoint ini.

## 4. Backup harian

Cakupan:

1. Database — `pg_dump` format custom.
2. Storage privat — `storage/app/private` (file privat bila ada).
3. Storage publik — `storage/app/public` (foto serah terima & pengembalian; wajib ikut backup,
   tidak bisa direkonstruksi dari DB).

Contoh script harian (cron di host backup, 02:00 WIB):

```bash
#!/usr/bin/env bash
set -euo pipefail

STAMP=$(date +%F)
DEST=/var/backups/ent-inventory

pg_dump -Fc --no-owner --no-privileges \
  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" \
  -f "$DEST/db-$STAMP.dump" ent_inventory

tar -czf "$DEST/private-$STAMP.tar.gz" -C /srv/ent-inventory/storage/app private
tar -czf "$DEST/public-$STAMP.tar.gz" -C /srv/ent-inventory/storage/app public

# Simpan 14 hari terakhir.
find "$DEST" -name 'db-*.dump' -mtime +14 -delete
find "$DEST" -name 'private-*.tar.gz' -mtime +14 -delete
find "$DEST" -name 'public-*.tar.gz' -mtime +14 -delete
```

`pg_dump` dijalankan dengan user yang punya hak baca seluruh tabel (biasanya role aplikasi).
Salin hasil backup ke lokasi di luar server aplikasi.

## 5. Restore drill (wajib sebelum go-live)

Drill dilakukan di server terpisah atau database scratch, **bukan** DB produksi.

```bash
# 1. Siapkan database kosong.
createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" ent_inventory_restore_drill

# 2. Restore.
pg_restore --no-owner --no-privileges --clean --if-exists \
  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" \
  -d ent_inventory_restore_drill /var/backups/ent-inventory/db-2026-11-09.dump

# 3. Storage.
mkdir -p /tmp/drill-storage/private /tmp/drill-storage/public
tar -xzf /var/backups/ent-inventory/private-2026-11-09.tar.gz -C /tmp/drill-storage/private
tar -xzf /var/backups/ent-inventory/public-2026-11-09.tar.gz -C /tmp/drill-storage/public
```

Foto serah terima/pengembalian harus muncul di detail pengembalian setelah restore; jika tidak,
link `storage:link` atau isi `storage/app/public` belum ikut dipulihkan.

Kriteria lulus:

- `pg_restore` selesai tanpa error, exit code 0.
- `php artisan migrate:status` terhadap DB drill: semua migration `Ran`.
- Jumlah baris tabel kunci sama dengan sumber: `members`, `items`, `assets`, `borrow_requests`,
  `loan_transactions`, `return_requests`, `audit_logs`.
- Aplikasi dijalankan dengan `DB_DATABASE=ent_inventory_restore_drill` dan:
  1. login akun nyata berhasil;
  2. dashboard menampilkan angka yang sama dengan produksi saat backup;
  3. satu pengajuan uji bisa dibuat dan dibatalkan.
- Catat tanggal drill, durasi restore, dan nama pemeriksa di log operasional.

Ulangi drill minimal setiap kuartal dan setelah perubahan schema besar.

## 6. Stock opname data pilot

Tujuan: angka stok di aplikasi sama dengan fisik sebelum go-live. Dilakukan oleh Admin Korlab
didampingi satu pengurus.

Persiapan:

- [ ] Semua barang sudah masuk aplikasi (`/admin/inventory/items`) dengan kode unik.
- [ ] Kategori dan lokasi sudah final.
- [ ] Barang serialized punya satu baris unit per fisik (`/admin/inventory/assets`).
- [ ] Label QR dicetak dari detail barang / detail unit.

Per barang (lakukan per lokasi, dua orang: satu hitung, satu catat):

- [ ] Kode di label sama dengan kode di aplikasi.
- [ ] Jumlah fisik = `quantity_total` (untuk barang quantity).
- [ ] Jumlah unit terdaftar = jumlah fisik (untuk barang serialized).
- [ ] Kondisi tiap unit sesuai: `available`, `damaged`, `maintenance`, `lost`.
- [ ] Lokasi di aplikasi sama dengan lokasi fisik.
- [ ] QR terpasang dan bisa discan (uji satu per barang minimal).

Penutupan:

- [ ] Selisih dicatat dan diperbaiki lewat UI, bukan SQL manual.
- [ ] Barang rusak/hilang punya laporan kerusakan terbuka (`/admin/inventory/damage-reports`).
- [ ] Total `Tersedia / Total` di `/inventory` cocok dengan rekap manual.
- [ ] Berita acara opname ditandatangani Admin Korlab + Wakil Manajer.

## 7. Pelatihan per role

### Anggota (`user`)

1. Login dengan NRP, ganti password awal.
2. Lupa password: tautan "Lupa password?" di halaman masuk → isi email akun → buka tautan reset.
   Email dikirim lewat queue, jadi pastikan worker dan `MAIL_*` aktif.
3. Cari barang di Inventaris → detail → "Tambah ke keranjang".
4. Ajukan: terjadwal minimal H-2; mendadak wajib alasan.
5. Setujui SOP, kirim, simpan nomor referensi.
6. Pantau status di "Peminjaman Saya".
7. Ajukan pengembalian dari detail setelah barang diterima; serahkan barang ke Admin Korlab.
8. Sebelum tenggat, ajukan perpanjangan (alasan + tanggal baru). Satu pengajuan menunggu per
   pinjaman; persetujuan mengecek ulang ketersediaan barang.
9. Pahami blokir: pinjaman terlambat memblokir semua pengajuan sampai diverifikasi.
10. Keamanan → "Perangkat & sesi": lihat perangkat yang masih login, cabut sesi yang tidak dikenal,
    atau "Keluarkan semua perangkat lain". Ganti password juga memutus semua sesi lain.

### Admin Korlab (`admin_korlab`)

1. Approval: periksa pengajuan, setujui/tolak dengan alasan. Tidak bisa menyetujui pengajuan sendiri.
2. Perpanjangan: `/admin/extensions` → setujui/tolak. Persetujuan memindahkan tenggat dan
   mereset pengingat; ditolak jika stok untuk periode perpanjangan tidak cukup.
3. Checkout: cari nomor referensi → "Serahkan" → scan QR tiap unit → centang checklist →
   unggah maks 5 foto kondisi (jpg/png/webp, 5 MB/foto) → konfirmasi.
4. Barang rusak saat keluar: pilih kondisi `damaged`.
5. Verifikasi pengembalian: isi kondisi per baris (`good`/`damaged`/`incomplete`/`lost`),
   unggah foto kondisi kembali. Jumlah kembali boleh sebagian; transaksi selesai hanya setelah
   semua barang kembali. Foto serah terima tampil sebagai pembanding.
6. Laporan kerusakan: selesaikan dengan `repaired` atau `written_off`, isi biaya dan
   vendor/petugas perbaikan. Riwayat 20 laporan terakhir tampil di detail inventaris.
7. Inventaris: tambah/ubah barang, unit, kategori, lokasi; impor CSV untuk data awal; centang
   beberapa barang lalu "Cetak QR terpilih" untuk label massal. Barang "Per unit" wajib
   ditambahkan unitnya di menu Unit aset — tanpa unit, anggota tidak bisa meminjam.
8. Laporan: `/admin/reports` → pilih rentang tanggal → unduh CSV pinjaman, pengembalian,
   kerusakan, atau audit (UTF-8, aman dibuka di Excel).
9. Pengumuman: buat draf lalu terbitkan.

### Wakil Manajer (`wm`)

1. Approval (sama seperti Admin Korlab), termasuk pengajuan mendadak dan perpanjangan.
2. Anggota: tambah/ubah anggota, aktivasi/nonaktivasi.
3. Akun: buat akun, assign/revoke role, reset password (semua sesi akun itu langsung diputus),
   nonaktifkan akun.
4. Divisi, audit log, dan daftar anggota terblokir (`/wm/blocked`).
5. Monitoring dashboard: approval menunggu, pinjaman terlambat, kerusakan terbuka.
6. Laporan: `/admin/reports` → ekspor CSV pinjaman, pengembalian, kerusakan, audit sesuai
   hak akses pengawasan.

## 8. Pemeriksaan pasca-deploy

- [ ] `GET /up` → 200.
- [ ] Login satu akun per role.
- [ ] Dashboard menampilkan angka (bukan error 500).
- [ ] Satu pengajuan uji end-to-end: ajukan → setujui → serah terima (dengan foto) → kembali
      (dengan foto) → verifikasi.
- [ ] `php artisan storage:link` sudah dijalankan; foto tampil di detail pengembalian.
- [ ] Uji email: permintaan reset password sampai ke inbox, dan notifikasi transaksi masuk queue
      (`php artisan queue:work` memproses tanpa error).
- [ ] `php artisan schedule:list` menampilkan `inventory:overdue` dan `inventory:due-reminders`.
- [ ] Log tidak berisi error; `storage/logs` atau stdout sesuai `LOG_CHANNEL`.
- [ ] Backup malam pertama ada (DB + `private` + `public`) dan ukurannya wajar.

## 9. Rollback

1. Aktifkan maintenance: `php artisan down --retry=60`.
2. Kembalikan kode ke rilis sebelumnya.
3. Jika ada migration yang tidak kompatibel ke belakang, restore dari `pg_dump` terakhir
   (data setelah backup akan hilang — konfirmasi ke Wakil Manajer sebelum restore).
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. `php artisan up`.
6. Verifikasi ulang daftar di §8.
