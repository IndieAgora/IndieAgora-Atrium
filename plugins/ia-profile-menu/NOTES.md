- 2026-04-06 style repair: added a late generic MyBB profile-menu layer so imported styles now recolour the menu panel and keep destructive rows visible under the selected palette.
- 2026-04-06 style import reset: profile menu now follows imported style surfaces; destructive rows stay deliberately red.
# AJAX notes for ia-profile-menu / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `ia-profile-menu.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

- 2026-04-06: Darkened `Deactivate Account` and `Delete Account` directly in `assets/css/ia-profile-menu.css` so the bottom-nav profile menu warning actions do not rely on Connect-side overrides.

- 2026-04-06 style ownership note: bottom-nav profile menu visuals belong here because the menu is plugin-owned. Keep destructive-row contrast local to `ia-profile-menu` instead of relying on Connect/Atrium to target those items from outside.
