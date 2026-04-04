# assets / js / split / feed

Split source slices for the feed runtime bundle.

## File tree
```text
├── _feed.body.js
├── feed.bind_card_actions.js
├── feed.card_template.js
├── feed.export.js
├── feed.links_modal.js
├── feed.load_request.js
├── feed.media_blocks_and_pills.js
├── feed.render.body.js
├── feed.render.clicks_and_boot.js
├── feed.render.loading.js
├── feed.render_entry.js
├── feed.utils.js
├── feed.video_open_and_attachments.js
└── feed.video_parsers.js
```

## File roles
- `_feed.body.js` — Shared feed runtime helpers and modal wiring.
- `feed.bind_card_actions.js` — Feed card action helpers.
- `feed.card_template.js` — Feed card HTML builder.
- `feed.export.js` — Window exports for the feed runtime.
- `feed.links_modal.js` — Links, attachments, and video modal helpers.
- `feed.load_request.js` — Feed AJAX request mapper.
- `feed.media_blocks_and_pills.js` — Media block and attachment-pill HTML helpers.
- `feed.render.body.js` — Start of `renderFeedInto()` and shell helpers.
- `feed.render.clicks_and_boot.js` — `renderFeedInto()` click delegation, sort binding, and boot.
- `feed.render.loading.js` — `renderFeedInto()` paging/load helpers.
- `feed.render_entry.js` — Feed render entry wiring.
- `feed.utils.js` — Feed utility helpers.
- `feed.video_open_and_attachments.js` — Inline media and video-modal helpers.
- `feed.video_parsers.js` — YouTube / PeerTube detection and embed metadata helpers.

## Maintenance entry point
Edit the split JS slices here, then run `./tools/build-assets.sh` from the plugin root to rebuild the generated runtime bundle.

Update note: feed video parsing keeps playlist-with-video embeds on the standard YouTube embed domain and reserves `videoseries` for list-only playlists.

Update note: feed video parsing now relies on the shared `ia-discuss.youtube.js` helper for canonical YouTube parsing and embed selection.
