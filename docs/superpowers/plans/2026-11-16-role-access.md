# Role Access Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make member borrowing member-only, and give `wm` every staff feature that `admin_korlab` has.

**Architecture:** Enforce role boundaries at route middleware and gates, then mirror them in the sidebar and dashboard. Keep staff catalog access read-only.

**Tech Stack:** Laravel 13, Inertia, Vue 3, PHPUnit, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-16-role-access-design.md`

## Global Constraints

- No new dependencies.
- No commits unless explicitly requested.
- Preserve the existing `user` borrowing flow and all E2E selectors.
- Keep `admin_korlab` out of WM-only routes.

## Review Focus

- Staff opening a member borrowing URL must get 403, not a partially rendered page.
- Staff must still cancel requests from the approval page.
- WM must see and use all Korlab admin destinations, including inventory management and announcements.
- Members must not gain staff routes.
- Catalog remains read-only for staff.

---

### Task 1: Restrict the member borrowing area

**Files:**
- Modify: `routes/borrowing.php`
- Modify: `app/Http/Controllers/Borrowing/RequestController.php`
- Test: `tests/Feature/RoleAccessTest.php`

**Interfaces:**
- Produces: `/peminjaman` index/create/show/return/extensions require `role:user`; cancel remains auth-only with controller authorization.

- [x] **Step 1: Write failing access tests**

Add tests asserting `admin_korlab` and `wm` receive 403 on `borrowing.index` and `borrowing.create`, while `user` receives 200.

- [x] **Step 2: Run tests and verify failure**

Run: `php artisan test --filter=RoleAccessTest`
Expected: staff access currently returns 200.

- [x] **Step 3: Apply middleware and controller restriction**

Split `routes/borrowing.php`: member routes under `role:user`, cancel under `auth` only. In `RequestController::show`, allow only the request owner.

- [x] **Step 4: Re-run tests**

Run: `php artisan test --filter=RoleAccessTest`
Expected: PASS.

### Task 2: Grant WM all Korlab staff features

**Files:**
- Modify: `routes/announcements.php`
- Modify: `routes/inventory.php`
- Modify: `routes/borrowing.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Admin/InventoryManagementTest.php`
- Test: `tests/Feature/AnnouncementTest.php`
- Test: `tests/Feature/RoleAccessTest.php`

**Interfaces:**
- Produces: `wm` passes `role:admin_korlab,wm` route groups and `manage-inventory` / `manage-announcements` gates.

- [x] **Step 1: Update access tests**

Change the inventory and announcement tests to assert `wm` is allowed and a plain `user` is denied. Add a WM admin-route smoke test covering inventory, announcements, checkout, returns, approval, extensions, and reports.

- [x] **Step 2: Run tests and verify failure**

Run: `php artisan test --filter='InventoryManagementTest|AnnouncementTest|RoleAccessTest'`
Expected: WM assertions fail.

- [x] **Step 3: Add WM to middleware and gates**

Update the four route declarations and both gate definitions. Leave WM-only routes unchanged.

- [x] **Step 4: Re-run tests**

Run: `php artisan test --filter='InventoryManagementTest|AnnouncementTest|RoleAccessTest'`
Expected: PASS.

### Task 3: Align navigation and dashboard with roles

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/pages/Dashboard.vue`
- Modify: `resources/js/pages/admin/extensions/Index.vue`
- Test: `e2e/06-ui-shell.spec.ts`

**Interfaces:**
- Produces: member borrowing links only for `user`; admin navigation for `admin_korlab` and `wm`; WM-only navigation for `wm`.

- [x] **Step 1: Write the failing navigation test**

Add a Playwright case that logs in as `korlab`, asserts no "Peminjaman Saya" and visible "Kelola barang", then logs in as `wm`, asserts no member link and visible "Kelola barang" plus "Anggota".

- [x] **Step 2: Run the test and verify failure**

Run: `bunx playwright test e2e/06-ui-shell.spec.ts -g "staff navigation"`
Expected: current sidebar still shows "Peminjaman Saya" and hides WM admin tools.

- [x] **Step 3: Implement role-aware navigation**

Make `mainNavItems` computed and include "Peminjaman Saya" only for `user`. Show admin nav for either staff role. Hide member CTA/quick action on the dashboard for staff and show staff actions. Change the extensions empty-state link to `/admin/approval`.

- [x] **Step 4: Re-run the test**

Run: `bunx playwright test e2e/06-ui-shell.spec.ts -g "staff navigation"`
Expected: PASS.

### Task 4: Full verification

**Files:** none.

- [x] **Step 1: Run the PHP suite**

Run: `php artisan test --compact`
Expected: PASS.

- [x] **Step 2: Run the full browser suite**

Run: `bun run e2e`
Expected: PASS.

- [x] **Step 3: Run static checks**

Run: `bun run types:check && bun run check`
Expected: PASS.
