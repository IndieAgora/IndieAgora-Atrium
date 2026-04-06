- 2026-04-06 follow-up: Connect feed cards now use a roomier card rhythm (more top/body padding and taller text line-height) and non-embeddable URLs now render as lightweight in-theme link cards under the post body. Kept scope inside `assets/js/ia-connect.js` and `assets/css/ia-connect.css`; no posting, sharing, or delete flows changed.
- 2026-04-06 hotfix: Connect delete-confirm modal now has explicit imported-light-style styling in `assets/css/ia-connect.fb.css` so post/comment delete prompts stay readable in the lighter MyBB-derived themes instead of inheriting the dark default confirmation sheet. Scope stayed CSS-only; no delete logic or modal behaviour changed.
- 2026-04-06 hotfix: Connect mention suggestion popup now mirrors the active `data-iac-style` onto the body-level popup element itself, so the light MyBB-derived styles (`calm`, `dawn`, `earth`, `flame`, `leaf`, `night`, `sun`, `twilight`, `water`) render the suggestions with the same light surfaces / readable text instead of falling back to the dark default popup styling.
- 2026-04-06 hotfix: Connect mention suggestions now merge canonical phpBB username matches with WP shadow-user display-name matches instead of only falling back to WP when phpBB returns nothing. Results are scored so exact and prefix matches on `username` / `display_name` surface first, improving `@mention` suggestions when typing a full display name.
- 2026-04-06 hotfix: posting with `@mentions` in Connect no longer fatals in the post-created notification hook. Root cause was an undefined `$comment_id` being forwarded from `ia_connect_notify_on_post_created()` into `ia_connect_create_mention_posts()`. Post mentions now pass `0` for the non-comment case so mention wall copies and emails still work without breaking post creation.
- 2026-04-06 hotfix: `ia_connect_mention_suggest()` fallback search no longer references undefined `$wp_id` / `$phpbb_id` variables while deduplicating WP shadow-user results. Keep fallback dedupe keyed from the actual fallback `$uid` / `$phpbb` values only.
- 2026-04-05 patch: `Subscriptions` was corrected again after live retest. It now uses `/api/v1/users/me/subscriptions` and renders subscribed channels/accounts. Do not switch this tab back to `/subscriptions/videos` unless the UI requirement changes.
- 2026-04-05 patch: self Stream tabs that require authenticated PeerTube reads now resolve the bearer through `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` first, matching the canonical token authority used by `ia-stream`. Legacy `IA_Auth` token storage remains fallback-only.
- `Likes` now merges video likes with locally stored Stream comment likes for the profile owner, because comment votes are stored in `wp_ia_stream_comment_votes` rather than exposed by a PeerTube comment-like API route.
- `History` and `Subscriptions` were previously able to appear empty or fall into PostgreSQL fallback/error paths when Connect skipped the canonical per-user token helper. Keep self Stream token resolution aligned with `ia-stream` before changing routes again.
## 2026-04-05 Connect Stream API-only fallback rule

- Treat PeerTube PDO as optional in `ia_connect_stream_activity()`. If API/feed routes can satisfy the tab, do not hard-fail the whole handler because a PostgreSQL query/enrichment step broke.
- `Comments` now comes from the documented comments feed endpoint using `accountName`; do not switch this back to direct `videoComment` SQL unless explicitly required.
- For `Videos`, prefer `/api/v1/users/me/videos` but fall back to `/api/v1/accounts/{name}/videos` so a stale/missing self token does not blank the tab for the profile owner.

# AJAX notes for ia-connect / includes/support

Files in this directory inspected for AJAX handling:

- `ajax.php`
- `assets.php`
- `notifications.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.


## 2026-04-05 Connect Stream handler guard

- `ia_connect_stream_activity()` talks to PeerTube's PostgreSQL tables directly.
- Do not use MySQL `LIMIT offset,count` paging here. Use PostgreSQL `LIMIT ... OFFSET ...` only.
- Stream activity also depends on complete PeerTube identity ids (`peertube_user_id`, `peertube_account_id`, `peertube_actor_id`). If one is missing, resolve from the others before assuming the user has no activity.
- Keep Stream fixes in this handler/backend layer; do not touch Connect wall rendering for Stream-tab data issues.

- Stream search clauses in this file now build distinct PostgreSQL/PDO placeholders per searched column. Do not reintroduce repeated named placeholders like `:q` across multiple `ILIKE` clauses in a single prepared statement.
- If Stream fails again, inspect PHP error log for `[ia-connect][stream_activity]` entries before widening scope into wall/profile rendering code.

## 2026-04-05 Connect Stream API alignment

- Stream now uses PeerTube 8.1 REST endpoints first for tabs that are actually exposed in the uploaded `openapi(1).json`: owner videos, subscription videos, playlists, history, and account ratings/likes.
- Keep authored comments on the PostgreSQL fallback path unless the stack later adds a confirmed authored-comments API route. The uploaded PeerTube 8.1 spec does not expose one.
- `Subscriptions` now intentionally lists subscribed channels/accounts, not the videos from those subscriptions. Keep this aligned with the user-facing requirement for the Connect profile Stream tab.
- When using API responses for Stream, enrich them with PostgreSQL URL/thumbnail metadata instead of inventing undocumented watch URLs.

- 2026-04-06 spacing follow-up: Connect feed cards now keep extra top padding in `.iac-card-body` so the first text line is not cramped against the header bar. CSS-only spacing change; no render logic changed.

- 2026-04-06 hotfix: increased Connect feed card spacing under the header bar for shared/light-style cards by raising head bottom padding and body top padding so the first text line no longer sits against the blue header.
- 2026-04-06 spacing correction: `ia-connect.fb.css` had still been zeroing the top padding on `.iac-card-body`, which cancelled the earlier Connect header/body buffer change. Feed cards now keep visible top padding below the header bar in the active layout. CSS-only; no render logic changed.
