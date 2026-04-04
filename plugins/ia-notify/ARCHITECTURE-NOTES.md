# Architecture Notes: IA Notify

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-notify`
- Version in header: `0.1.17`
- Main entry file: `ia-notify.php`
- Declared purpose: In-app notifications for Atrium (bell badge + toast + fullscreen inbox).

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Nonce strings seen in code: ia_notify_nonce.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_notify_list` — logged-in only; declared in `includes/ajax.php`.
- `ia_notify_mark_read` — logged-in only; declared in `includes/ajax.php`.
- `ia_notify_prefs_save` — logged-in only; declared in `includes/ajax.php`.
- `ia_notify_sync` — logged-in only; declared in `includes/ajax.php`.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-notify.php` — Notification-related logic.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-notify.css` — Stylesheet for ia notify.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-notify.js` — JavaScript for ia notify.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/ajax.php` — AJAX endpoint registration or callback logic.
- `includes/assets.php` — Asset enqueue and localization logic.
- `includes/db.php` — Runtime file for db.
- `includes/email-gate.php` — Runtime file for email gate.
- `includes/hooks.php` — Runtime file for hooks.
- `includes/identity.php` — Runtime file for identity.
- `includes/mail-intercept.php` — PeerTube or token integration logic.
- `includes/prefs.php` — Runtime file for prefs.
