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

- [ ] Migration: jenis `damage`/`loss`/`incomplete`/`maintenance`, status `open`/`resolved`, resolusi `repaired`/`written_off`, qty, item/asset, pelapor, resolver.
- [ ] Model + relasi ke item/asset/user.
- [ ] `AvailabilityService::summary(Item)` menghitung total/tersedia/rusak/maintenance/hilang/nonaktif; quantity dikurangi laporan terbuka.
- [ ] Unit test availability untuk kedua metode tracking.

### Task 2: CRUD kategori, lokasi, item, aset

**Files:** `Admin/Inventory/{Category,Location,Item,Asset}Controller`, FormRequest, routes.

- [ ] Gate `manage-inventory` untuk `admin_korlab`.
- [ ] Kategori/lokasi: list, create, update.
- [ ] Item: list + filter, create (generate `qr_token`), update (status, qty, dll).
- [ ] Aset: list + filter per item, create (generate `qr_token`), update kondisi/status.
- [ ] Guard qty: tidak boleh di bawah total laporan terbuka; tracking method tidak bisa diubah jika sudah ada aset.
- [ ] Audit `item.created/updated`, `asset.created/updated`, `category.*`, `location.*`.

### Task 3: Katalog + detail + QR

**Files:** `Inventory/CatalogController`, halaman katalog/detail, halaman QR.

- [ ] Katalog: filter nama/kode/kategori/status, tampilkan ketersediaan.
- [ ] Detail: info item, daftar unit, ringkasan stok, form laporan kerusakan untuk admin.
- [ ] QR: halaman cetak per item (semua unit untuk serialized, satu label untuk quantity).
- [ ] QR token tampil sebagai SVG, print-friendly.

### Task 4: Laporan kerusakan + resolusi

**Files:** `Admin/Inventory/DamageReportController`.

- [ ] Catat laporan dari detail item; aset serialized ikut berubah status.
- [ ] Resolusi `repaired`: aset kembali `available`; `written_off`: aset `lost`, quantity berkurang.
- [ ] Audit `damage.reported`, `damage.resolved`.
- [ ] Test: stok tidak kembali otomatis, resolusi mengubah stok dengan benar.

### Task 5: Verifikasi

- [ ] `php artisan migrate:fresh --seed`
- [ ] `php artisan test`
- [ ] `composer lint:check`, `composer types:check`
- [ ] `bun run check`, `bun run types:check`, `bun run build`
- [ ] Commit Sprint 2.
