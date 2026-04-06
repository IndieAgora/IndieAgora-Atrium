- 2026-04-06 follow-up: lighter imported styles now force readable Discuss-composer action buttons inside `ia-post` (`+ Add attachment` / `Post`) so the footer controls do not wash out against the light modal surface. CSS-only in `assets/css/ia-post.theme.mybb.css`; no composer logic changed.
# AJAX handlers for ia-post

Confirmed AJAX-related locations in this plugin:

- `assets/js`
- `includes`

Registered `wp_ajax_*` actions found in code:

- `ia_post_stream_bootstrap` in `includes/class-ia-post.php`
- `ia_post_stream_upload` in `includes/class-ia-post.php`

This file is inventory only. It should be updated whenever AJAX handlers are added, moved, renamed, or removed.

- 2026-04-06 follow-up: composer draft memory now persists `ia-post` Connect and Discuss composer text in localStorage so closing the Atrium composer or leaving the site does not discard unsent work. Connect stores title/body; Discuss stores title/body/notify and selected Agora. Drafts are cleared only after successful submit.

- 2026-04-06 hotfix: restored Connect composer draft persistence in ia-post. v18 only persisted the Discuss composer; Connect title/body now save to localStorage on input/change, restore on reopen/revisit, and clear only after a successful post.
