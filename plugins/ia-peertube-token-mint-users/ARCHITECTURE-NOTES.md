# Architecture Notes: IA PeerTube Token Mint Users

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-peertube-token-mint-users`
- Version in header: `0.1.9`
- Main entry file: `ia-peertube-token-mint-users.php`
- Declared purpose: Mints, stores, refreshes, and reports per-user PeerTube OAuth tokens.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Creates or restores WordPress login sessions with core auth cookies.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options.
- Nonce strings seen in code: ia_pt_tokens_admin.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- `/api/v1/users/` referenced in `includes/class-peertube-mint.php`.
- `/api/v1/users?search=` referenced in `includes/class-peertube-mint.php`.
- `/api/v1/users` referenced in `includes/class-peertube-mint.php`.
- `/api/v1/users/token` referenced in `includes/class-peertube-mint.php`.
- `/api/v1/users/me` referenced in `includes/class-token-helper.php`.
- `/api/v1/oauth-clients/local` referenced in `includes/class-token-mint.php`.
- `/api/v1/users?search=` referenced in `includes/class-token-mint.php`.
- `/api/v1/users/` referenced in `includes/class-token-mint.php`.
- `/api/v1/users` referenced in `includes/class-token-mint.php`.
- `/api/v1/users/token` referenced in `includes/class-token-refresh.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-peertube-token-mint-users.php` — PeerTube or token integration logic.

### `admin`

- Purpose: Admin-only UI or settings logic.
- `admin/class-admin-page.php` — Admin settings or dashboard logic.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-identity-resolver.php` — Runtime file for class identity resolver.
- `includes/class-password-capture.php` — PeerTube or token integration logic.
- `includes/class-peertube-mint.php` — PeerTube or token integration logic.
- `includes/class-schema.php` — Runtime file for class schema.
- `includes/class-token-helper.php` — PeerTube or token integration logic.
- `includes/class-token-mint.php` — PeerTube or token integration logic.
- `includes/class-token-refresh.php` — PeerTube or token integration logic.
- `includes/class-token-store.php` — PeerTube or token integration logic.

## 2026-04-04 token-budget trace note

Added request-scoped trace logging for post-login password grants and refresh grants.

This plugin may not be the first burner during login failure, but it can still spend `/users/token` budget after login succeeds.


## 2026-04-04 production note: why Stream ends in 401 instead of clean re-mint

Observed from code + uploaded debug log

- `IA_PeerTube_Token_Refresh::maybe_refresh()` correctly detects an expired refresh token and records `invalid_grant` when PeerTube rejects the refresh.
- `IA_PeerTube_Token_Helper::get_token_for_current_user()` then attempts fallback mint through `IA_PT_PeerTube_Mint::try_mint()`.
- On a normal Stream write request there is usually no fresh plaintext password unless the request came through the dedicated prompted mint path (`ia_stream_pt_mint_token`) or through a login flow that explicitly fired `ia_pt_user_password`.
- When that password is unavailable, mint fails with `Password not available in this request...`.

Important behavioural note

- After the `invalid_grant` refresh failure and the mint failure, the helper currently re-reads the token row and returns the old stored access token anyway.
- That behaviour is what converts an internal token-recovery failure into an external PeerTube 401 on comment/rate/delete operations.
- In other words: the helper is not stopping at `missing_user_token`; it is intentionally letting the stale bearer continue downstream.

Database-state note

- The per-user token table used here is `wp_ia_peertube_user_tokens`.
- In the uploaded SQL, phpBB user `347` has a stale row in that table with January timestamps and a historic `last_mint_error` showing prior invalid-token validation.
- That stale row is sufficient to trigger the refresh path, then the fallback mint path, then the stale-token return path.

Pithy summary: the plugin knows recovery failed, but still hands Stream the old bearer.

No code change applied in this note pass.


## 2026-04-04 patch: stop stale-token fallback after invalid_grant + mint failure

- `includes/class-token-helper.php` was patched so that the `invalid_grant` branch no longer falls through to the old stored `access_token_enc`.
- New behavior: if refresh fails with `invalid_grant` and fallback mint also fails, the helper records the mint error and returns `null`.
- This preserves the existing lazy-mint/password-prompt design instead of letting Stream continue with a known-stale bearer.
- Scope was kept patch-only: no schema changes, no new endpoints, no front-end flow changes, no refactor of refresh/mint orchestration.


## 2026-04-04 production confirmation: stale-token fallthrough fix validated

Observed after deploying the `0.1.10` helper patch:

- A Stream comment attempt with expired per-user token state now returns control to the caller as a recoverable missing-token condition.
- Stream then invokes the existing password-mint UI, the user enters the password, and the action succeeds.
- Stream video rating also succeeds immediately afterwards, which confirms the newly minted/refreshed per-user token is then reusable for subsequent write actions.

This validates the intended contract of `IA_PeerTube_Token_Helper::get_token_for_current_user()` for the expired-refresh / no-password branch:

- do **not** return the stale bearer
- do return a recoverable no-token state
- let the caller drive password-mint recovery

Remaining separation to remember:

- This plugin still owns the per-user write-token path used by Stream.
- The stack still also contains older legacy token storage elsewhere. The stale-bearer bug is fixed here, but token-store duplication still exists architecturally.



## 2026-04-04 patch: helper now exposes canonical status contract for callers

Patch-only hardening applied in `0.1.11`.

What changed in `includes/class-token-helper.php`

- `get_token_for_current_user()` still exists for back-compat.
- New canonical method: `get_token_status_for_current_user()`.
- The helper now returns structured caller-facing outcomes instead of forcing callers to treat every failure as bare `null`.

Current status contract

- `valid_token` — a usable bearer is available in the per-user token store path
- `password_required` — refresh failed or lazy mint needed, but the request has no captured plaintext password available for mint
- `mint_failed` — mint path failed for another reason
- `identity_missing` — no usable WordPress -> phpBB identity bridge
- `not_logged_in` — no authenticated WordPress session

Important architectural point

- The canonical read path for Stream-facing user write actions is now explicitly the per-user table `wp_ia_peertube_user_tokens`.
- This plugin remains the owner of that read/refresh/mint path.
- The older login-era token table still exists elsewhere in the stack, but this helper no longer asks Stream callers to reason about both stores.

Why this matters

- The stale-bearer fallthrough fix removed one specific 401 cause.
- The structured status contract removes a second class of ambiguity: callers no longer need to inspect SQL manually to work out whether they are missing identity, missing password capture, or dealing with a generic mint failure.


## 2026-04-04 patch: canonical Stream authority adopts legacy rows once, then stays single-owner

Patch-only consolidation step applied in `0.1.12`.

What changed

- `wp_ia_peertube_user_tokens` is now the sole authoritative Stream token table for read and write operations.
- If the canonical per-user row is missing, the helper may import a legacy `wp_ia_peertube_tokens` row once into the canonical table for that phpBB user.
- After that import, Stream continues only through the canonical helper and canonical table.

Boundary preserved

- The older login-era table still exists.
- The older table is retained for compatibility and observation, not as authoritative Stream state.
- No schema was removed in this patch.


## 2026-04-04 patch: legacy token table reduced from compatibility import to observation-only

Patch-only hardening applied in `0.1.13`.

What changed

- The helper no longer imports rows from legacy `wp_ia_peertube_tokens` into `wp_ia_peertube_user_tokens`.
- If the canonical per-user row is missing but a legacy row still exists, the helper now only logs an observation event and continues through the normal prompted mint path.
- This means the legacy table is no longer part of live Stream token authority or automatic recovery.

New trace points

- `ia-pt-token-helper.canonical_hit`
- `ia-pt-token-helper.canonical_miss`
- `ia-pt-token-helper.legacy_row_observed`
- `ia-pt-token-helper.cached_token_ready`
- `ia-pt-token-helper.refresh_invalid_grant`
- `ia-pt-token-helper.refreshed_token_ready`
- `ia-pt-token-helper.mint_recovery_ok`
- `ia-pt-token-helper.mint_recovery_fail`
- `ia-pt-token-helper.lazy_mint_begin`
- `ia-pt-token-helper.lazy_mint_ok`
- `ia-pt-token-helper.lazy_mint_fail`

Operational meaning

- `wp_ia_peertube_user_tokens` is now the only live Stream token store.
- `wp_ia_peertube_tokens` remains for observation and historical inspection only.
- If a user still relies on legacy token residue, recovery should now be visible through prompt-driven mint rather than silent legacy import.


## 2026-04-05 diagnostics table expansion

Patch-only admin visibility improvement:

- Expanded the PeerTube Tokens admin table to show:
  - derived canonical state
  - whether a legacy `wp_ia_peertube_tokens` row still exists
  - last refresh timestamp
- This is intentionally operator-facing observation, not new token authority.
- The goal is to make split-authority cases visible during PeerTube upgrade/debug work without mutating rows automatically.


## 2026-04-05 live stability confirmation and diagnostics reading

After deployment, the user confirmed the patched stack was working in live use. The expanded admin diagnostics also give a clearer picture of what "stable" means in this architecture.

Observed interpretation from the admin table:

- a small number of actively used accounts show healthy canonical rows such as `token+refresh`
- many linked accounts show `missing_user_token`
- many unlinked or incomplete identities show `identity_missing`
- at least one historic row still shows the older password-capture failure text as a recorded past mint error

Important operational meaning:

- `missing_user_token` for dormant or never-recovered linked users is not, by itself, a live failure
- canonical per-user rows are expected to exist primarily for users who have logged in recently or completed prompt-driven recovery
- the admin table is therefore an observation surface, not a requirement that every historical linked user must already have fresh tokens
- legacy login-era rows should still be treated as observation-only even when present

Stable in this pass therefore means:

- active tested users are obtaining and using canonical per-user tokens correctly
- Stream and ia-post flows are operating without reintroducing legacy token authority
- the diagnostics now make dormant-user gaps visible instead of hiding them behind silent fallback behaviour
