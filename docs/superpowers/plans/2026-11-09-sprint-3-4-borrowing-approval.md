# Sprint 3–4 — Pengajuan + Approval Implementation Plan

**Goal:** User mengajukan peminjaman (keranjang, SOP, H-2, nomor referensi, pembatalan), Admin/Korlab & WM memutuskan pengajuan, reservasi stok per periode dibuat atomik saat approve.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §5.3, §5.4, §5.5, §6.5, §6.6, §7.1, §7.2, §7.3, §7.7, §8.1, §8.3, §9.1, §9.2, §14 Sprint 3–4

## Decisions taken (defaults)

- SOP: satu dokumen versi 1 di-seed (`SopSeeder`), admin tidak mengelola SOP di MVP ini.
- Notifikasi: ditunda ke Sprint 7 (tabel `notifications` belum ada).
- Keranjang: `localStorage` di client, tanpa tabel server.
- Blokir anggota memakai `members.borrowing_status`; `member_borrowing_blocks` (per-loan) menyusul di Sprint 7.
- Cek overdue saat submit ditunda sampai `loan_transactions` terisi checkout (Sprint 5); approval tetap mengecek status anggota + blokir.

## Tasks

### Task 1: Skema + model
- [x] `sop_documents`, `sop_acknowledgements`, `borrow_requests`, `borrow_request_items`, `approvals`, `loan_transactions`, `reservations` (+ `btree_gist` exclusion constraint untuk serialized).
- [x] Model + relasi + factory + `SopSeeder`.
- [x] `AvailabilityService::availableForPeriod()` untuk kedua metode tracking.
- [x] `ReservationService` (`reserve`, `release`) dengan lock baris item.

### Task 2: Pengajuan (user)
- [x] `Borrowing\RequestController` (index, create, store, show, cancel).
- [x] `StoreBorrowRequestRequest`: items unik, H-2 `scheduled`, `purpose` wajib `urgent`, `return_at > pickup_at`, SOP wajib.
- [x] Nomor referensi `REQ-YYYYMMDD-{id}`; audit `borrow_request.submitted` / `cancelled`.
- [x] UI keranjang (`useBorrowCart`), form pengajuan, Peminjaman Saya, detail + batal.

### Task 3: Approval + reservasi
- [x] `Admin\ApprovalController` (index urgent-first, show, approve, reject).
- [x] Approve atomik: lock request → self-approval guard → eligibility → reservasi → loan transaction `reserved` → audit.
- [x] Reject wajib alasan; keputusan pertama menang (lock + unique `approvals.borrow_request_id`).
- [x] UI antrean approval + detail.

### Task 4: Verifikasi
- [x] `php artisan migrate:fresh --seed`, `php artisan test`, Pint, PHPStan.
- [x] `bun run check`, `bun run types:check`, `bun run build`.

## Completion Notes

- `php artisan migrate:fresh --seed` sukses dari nol; `btree_gist` dan constraint `reservations_asset_no_overlap` terpasang, `SopSeeder` membuat SOP aktif versi 1.
- `php artisan test`: 123 tes, 121 lulus, 2 skip.
- `composer lint:check` (Pint) dan `composer types:check` (PHPStan) hijau.
- `bun run check`, `bun run types:check` (vue-tsc), `bun run build` hijau.
- Pembatalan oleh user maupun approver wajib menyertakan alasan (`reason`, maks 1000 karakter).
- Penyimpangan kecil dari rencana: `reference_no` memakai `REQ-YYYYMMDD-{id padded 6}` sesuai keputusan sebelumnya.
- Ditunda tetap: notifikasi (Sprint 7), `member_borrowing_blocks` per-loan (Sprint 7), cek overdue saat submit (Sprint 5), halaman inventaris read-only WM.
