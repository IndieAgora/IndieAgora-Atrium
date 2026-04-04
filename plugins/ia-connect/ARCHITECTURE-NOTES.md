# Architecture Notes: IA Connect

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-connect`
- Version in header: `0.5.13`
- Main entry file: `ia-connect.php`
- Declared purpose: Atrium Connect mini-platform: profile header + wall posts + settings.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Contains WordPress user deletion logic.
- Contains WordPress logout/session termination logic.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options.
- Nonce strings seen in code: ia_connect:.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_connect_account_deactivate` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_account_delete` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_comment_create` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_comment_delete` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_comment_update` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_comments_page` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_discuss_activity` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_display_name_update` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_export_data` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_follow_toggle` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_followers_list` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_followers_search` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_mention_suggest` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_post_create` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_post_delete` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_post_get` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_post_list` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_post_share` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_post_update` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_privacy_get` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_privacy_update` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_settings_update` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_signature_update` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_stream_activity` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_upload_cover` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_upload_profile` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_user_block_toggle` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_user_follow_toggle` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_user_rel_status` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_user_search` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_connect_wall_search` — logged-in only; declared in `includes/support/ajax.php`.

## API and integration notes

- `https://www.youtube-nocookie.com/embed/` referenced in `assets/js/ia-connect.js`.
- `https://player.vimeo.com/video/` referenced in `assets/js/ia-connect.js`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `NOTES.md` — Local maintenance notes/documentation.
- `ia-connect.php` — Runtime file for ia connect.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-connect-typography.css` — Stylesheet for ia connect typography.
- `assets/css/ia-connect.activity.css` — Stylesheet for ia connect activity.
- `assets/css/ia-connect.css` — Stylesheet for ia connect.
- `assets/css/ia-connect.fb.css` — Stylesheet for ia connect fb.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-connect.activity.js` — JavaScript for ia connect activity.
- `assets/js/ia-connect.js` — JavaScript for ia connect.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/functions.php` — Runtime file for functions.

### `includes/db`

- Purpose: Database install or migration helpers.
- `includes/db/install.php` — Install or schema setup logic.

### `includes/modules`

- Purpose: Module classes or controller-like entry points.
- `includes/modules/panel.php` — Main panel renderer or mount point.

### `includes/support`

- Purpose: Shared support code such as assets, security, install, and AJAX bootstrapping.
- `includes/support/ajax.php` — AJAX endpoint registration or callback logic.
- `includes/support/assets.php` — Asset enqueue and localization logic.
- `includes/support/notifications.php` — Notification-related logic.
