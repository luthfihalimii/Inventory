# Sprint 5–6 — Checkout QR + Return/Inspeksi Implementation Plan

**Goal:** Admin/Korlab menyerahkan barang lewat scan QR (checkout atomik, partial, tukar unit), user mengajukan pengembalian, Admin memverifikasi inspeksi per item/kondisi campuran, transaksi selesai, stok dan laporan kerusakan konsisten.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §6.5, §7.3, §7.4, §7.5, §8.2–§8.5, §14 Sprint 5–6

## Global Constraints

- Hanya `admin_korlab` yang checkout dan verifikasi pengembalian.
- Scan QR read-only: tidak mengubah status apa pun.
- Checkout dan verifikasi pengembalian atomik dalam satu transaksi DB dengan lock.
- Partial checkout dan tukar unit wajib alasan dan tercatat audit.
- Minimal satu unit/quantity harus diserahkan.
- Stok quantity = `quantity_total` − laporan terbuka − reservasi aktif − pinjaman berjalan.
- Unit serialized yang pinjamannya sudah berakhir sebelum periode diminta boleh dipesan lagi (§7.3).
- Return hanya bisa `completed` jika total kembali per loan item = quantity loan item.
- Semua aksi tercatat di `audit_logs`.

## Decisions (defaults)

- Batas pengambilan belum didefinisikan eksplisit di spec; checkout ditolak setelah `return_at` lewat.
- Scan dikirim sebagai JSON endpoint; hasil scan disimpan di state halaman sampai checkout disubmit.
- Checkbox checklist checkout: `identity_verified`, `condition_checked`, `sop_explained`.
- `incomplete` pada serialized memakai status aset `maintenance` + damage report `incomplete`; pada quantity memakai damage report `incomplete`.
- Partial return tetap P1: verifikasi mewajibkan total kembali sama dengan quantity loan item.
- Notifikasi in-app tetap ditunda ke Sprint 7.

---

### Task 1: Skema + model

**Files:** migrations `000019`–`000021`, model `LoanItem`, `HandoverRecord`, `ReturnRequest`, `ReturnInspection`, `ReturnInspectionItem`.

- [x] `loan_items` (item/asset/quantity/condition_out) + CHECK serialized quantity = 1.
- [x] `handover_records` (unique loan transaction, checklist/scan JSONB, konfirmasi peminjam).
- [x] FK `damage_reports.loan_transaction_id` + `loan_item_id`.
- [x] `return_requests` (partial unique pending), `return_inspections`, `return_inspection_items` (CHECK quantity > 0).
- [x] Relasi di `LoanTransaction`, `Item`, `Asset`; factory `LoanItemFactory`, `HandoverRecordFactory`, `ReturnRequestFactory`.

### Task 2: Availability dengan pinjaman berjalan

**Files:** `app/Services/AvailabilityService.php`.

- [x] `summary()` mengurangi reservasi aktif dan pinjaman berjalan (`borrowed`, `overdue`, `return_pending_verification`).
- [x] `availableForPeriod()` quantity mengurangi pinjaman yang overlap.
- [x] Serialized: unit `borrowed` boleh tersedia untuk periode setelah `due_at` pinjamannya; unit dengan pinjaman/reservasi overlap tetap ditolak.
- [x] Test unit untuk kedua metode tracking.

### Task 3: Checkout QR

**Files:** `Admin\CheckoutController`, `StoreCheckoutRequest`, routes, halaman `admin/checkout/{Index,Show}.vue`.

- [x] Antrean transaksi `reserved` + detail.
- [x] Endpoint scan: validasi token aset/item terhadap reservasi transaksi (read-only).
- [x] Checkout atomik: lock transaksi + reservasi + unit → `loan_items`, `handover_records`, reservasi `fulfilled`/`released`, aset `borrowed`, transaksi `borrowed`, audit.
- [x] Partial checkout + tukar unit wajib alasan; minimal satu unit/quantity.
- [x] UI scan token / input manual + form aktual + checklist + konfirmasi peminjam.

### Task 4: Pengembalian + inspeksi

**Files:** `Borrowing\ReturnController@store`, `Admin\ReturnController`, requests, routes, halaman `admin/return/{Index,Show}.vue`, update `borrowing/Show.vue`.

- [x] User ajukan return (`borrowed`/`overdue`) → `return_pending_verification`.
- [x] Admin batalkan return dengan alasan → kembali `overdue`/`borrowed`.
- [x] Verifikasi: inspeksi per loan item, kondisi campuran, total = quantity.
- [x] Kondisi `good`/`damaged`/`incomplete`/`lost` → status aset + damage report + audit.
- [x] Transaksi `completed`, return request `verified`.

### Task 5: Verifikasi

- [x] `php artisan migrate:fresh --seed`.
- [x] `php artisan test`, `composer lint:check`, `composer types:check`.
- [x] `bun run check`, `bun run types:check`, `bun run build`.
- [x] Commit Sprint 5–6.

---

## Review Focus

- Checkout/return bisa dipanggil role salah atau atas transaksi orang lain.
- Partial checkout tanpa alasan atau tanpa unit terserahkan.
- Tukar unit ke aset yang sedang dipinjam/direservasi lain.
- Overbooking setelah checkout (stok tidak berkurang).
- Return `completed` padahal total kembali kurang.
- Stok tidak pulih setelah `good`, atau rusak/hilang tidak membuat laporan.
- Damage report dari inspeksi tidak tertaut ke loan/member.

---

## Completion Notes

- Migrations `000019`–`000021` applied and verified with `migrate:fresh --seed`.
- Test suite: 141 tests, 139 passed, 2 skipped (pre-existing skips). New: 15 checkout/return feature tests + 3 availability tests.
- `composer lint:check`, `composer types:check`, `bun run check`, `bun run types:check`, `bun run build` all green.
- Deferred: notifications (Sprint 7), partial return (stays P1), overdue scheduler, `summary()` N+1 optimisation.
- Deploy notes: production DB role needs `CREATE EXTENSION btree_gist`; `SopSeeder` must run or borrow submission 404s.

