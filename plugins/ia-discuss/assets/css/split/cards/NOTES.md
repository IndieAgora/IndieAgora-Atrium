# Notes: assets / css / split / cards

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `cards.card_body_actions.css` — Stylesheet. Primary selectors include: .iad-card-excerpt, .iad-card-excerpt *, .iad-card-meta, .iad-sub, .iad-card-title, .iad-card-excerpt p, .iad-card-actions, .iad-attachrow
- `cards.feed_list_layout.css` — Stylesheet. Primary selectors include: .iad-feed-list, .iad-card, .iad-card::before, .iad-card.is-unread::before, .iad-card.is-read::before, .iad-card.is-unread, .iad-card.is-read, .iad-card-main
- `cards.icon_buttons.css` — Stylesheet. Primary selectors include: .iad-video-frame, .iad-video-frame.is-vertical, .iad-video-el, .iad-video-frame.is-vertical .iad-video-el, .iad-iconbtn, .iad-iconbtn:hover, .iad-iconbtn:active, .iad-iconbtn svg
- `cards.links_modal_items.css` — Stylesheet. Primary selectors include: .iad-linksmodal-top, .iad-linksmodal-title, .iad-linksmodal-body, .iad-linkslist, .iad-linksitem, .iad-linksitem:hover, .iad-linksitem-ico, .iad-linksitem-txt
- `cards.media_thumbs.css` — Stylesheet. Primary selectors include: .iad-mediawrap, .iad-media-row, .iad-media-meta, .iad-media-line, .iad-media-tag, .iad-media-host, .iad-vthumb, .iad-vthumb:hover
- `cards.pills_and_links_modal.css` — Stylesheet. Primary selectors include: .iad-vthumb-fallback, .iad-vthumb-overlay, .iad-vthumb-play, .iad-mediastrip, .iad-pill, .iad-pill:hover, .iad-pill.is-muted, .iad-media-row
- `cards.video_modal.css` — Stylesheet. Primary selectors include: .iad-videomodal-backdrop, .iad-videomodal-sheet, .iad-videomodal-sheet, .iad-videomodal-top, .iad-videomodal-title, .iad-videomodal-actions, .iad-videomodal-open, .iad-videomodal-open:hover

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.92 feed toolbar and pager styling
- Added feed toolbar, pagination, jump-to, and mode-toggle styles to the feed list layout split CSS.
- Added mobile wrapping so the top controls stay aligned instead of splitting awkwardly.

## 0.3.96 feed toolbar visibility fix
- `cards.feed_list_layout.css` now respects the Jump To `hidden` state explicitly and tightens the top toolbar/pager spacing so the numbered controls sit higher.
- The feed mode buttons, sort icon button, and Jump To button remain compact SVG-first controls with no visible text labels.

## 0.3.97 pagination toolbar row alignment
- Removed the extra sort icon chrome, kept the dropdown pill only, and shifted the top pager into the same row as the mode toggles.
- Tightened the desktop header layout so the summary and Jump To icon sit directly after the top pager instead of drifting to the far edge.
