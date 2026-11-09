# Sprint 2 — Inventaris + QR Implementation Plan

**Goal:** Admin/Korlab mengelola kategori, lokasi, item (serialized/quantity), unit aset, menampilkan/mencetak QR, dan mencatat kerusakan/kehilangan. Semua pengguna bisa melihat katalog + ketersediaan.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §5.2, §6.5, §6.6, §7.3, §7.9, §14 Sprint 2

## Global Constraints

- Bun only untuk frontend.
- Tidak ada hard delete untuk item/aset; nonaktifkan lewat status.
- QR token acak 40 karakter, tanpa PII; scan hanya identifikasi (Sprint 5).
- Barang rusak/hilang/maintenance tidak dihitung tersedia.
- `admin_korlab` yang mengelola inventaris; WM memantau (read-only), user melihat katalog.
- Semua perubahan oleh admin tercatat di `audit_logs`.
- `written_off` pada quantity mengurangi `quantity_total`; pada serialized aset menjadi `lost`.
- `repaired` mengembalikan aset ke `available` / stok tersedia.

## Review Focus

- Non-`admin_korlab` bisa menulis inventaris.
- Stok tersedia negatif setelah perubahan `quantity_total` atau laporan kerusakan.
- QR token bisa ditebak/predictable, atau membocorkan data pribadi.
- Perubahan stok tidak tercatat audit.
- Item dinonaktifkan masih muncul di katalog sebagai tersedia.

---

### Task 1: Damage report + availability

**Files:** migration `damage_reports`, `app/Models/DamageReport.php`, `app/Services/AvailabilityService.php`.

- [x] Migration: jenis `damage`/`loss`/`incomplete`/`maintenance`, status `open`/`resolved`, resolusi `repaired`/`written_off`, qty, item/asset, pelapor, resolver.
- [x] Model + relasi ke item/asset/user.
- [x] `AvailabilityService::summary(Item)` menghitung total/tersedia/rusak/maintenance/hilang/nonaktif; quantity dikurangi laporan terbuka.
- [x] Unit test availability untuk kedua metode tracking.

### Task 2: CRUD kategori, lokasi, item, aset

**Files:** `Admin/Inventory/{Category,Location,Item,Asset}Controller`, FormRequest, routes.

- [x] Gate `manage-inventory` untuk `admin_korlab`.
- [x] Kategori/lokasi: list, create, update.
- [x] Item: list + filter, create (generate `qr_token`), update (status, qty, dll).
- [x] Aset: list + filter per item, create (generate `qr_token`), update kondisi/status.
- [x] Guard qty: tidak boleh di bawah total laporan terbuka; tracking method tidak bisa diubah jika sudah ada aset.
- [x] Audit `item.created/updated`, `asset.created/updated`, `category.*`, `location.*`.

### Task 3: Katalog + detail + QR

**Files:** `Inventory/CatalogController`, halaman katalog/detail, halaman QR.

- [x] Katalog: filter nama/kode/kategori/status, tampilkan ketersediaan.
- [x] Detail: info item, daftar unit, ringkasan stok, form laporan kerusakan untuk admin.
- [x] QR: halaman cetak per item (semua unit untuk serialized, satu label untuk quantity).
- [x] QR token tampil sebagai SVG, print-friendly.

### Task 4: Laporan kerusakan + resolusi

**Files:** `Admin/Inventory/DamageReportController`.

- [x] Catat laporan dari detail item; aset serialized ikut berubah status.
- [x] Resolusi `repaired`: aset kembali `available`; `written_off`: aset `lost`, quantity berkurang.
- [x] Audit `damage.reported`, `damage.resolved`.
- [x] Test: stok tidak kembali otomatis, resolusi mengubah stok dengan benar.

### Task 5: Verifikasi

- [x] `php artisan migrate:fresh --seed`
- [x] `php artisan test`
- [x] `composer lint:check`, `composer types:check`
- [x] `bun run check`, `bun run types:check`, `bun run build`
- [x] Commit Sprint 2.

---

## Completion Notes

- Sprint 2 shipped in `b0b656d`; review fixes applied in the follow-up commit.
- Deviations:
  - Tracking method is locked once the item has assets **or** damage reports (stricter than the plan).
  - One open damage report per asset is enforced both in the controller and by a partial unique index (`damage_reports_one_open_per_asset_idx`).
  - Resolving a report is reject-only for `status != open`; `written_off` refuses to reduce `quantity_total` below the reported quantity (no clamping).
  - `repaired` also resets `condition` to `good`.
  - Inactive items report `available = 0` in `AvailabilityService`.
  - Catalog hides `price`, `acquired_at`, `notes`, and asset `serial_number` from non-admin users.
- Deferred: WM read-only inventory screens (currently 403), dashboard still placeholder, N+1 availability queries in item lists.
