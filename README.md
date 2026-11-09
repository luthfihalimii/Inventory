# ENT Inventory

Aplikasi inventaris UKM Jurnalistik: katalog aset, pengajuan peminjaman, approval, serah terima QR, pengembalian, overdue, dan audit log.

## Stack

- PHP 8.5, Laravel 13
- Inertia 3 + Vue 3.5 + Tailwind 4 (Vite Plus)
- PostgreSQL 18
- **Bun** untuk seluruh tooling frontend (bukan npm/yarn/pnpm)
- PHPUnit 12

## Setup lokal

### 1. Database

Jalankan PostgreSQL 18 (contoh dengan Podman):

```bash
podman volume create ent_pgdata
podman run -d --name ent-postgres \
  -e POSTGRES_DB=ent_inventory \
  -e POSTGRES_USER=ent_inventory \
  -e POSTGRES_PASSWORD=ent_inventory \
  -p 5433:5432 \
  -v ent_pgdata:/var/lib/postgresql \
  postgres:18-alpine
```

Buat database test:

```bash
podman exec ent-postgres createdb -U ent_inventory ent_inventory_test
```

### 2. Aplikasi

```bash
composer install
cp .env.example .env
php artisan key:generate
bun install
php artisan migrate --seed
bun run build
```

### 3. Jalankan

```bash
composer dev
```

## Akun demo

| Username  | Role         | Password   |
| --------- | ------------ | ---------- |
| `2410000001` | user         | `password` |
| `korlab`  | admin_korlab | `password` |
| `wm`      | wm           | `password` |

Ganti password sebelum dipakai di lingkungan nyata.

## Perintah umum

```bash
php artisan test        # seluruh test (PostgreSQL test DB, lihat phpunit.xml)
composer lint           # Pint
composer lint:check     # Pint mode cek
bun run dev             # Vite Plus dev server
bun run build           # build produksi
bun run check           # lint + format check frontend
bun run types:check     # vue-tsc
bun run e2e             # build + Playwright (DB terpisah ent_inventory_e2e)
```

## Timezone

Aplikasi memakai `Asia/Jakarta` (`APP_TIMEZONE`). Timestamp database disimpan UTC.

## Dokumen

- `PRD.md` — kebutuhan produk
- `docs/runbook-deployment.md` — deploy, backup/restore, stock opname, pelatihan
- `docs/superpowers/specs/` — desain teknis dan ERD
- `docs/superpowers/plans/` — rencana implementasi per sprint
