# ENT Inventory — Security Review and Patch Notes

## Implemented in this revision

- Announcement HTML is normalized through a strict tag/attribute allowlist. Event handlers, scripts, styles, embedded documents, unsafe URL schemes, and arbitrary image URLs are removed. Existing announcement content is sanitized again at render time.
- Announcement editor supports H1–H6, lists/indentation, quotes, links, private image upload, preview, word/character count, estimated reading time, category, and pinning.
- A future `published_at` with status `PUBLISHED` is treated as scheduled: members only see it once the timestamp is reached. This preserves the existing database enum and avoids a destructive status migration.
- Login attempts are limited to five failures per identity/IP key per 60 seconds, with generic authentication errors.
- Return evidence photos are saved on the private local disk and served through an authenticated, ownership/manager-authorized endpoint. A migration moves existing `return_photos` files from the public disk to private storage where they exist.
- XLSX parsing is bounded by archive-entry count, total uncompressed size, XML size, row count, column count, and shared-string count. DTD/entity declarations are rejected and XML parsing uses `LIBXML_NONET`.
- CSV exports use formula-injection protection. Numeric values remain numeric.
- Import parser exceptions are logged internally and generic errors are shown to users. Import row limits are enforced.
- Basic browser security headers are applied to the web middleware group. Session encryption defaults to enabled; secure cookies default to enabled in production and remain compatible with local HTTP development.
- Duplicate item-issue create route was removed.
- Distribution packaging excludes `.env`, SQLite databases, runtime logs, compiled views/cache, nested duplicate project, `vendor`, and `node_modules`.

## Existing features retained from the supplied project

- Role-specific member/account creation with additional manager confirmation.
- Ordered ENT position options.
- Account creation/update timestamps in account settings.
- Back button and breadcrumb.
- Login error display.
- Inventory price and latest maintenance information.
- Focused borrowing SOP and export confirmation controls in the main sidebar.
- Cart-based multi-item loan submission, availability calendar, maintenance records, imports, issue reports, and existing role boundaries.

## Deployment steps

1. Keep the current database and back up files/database before deploying.
2. Copy the existing `.env` into the extracted project; do not distribute it.
3. Run `composer install` if `vendor` is not present.
4. Run `php artisan migrate` (never `migrate:fresh` on an existing database).
5. Run `php artisan optimize:clear`.
6. Ensure `storage/app/private` is writable by the web server.
7. In production set `APP_ENV=production`, `APP_DEBUG=false`, configure HTTPS, and use `SESSION_SECURE_COOKIE=true`.
8. Set `SESSION_ENCRYPT=true` in the copied `.env` (the existing `.env` value overrides config defaults). If the original archive or its `.env` was shared outside the trusted project team, rotate database credentials and `APP_KEY` as appropriate. Rotating `APP_KEY` invalidates encrypted values/sessions and can affect encrypted application data.

## Verification limitations

Static PHP syntax checks passed for the application, route, and migration PHP files. The execution environment used for this patch does not have `pdo_mysql`, `pdo_sqlite`, `zip`, `SimpleXML`, or DOM extensions, and Composer is unavailable. Therefore browser rendering, database migrations, XLSX import, file delivery, concurrency, and Composer CVE audit could not be executed here. Run these checks in the target WAMP environment before production use.
