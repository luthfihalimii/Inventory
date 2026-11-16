# Role Access: Staff vs Member

## Intent

Separate member borrowing from staff operations. `admin_korlab` and `wm` are staff roles: they must not use the member borrowing journey. `wm` gets every existing staff feature that `admin_korlab` has, plus its WM-only management features.

## Goals

- Staff cannot open or submit the member borrowing area.
- WM can use approval, extensions, reports, checkout, returns, inventory management, and announcements.
- The catalog stays read-only for staff; only `user` sees cart and request controls.
- Keep the existing member journey unchanged for `user`.

## Non-goals

- Changing approval, checkout, return, inventory, or announcement business rules.
- Giving `admin_korlab` WM-only features.
- Reworking the catalog into a staff-only screen.

## Decisions

- `/peminjaman` index, create, store, show, return, and extension routes require `role:user`.
- Cancellation stays authenticated and is authorized in the controller so staff can keep cancelling from the approval page.
- `wm` is added to the inventory, announcement, checkout, and return route middleware.
- `manage-inventory` and `manage-announcements` gates accept `admin_korlab` and `wm`.
- Sidebar and dashboard hide member borrowing for staff and show staff destinations to WM.
- The extension empty-state link moves from the member borrowing list to the approval queue.
- Tests cover both the new staff restrictions and WM access.

## Behavior

- A staff account that opens a member borrowing URL receives 403.
- A member account keeps the existing borrowing flow.
- WM sees the same admin navigation as Korlab plus WM management navigation.
- Staff can still browse item details read-only, including management-only data where already permitted.
