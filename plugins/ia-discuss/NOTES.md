- 2026-04-06 style import reset: Discuss now maps every imported Connect style directly to its matching classic scheme instead of only recognising Black.
## 0.3.99 Discuss housekeeping

- Removed the Discuss sidebar `AgoraBB Mode` control and forced Discuss back onto the standard Atrium layout so old browser storage can no longer reopen the board-style variant.
- Removed the Discuss sidebar theme/style controls, including the theme modal entry point and the direct MyBB scheme picker buttons.
- Cleared legacy `ia_discuss_theme` and `ia_discuss_layout_mode` localStorage keys at shell boot and reapplied the default Discuss baseline (`dark` theme, `atrium` layout) so housekeeping takes effect immediately without requiring users to clear storage manually.
- Kept the patch narrow to `assets/js/ia-discuss.ui.shell.js`; no feed, topic, API, or phpBB endpoint behaviour was changed.

# Notes: IA Discuss

## 0.3.60 architecture pass summary

- Added README.md and NOTES.md coverage across the full plugin tree so each folder carries local maintenance context.
- Rebuilt the large JS split folders into clean contiguous source slices mapped back to the stable runtime bundles.
- Replaced the placeholder asset build helper with a working `tools/build-assets.sh` builder that regenerates the live JS bundles from the split source tree.
- Kept WordPress enqueue handles stable in `includes/support/assets.php` and documented the generated-runtime workflow there.
- Bumped the plugin version to `0.3.60` to invalidate cached assets after the architecture pass.

## Core PHP entry points

- `ia-discuss.php` — plugin header, constants, safe loader.
- `includes/ia-discuss.php` — orchestrator that loads support files, services, modules, cron hooks, and AJAX dispatch.
- `includes/support/assets.php` — WordPress asset registration and enqueue order.
- `includes/support/ajax.php` — route dispatch into module AJAX handlers.
- `includes/functions.php` — shared helpers, logging, JSON helpers, phpBB identity helpers, and user relationship helpers.

## High-risk files

- `assets/js/ia-discuss.router.js` — navigation, deep links, topic opening, search opening, feed scroll restoration, and event wiring.
- `assets/js/ia-discuss.ui.feed.js` — feed rendering, load-more behaviour, media cards, share modal, link modal, and attachment/video handling.
- `assets/js/ia-discuss.ui.composer.js` — composer rendering, drafts, file handling, and editor tools.
- `assets/js/ia-discuss.ui.search.js` — suggestions, result rendering, and page loading.
- `includes/services/phpbb.php` and `includes/services/phpbb-write.php` — core data access and write operations.

## Operating rule

Edit split JS slices first, rebuild the generated bundles, then update the relevant README.md / NOTES.md entries for the folders that changed.

## 0.3.60 YouTube playlist fix summary
- Patched the YouTube embed path so playlist URLs use the YouTube playlist embed endpoint instead of the single-video endpoint.
- Preserved the existing single-video, Shorts, live, and PeerTube behaviour.
- Updated local notes in the PHP render layer and both topic/feed JS media layers.
## 0.3.61 YouTube playlist start-item fix

- Kept playlist behaviour for YouTube playlist URLs but stopped forcing all playlist embeds through `videoseries`.
- Playlist URLs that include both `v=` and `list=` now embed the selected video in playlist context, which preserves the chosen item and avoids the unavailable player seen on some playlists.
- Pure list-only playlist URLs still fall back to the `videoseries` endpoint.
## 0.3.62 YouTube playlist domain fix

- Kept the selected-video playlist rule from `0.3.61` but changed playlist-with-video embeds from `youtube-nocookie.com/embed/{videoId}` to `youtube.com/embed/{videoId}`.
- List-only playlist URLs still use `https://www.youtube.com/embed/videoseries?list=...`.
- Single-video and Shorts embeds remain on the existing no-cookie path.
- This change is intentionally narrow and targets the unavailable-player case seen in topic view for playlist posts.

## 0.3.63 YouTube playlist rebuild

- Replaced the mixed playlist experiments with one canonical YouTube helper: `assets/js/ia-discuss.youtube.js`.
- Playlist URLs now win as playlists. The plugin no longer tries to force a `v=` item into the iframe path for playlist posts.
- Feed JS, topic JS, and PHP rendering now all use the same rule set for YouTube URLs.

## 0.3.64 playlist fallback cards
- YouTube playlist URLs no longer render as inline iframes. They now render as simple playlist cards that open the playlist on YouTube.
- Single videos and Shorts keep inline embed behaviour.

## 0.3.65 light sidebar contrast patch

- Fixed the Discuss sidebar button contrast in light mode so sidebar labels are readable again.
- Kept the patch confined to `assets/css/ia-discuss.layout.css`.
- Styled the theme toggle so when light mode is active the button shown as `Dark` renders as a dark button with light text.

## 0.3.66 playlist iframe restore

- Restored inline YouTube playlist iframes so playlist URLs are not downgraded to click-out cards in topic rendering paths.
- Playlist URLs with both `v=` and `list=` now embed the selected video on `youtube.com/embed/{videoId}?list=...`.
- List-only playlist URLs still use the playlist embed form with `listType=playlist`.
- Feed and topic JavaScript now use the same shared helper output for playlist embeds.

## 0.3.67 playlist iframe endpoint correction

- Switched playlist iframe generation from mixed video-or-playlist embed URLs to the dedicated YouTube playlist iframe endpoint: `https://www.youtube.com/embed/videoseries?list=...`.
- Kept playlist rendering inline in topic/feed views, but stopped forcing the selected `v=` item into the iframe path because that form was producing the unavailable-player state.
- Playlist `index` and `start` values are still preserved when present in the pasted URL.


## 0.3.68 code-block URL literal fix

- URLs inside `[code]...[/code]` now stay literal text and are no longer auto-embedded, auto-linked, or converted into rich cards.
- Removed the legacy code-block collapse path that could turn code content into a standalone URL outside the code block.
- Kept the patch confined to `includes/render/bbcode.php` and bumped the plugin version for cache invalidation.

## 0.3.69 code-block HTML embed fix
- Fixed topic HTML post-processing so video embedding now stashes `<pre>` and `<code>` blocks before scanning for video links.
- URLs inside `[code]...[/code]` now remain literal text and do not turn into iframes during the HTML video-embed pass.

- 0.3.69a: Fixed a PHP parse error introduced in `includes/render/bbcode.php` during the code-block URL literal patch. Cause was unsafe regex quoting in single-quoted PHP strings inside `embed_video_links_in_html()`. Added `ERRORS-TO-AVOID.md` with the failure mode and prevention rule.

## 0.3.70 critical-error hotfix

- Fixed PHP parse error in `includes/render/bbcode.php` caused by unsafe regex quoting inside `embed_video_links_in_html()`.
- Added `ERRORS-TO-AVOID.md` documenting the failure mode and prevention rule.
- Purpose: restore plugin activation/runtime while keeping the code-block URL literal behavior.

- 0.3.71: Topic fullscreen view now starts below the fixed top bar (72px offset) so the topic title/header are visible in topic view. Patch-only CSS change in `assets/css/ia-discuss.modal.css`.

## 0.3.72 personal sidebar feeds

- Added a new `My` subsection in the Discuss sidebar after the light/dark toggle with `My Topics`, `My Replies`, and `My History`.
- `My Topics` shows the logged-in user's authored topics.
- `My Replies` shows topics where the logged-in user has made a reply, ordered by that user's latest reply in each topic.
- `My History` shows topics the logged-in user has opened in Discuss, stored in WP user meta and ordered by most recent visit by default.
- Kept the existing feed sort dropdown present for these personal views.
- Kept the existing Random behaviour intact instead of rebasing it onto the new personal feed contexts.

## 0.3.73 housekeeping and regression-cause notes

- Wrote down the concrete causes behind the earlier personal-feed/random regressions so they are preserved in-plugin instead of in chat only.
- Cause 1: personal feed semantics were initially grafted onto existing global feed paths instead of being kept as separate view keys end-to-end. That let `My Replies` fall through to the generic replies/feed behaviour.
- Cause 2: Random was temporarily coupled to whichever feed context remained active, so `My History` could shrink the candidate pool and make Random appear stuck on only a few topics.
- Cause 3: a later repair attempt touched broader feed/runtime paths than the requested target. That widened a Random fix into unrelated feed regressions.
- Housekeeping pass: reduced two large split JS source slices into smaller intent-labelled slices in `assets/js/split/router/` and `assets/js/split/feed/` while keeping the generated runtime bundle names and live behaviour unchanged.
- Updated the build script and folder notes so future edits target the smaller source slices instead of recreating the old monoliths.

## 0.3.74 private Agora moderation

- Added a dedicated `includes/services/agora-privacy.php` service for per-Agora private/public state and invite records.
- Added moderation controls for `Set private` / `Set public` and `Invite users` inside the existing Agora settings modal.
- Private Agora access is enforced server-side for Agora list, Agora meta, topic view, posting, replying, joining, and random/feed loading.
- Share to Connect is blocked server-side for private Agoras, and Discuss hides Share/Copy actions for private content in feed/topic UI.
- Invite notifications are emitted via IA Notify when that plugin is present, using an Agora invite URL that opens an accept/decline prompt before private content is shown.


## 0.3.75 private Agora access consistency fix

- Fixed private Agora invite notifications to deep-link into Discuss explicitly (`tab=discuss&ia_tab=discuss`) instead of relying on the current page context.
- Fixed feed/random privacy filtering to use the same canonical phpBB identity resolver as topic/forum-meta/membership paths, preventing invited users and moderators from being treated as anonymous in feed loaders.
- Invite acceptance now normalises the browser URL to the Discuss Agora route and removes the one-time `iad_invite` parameter after response.


## 0.3.76 private Agora leave/reinvite fix

- Leaving a private Agora as an invited non-moderator now revokes the accepted invite record as well as joined-state membership.
- That means the user immediately loses access to the private Agora until a moderator sends a fresh invite.
- Private-Agora leave now redirects non-moderators back to the global Discuss feed instead of leaving them inside an Agora they can no longer access.
- Moderator access remains intact for private Agoras because moderation access is preserved separately from invite-based membership.


## 0.3.77 private Agora invite hardening

- Fixed the private-Agora invite suggestion rows in moderation so invite search results are rendered with dedicated readable styling instead of reusing kicked-user row presentation.
- Fixed the Invite action to surface inline status and to use the first visible suggestion when text is typed but no row was explicitly clicked.
- Reinvite now invalidates any older invite row first and creates a fresh invite id, so stale invite URLs stop working permanently and Notify can emit a new reinvite item instead of deduping onto the old one.
- Invite responses now only succeed for pending invites, preventing accepted/declined stale links from being reused.


## 0.3.79 report override/admin access fix

- Fixed report-link access for WordPress admins who are not invited into a private Agora: report override is now checked before requiring a mapped phpBB identity, so admins can open the single reported post from the notification without gaining wider Agora access.
- Fixed report recipient fan-out so forum moderators are resolved from both `moderator_cache` and phpBB ACL grants (`m_*`) rather than only `moderator_cache`.
- Scope kept narrow: no general private-Agora admin bypass was added; the exemption remains report-link-only.

## 0.3.80 theme modal and legacy style

- Replaced the sidebar light/dark toggle with a theme picker modal offering `Dark`, `Light`, and `Legacy style`.
- Added a narrow new stylesheet, `assets/css/ia-discuss.legacy.css`, to carry both the new modal UI skin and the phpBB-inspired legacy Discuss theme.
- Legacy style uses a prosilver-like palette, classic font stack, simpler panel borders, and lighter forum-card surfaces to make posts easier to read.
## 0.3.81 legacy readability patch
- Improved legacy-theme readability in feed cards by overriding excerpt text away from inherited dark-theme white.
- Improved legacy-theme topic signature contrast so Connect bio signatures remain readable against the pale phpBB-style post backgrounds.




## 0.3.83 topic title bar viewport lift

- When the Atrium top nav/header auto-hides in Discuss topic view, the topic sheet now also lifts to the viewport top so the topic title bar does not sit below a letterbox gap.
- The topic title bar remains sticky in place while scrolling within the topic and drops back to the normal offset when the Atrium top bar returns.

## 0.3.82 topic topbar auto-hide

- Added a narrow topic-view scroll behaviour so the Atrium top nav/header hides while scrolling down in Discuss topic view and reappears when scrolling up or returning near the top.
- Kept the change local to `assets/js/ia-discuss.ui.topic.js` and restored the original topbar inline styles when the topic closes or the user leaves Discuss.


## 0.3.84 page-level titles and share metadata groundwork

- Added server-side document-title resolution for Discuss so browser titles and link scrapers no longer fall back to the global site title on Discuss routes.
- Topic view now resolves the real phpBB topic title through `IA_Discuss_Service_PhpBB::get_topic_row()` and uses it as the page title.
- Agora view now resolves the forum name through `IA_Discuss_Service_PhpBB::get_forum_row()` and uses it as the page title.
- Added narrow `og:title` and `twitter:title` output in `wp_head` so shared links can start reflecting page-level titles before fuller metadata work lands.
- Kept the change patch-only in `includes/functions.php` and boot wiring in `includes/ia-discuss.php`; no feed/topic UI paths were changed.

## 0.3.84 client-side page title sync

- Fixed Discuss page titles so they update on in-panel SPA navigation instead of only after a hard refresh.
- Added client-side `document.title` + `og:title` / `twitter:title` syncing for topic, agora, search, and feed-view transitions.
- Kept the patch narrow in `assets/js/ia-discuss.router.js`; no route or UI behavior was changed beyond title/meta refresh timing.


## 0.3.84b live title observer fix

- Fixed SPA title drift where Discuss topic titles could lag behind navigation until a full refresh.
- Client-side title resolution now falls back to `window.IA_DISCUSS_TOPIC_DATA.topic_title` when the topic header DOM has not settled yet.
- Added mutation/history observers in the router so `document.title`, `og:title`, and `twitter:title` are refreshed after topic/agora route changes and late DOM updates.

- 0.3.86: tightened Discuss title routing so the loading state now falls back to `Discuss | IndieAgora` instead of stale topic/agora names, and the client/server site-title source is pinned to `IndieAgora` so transient `| Free` suffixes cannot leak in during SPA navigation.

## 0.3.89 AgoraBB mode

- Added a separate `AgoraBB Mode` sidebar toggle that switches Discuss navigation into a phpBB-style forum index and forum-topic listing without changing the global theme system.
- In AgoraBB mode, the default Discuss landing route now opens the forum index (`Agoras`) when no explicit Discuss deep link is present.
- Topic view remains on the existing Atrium topic renderer; only the Agoras index and single-Agora navigation surfaces switch to the board-style layout.


## 0.3.90 MyBB colour themes in Discuss

- Added the default MyBB colour schemes as additional Discuss theme options: `black`, `calm`, `dawn`, `earth`, `flame`, `leaf`, `night`, `sun`, `twilight`, and `water`.
- Kept the existing Discuss dark/light/legacy theme system intact and extended it instead of replacing it.
- Added direct theme pick buttons in the Discuss sidebar so users can switch to the MyBB-style colour schemes without leaving the menu.
- Reworked `assets/css/ia-discuss.legacy.css` so the legacy/phpBB-style rules now run through a shared classic-theme class, with per-theme colour variables mapped from the MyBB default package colour names.
- Updated the search suggestions portal theme sync so the floating search dropdown follows the selected MyBB-style colour scheme as well.
- Bumped the plugin version to `0.3.90` for cache invalidation.

## 0.3.91 stronger MyBB schemes
- Renamed the Discuss sidebar theme subsection from `MyBB styles` to `Schemes`.
- Renamed the legacy blue picker label to `Blue` in the Discuss theme UI while keeping the stored theme key as `legacy` for compatibility.
- Expanded `assets/css/ia-discuss.legacy.css` so each MyBB-derived scheme now changes more of the forum chrome instead of mostly just accents: card backgrounds, alternate post rows, borders, sidebar gradients, modal header gradients, AgoraBB/topic header bars, and the Discuss topbar toggle all now vary by scheme.
- This change was based on reviewing the supplied screen recording frame-by-frame: the previous pass showed only small accent differences, so this patch intentionally broadens the visible scheme surfaces without changing Discuss routing or theme storage keys.


## 0.3.92 feed pagination system

- Added a real feed-pagination system across Discuss topic feeds with a per-feed toggle between `Load more` and numbered `Pages`.
- Numbered mode now renders forum-style page windows with previous/next arrows, a highlighted current page, and a `Jump to` control that only opens its mini page input on demand.
- Feed responses now return exact `total_count`, `total_pages`, and `current_page` values so New, single-Agora feeds, Replies, 0 replies, and personal feeds can paginate against real totals.
- Moved feed-access filtering for private Agoras and blocked users into the phpBB query layer so page counts and page fills stay correct instead of being shortened after fetch.
- Extended feed scroll restoration so returning from topic view restores the correct numbered page as well as the load-more depth.
- Added feed toolbar/pager styling and classic-scheme visibility rules so pagination controls, toggles, and sort controls remain readable in the MyBB-derived schemes.


## 0.3.93 packaging permission repair
- Repaired plugin packaging after the 0.3.92 pagination build so shipped JS/CSS/PHP files no longer carry restrictive mixed file modes.
- Normalised plugin directories to `755` and files to `644` so Discuss assets can be read consistently by the web server after install/unzip.
- Kept the runtime enqueue graph unchanged; this pass is specifically to prevent asset load failures that surface as the Discuss JS dependency warning.

## 0.3.94 pagination alignment and icon pass
- Tightened the feed toolbar layout so pagination controls, sort, totals, and jump-to sit in aligned groups instead of drifting apart at the top of the feed.
- Added an explicit SVG sort icon in the feed toolbar so all pagination-mode controls now use the requested icon treatment.
- Kept the jump-to mini form hidden until the button is pressed, but aligned the revealed form with the toolbar instead of leaving it stranded on the opposite side.
- Breakdown prevention note: feed toolbar markup now lives only in the split source (`assets/js/split/feed/feed.render.body.js`) and must be rebuilt into `assets/js/ia-discuss.ui.feed.js` with `tools/build-assets.sh`; do not patch only the generated bundle or the next rebuild will silently discard the fix.
## 0.3.95 pagination alignment and icon pass
- Compacted pagination mode and jump controls to SVG-first buttons, tightened toolbar alignment, and kept numbered pagination/jump behaviour intact.
- Reminder: pagination UI edits live in split feed JS and split cards CSS; always rebuild generated feed JS and verify packaged permissions before release.


## 0.3.96 pagination visibility and alignment repair
- Added a real `.iad-screen-reader-text` utility so SVG-only pagination controls do not leak visible text labels into the toolbar.
- Added an explicit `.iad-feed-jump[hidden]{display:none !important;}` rule because the authored feed layout CSS was overriding the browser hidden attribute and making the Jump To form appear before click.
- Tightened toolbar and pager spacing so the numbered pager sits higher and the top-row controls align more cleanly.

## 0.3.97 pagination row alignment follow-up
- Pagination header layout was corrected to the requested three-part structure: mode icons on the left, page numbers in the same row, and summary/jump grouped at the right.

## 0.3.98 page-title correlation fix

- Fixed Discuss server-side context parsing to read `iad_forum` for Agora pages instead of the wrong `forum_id` query key.
- Added client-side Discuss title syncing for feed routes so browser titles now follow what the user is actually viewing: Latest Posts, Latest Replies, Most Replies, Least Replies, 0 Replies, Agora List, specific Agora names, and topic titles.
- Added a narrow topic-loaded event so topic titles refresh immediately during SPA navigation without changing existing routing or feed behavior.
- 0.3.99: Specific Agora browser titles now include both the Agora name and the active sort mode, e.g. `Agora Name | Most Replies | IndieAgora`. The router now persists `iad_order` in the URL for Agora feeds so refresh/deep-link titles stay correlated.

## 0.3.100 specific Agora title label fix
- Fixed specific Agora browser titles so the in-Agora feed resolves as `Agora Name | Sort Mode | IndieAgora` instead of falling back to `Latest Posts | IndieAgora`.
- Agora feed mounts now identify themselves as Agora context, and router title refresh now honours in-Agora state when feed events fire.
- Matched the sort label text to the actual in-Agora selector labels: `Most recent`, `Oldest first`, `Most replies`, `Least replies`, and `Date created`.


## 0.3.101 title ownership guard
- Fixed Discuss client title syncing so it only writes browser/meta titles while Discuss is the active Atrium surface.
- This prevents hidden Discuss observers from racing with Connect or Stream in the shared shell.
- Scope stayed inside `assets/js/ia-discuss.router.js`; no feed/topic/agora routing behaviour changed.

## 0.3.102 title guard ordering fix
- Follow-up after live failure: stale Connect-style titles could persist because title guards were trusting the old `?tab=` value during shell tab switches.
- Discuss title ownership now resolves from Atrium's active shell tab first, then URL, then panel visibility as a final fallback.
- Scope stayed inside `assets/js/ia-discuss.router.js`; no feed/topic/agora behaviour changed.

## 0.3.98 Connect style bridge for Discuss
- Discuss no longer hard-forces the shell to `dark` only. On boot it now reads the active Connect style from `data-iac-style` and maps Connect `black` to Discuss `black`; all other Connect styles fall back to Discuss `dark`.
- Added a narrow bridge in `assets/js/ia-discuss.ui.shell.js` so Discuss re-syncs if Connect style changes later in the same SPA session. The bridge listens for the new `ia:connect-style-changed` document event and also watches `data-iac-style` mutations on `html`/`body`.
- Scope is intentionally narrow: this does not replace Discuss's own theme system or invent new storage. It only lets the confirmed Connect Black style drive the ia-discuss plugin appearance when that Connect style is selected.

- 2026-04-06 Black style follow-up after live Discuss review: improved Discuss attachment pill contrast under the `black` scheme so attachment controls stay visible against the lighter card surfaces introduced by the approved Connect → Discuss Black bridge.

## 2026-04-06 style bridge and ownership notes

- When Connect-selected style is intended to affect the real Discuss tab, bridge into Discuss through its existing theme/state path rather than trying to repaint Discuss from Connect CSS.
- Discuss continues to own Discuss-specific surfaces: forum/agora rows, topic/reply cards, pills, meta text, and other board/content internals.
- Under the approved Black reference, attachment pills need stronger contrast than the earlier pale-outline treatment so they stay visible on the lighter card surfaces.
- Do not move shared-shell fixes back into Discuss CSS; Atrium now owns that layer globally.
