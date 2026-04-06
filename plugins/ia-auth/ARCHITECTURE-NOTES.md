# Architecture Notes: IA Auth

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-auth`
- Version in header: `0.1.11`
- Main entry file: `ia-auth.php`
- Declared purpose: Atrium identity + session layer. phpBB is canonical. WP is shadow sessions. PeerTube tokens via API.

## Authentication and user-state notes


- March 2026 login compatibility patch: `ia_auth_login` now prefers phpBB when available but falls back to native WordPress auth when phpBB auth is unavailable or no longer authoritative. This preserves login for WP-backed Atrium accounts after phpBB removal.
- March 2026 recovery note: users whose WordPress accounts were created as phpBB shadow users may still need a one-time WordPress password reset, because shadow users were originally created with random WordPress passwords while phpBB remained authoritative.
- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Creates or restores WordPress login sessions with core auth cookies.
- Contains WordPress user deletion logic.
- Contains WordPress logout/session termination logic.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options.
- Nonce strings seen in code: ia_auth_nonce.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_auth_forgot` — logged-in only; declared in `includes/class-ia-auth.php`.
- `ia_auth_forgot` — public/nopriv; declared in `includes/class-ia-auth.php`.
- `ia_auth_login` — logged-in only; declared in `includes/class-ia-auth.php`.
- `ia_auth_login` — public/nopriv; declared in `includes/class-ia-auth.php`.
- `ia_auth_logout` — logged-in only; declared in `includes/class-ia-auth.php`.
- `ia_auth_logout` — public/nopriv; declared in `includes/class-ia-auth.php`.
- `ia_auth_register` — logged-in only; declared in `includes/class-ia-auth.php`.
- `ia_auth_register` — public/nopriv; declared in `includes/class-ia-auth.php`.

### Rewrite or pretty-URL routes

- Pattern `^ia-login/?$` -> `index.php?ia_auth_route=login` in `includes/class-ia-auth.php`.
- Pattern `^ia-register/?$` -> `index.php?ia_auth_route=register` in `includes/class-ia-auth.php`.
- Pattern `^ia-verify/([^/]+)/?$` -> `index.php?ia_auth_route=verify&ia_auth_token=$matches[1]` in `includes/class-ia-auth.php`.
- Pattern `^ia-reset/?$` -> `index.php?ia_auth_route=reset` in `includes/class-ia-auth.php`.
- Pattern `^ia-check-email/?$` -> `index.php?ia_auth_route=check_email` in `includes/class-ia-auth.php`.
- Pattern `^ia-check-reset/?$` -> `index.php?ia_auth_route=check_reset` in `includes/class-ia-auth.php`.

## API and integration notes

- `/api/v1/oauth-clients/local` referenced in `includes/class-ia-auth-peertube.php`.
- `/api/v1/users/token` referenced in `includes/class-ia-auth-peertube.php`.
- `/api/v1/users?search=` referenced in `includes/class-ia-auth-peertube.php`.
- `/api/v1/users` referenced in `includes/class-ia-auth-peertube.php`.
- `/api/v1/users/` referenced in `includes/class-ia-auth-peertube.php`.
- `/api/v1/users/me` referenced in `includes/class-ia-auth-peertube.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `README.md` — Local maintenance notes/documentation.
- `ia-auth.php` — Authentication-related logic.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-auth-admin.css` — Stylesheet for ia auth admin.
- `assets/css/ia-auth.css` — Stylesheet for ia auth.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-auth.js` — JavaScript for ia auth.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-auth-crypto.php` — Authentication-related logic.
- `includes/class-ia-auth-db.php` — Authentication-related logic.
- `includes/class-ia-auth-logger.php` — Authentication-related logic.
- `includes/class-ia-auth-peertube.php` — Authentication-related logic.
- `includes/class-ia-auth-phpbb.php` — Authentication-related logic.
- `includes/class-ia-auth.php` — Authentication-related logic.

### `includes/admin`

- Purpose: Admin-only UI or settings logic.
- `includes/admin/class-ia-auth-admin.php` — Authentication-related logic.

## 2026-04-04 auth-chain hardening note

Current observed ladder in the live stack is not a single login system. It is a chained identity path spread across multiple plugins:

- `ia-user` owns the `ia_user_login` AJAX surface by default.
- `ia-user-peertube-fallback-clean` swaps that handler and becomes the effective IA User login ladder when active.
- `ia-auth` owns the separate `ia_auth_login` AJAX surface and carries the shared phpBB / PeerTube helper classes.
- `ia-auth-peertube-fallback` is a parallel fallback path for the `ia_auth_login` surface.
- `ia-peertube-login-sync` provides another PeerTube-origin shadow-user recovery path.
- `ia-peertube-token-mint-users` depends on receiving plaintext login credentials during the request to mint per-user PeerTube tokens.

Pithy summary: this is a federated identity braid, not a single auth plugin.

Practical consequence:

- A user can exist in PeerTube, be absent from phpBB mapping, have a WordPress shadow user missing or stale, and still partially exist in token or identity tables.
- A failure can therefore be caused by mapping gaps, token expiry, rate limiting on `/api/v1/users/token`, or unsafe assumptions about PeerTube response shapes.

Patch in this pass:

- `includes/class-ia-auth-peertube.php` no longer assumes PeerTube user-search responses are zero-indexed arrays. It now tolerates list payloads wrapped in `data` and falls back to the first array entry safely.
- This specifically avoids `Undefined array key 0` during admin user search / canonical lookup work.

Consolidation intent for later:

- Keep one canonical login ladder.
- Keep one canonical identity map writer.
- Keep one canonical PeerTube token mint trigger.
- Remove duplicate fallback logic once the stack is stable.

## 2026-04-04 token-budget trace note

Added request-scoped PeerTube token trace logging. Grep `IA_PT_TOKEN_TRACE` in `debug.log`.

Pithy rule: no more black-box token burns.

Trace coverage in this pass:
- `ia-auth` ajax login entry and every `/api/v1/users/token` attempt from shared PeerTube helper.
- Candidate-loop retries in `ia-auth`.

Goal: identify which handler is actually consuming the PeerTube token budget before user-visible failure.


## 2026-04-04 confirmed live path note

Live token trace from a successful `atrium` login showed:

- request entered on `ia_user_login`
- `ia-user-peertube-fallback-clean` initiated the PeerTube attempt
- this plugin's shared PeerTube helper performed the actual `/api/v1/users/token` request
- the token request returned HTTP 200

Operational conclusion:

- `ia-auth` is still the shared PeerTube password-grant engine even when the visible modal is owned by `ia-user-peertube-fallback-clean`.
- When reading logs, treat `ia-auth.password_grant.*` as the low-level token call and `ia-user-fallback.*` as the route that led to it.

Pithy summary: route owner and token caller are not always the same plugin.

## 2026-04-04 structural cleanup: canonical ladder / reduced duplicate surfaces

Context captured from live debugging and user discussion:
- The Atrium auth system had become a braid rather than a ladder: `ia_user_login`, `ia_auth_login`, and `ia_ptls_login` all existed, with overlapping PeerTube token callers.
- Live token-trace work proved the visible successful `atrium` login path was:
  `ia_user_login` -> `ia-user-peertube-fallback-clean` -> `ia-auth.password_grant` -> success.
- The user explicitly requested structural cleanup rather than more emergency auth work, with detailed notes preserved for future consolidation into a single plugin.

What changed in this pass:
- `ia_auth_login` is now treated as a compatibility surface, not the preferred live ladder.
- `IA_Auth::ajax_login()` now delegates to `IA_User_PeerTube_Fallback_Clean::ajax_login()` when the clean fallback plugin is active.
- The built IA Auth login form now posts to `ia_user_login` so older IA Auth-rendered forms follow the canonical live route.

Resulting intended ladder:
1. Visible/public login submits to `ia_user_login`.
2. `ia-user-peertube-fallback-clean` owns the ladder.
3. That ladder tries phpBB first, native WordPress second, PeerTube third.
4. `ia-auth` remains the shared low-level helper/token layer, not the preferred public surface.

Operational note:
- This is a reduction step, not the final one-plugin consolidation.
- `ia_ptls_login` still exists as a legacy/auxiliary surface and should be treated as non-canonical unless future cleanup explicitly removes or rewires it.


## 2026-04-04 final canonical auth diagram

This section records the now-confirmed live ladder after the `atrium` recovery work, the 429 investigation, the token-trace pass, the token-store fix, and the structural cleanup that reduced duplicate public login surfaces.

Canonical live ladder (current stack)

```text
Visible login surface
    |
    v
IA Login modal / IA Auth-rendered login form
    |
    | posts to ajax_action=ia_user_login
    v
IA User AJAX surface
    |
    v
ia-user-peertube-fallback-clean::ajax_login()
    |
    |-- 1. phpBB auth first
    |-- 2. native WordPress auth second
    |-- 3. PeerTube password grant third
    v
ia-auth shared PeerTube helper
    |
    |-- GET /api/v1/oauth-clients/local (when client details are needed)
    |-- POST /api/v1/users/token
    |-- GET /api/v1/users/me
    v
Shadow / link reconciliation
    |
    |-- resolve or create phpBB-side canonical identity
    |-- resolve or create WordPress shadow user
    |-- repair identity map / link tables as needed
    v
Token persistence
    |
    |-- store PeerTube token against phpBB user id
    v
Session completion
    |
    |-- wp_set_current_user
    |-- wp_set_auth_cookie
    |-- return login success to frontend
```

Pithy summary: one public ladder, one low-level token engine, one post-success persistence step.

Confirmed by live trace:
- successful `atrium` logins entered on `ia_user_login`
- `ia-user-peertube-fallback-clean` owned the visible ladder
- `ia-auth` executed the low-level PeerTube password grant
- token request returned HTTP 200
- token store direct path later returned `ok` for `phpbb_uid=347`

This matters because it separates concerns cleanly:
- route owner: `ia-user-peertube-fallback-clean`
- token caller: `ia-auth`
- visible modal surface: `ia-login`
- legacy/compat surface: `ia_auth_login` delegated into the canonical ladder during structural cleanup

Known non-canonical or legacy surfaces that still exist in the stack

- `ia_auth_login` still exists as a compatibility surface, but the current design intent is that it should feed the canonical `ia_user_login` ladder rather than behave as a separate preferred route.
- `ia_ptls_login` from `ia-peertube-login-sync` still exists as a legacy/auxiliary route and should be treated as non-canonical until an explicit consolidation pass removes or rewires it.
- `ia-peertube-token-mint-users` still participates after login or during password-capture/token-maintenance workflows, but it is not the primary visible login route.

Operational rules captured from this debugging cycle

- Do not treat the auth system as a single plugin yet. It is still a braid with a confirmed dominant ladder.
- When diagnosing login, grep `IA_PT_TOKEN_TRACE` first, then inspect `ia_user_login` vs `ia_auth_login` entry.
- When debugging a PeerTube-origin user, separate these phases mentally: entry surface, low-level token call, shadow/link reconciliation, token storage, session finish.
- A successful password grant is not the same thing as a completed login unless token persistence and session completion both follow cleanly.

## 2026-04-04 one-plugin consolidation plan (documentation only)

The user asked that this conversation's conclusions be written down in the notes and that future work move toward one plugin that owns the full auth story cleanly.

This section is planning documentation only. It does not claim the stack has already been merged.

Target end-state

One auth plugin should eventually own all of the following:
- visible login/register/reset surfaces
- canonical AJAX login endpoint
- phpBB-first / WP-second / PeerTube-third ladder
- shadow user creation and repair
- identity map repair and reads
- PeerTube token acquisition, refresh, and persistence
- logout/session completion
- deletion/tombstone checks to prevent resurrection
- debugging/trace logging for the whole ladder

Suggested future internal modules for the merged plugin

```text
Auth UI / Entry Surface
    - renders modal/forms
    - posts only to one canonical AJAX action

Auth Orchestrator
    - owns the ladder and branching rules
    - records request-scoped debug trace

Identity Resolver
    - finds phpBB / WP / PeerTube identities
    - reads and repairs map rows

Provider: phpBB
    - authenticate
    - fetch canonical phpBB user data

Provider: WordPress
    - authenticate shadow/local user
    - create/update WP shadow
    - start/end WP session

Provider: PeerTube
    - local oauth client lookup
    - password grant
    - /users/me lookup
    - token refresh / persistence

Reconciliation Service
    - link/repair phpBB <-> WP <-> PeerTube
    - enforce no-resurrection / tombstone rules

Token Store
    - store/retrieve PeerTube access and refresh tokens
    - bind tokens to canonical phpBB-side identity

Deletion Boundary / Tombstone Guard
    - centralise checks now spread across multiple plugins

Admin Diagnostics
    - human-readable auth ladder state
    - recent trace lines / last-failure reason
```

Suggested migration path

1. Keep the current canonical live ladder exactly as it is now.
2. Continue reducing duplicate public surfaces.
3. Move shared code into one new auth-core plugin or one expanded auth plugin namespace.
4. Turn legacy plugins into thin compatibility shims.
5. Remove shims only after live traces prove the merged plugin fully owns the path.

Order of likely future consolidation work

1. Merge login entry surfaces (`ia-login`, IA Auth forms) into one owner.
2. Move the `ia_user_login` ladder into one orchestrator class.
3. Move PeerTube helper and token store responsibilities under the same namespace.
4. Absorb or retire `ia-auth-peertube-fallback`.
5. Absorb or retire `ia-peertube-login-sync`.
6. Absorb token-mint/password-capture behaviour that is still required.
7. Centralise deletion/tombstone guard checks.
8. Remove compatibility shims.

Risks to remember

- Do not merge by deleting behaviour blindly. The current system is ugly but it encodes survival paths for edge-case users like `atrium`.
- Preserve the confirmed live order: phpBB first, WordPress second, PeerTube third.
- Preserve token trace logging during every consolidation stage.
- Preserve the no-resurrection boundary around deleted users.

Pithy summary: migrate braid -> ladder -> single owner, without losing edge-case recovery paths.


## 2026-04-04 production note: database structure relevant to Stream 401

Relevant tables in the uploaded schema

- `wp_ia_identity_map`
  - primary key: `phpbb_user_id`
  - carries `wp_user_id`, `peertube_user_id`, status, timestamps, `last_error`
  - this is the bridge from the active WordPress session to canonical phpBB identity
- `wp_ia_peertube_tokens`
  - legacy token store keyed by `phpbb_user_id`
  - fields: `access_token_enc`, `refresh_token_enc`, `expires_at_utc`, `scope`, `token_source`, `updated_at`
  - still written by auth/login paths
- `wp_ia_peertube_user_tokens`
  - newer per-user action token store keyed by `phpbb_user_id`
  - fields: `peertube_user_id`, `access_token_enc`, `refresh_token_enc`, `expires_at`, `last_refresh_at`, `last_mint_at`, `last_mint_error`, timestamps
  - used by `ia-peertube-token-mint-users` and therefore by Stream write actions
- `wp_ia_stream_comment_votes`
  - local-only vote overlay for comment reactions
  - unrelated to the PeerTube 401 itself

Operational consequence

- The stack currently has split token persistence.
- A user can hold a fresh login-era token in `wp_ia_peertube_tokens` while still holding an expired Stream-era token in `wp_ia_peertube_user_tokens`.
- That exact split is present for phpBB user `347` in the uploaded SQL and is consistent with the uploaded Stream debug trace.

Pithy summary: one identity map, two token stores, different consumers.

No code change applied in this note pass.


## 2026-04-04 patch note: Stream token helper no longer returns stale bearer after invalid_grant

- The canonical auth ladder still ends in per-user token persistence for Stream write actions.
- The important correction is that an expired refresh token plus unavailable plaintext password is now treated as a recoverable missing-token state, not as permission to keep using the previously stored user bearer.
- This keeps the stack aligned with the documented ladder: refresh if possible, otherwise re-mint with user password, otherwise stop and prompt.


## 2026-04-04 production confirmation after Stream write test

The post-patch Stream test gives a useful boundary line for this plugin:

- The successful password prompt during Stream comment recovery confirms the wider stack can still obtain a valid PeerTube password grant when the user actively supplies credentials.
- That means the earlier visible Stream 401 was not caused by a broken shared password-grant engine in `ia-auth`; it was caused by the per-user helper returning a stale bearer instead of surfacing a recoverable state.

What the latest debug file adds:

- The supplied post-success debug capture is dominated by repeated `open_basedir` warnings, not by fresh PeerTube token-grant failures.
- So, for this incident, `ia-auth` now looks operational in the specific role of shared password-grant engine during recovery.

Pithy summary: the grant engine still worked; the bug was in what happened before recovery was allowed to start.



## 2026-04-04 patch note: Stream now has one canonical token-read owner

This does **not** consolidate the stack to one token table yet.

What it does do

- For Stream write actions, the canonical read owner is now the per-user token helper in `ia-peertube-token-mint-users`.
- That helper now exposes structured status codes so Stream can distinguish `password_required`, `identity_missing`, and `mint_failed` without hand-inspecting token rows.
- The older login-era token table still exists and is still written by login/auth surfaces, but Stream is now documented and patched to defer to the per-user helper first.

Boundary preserved

- login/auth persistence remains split in the wider stack
- Stream/user-write token reads are now explicitly single-owner
- full token-store consolidation remains a later deliberate architecture task, not part of this patch


## 2026-04-04 compatibility note: legacy login-era token table is no longer authoritative for Stream

- `wp_ia_peertube_tokens` still exists because login/auth surfaces may continue to write it during the broader transition.
- Stream now treats that table as compatibility baggage only.
- When Stream needs a bearer, the authoritative path is the per-user helper and `wp_ia_peertube_user_tokens`.


## 2026-04-05 opportunistic mint normalization

Patch-only cleanup in the canonical-login era:

- After successful login, `ia-auth` now prefers `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` for the non-blocking opportunistic mint/check.
- It still falls back to `get_token_for_current_user()` only if the status method is unavailable.
- This does not change visible login behaviour. It just aligns post-login token warmup with the structured canonical helper contract used elsewhere in the stack.


## 2026-04-05 live stability confirmation

Post-patch live verification reported by the user:

- login working
- Stream reads working
- comment / reply / rating flows working
- ia-post upload bootstrap working
- no immediate regression reported after deploy/reactivate/refresh test pass

Operational reading:

- the shared `ia-auth` grant/helper layer appears stable in the current stack role
- this does **not** mean every linked user should have a warm canonical token row at all times
- it **does** mean active login and prompted recovery paths are behaving correctly for tested users
