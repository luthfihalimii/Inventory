# Sprint 8 — Hardening + Pilot Implementation Plan

**Goal:** Alur kritis terbukti lewat E2E, matriks uji §13.2 tertutup, dan operasional (deploy, backup/restore, stock opname, pelatihan) punya runbook yang bisa dijalankan.

**Spec:** `docs/superpowers/specs/2026-11-09-ent-inventory-design.md` §12, §13.1, §13.2, §14 Sprint 8, §17 PRD

## Global Constraints

- Frontend tooling hanya Bun (`bun add`, `bun run`).
- E2E memakai database terpisah (`ent_inventory_e2e`), tidak menyentuh DB dev/test PHPUnit.
- E2E tidak menambah dependency runtime; Playwright hanya devDependency.
- Tidak ada perubahan perilaku bisnis di sprint ini kecuali bug yang ditemukan tes.
- Tidak ada secret di repo; kredensial E2E berasal dari seeder demo.

## Decisions (defaults)

- Playwright menjalankan `php artisan serve` di port 8123 dengan env DB E2E.
- Fixture E2E deterministik lewat `E2eSeeder` (barang quantity + serialized, 1 SOP, akun demo).
- Matriks §13.2 yang belum tertutup diuji sebagai feature test, bukan E2E.
- Concurrency diuji secara struktural (state transition + lock re-check), bukan dua proses paralel; uji paralel nyata = catatan manual.
- Stock opname & backup/restore = dokumen runbook, bukan fitur aplikasi (tidak diminta PRD).

---

### Task 1: E2E Playwright

**Files:** `playwright.config.ts`, `e2e/*.spec.ts`, `database/seeders/E2eSeeder.php`, `package.json`.

- [x] `@playwright/test` + Chromium terpasang, script `bun run e2e`.
- [x] Spec 1: happy path user → admin (submit, approve, checkout, verifikasi kembali).
- [x] Spec 2: pengajuan mendadak (urgent) lolos H-2 tapi wajib alasan.
- [x] Spec 3: QR salah ditolak, tidak mengubah status.
- [x] Spec 4: overdue + blokir (submit ditolak, alasan ditampilkan).
- [x] Spec 5: pengembalian rusak membuat damage report.

### Task 2: Hardening test matriks §13.2

**Files:** `tests/Feature/HardeningTest.php` (otorisasi), `tests/Feature/ConcurrencyTest.php` (state transition).

- [x] Self-approval lintas akun dengan `member_id` sama ditolak.
- [x] Anggota overdue memakai akun kedua dengan NRP sama tetap ditolak.
- [x] Dua pengajuan memperebutkan unit terakhir: satu berhasil, satu gagal tanpa approval parsial.
- [x] Reservasi quantity overlap tidak melebihi stok.
- [x] Unit dipinjam sampai due date, diminta untuk periode setelahnya → boleh.
- [x] QR unit milik barang lain ditolak + audit.
- [x] QR unit rusak/hilang/nonaktif ditolak.
- [x] Checkout melebihi reserved ditolak.
- [x] Approver kedua pada pengajuan yang sudah diputus gagal (sudah ada) + tidak membuat reservasi kedua.

### Task 3: Runbook operasional

**Files:** `docs/runbook-deployment.md`.

- [x] Deploy: env wajib, `migrate --force`, `SopSeeder`, scheduler + queue worker terpisah.
- [x] `btree_gist` extension + role DB.
- [x] Backup harian `pg_dump` + storage privat; prosedur restore drill dan kriteria lulus.
- [x] Checklist stock opname data pilot (kode, jumlah, kondisi, lokasi, QR).
- [x] Materi pelatihan singkat per role.

### Task 4: Verifikasi

- [x] `php artisan test`, `composer lint:check`, `composer types:check`.
- [x] `bun run e2e`, `bun run check`, `bun run types:check`, `bun run build`.
- [x] `php artisan migrate:fresh --seed`.
- [x] Commit Sprint 8.

---

## Review Focus

- E2E flaky karena waktu/tanggal (H-2, endOfDay) — pakai fixture relatif saat seeding.
- E2E menyentuh DB dev/test.
- Hardening test hanya menguji ulang jalur yang sudah ada (tidak menambah nilai).
- Runbook berisi langkah yang tidak bisa dijalankan (command salah, env hilang).

---

## Completion Notes

### Rulings

- **Ruling:** `ConcurrencyTest.php` tidak dibuat terpisah; seluruh kasus matriks §13.2 masuk `tests/Feature/HardeningTest.php` (8 test). Alasan: semua kasus berbagi helper fixture yang sama, dan pemisahan file tidak menambah cakupan. Biaya bila salah: nama file berbeda dari rencana, mudah dipecah nanti.
- **Ruling:** `bun run e2e` menjalankan `bun run build` lebih dulu. Alasan: `php artisan serve` menyajikan manifest `public/build` yang sudah ada; tanpa build ulang, E2E menguji aset lama dan gagal pada perubahan UI (terbukti saat `memberError` belum ada di bundel). Biaya bila salah: e2e ~6 detik lebih lambat.
- **Ruling:** `playwright.config.ts` memakai `${baseURL}/up` sebagai readiness URL, bukan `/`. Alasan: `/` menyentuh session + database; saat `globalSetup` belum membuat DB E2E, server tidak pernah siap dan webServer timeout 120 detik. `/up` tidak menyentuh DB.
- **Ruling:** QR salah pada Spec 3 memakai kasus "token tidak dikenal" + "barang bukan bagian pengajuan", bukan unit milik barang lain. Alasan: unit milik barang lain butuh dua barang serialized di fixture, sedangkan perilaku validasinya identik dan sudah diuji di `CheckoutTest`/`HardeningTest`. Biaya bila salah: satu varian pesan error tidak tercakup E2E.
- **Ruling:** Uji QR di E2E hanya membaca pesan validasi; status transaksi diverifikasi lewat kunjungan ulang `/admin/checkout`. Alasan: scan memang read-only (`test_scan_only_validates_tokens` sudah membuktikan), jadi E2E cukup membuktikan UI tidak mengubah apa pun.
- **Ruling:** `form.errors.member` di `borrowing/Create.vue` dibaca lewat `usePage().props.errors`, bukan `form.errors`. Alasan: `member` bukan field form, jadi `FormDataErrors` tidak punya tipenya dan `vue-tsc` gagal. Biaya bila salah: tidak ada, perilaku sama.

### Bug ditemukan tes

- Blokir anggota tidak pernah tampil sebagai error di form pengajuan: `RequestController::store` melempar `ValidationException` dengan key `member`, tapi `Create.vue` tidak merender key itu. Ditambahkan `memberError` + `<InputError>` (Spec 4 menemukannya).

### Hasil verifikasi

- `php artisan test`: 184 test, 182 pass, 2 skip, 775 assertion.
- `composer lint:check` dan `composer types:check`: bersih.
- `bun run e2e`: 5 spec lulus (1 worker, ~57 detik).
- `bun run check`, `bun run types:check`, `bun run build`: bersih.
- `php artisan migrate:fresh --seed`: sukses.

### Catatan manual (tidak diotomasi)

- Uji concurrency dua proses paralel sungguhan (dua approver/dua verifikasi bersamaan) — diuji struktural di feature test, uji proses ganda dilakukan manual saat pilot.
- Scan kamera di perangkat nyata, cetak QR fisik, usability.
- Backup/restore drill dan stock opname mengikuti `docs/runbook-deployment.md`.
