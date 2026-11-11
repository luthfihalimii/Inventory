# AUTH-12 — Manajemen Sesi & Pencabutan Akses

**Spec:** `docs/superpowers/specs/2026-11-10-auth-12-session-management-design.md`
**Goal:** Anggota bisa melihat dan mencabut sesinya sendiri; password change/reset memaksa keluar sesi lain; semua tercatat di audit.

## Tasks

- [x] `SessionService`: daftar sesi + label perangkat, cabut satu, cabut semua.
- [x] SecurityController: prop `sessions`, `destroySession`, `destroyOthers`; ganti password mencabut sesi lain + audit.
- [x] Routes `DELETE /settings/sessions/{session}` dan `DELETE /settings/sessions`.
- [x] WM reset password mencabut semua sesi akun + metadata audit.
- [x] UI section "Perangkat & sesi" di `settings/Security.vue`.
- [x] Tests: daftar hanya milik sendiri, cabut sesi lain, 404 sesi orang lain, sesi berjalan dipertahankan, reset WM mencabut semua, audit.
- [x] Verifikasi: `php artisan test`, Pint, PHPStan, `bun run check`, `vue-tsc`, build.
- [x] Runbook §7: satu baris untuk anggota & WM; commit.

## Decisions

- Tanpa migration; tabel `sessions` sudah ada.
- Sesi berjalan tidak bisa dicabut lewat daftar (422); gunakan Logout.
- Label perangkat dari regex UA, tanpa dependency baru.

## Review focus

- IDOR: session id milik user lain tidak boleh bisa dihapus.
- Password reset WM harus benar-benar mematikan sesi lama.
- Audit tidak boleh menggagalkan pencabutan.
