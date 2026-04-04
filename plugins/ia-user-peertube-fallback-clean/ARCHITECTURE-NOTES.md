# Architecture Notes: IA User PeerTube Fallback (Clean)

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-user-peertube-fallback-clean`
- Version in header: `0.2.3`
- Main entry file: `ia-user-peertube-fallback-clean.php`
- Declared purpose: phpBB first, then PeerTube fallback for IA User modal login. On PeerTube success, auto-create/link phpBB + WP shadow + identity map and log in.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Creates or restores WordPress login sessions with core auth cookies.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_user_login` — logged-in only; declared in `ia-user-peertube-fallback-clean.php`.
- `ia_user_login` — public/nopriv; declared in `ia-user-peertube-fallback-clean.php`.

## API and integration notes

- `/api/v1/users/me` referenced in `ia-user-peertube-fallback-clean.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-user-peertube-fallback-clean.php` — PeerTube or token integration logic.

## Deletion-resurrection note

This clean PeerTube fallback path is another possible account-resurrection route. After IA Goodbye writes a tombstone, this plugin must not recreate the local phpBB user or WordPress shadow user for the same deleted identifier set.

In other words: remote PeerTube success is not, by itself, permission to relink a locally deleted Atrium identity.


## 2026-04-04 deterministic ladder note

This plugin is the effective owner of `ia_user_login` when active, so its behaviour has to be conservative.

Current ladder after this patch:

1. phpBB auth first.
2. Native WordPress auth second.
3. PeerTube password grant third.
4. Only one canonical username retry is allowed after a failed email-based PeerTube grant, and only when admin search returns an exact email match.

Pithy summary: one ladder, no spray.

Why this matters:

- PeerTube `POST /users/token` is rate-limited.
- Repeated candidate spraying can burn the token budget before the real credential test happens.
- WordPress-backed users must still be able to log in even while this fallback plugin has replaced the stock `ia_user_login` handler.

Patch in this pass:

- Added a native WordPress auth step before PeerTube.
- Added short-lived local backoff when PeerTube returns HTTP 429.
- Added clearer debug lines with HTTP code + message.
- Limited PeerTube retries to the submitted identifier plus at most one canonical-username retry.

Consolidation intent for later:

- Once the auth stack is proven stable, fold this ladder into a single canonical auth plugin rather than maintaining parallel login handlers.

## 2026-04-04 token-budget trace note

Added request-scoped trace lines around `ia_user_login` and its PeerTube password-grant attempt. Grep `IA_PT_TOKEN_TRACE` in `debug.log`.

Pithy rule: one visible login should produce one explainable token path.


## 2026-04-04 tidy-up note

Observed from live trace after cooldown:

- Successful `atrium` login ran through `ia_user_login`.
- Effective runtime path was `ia-user-peertube-fallback-clean` -> shared `ia-auth` PeerTube helper -> `/api/v1/users/token` -> HTTP 200.
- In the successful request we did **not** see a same-request token spray across multiple handlers.
- Earlier 429s therefore look more consistent with repeated attempts within the PeerTube limit window than with one click detonating several password grants inside this path.

Bug found during the successful login:

- Post-login token persistence failed because `IA_Auth_DB::store_peertube_token()` expects `phpbb_user_id` as `int`, while this plugin was calling generic token-store methods with the wrong argument shape.

Patch in this pass:

- Token persistence now uses the already-obtained PeerTube token from the successful password grant when available.
- `phpbb_user_id` is cast and passed as `int` to `IA_Auth_DB::store_peertube_token()`.
- The fallback no longer tries the incompatible generic `store_peertube_token($identifier, $password, $phpbb_uid)` call shape.
- phpBB-first logins still skip token persistence unless a compatible mint helper exists, because there is no fresh PeerTube token available in that branch.

Pithy summary: store the token you have; do not re-ask for one and do not call the DB layer with the wrong shape.

Current working understanding:

- Visible Connect modal login is currently hitting `ia_user_login`.
- This plugin is the effective owner of that route.
- Shared PeerTube password-grant logic still lives in `ia-auth`, so the runtime ladder is split but explainable.
- Longer-term cleanup still points toward one canonical auth plugin once the live behaviour is fully pinned down.

## 2026-04-04 structural cleanup follow-up

Additional result after stabilising `atrium`:
- The user requested structural cleanup and detailed notes, with the long-term aim of merging the auth braid into one plugin.
- Public login surfaces are being reduced around this plugin rather than expanded.
- `ia_user_login` remains the canonical live ladder.
- `ia_auth_login` is now being steered into this ladder as compatibility glue instead of remaining a separate preferred path.


## 2026-04-04 canonical auth diagram reference

This plugin is the current owner of the canonical visible login ladder.

Current live ladder reference:
- entry surface: login modal / compatible login form
- canonical AJAX action: `ia_user_login`
- ladder owner: `ia-user-peertube-fallback-clean`
- low-level PeerTube password grant: shared `ia-auth` helper
- reconciliation: repair/create phpBB + WordPress shadow + map rows as needed
- token persistence: store token against canonical phpBB user id
- session completion: WordPress auth cookies/session

Pithy summary: this plugin currently owns the ladder, but not every rung.

Longer-term direction recorded from user discussion:
- later consolidation should move this ladder into one plugin that also owns the low-level token, reconciliation, and session layers cleanly.
- until then, treat this plugin as the live route owner and preserve compatibility.
