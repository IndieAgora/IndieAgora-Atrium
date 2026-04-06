- 2026-04-06 deep-link correction patch: message thread loads can now request a specific target message; the server computes an offset around the target and the client retries the jump after render so message notifications land on the actual bubble that triggered them.
# Architecture Notes: IA Message

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-message`
- Version in header: `0.4.6`
- Main entry file: `ia-message.php`
- Declared purpose: Atrium-native messaging module (DM + group) with email-first import adapters.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_message_forward` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_group_invite_respond` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_group_invite_send` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_group_invites` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_group_kick` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_group_members` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_new_dm` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_new_group` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_prefs_get` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_prefs_set` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_send` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_thread` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_threads` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_unread_count` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_upload` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_user_block_toggle` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_user_follow_toggle` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_user_rel_status` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_message_user_search` — logged-in only; declared in `includes/support/ajax.php`.

## API and integration notes

- `https://www.youtube-nocookie.com/embed/` referenced in `assets/js/ia-message.boot.js`.
- `https://player.vimeo.com/video/` referenced in `assets/js/ia-message.boot.js`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `LICENSE` — Runtime file for license.
- `README.md` — Local maintenance notes/documentation.
- `ia-message.php` — Runtime file for ia message.
- `uninstall.php` — Install or schema setup logic.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-message.admin.css` — Stylesheet for ia message admin.
- `assets/css/ia-message.base.css` — Stylesheet for ia message base.
- `assets/css/ia-message.chat.css` — Stylesheet for ia message chat.
- `assets/css/ia-message.composer.css` — Stylesheet for ia message composer.
- `assets/css/ia-message.layout.css` — Stylesheet for ia message layout.
- `assets/css/ia-message.modal.css` — Stylesheet for ia message modal.
- `assets/css/ia-message.threads.css` — Stylesheet for ia message threads.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/README.md` — Local maintenance notes/documentation.
- `assets/js/ia-message.admin.js` — JavaScript for ia message admin.
- `assets/js/ia-message.api.js` — JavaScript for ia message api.
- `assets/js/ia-message.boot.js` — JavaScript for ia message boot.
- `assets/js/ia-message.core.js` — JavaScript for ia message core.
- `assets/js/ia-message.state.js` — JavaScript for ia message state.
- `assets/js/ia-message.ui.chat.js` — JavaScript for ia message ui chat.
- `assets/js/ia-message.ui.composer.js` — JavaScript for ia message ui composer.
- `assets/js/ia-message.ui.modals.js` — JavaScript for ia message ui modals.
- `assets/js/ia-message.ui.shell.js` — JavaScript for ia message ui shell.
- `assets/js/ia-message.ui.threads.js` — JavaScript for ia message ui threads.

### `docs`

- Purpose: Human-maintained notes and operational docs.
- `docs/DEVELOPMENT-NOTES.md` — Local maintenance notes/documentation.
- `docs/ENDPOINTS.md` — Local maintenance notes/documentation.
- `docs/ERROR-NOTES.md` — Local maintenance notes/documentation.
- `docs/LIVE-NOTES.md` — Local maintenance notes/documentation.
- `docs/RULES.md` — Local maintenance notes/documentation.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/constants.php` — Runtime file for constants.
- `includes/functions.php` — Runtime file for functions.
- `includes/ia-message.php` — Runtime file for ia message.

### `includes/admin`

- Purpose: Admin-only UI or settings logic.
- `includes/admin/admin.php` — Admin settings or dashboard logic.
- `includes/admin/menu.php` — Runtime file for menu.

### `includes/admin/pages`

- Purpose: Directory used by this plugin.
- `includes/admin/pages/diagnostics.php` — Runtime file for diagnostics.
- `includes/admin/pages/import.php` — Runtime file for import.
- `includes/admin/pages/logs.php` — Runtime file for logs.
- `includes/admin/pages/mapping.php` — Runtime file for mapping.
- `includes/admin/pages/tools.php` — Runtime file for tools.

### `includes/admin/partials`

- Purpose: Directory used by this plugin.
- `includes/admin/partials/notices.php` — Runtime file for notices.
- `includes/admin/partials/progress.php` — Runtime file for progress.

### `includes/contracts`

- Purpose: Shared object/event contracts.
- `includes/contracts/events.php` — Runtime file for events.
- `includes/contracts/objects.php` — Runtime file for objects.

### `includes/diagnostics`

- Purpose: Health checks or debug surfaces.
- `includes/diagnostics/health.php` — Runtime file for health.

### `includes/migrations`

- Purpose: Schema migration files.
- `includes/migrations/0001-initial.php` — Runtime file for 0001 initial.

### `includes/modules`

- Purpose: Module classes or controller-like entry points.
- `includes/modules/diag.php` — Runtime file for diag.
- `includes/modules/message-actions.php` — Runtime file for message actions.
- `includes/modules/message-send.php` — Runtime file for message send.
- `includes/modules/module-interface.php` — Runtime file for module interface.
- `includes/modules/panel.php` — Main panel renderer or mount point.
- `includes/modules/thread-list.php` — Runtime file for thread list.
- `includes/modules/thread-new.php` — Runtime file for thread new.
- `includes/modules/thread-view.php` — Runtime file for thread view.

### `includes/render`

- Purpose: Rendering helpers for HTML, media, and text.
- `includes/render/admin.php` — Admin settings or dashboard logic.
- `includes/render/messages.php` — Runtime file for messages.
- `includes/render/participants.php` — Runtime file for participants.
- `includes/render/threads.php` — Runtime file for threads.

### `includes/services`

- Purpose: Service-layer logic, data access, or integrations.
- `includes/services/README.md` — Local maintenance notes/documentation.
- `includes/services/db.php` — Runtime file for db.
- `includes/services/format.php` — Runtime file for format.
- `includes/services/identity.php` — Runtime file for identity.
- `includes/services/import-bm.php` — Runtime file for import bm.
- `includes/services/import-csv.php` — Runtime file for import csv.
- `includes/services/import-phpbb.php` — phpBB integration logic.
- `includes/services/import.php` — Runtime file for import.
- `includes/services/log.php` — Runtime file for log.
- `includes/services/messages.php` — Runtime file for messages.
- `includes/services/notifications.php` — Notification-related logic.
- `includes/services/notify.php` — Notification-related logic.
- `includes/services/participants.php` — Runtime file for participants.
- `includes/services/rate-limit.php` — Runtime file for rate limit.
- `includes/services/search.php` — Runtime file for search.
- `includes/services/threads.php` — Runtime file for threads.
- `includes/services/users.php` — Runtime file for users.

### `includes/support`

- Purpose: Shared support code such as assets, security, install, and AJAX bootstrapping.
- `includes/support/README.md` — Local maintenance notes/documentation.
- `includes/support/ajax.php` — AJAX endpoint registration or callback logic.
- `includes/support/assets.php` — Asset enqueue and localization logic.
- `includes/support/capabilities.php` — Runtime file for capabilities.
- `includes/support/install.php` — Install or schema setup logic.
- `includes/support/routes.php` — Routing logic.
- `includes/support/security.php` — Security helpers such as nonce or permission checks.

### `includes/support/ajax`

- Purpose: Directory used by this plugin.
- `includes/support/ajax/groups.php` — Runtime file for groups.
- `includes/support/ajax/messages.php` — Runtime file for messages.
- `includes/support/ajax/threads.php` — Runtime file for threads.
- `includes/support/ajax/users.php` — Runtime file for users.

### `includes/templates`

- Purpose: PHP templates rendered into the front end.
- `includes/templates/modal.php` — Runtime file for modal.
- `includes/templates/panel.php` — Main panel renderer or mount point.

### `includes/templates/partials`

- Purpose: Directory used by this plugin.
- `includes/templates/partials/composer.php` — Runtime file for composer.
- `includes/templates/partials/modals.php` — Runtime file for modals.
- `includes/templates/partials/thread-list.php` — Runtime file for thread list.
- `includes/templates/partials/thread-view.php` — Runtime file for thread view.

## 2026-03-15 display-name label path

- IA Message now applies the `ia_message_user_label` filter inside `ia_message_display_ui_name_for_phpbb_id()`.
- This widens the UI-label path without changing message identity rules.
- Resulting labels in thread titles, DM names, message author labels, and user pickers can now honour stack-level display-name resolvers more reliably.
