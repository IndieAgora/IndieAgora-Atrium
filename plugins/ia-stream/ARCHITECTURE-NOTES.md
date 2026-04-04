# Architecture Notes: IA Stream

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-stream`
- Version in header: `0.1.3`
- Main entry file: `ia-stream.php`
- Declared purpose: Atrium Stream panel (PeerTube-backed) with mobile-first video feed + channels + modal video view + PeerTube comments.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options, moderate_comments.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_stream_channels` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_channels` — public/nopriv; declared in `includes/support/ajax.php`.
- `ia_stream_comment_create` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_comment_delete` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_comment_rate` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_comment_reply` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_comment_thread` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_comment_thread` — public/nopriv; declared in `includes/support/ajax.php`.
- `ia_stream_comments` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_comments` — public/nopriv; declared in `includes/support/ajax.php`.
- `ia_stream_feed` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_feed` — public/nopriv; declared in `includes/support/ajax.php`.
- `ia_stream_pt_mint_token` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_video` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_video` — public/nopriv; declared in `includes/support/ajax.php`.
- `ia_stream_video_rate` — logged-in only; declared in `includes/support/ajax.php`.
- `ia_stream_whoami` — logged-in only; declared in `includes/support/ajax.php`.

## API and integration notes

- `/api/v1/videos` referenced in `includes/services/peertube-api.php`.
- `/api/v1/video-channels` referenced in `includes/services/peertube-api.php`.
- `/api/v1/videos/` referenced in `includes/services/peertube-api.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-stream.php` — Runtime file for ia stream.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-stream.base.css` — Stylesheet for ia stream base.
- `assets/css/ia-stream.cards.css` — Stylesheet for ia stream cards.
- `assets/css/ia-stream.channels.css` — Stylesheet for ia stream channels.
- `assets/css/ia-stream.layout.css` — Stylesheet for ia stream layout.
- `assets/css/ia-stream.modal.css` — Stylesheet for ia stream modal.
- `assets/css/ia-stream.player.css` — Stylesheet for ia stream player.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-stream.api.js` — JavaScript for ia stream api.
- `assets/js/ia-stream.boot.js` — JavaScript for ia stream boot.
- `assets/js/ia-stream.core.js` — JavaScript for ia stream core.
- `assets/js/ia-stream.state.js` — JavaScript for ia stream state.
- `assets/js/ia-stream.ui.channels.js` — JavaScript for ia stream ui channels.
- `assets/js/ia-stream.ui.comments.js` — JavaScript for ia stream ui comments.
- `assets/js/ia-stream.ui.feed.js` — JavaScript for ia stream ui feed.
- `assets/js/ia-stream.ui.shell.js` — JavaScript for ia stream ui shell.
- `assets/js/ia-stream.ui.video.js` — JavaScript for ia stream ui video.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/functions.php` — Runtime file for functions.
- `includes/ia-stream.php` — Runtime file for ia stream.

### `includes/modules`

- Purpose: Module classes or controller-like entry points.
- `includes/modules/channels.php` — Runtime file for channels.
- `includes/modules/comments.php` — Runtime file for comments.
- `includes/modules/diag.php` — Runtime file for diag.
- `includes/modules/feed.php` — Runtime file for feed.
- `includes/modules/module-interface.php` — Runtime file for module interface.
- `includes/modules/panel.php` — Main panel renderer or mount point.
- `includes/modules/video.php` — Runtime file for video.

### `includes/render`

- Purpose: Rendering helpers for HTML, media, and text.
- `includes/render/media.php` — Runtime file for media.
- `includes/render/text.php` — Runtime file for text.

### `includes/services`

- Purpose: Service-layer logic, data access, or integrations.
- `includes/services/auth.php` — Authentication-related logic.
- `includes/services/comment-votes.php` — Runtime file for comment votes.
- `includes/services/peertube-api.php` — PeerTube or token integration logic.
- `includes/services/text.php` — Runtime file for text.

### `includes/support`

- Purpose: Shared support code such as assets, security, install, and AJAX bootstrapping.
- `includes/support/ajax.php` — AJAX endpoint registration or callback logic.
- `includes/support/assets.php` — Asset enqueue and localization logic.
- `includes/support/security.php` — Security helpers such as nonce or permission checks.


## 2026-04-04 production note: Stream 401 on comment/rate

Observed failure shape

- Stream write paths (`ia_stream_comment_create`, `ia_stream_comment_reply`, `ia_stream_video_rate`, `ia_stream_comment_delete`) now call `IA_PeerTube_Token_Helper::get_token_for_current_user()` rather than the older best-effort auth service.
- The debug trace for a failed comment attempt showed the token plugin trying a refresh for phpBB user `347`, receiving PeerTube `invalid_grant` because the refresh token had expired, then logging `Password not available in this request`, which means the request had no captured plaintext password available for a fallback re-mint.
- That combination matches a stale per-user token row plus a non-login write request.

Structural finding

- Stream still ships an older auth helper in `includes/services/auth.php` that reads the legacy table `wp_ia_peertube_tokens`.
- Stream write handlers do **not** use that helper anymore; they use the newer per-user token helper from `ia-peertube-token-mint-users`, which reads `wp_ia_peertube_user_tokens`.
- The database in this stack therefore contains two live PeerTube token stores with different schemas and different freshness semantics:
  - `wp_ia_peertube_tokens` = legacy auth/login token store (`expires_at_utc`, `token_source`).
  - `wp_ia_peertube_user_tokens` = Stream/user-action token store (`expires_at`, `last_refresh_at`, `last_mint_at`, `last_mint_error`).
- For phpBB user `347`, the uploaded SQL shows a fresh row in the legacy table dated 2026-04-04, but an older row in the per-user table dated January 2026. That means login can look healthy while Stream still uses an expired user-action token row.

Root-cause conclusion

- The immediate cause of the Stream 401 is not the comment UI itself. It is token-path divergence plus stale per-user token state.
- The more specific runtime bug is in `ia-peertube-token-mint-users/includes/class-token-helper.php`: after refresh fails with `invalid_grant`, the helper tries fallback mint, but if mint also fails because no password was captured for this request, it deliberately falls through and returns the old stored access token anyway.
- Stream then sends that stale bearer to PeerTube, which produces the user-visible 401 during comment/rate actions.

Pithy summary: login success can coexist with Stream 401 because the stack has two token stores and Stream is using the older expired one from the per-user path.

No code change applied in this note pass.


## 2026-04-04 patch: helper now bubbles recoverable missing-token state

- Stream write actions still call `IA_PeerTube_Token_Helper::get_token_for_current_user()` exactly as before.
- The difference is downstream of `invalid_grant`: the helper now returns `null` after fallback mint failure instead of reusing the stale bearer from `wp_ia_peertube_user_tokens`.
- Result: comment/rate/delete paths can now keep using the existing `missing_user_token` handling so the UI can trigger the one-time password mint flow instead of producing a PeerTube 401 with an expired bearer.


## 2026-04-04 production confirmation: password prompt recovery path works

Live confirmation after the patch:

- A user with stale/expired per-user PeerTube token state attempted to comment in Stream.
- Stream prompted for the password instead of surfacing a PeerTube 401.
- After entering the password, the comment posted successfully.
- The same user was then able to submit a Stream video rating successfully.

Operational conclusion:

- The `missing_user_token` recovery path is now functioning as intended for Stream write actions.
- The stale-bearer fallthrough is no longer blocking comment/rate actions.
- For this issue, the fix is validated in production by successful write operations, not just by absence of an error.

Follow-up log note:

- The subsequent debug capture no longer showed the earlier `IA_PT_TOKEN_TRACE ... refresh_grant.fail ... invalid_grant` plus stale-token behavioural failure that produced the visible 401.
- The dominant remaining noise in the later debug files is now narrowed to repeated WordPress core `open_basedir` warnings from `file_exists(.../wp-content/db.php)` in `wp-includes/load.php`. The earlier `ia-server-diagnostics` sampler-path warning spam was resolved separately. These warnings remain separate hygiene/configuration issues, not the Stream comment/rate auth-path failure.



## 2026-04-04 patch: canonical Stream token read path + structured helper status

Patch-only hardening applied after production confirmation.

What changed

- Stream write paths were updated to prefer `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` when available.
- This makes `wp_ia_peertube_user_tokens` the explicit canonical read path for Stream write actions.
- Stream no longer re-reads `last_mint_error` by hand from SQL just to infer what happened.
- The helper now returns a small status contract instead of forcing callers to infer meaning from `null`.

Current status codes used by Stream callers

- `valid_token`
- `password_required`
- `mint_failed`
- `identity_missing`
- `not_logged_in`
- `token_helper_exception`

Operational effect

- `ia_stream_comment_create`, `ia_stream_comment_reply`, `ia_stream_video_rate`, `ia_stream_comment_delete`, and the prompted mint endpoint now all receive the same canonical reason code surface from the helper.
- This reduces the chance of “login looks fine, Stream is stale” reappearing through a different branch inside Stream itself.
- The older `includes/services/auth.php` path remains present for compatibility, but when the newer helper is loaded it now defers to that helper first instead of independently preferring the legacy login-era token table.

Scope kept deliberately narrow

- no schema changes
- no new endpoints
- no front-end contract rewrite
- no token-store consolidation yet


## 2026-04-04 hold point before further auth changes

- After the diagnostics-plugin cleanup was deployed, the remaining repeated log pollution narrowed to the WordPress core `db.php` probe warning only.
- Based on that narrower signal, no further auth-plugin changes are planned until the stack owner inspects the active WordPress path constants and PHP restriction scope together.
- Required inspection items preserved in notes: `WP_CONTENT_DIR`, any custom `ABSPATH` / content-dir definitions, and the effective `open_basedir` scope applied to the site.
- Token-store consolidation remains the next structural phase, but only after the remaining `db.php` warning is either removed or explicitly documented as benign in this hosting layout.


## 2026-04-04 patch: Stream legacy token table no longer authoritative

- `includes/services/auth.php` no longer falls back to `wp_ia_peertube_tokens` when resolving a current-user bearer for Stream.
- Stream now reads only through `IA_PeerTube_Token_Helper::get_token_status_for_current_user()`.
- The old login-era token table remains in the stack for compatibility and observation, but not as live Stream authority.

Operational effect

- Stream comment/rate/delete write paths cannot silently look healthy because of a fresh login token in the legacy table while the per-user table is stale.
- If the helper is unavailable or the canonical per-user path cannot supply a usable token, Stream now fails closed and keeps the existing recovery flow.
