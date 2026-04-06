- 2026-04-06 follow-up: Connect feed cards now use a roomier card rhythm (more top/body padding and taller text line-height) and non-embeddable URLs now render as lightweight in-theme link cards under the post body. Kept scope inside `assets/js/ia-connect.js` and `assets/css/ia-connect.css`; no posting, sharing, or delete flows changed.
- 2026-04-06 hotfix: Connect delete-confirm modal now has explicit imported-light-style styling in `assets/css/ia-connect.fb.css` so post/comment delete prompts stay readable in the lighter MyBB-derived themes instead of inheriting the dark default confirmation sheet. Scope stayed CSS-only; no delete logic or modal behaviour changed.
- 2026-04-06 hotfix: Connect mention suggestion popup now mirrors the active `data-iac-style` onto the body-level popup element itself, so the light MyBB-derived styles (`calm`, `dawn`, `earth`, `flame`, `leaf`, `night`, `sun`, `twilight`, `water`) render the suggestions with the same light surfaces / readable text instead of falling back to the dark default popup styling.
- 2026-04-06 hotfix: Connect mention suggestions now merge canonical phpBB username matches with WP shadow-user display-name matches instead of only falling back to WP when phpBB returns nothing. Results are scored so exact and prefix matches on `username` / `display_name` surface first, improving `@mention` suggestions when typing a full display name.
- 2026-04-06 hotfix: posting with `@mentions` in Connect no longer fatals in the post-created notification hook. Root cause was an undefined `$comment_id` being forwarded from `ia_connect_notify_on_post_created()` into `ia_connect_create_mention_posts()`. Post mentions now pass `0` for the non-comment case so mention wall copies and emails still work without breaking post creation.
- 2026-04-06 hotfix: `ia_connect_mention_suggest()` fallback search no longer references undefined `$wp_id` / `$phpbb_id` variables while deduplicating WP shadow-user results. Keep fallback dedupe keyed from the actual fallback `$uid` / `$phpbb` values only.
- 2026-04-05 patch: self Stream tabs that require authenticated PeerTube reads now resolve the bearer through `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` first, matching the canonical token authority used by `ia-stream`. Legacy `IA_Auth` token storage remains fallback-only.
- `Likes` now merges video likes with locally stored Stream comment likes for the profile owner, because comment votes are stored in `wp_ia_stream_comment_votes` rather than exposed by a PeerTube comment-like API route.
- `History` and `Subscriptions` were previously able to appear empty or fall into PostgreSQL fallback/error paths when Connect skipped the canonical per-user token helper. Keep self Stream token resolution aligned with `ia-stream` before changing routes again.
- `ia_connect_stream_activity` now uses PeerTube 8.1 API/feed routes directly for all Stream subtabs and treats PeerTube PostgreSQL as optional support instead of a hard rendering dependency. `Comments` is sourced from `/feeds/video-comments.json?accountName=...`; `Videos` falls back from `/api/v1/users/me/videos` to `/api/v1/accounts/{name}/videos`.
# AJAX handlers for ia-connect

Confirmed AJAX-related locations in this plugin:

- `assets/js`
- `includes/support`

Primary files:

- `includes/support/ajax.php`

Registered `wp_ajax_*` actions found in code:

- `ia_connect_account_deactivate` in `includes/support/ajax.php`
- `ia_connect_account_delete` in `includes/support/ajax.php`
- `ia_connect_comment_create` in `includes/support/ajax.php`
- `ia_connect_comment_delete` in `includes/support/ajax.php`
- `ia_connect_comment_update` in `includes/support/ajax.php`
- `ia_connect_comments_page` in `includes/support/ajax.php`
- `ia_connect_discuss_activity` in `includes/support/ajax.php`
- `ia_connect_display_name_update` in `includes/support/ajax.php`
- `ia_connect_export_data` in `includes/support/ajax.php`
- `ia_connect_home_tab_update` in `includes/support/ajax.php`
- `ia_connect_follow_toggle` in `includes/support/ajax.php`
- `ia_connect_followers_list` in `includes/support/ajax.php`
- `ia_connect_followers_search` in `includes/support/ajax.php`
- `ia_connect_mention_suggest` in `includes/support/ajax.php`
- `ia_connect_post_create` in `includes/support/ajax.php`
- `ia_connect_post_delete` in `includes/support/ajax.php`
- `ia_connect_post_get` in `includes/support/ajax.php`
- `ia_connect_post_list` in `includes/support/ajax.php`
- `ia_connect_post_share` in `includes/support/ajax.php`
- `ia_connect_post_update` in `includes/support/ajax.php`
- `ia_connect_privacy_get` in `includes/support/ajax.php`
- `ia_connect_privacy_update` in `includes/support/ajax.php`
- `ia_connect_settings_update` in `includes/support/ajax.php`
- `ia_connect_signature_update` in `includes/support/ajax.php`
- `ia_connect_stream_activity` in `includes/support/ajax.php`
- `ia_connect_upload_cover` in `includes/support/ajax.php`
- `ia_connect_upload_profile` in `includes/support/ajax.php`
- `ia_connect_user_block_toggle` in `includes/support/ajax.php`
- `ia_connect_user_follow_toggle` in `includes/support/ajax.php`
- `ia_connect_user_rel_status` in `includes/support/ajax.php`
- `ia_connect_user_search` in `includes/support/ajax.php`
- `ia_connect_wall_search` in `includes/support/ajax.php`

Regression guard:

- Search work must stay inside search endpoints and search UI only.
- Do not alter wall body render, URL extraction, URL stripping, or embed-link cleanup code when changing search output.
- The prior wall-vanish regression was tied to render/link cleanup drift, not the search requirement itself.

This file is inventory only. It should be updated whenever AJAX handlers are added, moved, renamed, or removed.
- `ia_connect_stream_activity` now uses PostgreSQL `LIMIT ... OFFSET ...` pagination, backfills missing PeerTube identity ids from existing `wp_ia_identity_map` data before querying profile Stream activity, and generates distinct PDO search placeholders per column so PeerTube PostgreSQL queries do not fail on repeated named bindings.
- On query failure it now writes a narrow PHP error-log entry with the activity type and exception message before returning the existing JSON error response.

- `ia_connect_stream_activity` now mixes PeerTube 8.1 REST endpoints with narrow PostgreSQL enrichment/fallbacks: API-first for videos, likes, subscription videos, playlists, and history; PostgreSQL fallback remains for authored comments and for API-unavailable cases.

- 2026-04-06 spacing follow-up: Connect feed cards now keep extra top padding in `.iac-card-body` so the first text line is not cramped against the header bar. CSS-only spacing change; no render logic changed.

- 2026-04-06 hotfix: increased Connect feed card spacing under the header bar for shared/light-style cards by raising head bottom padding and body top padding so the first text line no longer sits against the blue header.
- 2026-04-06 spacing correction: `ia-connect.fb.css` had still been zeroing the top padding on `.iac-card-body`, which cancelled the earlier Connect header/body buffer change. Feed cards now keep visible top padding below the header bar in the active layout. CSS-only; no render logic changed.
