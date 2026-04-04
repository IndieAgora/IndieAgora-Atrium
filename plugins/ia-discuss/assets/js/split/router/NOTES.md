# Notes: assets / js / split / router

## What changed in the 0.3.59 architecture pass
- Router source slices were rebuilt from the live runtime bundle and separated into render, scroll-state, event-bus, and route files.

## File/function index
- `_router.body.js` — Functions: depsReady, safeQS, setParam, setParams, mount
- `router.event_bus.js` — Functions: bindRandomTopic, composerError
- `router.export.js` — Window exports: IA_DISCUSS_ROUTER
- `router.feed_scroll_state.js` — Functions: getDiscussScroller, getFeedMount, computeFeedAnchor, saveFeedScroll, restoreFeedScrollAfterFeed
- `router.open_pages.js` — Functions: openTopicPage, openSearchPage, viewToTab
- `router.render.entry.js` — Start of function: render, setModerationContext
- `router.render.agoras.js` — Middle of function: render, agRowHTML, previewHTML, renderShell, renderMoreButton, appendFromCache, loadAgorasNext
- `router.render.default_views.js` — End of function: render
- `router.reply_submit_hook.js` — Functions: bindReplySubmitHook
- `router.route_from_url.js` — Functions: routeFromURL

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.
- Treat the three `router.render.*.js` files as one ordered function body. Preserve their concatenation order in `tools/build-assets.sh`.

## 0.3.73 housekeeping split
- Replaced the large `router.mount_and_view.js` source slice with three intent-labelled slices: entry, agoras, and default-views.
- This was a structure-only change in the split source tree. The generated runtime bundle path and runtime behaviour were kept the same.

## 0.3.92 feed restore for numbered pages
- Feed scroll restoration now records pagination mode and current page, and will reopen the correct numbered page before restoring the anchor position.
