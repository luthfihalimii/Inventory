# Implementation status — ENT Inventory

## Included in this package

- Shared workspace shell with ENT logo/identity, role-aware navigation, collapsible desktop sidebar, mobile drawer, account menu, notification dropdown, and link to the main ENT website.
- Light/dark/system theme preference, responsive layout, reduced-motion support, micro-interactions, sticky table headers, basic client-side table sorting, search/filter/reset on major directories, status badges, empty states, and print stylesheet with ENT print header.
- Role-specific dashboards and date-range summary for loan submissions.
- Account settings: read-only member identity, username update, current-password verification, new password and confirmation; initial-password flag and USER-only notification.
- Session-expiration warning and explicit keep-alive action; unsaved form warning; submit loading feedback; confirmation prompts; scroll-to-top.
- Inventory unit CSV import/export, member CSV import/export, inventory report CSV export, loan history CSV export, and audit CSV export.
- CSV imports validate the batch before writing; invalid data aborts the batch; duplicate existing codes/NRP are skipped; imports are audited.
- Bulk deactivation for selected inventory item types, with a transaction-level guard against units that are reserved or borrowed.
- Fixed duplicate legacy `ent:create-super-admin` command in `routes/console.php`; the dedicated command class remains the single implementation.

## Verification performed in the build environment

- PHP syntax check passed for application controllers, routes, migrations, and seeders.
- Laravel route registry loaded and listed 79 routes.
- All 39 Blade templates compiled through Laravel's Blade compiler.
- Login page smoke request returned HTTP 200 and rendered the login markup.
- Layout JavaScript passed `node --check`.
- View route-name references were checked; no missing route names were found.

## Verification not completed

- Full automated PHPUnit suite could not run because the build runtime lacks PHP extensions `dom`, `mbstring`, and `xmlwriter`.
- Database migration and end-to-end transaction tests could not run because the build runtime has no PDO MySQL or SQLite driver. Run migrations and test workflows in the local WAMP environment before production use.
- PDF files are not generated server-side in this package. Report pages use a print stylesheet and browser **Print → Save as PDF**. Structured exports are CSV.

## Before production

- Configure a non-development `APP_ENV`, `APP_DEBUG=false`, strong `APP_KEY`, database credentials, and HTTPS `APP_URL`.
- Confirm PHP extensions and run `composer install`.
- Run `php artisan migrate --seed` (do not use `migrate:fresh` on an existing database).
- Create/verify the one ADMIN and one SUPER_ADMIN account.
- Verify role permissions, CSV templates, and transaction workflows with a staging database and real test accounts.
- Back up the database and uploaded return photos.
