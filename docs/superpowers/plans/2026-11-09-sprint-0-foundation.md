# Sprint 0 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyiapkan fondasi ENT Inventory yang bisa dijalankan: Laravel 13 terbaru, Inertia 3 + Vue 3, PostgreSQL 18, autentikasi username/NRP, skema identitas + inventaris awal, seeder, test, dan CI.

**Architecture:** Modular monolith Laravel dengan Inertia/Vue. Satu database PostgreSQL. Semua tooling frontend memakai Bun; tidak ada npm/npx/yarn/pnpm.

**Tech Stack:** PHP 8.5.12, Laravel 13.40.0, Inertia Laravel 3.7 / @inertiajs/vue3 3.11, Vue 3.5.45, Tailwind 4.3.5, Vite Plus 1.4.0, Bun 1.4.3, PostgreSQL 18, PHPUnit 12.5.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md`

## Global Constraints

- Gunakan versi stabil terbaru yang kompatibel dengan starter kit resmi Laravel 13.
- **Bun wajib** untuk semua instalasi/build/script frontend. Tidak boleh ada `npm`, `npx`, `yarn`, `pnpm`, `package-lock.json`, atau `pnpm-workspace.yaml`.
- Database: PostgreSQL 18 di `127.0.0.1:5433`, database/user `ent_inventory`.
- Timezone aplikasi: `Asia/Jakarta`; timestamp database tetap UTC.
- Jangan menghapus `PRD.md` atau dokumen desain.
- Semua perubahan skema harus punya migration dan test.
- Jangan commit `.env`, vendor, node_modules, atau build artifacts.

## Review Focus

- Script/CI yang diam-diam memanggil npm → build gagal atau melanggar constraint Bun.
- Koneksi PostgreSQL salah → migration/test gagal.
- Login masih memakai email → user NRP tidak bisa masuk.
- Relasi `users.member_id` / `members.nrp` putus → self-approval guard tidak bisa diandalkan nanti.
- Frontend build tidak menghasilkan asset → halaman Inertia blank.

---

### Task 1: Baseline repo + scaffold

**Files:**
- Create: seluruh skeleton Laravel 13 dari `laravel/vue-starter-kit:dev-main`
- Preserve: `PRD.md`, `docs/`

- [ ] Init git, commit dokumen awal.
- [ ] Scaffold ke folder sementara, hapus fitur auth yang tidak dipakai (registration, email verification, 2FA, passkeys), lalu copy ke workspace.
- [ ] Verifikasi `php artisan --version` = Laravel 13.x.

### Task 2: Bun-only toolchain

**Files:**
- Modify: `composer.json`, `package.json`, `.github/workflows/tests.yml`
- Delete: `.npmrc`, `pnpm-workspace.yaml`, lockfile non-Bun

- [ ] Hapus file konfigurasi npm/pnpm.
- [ ] Ganti semua script `npm` menjadi `bun` (setup, ci:check).
- [ ] Jalankan `bun install`, `bun run build`.
- [ ] Pastikan `bun.lock` ada dan tidak ada `package-lock.json`.

### Task 3: PostgreSQL + environment

**Files:**
- Modify: `.env`, `.env.example`, `config/app.php`

- [ ] Set `APP_NAME`, `APP_TIMEZONE=Asia/Jakarta`, `DB_CONNECTION=pgsql`, host/port/database/user.
- [ ] Jalankan `php artisan migrate` pada database kosong.
- [ ] Verifikasi koneksi dan tabel `migrations`, `users`, `sessions`.

### Task 4: Skema identitas

**Files:**
- Create migrations/models/factories: `divisions`, `members`, `roles`, `role_user`
- Modify: `users` (username, member_id, status, must_change_password)

- [ ] Tulis migration dengan FK dan unique constraint.
- [ ] Tulis model + relasi.
- [ ] Tulis feature test yang memverifikasi unique NRP, relasi user→member, role banyak-ke-banyak.
- [ ] Jalankan migration + test.

### Task 5: Skema inventaris

**Files:**
- Create migrations/models/factories: `categories`, `locations`, `items`, `assets`

- [ ] Tulis migration dengan check constraint tracking method.
- [ ] Tulis model + relasi item→assets.
- [ ] Tulis feature test untuk serialized vs quantity dan unique asset code/QR token.
- [ ] Jalankan migration + test.

### Task 6: Login username/NRP

**Files:**
- Modify: `config/fortify.php`, `app/Models/User.php`, `resources/js/pages/auth/Login.vue`, auth tests
- Create: `app/Http/Requests/Auth/LoginRequest.php` jika diperlukan

- [ ] Set Fortify username ke `username`.
- [ ] Ganti field login dari email ke username/NRP.
- [ ] Update factory/seeder dan auth tests.
- [ ] Test login sukses, password salah, akun inactive.

### Task 7: Seeder baseline

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `DivisionSeeder`, `RoleSeeder`, `CategorySeeder`, `LocationSeeder`, `DemoUserSeeder`

- [ ] Seed 3 role, divisi contoh, kategori, lokasi.
- [ ] Seed satu akun demo per role, terhubung ke member.
- [ ] Test `migrate:fresh --seed` dan jumlah data.

### Task 8: CI + dokumentasi

**Files:**
- Modify: `.github/workflows/tests.yml`
- Create: `README.md`, `compose.yaml`

- [ ] CI memakai Bun + PostgreSQL service + `php artisan test` + `bun run build`.
- [ ] README berisi setup Bun/Podman/PostgreSQL.
- [ ] `compose.yaml` menyediakan app + postgres + queue + scheduler (best-effort; Docker tidak tersedia di mesin ini).

### Task 9: Verifikasi akhir

- [ ] `php artisan migrate:fresh --seed`
- [ ] `php artisan test`
- [ ] `bun run build`
- [ ] `composer lint:check`
- [ ] Cek tidak ada referensi npm/npx/yarn/pnpm di file yang di-commit.
- [ ] Commit Sprint 0.
