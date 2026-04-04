# Notes: assets / js / split / feed

## What changed in the 0.3.60 architecture pass
- Feed source slices were rebuilt from the live runtime bundle so each file now maps to a clean contiguous concern.

## File/function index
- `_feed.body.js` — Functions: ensureShareModal, isShareModalOpen, closeShareModal, openShareModal, togglePicked, runShareSearch, submitShare, currentUserId, makeTopicUrl, copyToClipboard, openConnectProfile, ico
- `feed.bind_card_actions.js` — Feed action helpers used by the runtime bundle.
- `feed.card_template.js` — Functions: feedCard
- `feed.export.js` — Functions: renderFeed Window exports: IA_DISCUSS_UI_FEED
- `feed.links_modal.js` — Functions: ensureLinksModal, openLinksModal, lockPageScroll, openAttachmentsModal, ensureVideoModal, closeVideoModal
- `feed.load_request.js` — Functions: loadFeed
- `feed.media_blocks_and_pills.js` — Functions: mediaBlockHTML, linkLabel, attachmentPillsHTML
- `feed.render.body.js` — Start of function: renderFeedInto, renderShell, setMoreButton, appendItems
- `feed.render.loading.js` — Middle of function: renderFeedInto, loadNext
- `feed.render.clicks_and_boot.js` — End of function: renderFeedInto
- `feed.render_entry.js` — Feed render entry wiring.
- `feed.utils.js` — Feed helper utilities.
- `feed.video_open_and_attachments.js` — Functions: openVideoModal, isImageAtt, isVideoAtt, isAudioAtt, agoraPlayerHTML, attachmentInlineMediaHTML
- `feed.video_parsers.js` — Functions: decodeHtmlUrl, parseYouTubeMeta, cleanId, parseStartSeconds, parsePeerTubeUuid, buildVideoMeta, detectVideoMeta

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.
- Treat `feed.render.body.js`, `feed.render.loading.js`, and `feed.render.clicks_and_boot.js` as one ordered `renderFeedInto()` function body. Preserve their concatenation order in `tools/build-assets.sh`.

## 0.3.60 playlist embed fix
- `feed.video_parsers.js` now keeps `playlistId` alongside `id` and preserves the selected `v=` id for playlist URLs, using the single-video embed path in playlist context when available and falling back to `videoseries` for list-only URLs.
- Feed cards should now keep playlist behaviour without dropping the selected video, which avoids the unavailable embed seen on some playlists.
## 0.3.62 playlist domain follow-up
- `feed.video_parsers.js` now builds playlist-with-video embeds on `youtube.com/embed/{videoId}` while keeping `videoseries` for list-only playlists.
- Feed media keeps the existing no-cookie path for non-playlist single-video embeds.

## 0.3.63 playlist rebuild
- `feed.video_parsers.js` now consumes the shared `window.IA_DISCUSS_YOUTUBE` helper.
- Feed media treats playlist URLs as playlist embeds only and no longer builds hybrid selected-video playlist iframes.

## 0.3.64 playlist fallback cards
- YouTube playlist URLs no longer render as inline iframes. They now render as simple playlist cards that open the playlist on YouTube.
- Single videos and Shorts keep inline embed behaviour.

## 0.3.73 housekeeping split
- Replaced the larger `feed.load_and_render.js` source slice with four intent-labelled slices covering request mapping, render body, render loading, and render click/bootstrap flow.
- This was a structure-only change in the split source tree. The generated runtime bundle path and runtime behaviour were kept the same.

## 0.3.92 numbered feed pagination
- Added a dedicated split slice for feed pagination UI/state so numbered pages, jump-to, and mode toggles do not bloat the main render slices.
- `feed.load_request.js` now passes explicit page requests when numbered mode is active.
- `renderFeedInto()` now exposes `goToPage()` and pagination mode/state for router scroll restoration.

## 0.3.94 toolbar alignment and icon pass
- `feed.render.body.js` now groups pagination mode, sort, totals, and jump-to into cleaner toolbar blocks and adds the SVG sort icon.
- Keep toolbar markup changes in the split file only, then rebuild the runtime bundle. Editing only `assets/js/ia-discuss.ui.feed.js` will be overwritten on the next build.
## 0.3.95 pagination control compaction
- Replaced text-heavy pagination mode and jump controls with SVG-first buttons plus accessible labels so the toolbar stops wrapping into cramped pills on smaller widths.
- Kept feed markup split-based; rebuild `assets/js/ia-discuss.ui.feed.js` after editing the feed slices.


## 0.3.96 pagination UI follow-up
- No feed click-handler logic change was needed for Jump To. The visible-always bug came from CSS overriding `hidden`, not from missing JS.
- Keep SVG button labels as screen-reader-only text and verify the helper class exists in CSS before release.

## 0.3.97 pagination row alignment follow-up
- `feed.render.body.js` now places the top pager in the toolbar center, keeps sort as dropdown-only, and leaves Jump To as an icon toggle that reveals the form only when clicked.
