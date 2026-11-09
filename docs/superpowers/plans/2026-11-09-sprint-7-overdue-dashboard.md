# Sprint 7 — Overdue, Dashboard, Pengumuman Implementation Plan

**Goal:** Keterlambatan terdeteksi otomatis dan konsisten, anggota terblokir terkontrol, dashboard per role berisi data nyata, pengumuman internal, dan notifikasi dalam aplikasi.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §5, §6.5, §7.1, §7.6, §7.7, §7.8, §9.1, §9.4, §12, §14 Sprint 7

## Global Constraints

- Blokir berlaku pada `members.id`, bukan `users.id`.
- Transaksi `reserved` tidak pernah overdue; tidak diambil = kedaluwarsa, tanpa blokir.
- Scheduler idempotent: dijalankan dua kali tidak boleh membuat blok/notifikasi duplikat.
- Pengecekan overdue juga berjalan sinkron saat submit pengajuan.
- Anggota terblokir tetap bisa login, melihat riwayat, dan mengajukan pengembalian.
- Blok ditutup saat transaksi selesai; `eligible` hanya jika tidak ada blok aktif lain.
- Tidak ada pembukaan blok manual (MVP).
- Notifikasi dikirim setelah commit; kegagalan notifikasi tidak membatalkan transaksi inti.
- Dashboard memakai count/aggregate, bukan memuat riwayat penuh.
- Konten pengumuman plain text (tanpa HTML mentah).

## Decisions (defaults)

- Command `inventory:overdue` dijadwalkan tiap 10 menit, sekaligus menangani reservasi kedaluwarsa.
- Batas kedaluwarsa pengambilan: akhir hari `pickup_at` di zona `Asia/Jakarta`.
- Notifikasi dibuat untuk: keputusan approval, checkout, verifikasi pengembalian, overdue/blokir.
- Pengumuman dikelola `admin_korlab`; `wm` dan `user` hanya melihat yang `published`.
- Audit view yang sudah ada tidak dibangun ulang; hanya ditambah filter aktor/entity bila perlu.
- Pengumuman tidak mem-fan-out notifikasi; ia tampil di dashboard.

---

### Task 1: Skema + model

**Files:** migration `000022`, model `MemberBorrowingBlock`, `Announcement`, `Notification`, relasi `Member`/`LoanTransaction`.

- [x] `member_borrowing_blocks` + partial unique aktif per loan.
- [x] `announcements` (`draft`/`published`).
- [x] `notifications` (`user_id`, `type`, `title`, `body`, `data`, `read_at`, `created_at`).
- [x] Factory `MemberBorrowingBlockFactory`, `AnnouncementFactory`.

### Task 2: Overdue + kedaluwarsa reservasi

**Files:** `app/Services/OverdueService.php`, `app/Console/Commands/MarkOverdueCommand.php`, `routes/console.php`.

- [x] `markOverdue()`: `borrowed`→`overdue`, `return_pending_verification` tetap status, isi `overdue_at`, buat blok idempotent, audit.
- [x] `syncMember()`: cek sinkron untuk satu anggota saat submit.
- [x] `releaseForLoan()`: tutup blok, `eligible` bila tidak ada blok aktif, audit.
- [x] `expireUnclaimedReservations()`: `reserved` lewat akhir hari `pickup_at` → `cancelled`, reservasi released, audit.
- [x] Command terjadwal tiap 10 menit, `withoutOverlapping`.

### Task 3: Integrasi blokir

**Files:** `MemberEligibilityService`, `Admin\ReturnController`, `Borrowing\RequestController` (bila perlu).

- [x] Eligibility: blok aktif + overdue sinkron, bukan hanya `members.borrowing_status`.
- [x] Verifikasi pengembalian menutup blok dan memulihkan `eligible`.

### Task 4: Notifikasi internal

**Files:** `app/Services/NotificationService.php`, `NotificationController`, routes, header bell + halaman.

- [x] Kirim setelah commit untuk approval, checkout, verifikasi return, overdue.
- [x] Daftar notifikasi, tandai dibaca, tandai semua dibaca, badge unread.

### Task 5: Dashboard + pengumuman + anggota terblokir

**Files:** `DashboardController`, `Admin\AnnouncementController`, `Wm\BlockedMemberController`, routes, halaman.

- [x] Dashboard per role dengan count/aggregate + pengumuman published.
- [x] CRUD pengumuman (admin), publish/unpublish, audit.
- [x] Daftar blok aktif (WM) dengan transaksi pemicu.
- [x] Sidebar: Pengumuman (admin), Anggota terblokir (WM).

### Task 6: Verifikasi

- [x] `php artisan migrate:fresh --seed`.
- [x] `php artisan test`, `composer lint:check`, `composer types:check`.
- [x] `bun run check`, `bun run types:check`, `bun run build`.
- [x] Commit Sprint 7.

---

## Review Focus

- Scheduler dua kali → blok/notifikasi duplikat.
- Anggota overdue lolos submit karena scheduler telat.
- `return_pending_verification` lewat tenggat tidak terhitung terlambat.
- Blok tidak dilepas setelah verifikasi, atau dilepas padahal ada overdue lain.
- Reservasi tidak diambil malah memblokir anggota.
- Pengumuman draft bocor ke user.
- Notifikasi gagal membatalkan transaksi inti.
- Dashboard memuat seluruh riwayat.

---

## Completion Notes

- `inventory:overdue` runs every 10 minutes with `withoutOverlapping()`; expiry uses the end of `pickup_at` in `Asia/Jakarta`.
- Blocks are idempotent via partial unique index `member_blocks_active_loan_idx` and a pre-insert check.
- `markOverdue()` and `expireUnclaimedReservations()` return data from the transaction and notify after commit; notification failures are swallowed by `NotificationService`.
- `syncMember()` runs before validation in `RequestController::store` (outside the request transaction, so the flag/block survives), and inside the approval eligibility check.
- Return verification closes only the finished loan's block; `eligible` is restored only when no other active block remains.
- Announcements are plain text, published-only on the dashboard, managed by `admin_korlab`; publish/unpublish are both audited.
- Notifications cover approval, checkout, return verification, overdue, and reservation expiry. Bell badge is shared via `HandleInertiaRequests`.
- Tests: `OverdueTest` (14), `NotificationTest` (8), `AnnouncementTest` (8) — total suite 174 tests (172 pass, 2 skipped).
- Verification: `composer lint:check`, `composer types:check`, `bun run check`, `bun run types:check`, `bun run build`, `migrate:fresh --seed` all green.
