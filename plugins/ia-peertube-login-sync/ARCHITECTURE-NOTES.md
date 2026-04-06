# Architecture Notes: IA PeerTube Login Sync

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-peertube-login-sync`
- Version in header: `0.1.1`
- Main entry file: `ia-peertube-login-sync.php`
- Declared purpose: Allows local PeerTube users to log in to Atrium without separate signup by auto-creating/linking phpBB canonical users + WP shadow users.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Creates or restores WordPress login sessions with core auth cookies.
- Uses capability checks: manage_options.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_ptls_login` — logged-in only; declared in `includes/class-ia-ptls.php`.
- `ia_ptls_login` — public/nopriv; declared in `includes/class-ia-ptls.php`.

## API and integration notes

- `/api/v1/users/token` referenced in `includes/class-ia-ptls.php`.
- `/api/v1/users/me` referenced in `includes/class-ia-ptls.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-peertube-login-sync.php` — PeerTube or token integration logic.
- `readme.txt` — Local maintenance notes/documentation.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/admin.css` — Stylesheet for admin.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-ptls.php` — PeerTube or token integration logic.

### `includes/admin`

- Purpose: Admin-only UI or settings logic.
- `includes/admin/class-ia-ptls-admin.php` — PeerTube or token integration logic.

## Deletion-resurrection note

This plugin can auto-create/link local phpBB and WordPress shadow users from a successful PeerTube-origin login. Because of that, it is one of the critical resurrection paths that must respect IA Goodbye tombstones.

Current expectation:

- deleted local identifiers must be refused for auto-link/auto-create,
- a deleted local account must not silently return just because the remote PeerTube account still exists,
- account deletion therefore relies on this plugin respecting the local tombstone block.


## 2026-04-04 token-budget trace note

Added request-scoped trace logging for `ia_ptls_login` and its direct PeerTube password grant.

Pithy rule: another login surface means another potential budget burner.

## 2026-04-04 structural cleanup note

This plugin still exposes `ia_ptls_login`, but live token-trace debugging showed the successful `atrium` login path was not using this surface.
Treat this route as legacy/auxiliary for now, not canonical. Do not expand its public role unless a future consolidation pass explicitly decides to keep it.


## 2026-04-05 compatibility delegation patch

Patch-only cleanup applied in this stack:

- `ia_ptls_login` now delegates into `IA_User_PeerTube_Fallback_Clean::ajax_login()` when the canonical ladder is available.
- This keeps the legacy surface callable without letting it behave as a co-equal login authority.
- The fallback nonce guard was widened to accept `ia_ptls_login_nonce` so old PTLS callers can pass through the canonical ladder without frontend rewiring in this patch.

Operational rule after this patch:
- treat `ia_ptls_login` as compatibility ingress only
- treat `ia_user_login` as the effective live ladder
- do not add new token behaviour to PTLS


## 2026-04-05 live stability confirmation

After the compatibility-delegation patch was deployed, the user confirmed the stack was stable in live testing.

Confirmed behaviour:

- normal login still works
- no regression requiring PTLS-specific frontend rewiring was reported
- the legacy PTLS surface can remain in place as compatibility ingress without being treated as co-equal auth authority

Operational rule remains unchanged:
- keep PTLS as compatibility ingress only
- keep `ia_user_login` as the effective live ladder
