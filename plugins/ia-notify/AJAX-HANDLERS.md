# AJAX handlers for ia-notify

- 2026-04-06 deep-link follow-up patch: existing handlers now return message URLs with `ia_msg_mid` when available and Stream URLs normalized to local Atrium deep links when notification payloads include a video/comment target.
- 2026-04-06 patch: added `ia_notify_clear` in `includes/ajax.php`.
- `ia_notify_list`, `ia_notify_sync`, and `ia_notify_mark_read` now also merge/broker PeerTube notification reads through documented `users/me/notifications` and read/read-all endpoints when canonical token resolution succeeds.


Confirmed AJAX-related locations in this plugin:

- `assets/js`
- `includes`

Primary files:

- `includes/ajax.php`

Registered `wp_ajax_*` actions found in code:

- `ia_notify_list` in `includes/ajax.php`
- `ia_notify_mark_read` in `includes/ajax.php`
- `ia_notify_prefs_save` in `includes/ajax.php`
- `ia_notify_sync` in `includes/ajax.php`

This file is inventory only. It should be updated whenever AJAX handlers are added, moved, renamed, or removed.