# assets / js / split / router

Split source slices for the router runtime bundle.

## File tree
```text
├── _router.body.js
├── router.event_bus.js
├── router.export.js
├── router.feed_scroll_state.js
├── router.open_pages.js
├── router.render.agoras.js
├── router.render.default_views.js
├── router.render.entry.js
├── router.reply_submit_hook.js
└── router.route_from_url.js
```

## File roles
- `_router.body.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `router.event_bus.js` — Event listeners and cross-view router reactions.
- `router.export.js` — Window exports for the router runtime.
- `router.feed_scroll_state.js` — Feed scroll save/restore helpers.
- `router.open_pages.js` — Topic and search page openers.
- `router.render.entry.js` — Start of `render()` including moderation/list-view setup.
- `router.render.agoras.js` — Agora-list render branch and its local helpers.
- `router.render.default_views.js` — Agora/default feed render tail for `render()`.
- `router.reply_submit_hook.js` — Reply-submit route hook.
- `router.route_from_url.js` — URL-to-view routing.

## Maintenance entry point
Edit the split JS slices here, then run `./tools/build-assets.sh` from the plugin root to rebuild the generated runtime bundle.
