- 2026-04-06 follow-up: lighter imported styles now force readable Discuss-composer action buttons inside `ia-post` (`+ Add attachment` / `Post`) so the footer controls do not wash out against the light modal surface. CSS-only in `assets/css/ia-post.theme.mybb.css`; no composer logic changed.
# AJAX notes for ia-post / includes

Files in this directory inspected for AJAX handling:

- `class-ia-post-assets.php`
- `class-ia-post.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

- 2026-04-06 follow-up: composer draft memory now persists `ia-post` Connect and Discuss composer text in localStorage so closing the Atrium composer or leaving the site does not discard unsent work. Connect stores title/body; Discuss stores title/body/notify and selected Agora. Drafts are cleared only after successful submit.

- 2026-04-06 hotfix: restored Connect composer draft persistence in ia-post. v18 only persisted the Discuss composer; Connect title/body now save to localStorage on input/change, restore on reopen/revisit, and clear only after a successful post.
