# Sprint 1 — Auth, Anggota, RBAC Implementation Plan

**Goal:** Login NRP/username penuh, ganti password awal, CRUD anggota/divisi/akun oleh WM, role middleware + Policy, audit log append-only, dashboard + navigasi per role.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §5, §7.8, §11, §14 Sprint 1

**Tech:** Laravel 13.40, Inertia 3, Vue 3.5, Bun, PostgreSQL 18, PHPUnit 12.

## Global Constraints

- Bun only untuk frontend. Tidak ada npm/npx/yarn/pnpm.
- Tidak ada hard delete untuk anggota, akun, atau audit log.
- Self-approval nanti memakai `members.id`, bukan `users.id`.
- Akun `inactive` tidak bisa login. Anggota `inactive` tetap bisa login.
- WM tidak bisa menonaktifkan akunnya sendiri atau WM aktif terakhir.
- Semua perubahan data oleh WM tercatat di `audit_logs`.
- Test memakai PostgreSQL (`ent_inventory_test`), timezone aplikasi `Asia/Jakarta`.

## Review Focus

- Middleware/policy bocor: non-WM bisa mengubah anggota, akun, atau role.
- Guard WM terakhir bisa dilewati lewat urutan request atau reset password.
- `must_change_password` bisa dilewati dengan membuka route lain.
- Audit log bisa diubah/dihapus lewat model atau route.
- Password baru tersimpan plaintext atau tidak ter-hash.
- NRP dan username bentrok saat membuat akun.

---

### Task 1: Audit log

**Files:** migration `audit_logs`, `app/Models/AuditLog.php`, `app/Services/AuditLogger.php`, update login flow.

- [ ] Migration: append-only columns, index `(entity_type, entity_id, created_at)` dan `(actor_user_id, created_at)`.
- [ ] Model: `$timestamps = false`, `created_at` only, update/delete diblokir.
- [ ] `AuditLogger::record(action, subject, context)` menulis aktor, role, IP, user agent.
- [ ] Login sukses/gagal tercatat.
- [ ] Test: entri tertulis; update/delete melempar exception.

### Task 2: Role + Policy + dashboard

**Files:** `EnsureRole` middleware, `MemberPolicy`, `UserPolicy`, `DashboardController`, sidebar.

- [ ] `User::hasRole()` / `hasAnyRole()`.
- [ ] Middleware alias `role` → 403 untuk role salah.
- [ ] Policy dasar `manage` untuk WM.
- [ ] Dashboard mengirim ringkasan + role; sidebar menampilkan menu WM hanya untuk WM.
- [ ] Test: user biasa 403 di route WM; WM 200.

### Task 3: Ganti password awal

**Files:** `EnsurePasswordChanged` middleware, `PasswordChangeController`, `Pages/Auth/ChangePassword.vue`.

- [ ] User dengan `must_change_password` diarahkan ke halaman ganti password di semua route kecuali ganti password dan logout.
- [ ] Update password butuh password saat ini, menghapus flag, mencatat audit.
- [ ] Test: redirect, loop tidak terjadi, flag hilang setelah ganti.

### Task 4: Anggota + divisi

**Files:** `Wm/MemberController`, `Wm/DivisionController`, pages.

- [ ] Anggota: list + filter, create, update (termasuk status).
- [ ] Divisi: list, create, update.
- [ ] NRP unik divalidasi di FormRequest.
- [ ] Tidak ada route delete.
- [ ] Test: CRUD, validasi unik, non-WM 403, audit tercatat.

### Task 5: Akun + role

**Files:** `Wm/AccountController`, pages.

- [ ] Buat akun (username, member, role, password awal) dengan `must_change_password = true`.
- [ ] Update role (sync), reset password, aktif/nonaktif.
- [ ] Guard: tidak bisa menonaktifkan diri sendiri; tidak bisa mencabut role WM terakhir.
- [ ] Auditor mencatat perubahan role dan status.
- [ ] Test: create/update/reset, guard self, guard WM terakhir.

### Task 6: Halaman audit + UI

- [ ] `Wm/AuditLogController` + halaman list (paginate 20, filter aksi).
- [ ] Sidebar role-aware: Anggota, Divisi, Akun, Audit untuk WM.
- [ ] Dashboard ringkas per role.

### Task 7: Verifikasi

- [ ] `php artisan migrate:fresh --seed`
- [ ] `php artisan test`
- [ ] `composer lint:check`, `composer types:check`
- [ ] `bun run check`, `bun run types:check`, `bun run build`
