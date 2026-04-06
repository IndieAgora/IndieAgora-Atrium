- 2026-04-06 reply-post follow-up: PeerTube reply writes in Stream must target the thread id from `/comment-threads` list payloads, not the root comment id from the rendered node. The prior hotfix separated clicked-node id from write target correctly for UI placement, but still used the wrong write target. Token trace in debug during reproduction showed canonical token + refreshed token ready, so this follow-up keeps auth flow intact and only swaps reply write targeting to the thread id contract.
## 2026-04-06 style ownership hardening
- Connect remains the source of truth for the selected user style.
- Atrium owns shared shell chrome and panel framing.
- Stream owns Stream-local repainting. For the approved Black style, Stream now mirrors the active Connect style onto `#ia-stream-shell[data-ia-stream-theme]` so its own cards/tabs/meta/buttons can restyle reliably even through SPA-style tab transitions.
- Do not move Stream-local black overrides into Atrium. Keep the bridge narrow and plugin-owned.

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


## 2026-04-04 Stream token recovery follow-up: `password_required` is recoverable, not fatal

Production retest after token-authority consolidation exposed a different remaining UX bug.

Observed behaviour:
- Some users could still hit a Stream modal error containing the raw internal string:
  `password_required: Password not available in this request...`
- This happened after a valid logged-in session attempted a Stream write action with an expired or invalid PeerTube refresh token.
- The backend trace showed the expected sequence:
  1. canonical per-user token row was found
  2. refresh grant was attempted
  3. PeerTube returned `invalid_grant`
  4. lazy fallback mint attempted
  5. fallback mint returned `password_required` because the current request did not include a captured plaintext password

Important conclusion:
- This was **not** a regression to the old stale-token fallthrough.
- This was **not** a user-specific hardcoded fix for `atrium`.
- It was a frontend recovery-gap: Stream UI only treated `missing_user_token` as promptable, but did not treat `password_required` as the same recoverable state.
- In this stack, `password_required` means "ask the user for their password now and call `ia_stream_pt_mint_token`", not "fatal error".

Patch applied:
- `assets/js/ia-stream.ui.comments.js`
- `assets/js/ia-stream.ui.video.js`
- `assets/js/ia-stream.ui.feed.js`

Behavioural change:
- Stream now treats both `missing_user_token` and `password_required` as recoverable token states.
- For either code, the existing password prompt/mint flow is triggered.
- This preserves the canonical ladder already in place:
  - user attempts Stream write
  - token helper returns no usable token
  - UI prompts once for password
  - `ia_stream_pt_mint_token` captures password for the request
  - canonical token helper mints/stores into `wp_ia_peertube_user_tokens`
  - original write action is retried

Why this is correct:
- The backend already had the right recovery endpoint.
- The remaining fault was that some write surfaces surfaced the internal token-helper reason directly instead of sending the user through the existing recovery modal.
- This patch is patch-only and does not change token ownership, login ownership, or database schema.

Comprehensive debug guide for this Stream auth series

1. **Original 401 on comment/rate**
   - Symptom: Stream write actions returned 401.
   - Root cause: helper returned stale stored bearer after refresh `invalid_grant` and fallback mint failure.
   - Fix: stop returning stale token; bubble recoverable no-token state instead.

2. **Prompted password mint works**
   - Symptom: after patch, comment/rate succeeded when password was entered.
   - Interpretation: fallback mint path and Stream retry flow were fundamentally correct.

3. **Split token authority found**
   - Symptom: login could look healthy while Stream still failed.
   - Root cause: legacy `wp_ia_peertube_tokens` and canonical `wp_ia_peertube_user_tokens` coexisted with different semantics.
   - Fix: Stream made `wp_ia_peertube_user_tokens` sole authority; legacy table left as one-time adoption source only.

4. **Structured status codes added**
   - Need: remove guesswork around null tokens.
   - Resulting codes included `valid_token`, `password_required`, `mint_failed`, `identity_missing`, `not_logged_in`, `token_helper_exception`.

5. **`ia-server-diagnostics` open_basedir spam**
   - Symptom: debug logs flooded by diagnostics path checks.
   - Fix: plugin now skips auto-import checks when target path is outside `open_basedir`.
   - Status: fixed.

6. **Remaining WordPress `db.php` open_basedir warning**
   - Investigation proved runtime values were internally consistent:
     - `open_basedir=/var/www/vhosts/indieagora.com/httpdocs:/tmp:/tmp`
     - `ABSPATH=/var/www/vhosts/indieagora.com/httpdocs/`
     - `WP_CONTENT_DIR=/var/www/vhosts/indieagora.com/httpdocs/wp-content`
     - `realpath_WP_CONTENT_DIR=/var/www/vhosts/indieagora.com/httpdocs/wp-content`
   - Conclusion: treat as separate benign infrastructure/runtime quirk, not a Stream blocker.

7. **Post-consolidation `password_required` popup**
   - Symptom: raw internal `password_required` text surfaced to user for some accounts.
   - Root cause: UI only recognized `missing_user_token` as recoverable and did not prompt on `password_required`.
   - Fix: UI now treats both as recoverable and uses the existing password prompt/mint ladder.

Operational takeaway:
- `password_required` during a Stream write should now be interpreted as a normal recovery path, not a terminal error.
- If this appears again in logs without the UI prompt appearing, debug the frontend handler for that specific write surface before touching token storage again.


## 2026-04-04 follow-up: active Stream comment JS still missed recoverable password flow

Production retest after the first `password_required` UI patch showed a remaining gap in the active comment-send surface.

Observed behaviour
- Clicking **Send** on the Stream comment composer returned HTTP 200 from `admin-ajax.php`, but no password modal appeared.
- The UI looked inert even though the request completed.
- Debug still showed the backend reaching refresh failure and then returning the recoverable `password_required` state.

Root cause
- The earlier UI patch did not fully cover the exact active JS surface in production.
- `ia-stream.ui.comments.js` and `ia-stream.ui.feed.js` were calling `isRecoverableTokenState(...)` without a local definition in those files, so the recovery ladder could silently fail in the browser when that branch executed.
- `ia-stream.ui.video.js` still had one narrow branch that only handled `missing_user_token` instead of all recoverable token states.
- The frontend was also relying too heavily on a top-level `res.code` shape instead of normalizing common nested AJAX response forms.

Patch applied
- Added explicit recoverable-token helpers to:
  - `assets/js/ia-stream.ui.comments.js`
  - `assets/js/ia-stream.ui.feed.js`
  - `assets/js/ia-stream.ui.video.js`
- Expanded recovery detection to recognize both:
  - `missing_user_token`
  - `password_required`
- Broadened detection across common response shapes:
  - top-level `code`
  - top-level `error`
  - top-level `message`
  - nested `data.code`
  - nested `data.error`
  - nested `error.code`
  - nested `error.message`
- Normalized AJAX responses in `assets/js/ia-stream.api.js` so Stream write surfaces receive a flatter and more consistent error contract before UI handling.
- Updated the remaining narrow video-rating branch to use the same recoverable-token helper instead of checking only `missing_user_token`.

Result intended
- If a Stream write hits invalid/expired refresh and the backend returns `password_required`, the active UI should now treat it the same as `missing_user_token`:
  1. open password modal
  2. call `ia_stream_pt_mint_token`
  3. retry the original write action

Debug guide update
- If Stream appears inert after clicking **Send** but network shows HTTP 200, inspect the browser console first for JS branch errors before changing token storage again.
- If backend logs show `refresh_grant.fail` followed by `Password not available in this request`, and no `ia-pt-mint.password_grant.begin` appears afterward, the frontend recovery ladder did not complete.
- If `ia-pt-mint.password_grant.begin` appears, the password modal and mint call did fire, and any remaining problem is later in the retry chain.


## 2026-04-04 production confirmation: comment prompt recovery now validated for admin and non-admin users

Live confirmation after the frontend recovery normalization patch:

- An admin user with invalid/expired Stream token state was prompted for password and then posted a comment successfully.
- A non-admin user with the same invalid/expired recovery shape was also prompted for password and then posted a comment successfully.
- This confirms the recoverable token ladder now works across at least two different account classes and is not specific to one user identity.

Operational reading:

- `password_required` is now being treated as a promptable state in the active Stream comment UI path.
- The visible behaviour now matches the intended backend contract: refresh fails, prompt, mint, retry, success.
- The remaining repeated `open_basedir` `db.php` warning remains unrelated infrastructure noise and is not part of the Stream comment recovery chain.

Remaining verification still worth doing

- Explicitly retest Stream reply creation after prompt recovery.
- Explicitly retest Stream video rating after prompt recovery in the same build, even though earlier rating recovery had already succeeded in the prior stale-token fix cycle.
- Keep watching for any users who still require one-time legacy token adoption, so the legacy table can be reduced further from compatibility to observation only.


## 2026-04-04 hardening pass: reply/video recovery verified, legacy token store reduced, prompt recovery traces expanded

Live verification added after the comment recovery patch:

- admin and non-admin users successfully posted comments after the password prompt
- reply creation was also confirmed working through the same recovery ladder
- comment rating and video rating were confirmed working after prompt recovery as well

Hardening change applied

- Stream prompt recovery logging was expanded around `ia_stream_pt_mint_token` so trace logs now show:
  - `ia-stream.prompt_recovery.begin`
  - `ia-stream.prompt_recovery.ok`
  - `ia-stream.prompt_recovery.fail`
  - `ia-stream.prompt_recovery.exception`

Legacy-store position update

- The legacy login-era PeerTube token table is no longer used for compatibility import into the canonical store during Stream token resolution.
- It is now observation-only.
- Stream operational authority remains the canonical per-user table `wp_ia_peertube_user_tokens`.

Debug reading guide

- If a request shows `ia-pt-token-helper.canonical_miss` plus `ia-pt-token-helper.legacy_row_observed`, the user still has historical token residue but Stream will no longer silently import it.
- If prompt recovery succeeds, expect `ia-stream.prompt_recovery.begin` followed by `ia-stream.prompt_recovery.ok` and a later successful retry of the original write action.
- If refresh fails and mint cannot proceed, expect one of the helper fail traces rather than a silent fallback to legacy storage.


## 2026-04-04 UI facelift patch: Discover/Browse shell, PeerTube-style browse tools, responsive cleanup

User request captured in this note chain

- Stream no longer feels right visually compared with the current PeerTube browse experience.
- Replace the old top-level Stream tabs (`Feed`, `Channels`) with `Discover` and `Browse videos`.
- Make Stream feel closer to PeerTube on both mobile and desktop, while still living inside the Atrium shell.
- Add Stream-local search and sort controls that behave like the other Atrium surfaces: the controls are available when the user is in Stream and the browse state persists in local storage.
- User explicitly wanted search coverage for categories, tags, video names, users, and comments.
- Keep the work tidy and add the discussion itself to the notes.

Patch shape

- `includes/modules/panel.php`
  - Replaced the old two-panel shell with:
    - `Discover` panel
    - `Browse videos` panel
  - Added a Stream toolbar containing:
    - search input
    - search scope selector (`Everything`, `Video names`, `Users`, `Tags`, `Categories`, `Comments`)
    - sort selector (`Recently added`, `Most viewed`, `Most liked`, `A–Z`)
- `assets/js/ia-stream.ui.shell.js`
  - Stores/restores Stream tab, search query, scope, and sort.
  - Toolbar is only shown on the `Browse videos` tab.
  - Search submit now dispatches a Stream-local browse refresh instead of relying on a separate top-nav search implementation.
- `assets/js/ia-stream.ui.feed.js`
  - Split UI behaviour into:
    - Discover strips (`Recently added`, `Trending now`)
    - Browse results list with load-more pagination
    - optional matching channels block
    - optional comment-match block
  - Browse search applies server-side video/channel search where available and then narrows the current result set client-side by scope.
  - Comment search is implemented as a bounded compatibility pass over the first page of comment threads for the first few matched videos. This keeps the patch safe and avoids inventing unsupported endpoints.
- `assets/js/ia-stream.ui.channels.js`
  - Channels are now rendered into the Discover surface and can also be reused in browse-side match blocks.
- `includes/modules/feed.php`
  - Feed query now accepts `search` and `sort`.
- `includes/support/ajax.php`
  - `ia_stream_feed` now forwards `search` and `sort` through to the feed module.
- `includes/render/media.php`
  - Video normalization now preserves `category` and `tags` when PeerTube returns them so browse search can match against those fields.
- CSS (`assets/css/ia-stream.base.css`, `layout.css`, `cards.css`, `channels.css`)
  - Reworked Stream into a darker PeerTube-adjacent panel style with:
    - sticky header
    - rounded segmented tabs
    - search/sort toolbar
    - thumbnail-first video cards
    - responsive discover grids
    - cleaner channel cards
    - mobile-first spacing that expands cleanly on desktop

Behaviour notes / limits

- Search now supports these practical layers:
  - video names / excerpts / support text
  - users/channels by display name or handle
  - categories from normalized PeerTube payloads
  - tags from normalized PeerTube payloads
  - comments via bounded recent-thread scanning on the currently returned browse set
- The comments search is intentionally conservative in this patch. It does **not** claim to be an exhaustive instance-wide full-text comment index. It is a compatibility layer built on the endpoints already present in this stack.
- Existing Stream modal, comment posting, reply, rating, delete, token prompt recovery, and share/copy actions were preserved.

Operator-facing summary

- Stream now opens as a Discover/Browse experience instead of Feed/Channels.
- Discover gives the user a cleaner landing surface.
- Browse gives the user the missing search/sort affordances the user asked for.
- The implementation stays within the confirmed plugin/API surface and does not invent a new backend search schema.


## 2026-04-04 patch: Stream search moved into the Atrium topbar

- This pass removes the separate in-panel Stream search bar and uses the existing Atrium topbar search surface when Stream is the active tab.
- Stream topbar suggestions are now grouped by type while typing: videos, channels, users, tags/categories, and comments.
- Pressing Enter from Atrium topbar search while Stream is active now opens a Stream search view inside the Stream panel rather than a separate internal search control.
- Stream search routing uses URL state (`stream_view`, `stream_q`, `stream_scope`, `stream_sort`) so SPA tab changes and refreshes can reopen the same Stream search view.
- Opening Stream without Stream search URL state now falls back to Discover / Recently added rather than preserving the previous in-panel browse state.
- User request captured here: the earlier separate search bar inside Stream was rejected; search should live in Atrium's shared search bar, show suggestions as the user types, segregate responses by type, open a full Stream search page on Enter, and default Stream to Recently added on entry.
- The implementation keeps to the confirmed Stream AJAX surface already present in the stack (`ia_stream_feed`, `ia_stream_channels`, `ia_stream_comments`) and derives broader user/tag/category groupings from returned video and comment metadata instead of inventing new backend directory endpoints.


## 2026-04-04 follow-up patch: Stream search result sizing + reply-aware search

User follow-up after the Atrium-topbar Stream search integration:
- Search results inside Stream should not render as oversized single-column cards when browsing/searching.
- The neat Discover card sizing is the expected presentation.
- Stream search also needs to include comments and replies, not just top-level comments.

Patch applied:
- `ia-stream/includes/modules/panel.php`
  - Browse/search result mount now reuses the same grid card class as Discover (`ia-stream-feed--grid`) so search results stay visually bounded on desktop and mobile instead of expanding into oversized single-card rows.
- `ia-stream/assets/js/ia-stream.ui.feed.js`
  - Stream full search comment matching now walks reply trees via the existing `ia_stream_comment_thread` endpoint.
  - Matches now include both top-level comments and nested replies, labelled conservatively in the UI.
- `ia-atrium/assets/js/atrium.js`
  - Shared Atrium topbar suggestions for Stream now also inspect reply trees, so suggestions can surface reply text/authors as well as top-level comments.

Boundary preserved:
- This is still built only on the confirmed Stream AJAX surface already present in the stack.
- No new backend-wide full-text index, no invented user directory, and no new comment search endpoint were added.

## 2026-04-04 follow-up patch: separate Stream search-results tab + comment/reply jump highlighting

User follow-up after the earlier Stream search work:
- Search should not permanently take over Browse videos.
- Stream needs an explicit in-panel Search results tab so users can leave search state cleanly and return to Browse or Discover.
- Clicking a comment/reply result should open the target video and highlight the matched comment/reply inside the modal.
- PeerTube search results page remains the visual reference, but the implementation must stay within the existing confirmed stack surface.

Patch applied:
- `ia-stream/includes/modules/panel.php`
  - Added a third Stream tab: `Search results`.
  - Kept `Discover` and `Browse videos` intact.
  - Browse now remains a normal recently-added video surface; search results render in their own panel.
- `ia-stream/assets/js/ia-stream.state.js`
  - URL search state now lands Stream on the `search` tab instead of hijacking `browse`.
- `ia-stream/assets/js/ia-stream.ui.shell.js`
  - The `Search results` tab is shown only when a Stream query exists.
  - Users can leave search state cleanly by switching back to Browse or Discover.
- `ia-stream/assets/js/ia-stream.ui.feed.js`
  - Split Browse and Search rendering so Browse stays on recent videos while Search owns query-driven results.
  - Search extras continue to surface channels, users, tags/categories, and comments/replies, but only inside the Search results panel.
  - Comment/reply rows in Stream search now carry the matched comment identifier and open the target video modal directly.
- `ia-stream/assets/js/ia-stream.ui.video.js`
  - Video-modal open path now accepts a requested comment highlight target.
- `ia-stream/assets/js/ia-stream.ui.comments.js`
  - Comment load now highlights and scrolls to a requested top-level comment or nested reply when opened from search results.

Boundary preserved:
- No invented backend-wide search endpoint was added.
- Comment/reply targeting still uses existing Stream endpoints and comment-thread hydration already present in the stack.


## 2026-04-04 follow-up patch: Stream search-result navigation + per-entity URLs

User-reported live issues after the search-tab/comment-highlight patch:

- Clicking a comment result in Stream search did not open the video modal at the targeted comment/reply. It fell back into the Stream search feed.
- User wants distinct Atrium URLs for Stream videos, comments, and replies.
- User also wants Stream search result behaviour to follow the PeerTube pattern more closely: search results remain a separate surface, but comment/reply hits must deep-link into the exact discussion target.

Patch applied:

- Fixed Atrium top-search delegated Stream navigation so Stream result items now carry `streamVideo`, `streamComment`, and `streamReply` through the existing `goToStreamSearch(...)` path instead of dropping the entity target and reopening only the generic search feed.
- Stream comment search results now distinguish top-level comments vs replies:
  - top-level comment hits carry `stream_comment`
  - reply hits carry `stream_reply`
- Stream modal open now keeps the current Atrium URL in sync:
  - opening a video writes `?tab=stream&video=<id>`
  - highlighting a comment writes `stream_comment=<comment_id>`
  - highlighting a reply writes `stream_reply=<reply_id>` and preserves `stream_comment` when known
- Closing the Stream modal clears the video/comment/reply route state while preserving the surrounding Atrium shell route.
- Comment permalink copy now uses Atrium deep links rather than only hash-based local fragments, so copied comment/reply links reopen Stream in the expected state.

Files changed in this patch:

- `ia-atrium/assets/js/atrium.js`
- `ia-stream/assets/js/ia-stream.ui.feed.js`
- `ia-stream/assets/js/ia-stream.ui.video.js`
- `ia-stream/assets/js/ia-stream.ui.comments.js`

Boundary preserved:

- No new backend endpoint was introduced.
- No instance-wide invented search index was added.
- This remains a patch-only routing/navigation fix on top of the existing Stream/Atrium AJAX surface.


## 2026-04-04 discover expansion, subscriptions feed, channel deep links

- Discover needed a way to see more than the initial cards.
- Added Discover section expansion controls so Recently added and Trending can load additional cards in place instead of stopping at the first strip.
- Removed the extra hero sentence under Discover.
- Added a dedicated `Subscriptions` tab in Stream.
- Subscriptions feed now requests the logged-in user subscription video feed using the current user token when available.
- Added internal Stream deep links for channels. Channel cards and channel labels now reopen Stream on a channel-specific browse view using `stream_channel` and `stream_channel_name`.
- Channel browse uses the PeerTube channel-videos API path rather than an invented local route.
- Request context from this discussion: Discover needed to show more videos, the extra marketing sentence should be removed, subscriptions should reflect the logged-in user, and each channel should have its own deep link.


## 2026-04-04 Feed loading hardening
- Fixed `ia_stream_feed` AJAX request parsing so Stream now forwards `mode` and `channel_handle` to the backend instead of silently dropping them. This repairs subscriptions and channel-browse requests from the frontend.
- Hardened Discover, Browse, and Subscriptions loaders so failed requests now resolve to a visible placeholder/error state instead of leaving sections stranded on indefinite `Loading…`.
- This patch follows live testing where Discover, Browse videos, Subscriptions, and Search results were intermittently staying on `Loading…` or not filling reliably after the subscriptions/channel/deeplink facelift work.
- During the same test window the provided debug log showed repeated WordPress `open_basedir` warnings around `wp-content/db.php`. That log noise is real and worth fixing separately, but it was not used here to invent unrelated Stream behavior changes.


## April 4, 2026 — IA Post Stream upload modal

- Added Stream as a first-class destination inside the global Atrium composer so ia-post can initiate PeerTube video uploads.
- New ia-post AJAX endpoints: `ia_post_stream_bootstrap` and `ia_post_stream_upload`.
- Bootstrap loads the current user's PeerTube account context, owned/collaborator channels, account playlists, and upload dictionaries for categories, licences, languages, and privacies.
- Upload flow is patch-only and keeps the existing token contract: ia-post resolves the current user token through `IA_PeerTube_Token_Helper` and does not introduce a parallel auth path.
- Upload UI now opens a modal after file selection. The modal allows channel selection, title, description, tags, privacy, playlist assignment, comments policy, category, licence, language, sensitive-content flags, support text, optional thumbnail, optional password, and upload-progress display.
- The browser progress bar currently tracks the browser-to-WordPress upload leg and then transitions to finalization while WordPress forwards the file to PeerTube. No direct browser-to-PeerTube token exposure was added in this patch.
- On success the modal offers open-in-Stream and open-on-PeerTube actions using the returned uploaded video identifiers.
- Discussion captured from this request: the user asked for ia-post video uploading now that user-token issues are sorted, with channel selection, tags, description, privacy/sensitive-content settings, playlist assignment, and a diverted modal that shows progress while allowing those settings to be edited.


## 2026-04-05 subscriptions helper-normalization patch

Patch-only consistency cleanup:

- The subscriptions feed path in `includes/modules/feed.php` now prefers `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` before falling back to the older token getter.
- This keeps Stream subscription browse aligned with the same canonical per-user status contract already used by Stream write flows.
- If helper resolution fails, subscriptions simply behave as unauthenticated; no new fallback token store was introduced.


## 2026-04-05 live stability confirmation

User-reported post-deploy test result:

- Stream works
- read flows work
- comment / reply / rating flows work

Interpretation for this plugin:

- the existing Stream recovery path remains intact
- the subscriptions helper-normalization patch did not introduce a visible regression
- Stream is currently behaving as intended against the canonical per-user token helper for active tested users


## 2026-04-05 route-title pass + deep-link map

User request captured here:
- browser titles should reflect the exact surface being viewed so the later SEO pass can reuse the same route semantics
- examples requested: Connect profile/post titles, Discuss topic/reply/agora titles, Stream discover/search/subscriptions/video titles
- a dedicated deep-link notes file is needed for later SEO work

Patch applied in this stack pass:
- `ia-stream/includes/functions.php`
  - Added server-side Stream document-title resolution and matching `og:title` / `twitter:title` output for direct Stream routes.
  - Server-side routes now resolve titles from confirmed query state only: `video`, `stream_q`, `stream_subscriptions`, `stream_channel`, `stream_channel_name`.
  - Video routes use the existing `IA_Stream_Module_Video::get_video(...)` path rather than inventing a separate title lookup.
- `ia-stream/includes/ia-stream.php`
  - Boot now registers the Stream meta/title hooks.
- `ia-stream/assets/js/ia-stream.ui.feed.js`
  - Added client-side title syncing for Stream SPA navigation so Discover, Browse videos, channel browse, Subscriptions, and Search update the browser title during in-panel route changes.
- `ia-stream/assets/js/ia-stream.ui.video.js`
  - Opening a Stream video now promotes the active video title into `document.title`; closing the modal restores the underlying Stream surface title.
- `DEEP-LINKS.md`
  - Added a stack-level route map for Connect / Discuss / Stream deep links, intended as input to later SEO work.

Boundary preserved:
- patch-only route/title work; no tab/path redesign
- no invented backend endpoint or schema
- Stream title resolution only uses existing route state and existing video lookup surfaces already present in the stack

## 2026-04-06 Stream Black style bridge

- Added `assets/css/ia-stream.theme.black.css`.
- The file is gated by the existing stack-wide Connect style attribute (`html/body/#ia-atrium-shell[data-iac-style="black"]`) and only styles Stream-owned internals.
- Ownership split remains unchanged:
  - Atrium owns shell chrome/background.
  - IA Stream owns Stream tabs, cards, feeds, channels, search rows, and modal/comment internals.
- The Black bridge replaces Stream's older dark-gradient internals with the approved light-surface / dark-text treatment already established elsewhere in the stack, while keeping video/player/backdrop surfaces dark where that remains functionally appropriate.
- 2026-04-06 black Stream follow-up: in video modal view, channel/meta text and SVG action icons were still too faint against the light-black surface, and comment/reply cards needed the same alternating fill rhythm used in Connect. Patch keeps the player surface dark but raises modal meta/icon contrast and alternates threaded comment card fills for readability.
- 2026-04-06 Stream tab default follow-up: entering Stream from another Atrium tab must land on Discover unless the current URL explicitly owns a Stream search route (`stream_q` / `stream_view=search`). Stored search text alone must not force the Search results tab on entry.

- 2026-04-06 0.1.11: Black Stream follow-up. Forced readable card/video meta + SVG/icon contrast for channel/count rows, and moved comment/reply alternation to a JS-applied class because live reply markup is a flat `.ia-stream-comment` sequence rather than a consistently wrapped nth-child structure.
## 2026-04-06 Black style architecture reference
- Preferred style architecture for Stream is now explicit:
  - Connect = preference owner.
  - Atrium = shared shell/chrome/background owner.
  - Stream = Stream-internal repaint owner.
- Stream mirrors the resolved shell style onto `#ia-stream-shell[data-ia-stream-theme]` so repainting can survive SPA transitions, delayed tab swaps, and hidden-panel lifecycle quirks.
- The mirrored marker is deliberately local and declarative. It exists to let Stream CSS target its own surfaces without depending on broad global selectors or guessing which outer shell node will still be present.
- `assets/js/ia-stream.ui.shell.js` is the bridge point for theme synchronization. It listens for the Connect bridge event and mutation changes on the existing shell style attributes. Keep future theme-sync logic there instead of scattering style detection across other UI modules.
- `assets/css/ia-stream.theme.black.css` is the override layer for Black mode. Keep it late-loaded and colour-focused. Avoid moving baseline layout or behaviour into that file.
- Theme implementation by surface role:
  - Feed/list cards: lightened body surface, dark text, readable meta, darkened action icons.
  - Stream tab strip/section chrome: adjusted to sit inside the black shell without keeping the old fully black content treatment.
  - Video detail / modal side surfaces: separate contrast rules because these sit next to a dark player.
  - Comment composer/comments/replies: light surfaces with darker text and alternating reply rhythm for scanability.
- Where selector specificity was fighting the historical Stream skin, late bridge selectors were strengthened rather than renaming classes or rebuilding templates. This stays aligned with patch-only constraints.
- Where structural CSS was unreliable, JS adds narrow semantic classes and CSS consumes them. Current example: comment/reply alternation. This is preferred over changing render markup when the request is purely presentation.
- Guardrail for future work: do not push shared shell colours back into Stream stylesheets. If a change touches top nav, bottom nav, page background, or other shell-level surfaces, it belongs in Atrium notes/code, not Stream.
- Guardrail for future work: do not create per-view one-off theme branches if the same result can be reached by mapping the surface type (card/meta/control/modal/player-adjacent). That note exists because the successful Black pass came from surface-role mapping, not from route-by-route ad hoc fixes.
- 2026-04-06 hotfix v8: restored the Stream inline reply button after v7 accidentally left `openInlineReply()` referencing removed `threadRootId` state. The regression was client-side only: the click path aborted before opening the inline reply box or issuing any PeerTube request. Keep the current reply write target logic unchanged; this patch only repairs the inline reply opener.

- 2026-04-06: Stream comment UX hotfix: reply and delete actions are now reflected immediately in the modal DOM, then a delayed comment reload re-syncs with PeerTube. This avoids the user having to hard refresh after a successful reply/delete while still tolerating PeerTube/WP-AJAX read-after-write lag. Keep this patch-only; do not replace it with blind immediate reload-only behaviour unless the backend read path is proven strongly consistent.
