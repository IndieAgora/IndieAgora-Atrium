# Architecture Notes: IA Auth PeerTube Fallback

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-auth-peertube-fallback`
- Version in header: `0.1.0`
- Main entry file: `ia-auth-peertube-fallback.php`
- Declared purpose: Adds transparent PeerTube credential fallback to IA Auth login (phpBB first, then PeerTube; auto-create/link phpBB + WP shadow).

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Creates or restores WordPress login sessions with core auth cookies.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_auth_login` — logged-in only; declared in `ia-auth-peertube-fallback.php`.
- `ia_auth_login` — public/nopriv; declared in `ia-auth-peertube-fallback.php`.

## API and integration notes

- `/api/v1/users/me` referenced in `ia-auth-peertube-fallback.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-auth-peertube-fallback.php` — Authentication-related logic.

## Deletion-resurrection note

This plugin is one of the routes that could previously recreate a deleted local user after a successful PeerTube fallback login. In the current stack it must be read as part of the account-deletion boundary, not just as a login helper.

Current expectation:

- if a local identifier has been tombstoned by IA Goodbye,
- this fallback path must not auto-create or relink the local phpBB/WP identity for that deleted identifier set.


## 2026-04-04 token-budget trace note

Added request-scoped trace logging for the parallel `ia_auth_login` fallback route so duplicate PeerTube token calls can be proven from the log instead of guessed.

## 2026-04-04 structural cleanup: compatibility-only role

Context from live debugging:
- The user wanted duplicate auth surfaces reduced after the emergency login issue was stabilised.
- Successful live traces showed the real login path for `atrium` was on `ia_user_login`, not this plugin's parallel `ia_auth_login` path.

What changed:
- This plugin's `ia_auth_login` handler now delegates to `IA_User_PeerTube_Fallback_Clean::ajax_login()` when available.
- That keeps old `ia_auth_login` callers working without spawning a separate PeerTube fallback path.

Practical meaning:
- This plugin is now compatibility glue for older callers, not the preferred public login ladder.
- Longer term, this whole plugin should likely disappear into a consolidated auth plugin.
