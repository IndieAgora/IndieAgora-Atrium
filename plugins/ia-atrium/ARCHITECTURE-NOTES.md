# Architecture Notes: IA Atrium

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-atrium`
- Version in header: `0.1.11`
- Main entry file: `ia-atrium.php`
- Declared purpose: Core Atrium shell (Connect / Discuss / Stream tabs + bottom navigation). All features are added via micro-plugins.

## Authentication and user-state notes

- Contains WordPress logout/session termination logic.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Nonce strings seen in code: ia_connect:user_search, ia_connect:wall_search.

## Endpoint inventory

### Shortcodes

- `ia-atrium` — registered in `includes/class-ia-atrium.php`.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-atrium.php` — Runtime file for ia atrium.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/atrium.css` — Stylesheet for atrium.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/atrium.js` — JavaScript for atrium.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-atrium-assets.php` — Asset enqueue and localization logic.
- `includes/class-ia-atrium.php` — Runtime file for class ia atrium.

### `templates`

- Purpose: PHP templates rendered into the front end.
- `templates/atrium-shell.php` — Runtime file for atrium shell.


## 2026-04-04 patch: Atrium topbar search now understands Stream

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

## 2026-04-04 follow-up patch: topbar Stream comment hits now deep-link into highlighted modal comments

User follow-up captured here:
- Stream search needs a way to exit search cleanly rather than replacing Browse videos.
- Search results should live in a distinct Stream tab.
- Clicking a comment/reply suggestion or result should open the target Stream video and highlight the matched comment/reply.

Patch applied in Atrium shell integration:
- `ia-atrium/assets/js/atrium.js`
  - Stream suggestion items now carry optional video/comment identifiers in addition to the broader Stream search state.
  - Clicking a Stream comment/reply suggestion deep-links back into the Stream search state and opens the relevant video modal with comment focus/highlight.
  - Existing shared Atrium search overlay remains the only global search surface; no second topbar search or standalone Stream search overlay was added.


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
