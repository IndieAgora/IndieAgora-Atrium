- 2026-04-06 frontend wording cleanup follow-up: removed the remaining Connect settings references to legacy platform names in the visible style copy so frontend account/settings text stays platform-neutral.
- 2026-04-06 frontend wording pass: account lifecycle copy and account-settings error messages no longer name backend products. Connect now refers to the Atrium account and generic account mapping/update states only; behaviour is unchanged.
- 2026-04-06 follow-up: Connect feed cards now use a roomier card rhythm (more top/body padding and taller text line-height) and non-embeddable URLs now render as lightweight in-theme link cards under the post body. Kept scope inside `assets/js/ia-connect.js` and `assets/css/ia-connect.css`; no posting, sharing, or delete flows changed.
- 2026-04-06 hotfix: Connect delete-confirm modal now has explicit imported-light-style styling in `assets/css/ia-connect.fb.css` so post/comment delete prompts stay readable in the lighter MyBB-derived themes instead of inheriting the dark default confirmation sheet. Scope stayed CSS-only; no delete logic or modal behaviour changed.
- 2026-04-06 hotfix: Connect mention suggestion popup now mirrors the active `data-iac-style` onto the body-level popup element itself, so the light MyBB-derived styles (`calm`, `dawn`, `earth`, `flame`, `leaf`, `night`, `sun`, `twilight`, `water`) render the suggestions with the same light surfaces / readable text instead of falling back to the dark default popup styling.
- 2026-04-06 hotfix: Connect mention suggestions now merge canonical phpBB username matches with WP shadow-user display-name matches instead of only falling back to WP when phpBB returns nothing. Results are scored so exact and prefix matches on `username` / `display_name` surface first, improving `@mention` suggestions when typing a full display name.
- 2026-04-06 hotfix: posting with `@mentions` in Connect no longer fatals in the post-created notification hook. Root cause was an undefined `$comment_id` being forwarded from `ia_connect_notify_on_post_created()` into `ia_connect_create_mention_posts()`. Post mentions now pass `0` for the non-comment case so mention wall copies and emails still work without breaking post creation.
- 2026-04-06 hotfix: `ia_connect_mention_suggest()` fallback search no longer references undefined `$wp_id` / `$phpbb_id` variables while deduplicating WP shadow-user results. Keep fallback dedupe keyed from the actual fallback `$uid` / `$phpbb` values only.
- 2026-04-06 style repair follow-up: generic imported MyBB styles now mirror the approved Black header behaviour in Connect profile view (no boxed identity block, readable black username/grey bio, clearer Change cover button) and now restyle Connect > Discuss agoras/topics/replies plus nested shared Discuss previews with the same light-surface/dark-text hierarchy used in Black.
- 2026-04-06 style repair: Connect style save/apply now preserves all imported MyBB keys instead of collapsing everything except Black back to Default.
- 2026-04-06 style repair: added a late generic MyBB surface layer for Connect so non-default/non-black schemes now recolour profile shells, cards, card headers, tabs, inputs, and comments from the shared palette vars.
- 2026-04-06 style import reset: Black and Default left untouched. Imported MyBB styles now reuse the approved Black surface map but pull their own shell/card/header colours instead of only recolouring active pills and links.
- 2026-04-05 patch: `Subscriptions` was corrected again after live retest. It now uses `/api/v1/users/me/subscriptions` and renders subscribed channels/accounts. Do not switch this tab back to `/subscriptions/videos` unless the UI requirement changes.
- 2026-04-05 patch: self Stream tabs that require authenticated PeerTube reads now resolve the bearer through `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` first, matching the canonical token authority used by `ia-stream`. Legacy `IA_Auth` token storage remains fallback-only.
- `Likes` now merges video likes with locally stored Stream comment likes for the profile owner, because comment votes are stored in `wp_ia_stream_comment_votes` rather than exposed by a PeerTube comment-like API route.
- `History` and `Subscriptions` were previously able to appear empty or fall into PostgreSQL fallback/error paths when Connect skipped the canonical per-user token helper. Keep self Stream token resolution aligned with `ia-stream` before changing routes again.
## 0.5.30 Connect Stream API-only hardening

- Repaired the Connect `Stream` tab again after live retest showed the previous patch could still hit handler-level 500s and render nothing.
- Root cause: the Stream path was still depending on PeerTube PostgreSQL enrichment/fallback queries for some subtabs, so a bad DB-side assumption could still collapse the AJAX response even when PeerTube's 8.1 REST/feed endpoints were available.
- Stream now fails much softer:
  - `Videos` prefers `/api/v1/users/me/videos` and falls back to `/api/v1/accounts/{name}/videos`.
  - `Comments` now reads authored comment activity from PeerTube's documented comments feed endpoint (`/feeds/video-comments.json?accountName=...`) instead of querying PostgreSQL comment tables.
  - `Likes`, `Subscriptions`, `Playlists`, and `History` now render directly from PeerTube 8.1 API payloads without requiring PostgreSQL thumbnail/url enrichment.
- PeerTube PDO is now optional for Stream rendering. If DB lookup/identity repair is unavailable, API-backed subtabs still render instead of hard failing.
- Keep Connect Stream aligned to confirmed PeerTube API/feed routes first. Do not reintroduce DB-only rendering on tabs that the uploaded 8.1 spec already covers.

## 0.5.29 Connect Stream API alignment

- Reworked the Connect `Stream` tab to use PeerTube 8.1 REST endpoints where the uploaded API spec actually supports the requested data shape.
- `Videos` now prefers `/api/v1/users/me/videos` for the owner and `/api/v1/accounts/{name}/videos` for account-profile fallback.
- `Subscriptions` now returns videos from subscribed accounts via `/api/v1/users/me/subscriptions/videos` instead of incorrectly listing followed actors/channels.
- `Playlists` now prefers `/api/v1/accounts/{name}/video-playlists`.
- `History` now prefers `/api/v1/users/me/history/videos`.
- `Likes` now prefers `/api/v1/accounts/{name}/ratings?rating=like`, which is the latest API route exposed in the uploaded PeerTube 8.1 schema for account video likes.
- `Comments` stays on the existing PostgreSQL path because the uploaded PeerTube 8.1 schema does not expose a direct authored-comments listing endpoint for account/profile activity; the current DB query is still the compatible path for comments/replies by that account.
- API responses are now enriched with PeerTube PostgreSQL URL/thumbnail metadata so the existing Connect UI can keep rendering clickable rows and thumbnails without inventing undocumented watch URLs.
- Scope stayed inside `ia-connect/includes/support/ajax.php` plus notes/version bumps only.

## 0.5.28 Connect Stream query-parameter repair

- Kept the earlier PostgreSQL pagination and identity-backfill work, but patched the actual query binding failure that was still breaking the Connect `Stream` tab.
- Root cause: the Stream handler reused the same named PDO placeholder (`:q`) multiple times in single PostgreSQL queries. On the deployed driver/path this could still throw a query failure even though the SQL shape looked correct.
- Replaced repeated named search placeholders with per-column generated placeholders so videos, comments, likes, subscriptions, playlists, and history can all search safely without tripping PeerTube query errors.
- Added narrow server-side error logging in `ia_connect_stream_activity` so future PeerTube query failures report the failing activity type instead of collapsing silently into a generic browser-side 500 with no handler context.
- Scope intentionally stayed inside `ia-connect/includes/support/ajax.php`; no wall-rendering paths, Connect post rendering, or other profile tabs were changed.

# Notes: IA Connect

## Core entry points

- `ia-connect.php` — plugin header, constants, activation, boot wiring.
- `includes/functions.php` — shared helpers, phpBB identity mapping, privacy helpers, and page-level metadata helpers.
- `includes/modules/panel.php` — profile/wall renderer and Connect panel output.
- `includes/support/assets.php` — asset enqueue and front-end config payload.
- `includes/support/ajax.php` — Connect AJAX actions.

## Operating rule

Keep changes patch-only. Preserve existing routing, UI paths, AJAX actions, and phpBB/WordPress identity mapping.

## 0.5.10 page-level titles and share metadata groundwork

- Added server-side document-title resolution for Connect so shared links do not fall back to the global site title on Connect routes.
- Profile routes now use the resolved display name where available.
- Post routes (`ia_post`) now use the stored post title, or a short body excerpt when the post has no title.
- Added narrow `og:title` and `twitter:title` output in `wp_head` so page-level titles can start flowing into shared-link metadata.
- Kept the change confined to `includes/functions.php` and the root loader in `ia-connect.php`; no UI behaviour was changed.

## 0.5.11 client-side page title sync

- Fixed Connect page titles so modal/deep-link navigation updates the browser title immediately instead of waiting for a manual refresh.
- Added client-side `document.title` + `og:title` / `twitter:title` syncing for profile and post-modal states.
- Kept the patch narrow in `assets/js/ia-connect.js`; no feed, modal, or routing behavior was widened beyond title/meta refresh timing.


## 0.5.12 live title consistency fix

- Fixed client-side title drift on Connect where post routes could remain at `Post` and profile routes could keep an older title until refresh.
- Post-title resolution now falls back through modal title, Discuss-share title, embed title, then body excerpt.
- Server-side title resolution for shared Discuss posts now looks up the original Discuss topic title when the Connect post itself has no stored title.
- Profile resolution now also checks WordPress user slug when `ia_profile_name` is present.
- Added mutation/history observers so `document.title`, `og:title`, and `twitter:title` stay in sync during Connect SPA navigation.

- 0.5.13: tightened Connect title routing so modal/profile loading states now fall back to `Connect | IndieAgora` until the requested post/profile content has actually loaded, and pinned the client/server site-title source to `IndieAgora` so transient `| Free` suffixes cannot leak in during SPA navigation.

## 0.5.14 account deletion delegation retained

- Kept Connect's existing account-delete endpoint and UI path intact.
- Delete flow now relies on IA Goodbye for deeper purge/tombstone enforcement across phpBB, Message, and Connect data.

- 2026-03-15: Wrapped `ia_connect_ajax_account_delete()` in a `try/catch` so frontend delete failures return JSON instead of fatal HTML/non-JSON responses.

- 2026-03-15: Connect delete endpoint still delegates lifecycle work to IA Goodbye, but phpBB tombstoning underneath is now schema-tolerant and surfaces the underlying phpBB failure text instead of only the generic `phpBB delete failed.` string.

## 2026-03-15 detailed account-delete delegation note

Connect still owns the visible frontend delete action, but Connect is not the system of record for deletion. The current design is intentionally split:

- Connect owns the UI, password-confirm form, and AJAX entry point.
- IA Goodbye owns irreversible delete orchestration.
- IA Auth owns the final phpBB-user tombstone/update path.

This matters because a Connect-only delete would be incomplete in this stack. The same person can exist as:

- a Connect-visible WordPress shadow user,
- a phpBB canonical account,
- a PeerTube-linked identity,
- an `ia_identity_map` binding across systems.

So the Connect endpoint should continue to delegate rather than attempting to directly delete users itself.

### Frontend expectation

The current frontend expectation is:

- user enters current password,
- Connect validates/delegates,
- Connect receives structured JSON success/failure,
- deeper lifecycle work happens outside Connect.

### Why the JSON handling note matters

The earlier non-JSON error was caused by a deeper delete fatal bubbling up as HTML. The current Connect-side note is important because the endpoint should remain a clean JSON boundary even when a lower layer fails.

### Practical maintenance rule

Do not move destructive cross-system delete logic into Connect. Keep Connect as the frontend gate and response boundary only.


## 0.5.25 non-embeddable URL post/render fix

- Removed the stray `iaConnectRefreshPageTitle(root, postModal);` call left inside `renderVideoEmbedsFromText()`.
- That call referenced out-of-scope variables, so ordinary non-embeddable URLs in post bodies could throw during `renderCard()` on create/load.
- Resulting symptom matched the reported behavior: the post insert succeeded server-side, the frontend showed `Post failed`, and wall rendering could blank when that post was present.
- Patch kept narrow to `assets/js/ia-connect.js` plus version/notes sync.


## 0.5.26 render guard + stable baseline note

- Added a narrow defensive guard at the top of `renderCard()` so non-object values return early before any field access.
- Marked the current `0.5.25` URL-post/render fix line as the working stable baseline for this issue family.
- Diagnostic note for future incidents: avoid long trial-and-error by checking in this order:
  1. confirm the wall SQL returns the expected post IDs,
  2. confirm `ia_connect_post_list` returns real post payloads rather than empty arrays,
  3. only then inspect `renderCard()`/embed rendering for the first thrown client-side error.
- Practical rule: when AJAX returns matched IDs and non-empty payloads but the wall is blank, stop changing SQL/PHP query logic and inspect the first frontend render exception instead.


## 2026-04-05 wall-render regression guard for search work

- Reference incident: the blank profile wall was previously traced to a stray `iaConnectRefreshPageTitle(root, postModal);` call left inside `renderVideoEmbedsFromText()`. Because `root` and `postModal` were out of scope there, ordinary non-video URLs could throw during card render and blank the wall.
- Hard rule: search changes must not touch Connect wall render, URL parsing, linkification, embed cleanup, or page-title refresh paths.
- For Connect search work, limit PHP edits to search handlers/payload shaping and limit JS edits to the Atrium search overlay/UI only.
- Fast diagnostic order for any future blank-wall report: verify wall SQL IDs, verify AJAX payload objects, then inspect first frontend render exception before changing persistence or wall query logic again.

## 2026-04-05 search-result regression boundary reinforcement

- Connect search-result presentation changes were kept out of wall rendering again after the earlier blank-wall incident.
- Search preview cleanup/highlighting now happens in the shared Atrium overlay; Connect wall/card render, URL/embed parsing, and page-title refresh paths remain untouched.


## 0.5.27 Connect Stream profile-activity repair

- Fixed the Connect profile `Stream` tab backend to use PostgreSQL pagination syntax (`LIMIT ... OFFSET ...`) instead of MySQL's `LIMIT offset,count` form.
- This matters because Connect reads profile Stream activity directly from the PeerTube PostgreSQL database. The old pagination form could make every Stream subtab query fail even when matching data existed.
- Added a narrow identity backfill step for Stream activity so partially populated `wp_ia_identity_map` rows can recover missing PeerTube `user/account/actor` ids from whichever PeerTube id is already present.
- Repaired ids are written back to the identity map to avoid repeated misses on later profile loads.
- Scope stayed patch-only in `includes/support/ajax.php`; no Connect wall render, search overlay, or routing code was touched.
- Regression guard: when Connect needs PeerTube profile activity, treat the source as PostgreSQL-first and do not introduce MySQL paging syntax into these queries.

## 0.5.31 title ownership guard

- Fixed cross-tab title stomping where Connect's live title observer could keep writing the logged-in profile name after the user had already switched into Discuss or Stream.
- Root cause: Atrium keeps multiple tab panels mounted in the shell, so hidden Connect observers were still alive and still capable of calling `document.title`.
- Connect client title writes are now gated by the active Atrium surface / `tab=connect` before they touch `document.title`, `og:title`, or `twitter:title`.
- Scope stayed patch-only in `assets/js/ia-connect.js`; no profile, wall, or modal behaviour was changed.

## 0.5.32 title guard ordering fix

- Follow-up after live failure: the browser title was still reverting to the logged-in Connect profile name (`A Tree Stump | IndieAgora`) even on Discuss and Stream views.
- Root cause: Atrium fires the surface switch before it finishes replacing the `?tab=` query param. Hidden Connect timers could wake up during that short window, read the stale URL first, and wrongly decide Connect still owned `document.title`.
- Fix: Connect title ownership now checks Atrium's active shell surface first, then falls back to the URL, then the panel visibility state.
- Scope stayed patch-only in `assets/js/ia-connect.js`; no wall, profile, modal, or routing behaviour changed.

- 2026-04-05 user homepage preference: Connect settings now include a per-user homepage selector (`Connect` / `Discuss` / `Stream`). The choice is stored in user meta and only affects plain homepage entry with no explicit `?tab=` or deep-link route.

- 2026-04-05 tidy-only Connect pass after rollback: kept scope inside `ia-connect` presentation only. Improved Settings card spacing, switch alignment, input box-sizing, composer spacing, and Connect post-card container polish. No shared style/theme system changes were reapplied in this rollback branch.

- 2026-04-05 follow-up on the rollback tidy branch: danger-button text in Connect settings was too faint against the red deactivate/delete styling, especially in the disabled pre-confirm state. Kept scope to `assets/css/ia-connect.fb.css` and raised label contrast without touching broader theme work.

- 2026-04-06 Connect style import phase started with BLACK only. Added a per-user Connect Settings style selector with `Default` and `Black`, keeping Default as the Atrium baseline. Scope is colour-only inside Connect for this pass: no layout rhythm changes, no Discuss/Stream/messages changes yet, and no frontend mention of MyBB. BLACK palette was calibrated from the imported source into Connect CSS variables and component overrides with light-surface/dark-text contrast rules.

- 2026-04-06 BLACK follow-up calibration after live screenshots: kept scope inside Connect presentation/CSS only. Raised contrast for the profile header text, moved the Connect panel background to the lighter off-white grey black-theme surfaces, changed feed cards to a black header + grey body treatment, improved feed/meta/title/date readability, darkened light-surface SVG/icon controls, restyled Connect Stream activity rows/comments for readable light-mode contrast, and limited Atrium shell recolouring (top nav, bottom nav, search overlay) to the Connect-active state under the BLACK palette.

- 2026-04-06 black style calibration follow-up: raised contrast again for Atrium shared search overlay history/results, Connect profile dropdown menu, Connect > Discuss embedded/shared Discuss text, Connect > Discuss activity rows, and the profile header meta block. The post viewer stays on the previous styling because live feedback marked that view as correct.

- 2026-04-06 BLACK Connect contrast tidy: profile name/signature now sit directly on the light surface instead of inside a dark box; shared Discuss card contrast was lifted again for title/attachment/signature readability; Connect→Discuss activity agoras/topics/replies were retuned toward the same light-card/dark-text direction as Stream cards; profile-menu destructive actions were darkened for clearer warning weight.

## 0.5.39 Black palette discuss/card contrast tidy

- Darkened the profile-menu danger actions in the bottom-nav profile menu only, keeping the Settings-screen danger styling unchanged.
- Retuned Connect → Discuss agora rows so joined/created lists no longer wash out to white-on-light and now hold dark text on the light black-theme surfaces.
- Retuned Connect → Discuss topic/reply cards to match the lighter Stream-card body more closely, with darker titles/excerpts and the same narrow patch-only scope in CSS.


## 0.5.40 Black palette final Discuss contrast pass

- Darkened the bottom-nav profile-menu deactivate/delete entries again, including their icons, without changing the Settings-tab danger buttons.
- Forced darker text/metadata on Connect → Discuss `Agoras created` and `Agoras joined` rows under the Black palette so the row titles and counts cannot wash out on the light surface.
- Lightened Connect → Discuss `Topics` and `Replies` cards another step while keeping a darker slate meta strip and dark body copy for readability.
- Corrected the shared Discuss signature block rendered inside Connect wall cards by targeting the real topic-signature selectors (`.iad-post-signature`, `.iad-post-sig-body`, `.iad-post-sig-divider`) and giving that nested signature area a dark-grey treatment.


## 0.5.41 Black palette Connect Discuss selector correction

- Previous contrast attempts were too broad and in one case styled the shared Discuss signature as a boxed block instead of only correcting the signature text colour.
- Moved the destructive-action darkening to `ia-profile-menu/assets/css/ia-profile-menu.css` so the bottom-nav profile menu no longer depends on Connect-side selector guesses.
- Narrowed the Connect → Discuss activity fixes to the actual `.iac-activity-list` render tree used by `Agoras created`, `Agoras joined`, `Topics created`, and `Replies`.
- Black palette activity cards now remove the inherited dark Discuss gradient/header treatment inside Connect and use the light grey card treatment the user asked for, with darker readable meta/title/body text.
- Shared Discuss signatures inside Connect wall cards now stay unboxed and render as dark-grey signature text with a subtle divider on the existing light surface.


## 0.5.42 theme-reference notes + first-load CSS tidy

- Added `assets/css/BLACK-STYLE-REFERENCE.md` so future style work can follow the user-approved Black theme behaviour instead of re-discovering selector ownership each time.
- The reference note records the confirmed ownership split: Connect Discuss activity styling belongs to the real `.iac-activity-list` → Discuss render tree, destructive bottom-nav menu actions belong to `ia-profile-menu`, and shared Discuss signatures inside Connect should stay restrained on the nested card surface.
- Removed a duplicate enqueue of `ia-connect-fb` in `includes/support/assets.php`. This keeps appearance unchanged but avoids re-processing the same Connect override stylesheet twice on first load.

## 0.5.31 Connect style bridge for ia-discuss
- Kept Connect as the source of truth for the selected profile style and added a narrow front-end bridge event so other panels can react without guessing at Connect internals.
- `assets/js/ia-connect.js` now dispatches `ia:connect-style-changed` after `applyStyle()` updates `data-iac-style` on `html`/`body`.
- Current consumer is the real `ia-discuss` plugin, which maps Connect `black` to Discuss `black` and uses its existing Discuss theme CSS rather than replacement markup or duplicate theme storage.

## 2026-04-06 style ownership clarification

- Connect remains the source of truth for the user-selected style, but it is not the owner of every rendered surface.
- The saved Connect style should be exposed as shared state for the stack to consume; do not duplicate top-nav/bottom-nav overrides inside every plugin.
- When Connect Black is used as the reference, future theme work should map into existing owner paths: Atrium for shared chrome, Discuss for Discuss internals, Post for composer internals, Profile Menu for destructive rows.
- If a style issue appears outside Connect itself, confirm the owning plugin before patching selectors.

- 2026-04-06 hotfix v3: added imported-light-theme styling for `.iac-avatar-change` so the profile-picture change button remains visible and matches the same light-surface button treatment as the cover-change control.

- 2026-04-06 spacing follow-up: Connect feed cards now keep extra top padding in `.iac-card-body` so the first text line is not cramped against the header bar. CSS-only spacing change; no render logic changed.

- 2026-04-06 hotfix: increased Connect feed card spacing under the header bar for shared/light-style cards by raising head bottom padding and body top padding so the first text line no longer sits against the blue header.
- 2026-04-06 spacing correction: `ia-connect.fb.css` had still been zeroing the top padding on `.iac-card-body`, which cancelled the earlier Connect header/body buffer change. Feed cards now keep visible top padding below the header bar in the active layout. CSS-only; no render logic changed.
