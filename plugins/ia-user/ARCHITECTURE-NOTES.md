# Architecture Notes: IA User

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-user`
- Version in header: `0.1.8`
- Main entry file: `ia-user.php`
- Declared purpose: Atrium login/register UI that uses phpBB (phpbb_users) as the user authority. On success, a WP shadow user is created and logged in for session/UI purposes.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Creates or restores WordPress login sessions with core auth cookies.
- Contains WordPress logout/session termination logic.
- Nonce strings seen in code: ia_user_nonce.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_user_forgot` — logged-in only; declared in `includes/class-ia-user.php`.
- `ia_user_forgot` — public/nopriv; declared in `includes/class-ia-user.php`.
- `ia_user_login` — logged-in only; declared in `includes/class-ia-user.php`.
- `ia_user_login` — public/nopriv; declared in `includes/class-ia-user.php`.
- `ia_user_logout` — logged-in only; declared in `includes/class-ia-user.php`.
- `ia_user_logout` — public/nopriv; declared in `includes/class-ia-user.php`.
- `ia_user_register` — logged-in only; declared in `includes/class-ia-user.php`.
- `ia_user_register` — public/nopriv; declared in `includes/class-ia-user.php`.
- `ia_user_reset` — logged-in only; declared in `includes/class-ia-user.php`.
- `ia_user_reset` — public/nopriv; declared in `includes/class-ia-user.php`.

### Rewrite or pretty-URL routes

- Pattern `^ia-verify/([A-Za-z0-9_-]+)/?$` -> `index.php?ia_verify=$matches[1]` in `includes/class-ia-user.php`.

## API and integration notes

- `/api/v1/users` referenced in `includes/class-ia-user.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-user.php` — Runtime file for ia user.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-user.css` — Stylesheet for ia user.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-user.js` — JavaScript for ia user.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-user-phpbb.php` — phpBB integration logic.
- `includes/class-ia-user.php` — Runtime file for class ia user.
- `includes/ia-message-bridge.php` — Runtime file for ia message bridge.

## Deletion-resurrection note

Although IA User is a login/register UI plugin, it is also relevant to deletion because it can create local phpBB/WP-linked identities. In the current stack, deleted identifiers must be blocked here as well or a deleted user can be recreated through the alternate registration/login path.

Read this plugin as part of the wider identity/deletion system, not in isolation.


## 2026-03-15 admin control UI note

IA User now also ships an admin-only user-control surface in `includes/admin/class-ia-user-admin.php`, exposed as a WordPress admin menu page at `wp-admin/admin.php?page=ia-user`.

This admin UI is intentionally placed in `ia-user` because this plugin already sits on the boundary between phpBB authority, WordPress shadow users, and PeerTube-linked login/registration flows. The page is meant to let an admin perform the same practical account edits a user can make from the frontend, but from the backend and across linked systems.

### Admin functions now exposed

- Find/search Atrium-linked users (WordPress users carrying `ia_phpbb_user_id` meta)
- Inspect linked account state:
  - WordPress user id/login/display name/email
  - phpBB user id/username/email
  - PeerTube user id from the identity map
  - identity-map status
  - deactivated flag
  - tombstone state if present
- Edit WordPress-only display name (matches the simple frontend display-name save)
- Edit Connect Discuss signature + show-in-Discuss flag
- Edit account name using the same cross-system semantics as the frontend account-name change:
  - phpBB `username`
  - phpBB `username_clean`
  - WordPress `display_name`
  - WordPress `nickname`
  - WordPress `user_login`
  - WordPress `user_nicename`
  - identity-map `phpbb_username_clean`
  - best-effort PeerTube display name via stored token
- Edit email using the same cross-system semantics as the frontend email change:
  - phpBB `user_email`
  - WordPress `user_email`
  - WordPress `ia_email` meta
  - identity-map `email`
  - PeerTube email where a linked PeerTube user id exists
- Edit password using the same cross-system semantics as the frontend password change:
  - phpBB `user_password`
  - WordPress password
  - PeerTube password where linked
- Deactivate account using `IA_Goodbye->deactivate_account()`
- Reactivate account by clearing phpBB deactivation and WordPress local deactivation markers
- Delete account using the new tombstone-first deletion logic via `IA_Goodbye->delete_account()`

### Files added for this admin UI

- `includes/admin/class-ia-user-admin.php` — admin page, search UI, form handlers, cross-system edit actions
- `assets/css/ia-user-admin.css` — admin page styles

### Routing / actions added

These backend actions are implemented as WordPress `admin-post.php` handlers rather than frontend AJAX:

- `ia_user_admin_save_display_name`
- `ia_user_admin_save_signature`
- `ia_user_admin_save_account_name`
- `ia_user_admin_save_email`
- `ia_user_admin_save_password`
- `ia_user_admin_deactivate`
- `ia_user_admin_reactivate`
- `ia_user_admin_delete`

### Deletion/admin safety note

The admin delete action does not ask for the user's current password because this is a backend administrator override. Instead it requires a typed `DELETE` confirmation and then delegates to the same `IA_Goodbye` delete path used by the frontend lifecycle system.

### Tombstone-respect note

The admin rename/email handlers also respect the deletion tombstone rule. They refuse to move an active user onto an email or username identifier that belongs to a previously deleted account.


## 2026-03-15 admin user list usability note

The IA User admin page now paginates the linked-user list instead of hard-capping the visible results to a fixed first slice. The page accepts `paged` and `per_page` query args and keeps those values in the user-detail links so moving between the list and a selected user does not drop the current view state.

The search box now also has live backend suggestions via `wp_ajax_ia_user_admin_search_suggest`. This is an admin-only AJAX helper that returns a small JSON suggestion payload based on the same Atrium-linked user query, intended to surface likely matches while the admin types.

Files touched for this usability pass:
- `includes/admin/class-ia-user-admin.php`
- `assets/js/ia-user-admin.js`
- `assets/css/ia-user-admin.css`

## 2026-03-15 display-name resolver for dependent plugins

- Added `IA_User::phpbb_user_display_name(int $phpbb_user_id)` as a display-only resolver for other plugins such as IA Message.
- Resolution order is: `ia_identity_map.wp_user_id` -> common phpBB usermeta keys -> WordPress `display_name` -> `nickname` -> `user_login`.
- This method does not change authentication, identity authority, or account-linking behaviour.
