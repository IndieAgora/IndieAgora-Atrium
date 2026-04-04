# Notes: assets / css

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `split/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `ia-discuss.agora.create.css` — Stylesheet. Primary selectors include: .iad-tab-action, .iad-modal-overlay, .iad-modal-sheet, .iad-modal-head, .iad-modal-title, .iad-modal-x, .iad-modal-body, .iad-field
- `ia-discuss.agora.css` — Stylesheet. Primary selectors include: .iad-agora-head, .iad-agora-banner, .iad-agora-inner, .iad-agora-name, .iad-agora-sub, .iad-agora-desc, .iad-agora-row, .iad-agora-row__open
- `ia-discuss.audio.css` — Stylesheet. Primary selectors include: .iad-audio-player, .iad-audio-player:before, .iad-audio-player > *, .iad-ap-head, .iad-ap-logo, .iad-ap-brand, .iad-ap-file, .iad-ap-main
- `ia-discuss.base.css` — Stylesheet. Primary selectors include: :root, .iad-shell, .ia-discuss-root h4, .iad-empty, .iad-user-link, .iad-user-link:hover, .iad-shell a:visited, .iad-shell a:hover
- `ia-discuss.cards.css` — Stylesheet. Primary selectors include: .iad-feed-list, .iad-card, .iad-card-main, .iad-card object, .iad-card object, .iad-card table, .iad-card pre, .iad-card code
- `ia-discuss.composer.css` — Stylesheet. Primary selectors include: .iad-composer-body[hidden], .iad-composer, .iad-composer-top, .iad-composer-toggle, .iad-dot, .iad-composer-body, .iad-input, .iad-textarea
- `ia-discuss.layout.css` — Stylesheet. Primary selectors include: .iad-shell, .iad-view, .iad-sidebar-backdrop, .iad-sidebar-backdrop[hidden], .iad-sidebar, .ia-discuss-root.is-sidebar-open .iad-sidebar, .iad-sidebar-head, .iad-sidebar-title
- `ia-discuss.legacy.css` — Stylesheet. Primary selectors include: .ia-discuss-root[data-iad-theme="legacy"], .ia-discuss-root[data-iad-theme="legacy"] .iad-card, .ia-discuss-root[data-iad-theme="legacy"] .iad-post, .ia-discuss-root[data-iad-theme="legacy"] .iad-sidebar, .iad-theme-sheet, .iad-theme-list, .iad-theme-choice, .iad-theme-choice.is-active
- `ia-discuss.light.css` — Stylesheet. Primary selectors include: .ia-discuss-root[data-iad-theme="light"], .ia-discuss-root[data-iad-theme="light"] .iad-btn, .ia-discuss-root[data-iad-theme="light"] .iad-btn:hover, .ia-discuss-root[data-iad-theme="light"] .iad-tab.is-active, .ia-discuss-root[data-iad-theme="light"] .iad-textarea, .ia-discuss-root[data-iad-theme="light"] .iad-textarea::placeholder, .ia-discuss-root[data-iad-theme="light"] .iad-card, .ia-discuss-root[data-iad-theme="light"] .iad-card.is-unread
- `ia-discuss.modal.css` — Stylesheet. Primary selectors include: .iad-modal[hidden], .iad-modal, .iad-modal-backdrop, .iad-modal-sheet, .iad-modal-top, .iad-x, .iad-modal-title, .iad-modal-body
- `ia-discuss.moderation.css` — Stylesheet. Primary selectors include: .iad-mod-form .iad-form-row, .iad-mod-form, .iad-mod-form .iad-help, .iad-mod-form .iad-label, .iad-mod-form .iad-textarea, .iad-mod-form .iad-textarea, .iad-form-actions, .iad-form-actions .iad-btn
- `ia-discuss.rules.css` — Stylesheet. Primary selectors include: .iad-modal-sheet.iad-modal-sheet--full, .iad-rules-body, .iad-rules-empty
- `ia-discuss.search.css` — Stylesheet. Primary selectors include: .iad-suggest, .iad-suggest--portal[data-iad-theme="light"], .iad-suggest--portal[data-iad-theme="light"] .iad-sug-row, .iad-suggest--portal[data-iad-theme="light"] .iad-sug-row:hover, .iad-suggest--portal[data-iad-theme="light"] .iad-sug-row.is-cta, .iad-suggest--portal[data-iad-theme="light"] .iad-sg-title, .iad-suggest--portal[data-iad-theme="light"] .iad-sug-sn, .iad-suggest--portal[data-iad-theme="light"] .iad-av
- `ia-discuss.topic.css` — Stylesheet. Primary selectors include: .iad-att-iframe, .iad-att-iframe.is-vertical, .iad-topic-modal .iad-attwrap, .iad-topic-modal .iad-att-media.iad-att-video, .iad-topic-modal .iad-att-media.iad-att-video, .iad-topic-modal .iad-att-media.iad-att-video .iad-att-iframe, .iad-compose-overlay, .iad-compose-sheet

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.65 light sidebar contrast patch
- `ia-discuss.layout.css` now carries the light-mode sidebar contrast fix so sidebar tabs/actions stay readable and the `Dark` theme button renders dark in light mode.

- 0.3.71: Topic fullscreen view now starts below the fixed top bar (72px offset) so the topic title/header are visible in topic view. Patch-only CSS change in `assets/css/ia-discuss.modal.css`.
- 0.3.83: `ia-discuss.modal.css` now lifts the fullscreen topic sheet to `top: 0` while topic-view topbar auto-hide is active so the topic title bar fills the reclaimed viewport space.

## 0.3.72 layout note

- Added minimal sidebar divider/subtitle styling for the new personal-feed subsection.


## 0.3.80 theme modal and legacy style

- Added `ia-discuss.legacy.css` with a phpBB prosilver-inspired legacy theme for users who prefer the classic forum look.
- The shared theme picker modal styling now also lives in `ia-discuss.legacy.css` so the new dark/light/legacy theme chooser stays in one small intent-labelled file.
## 0.3.81 legacy readability patch

- `ia-discuss.legacy.css` now overrides feed excerpt text and topic signature text/dividers for the legacy theme so feed previews and signatures remain readable on pale cards/posts.



## 0.3.89 AgoraBB layout styling

- `ia-discuss.agora.css` now includes the board-style index/forum table styling used only when the root has `data-iad-layout="agorabb"`.
- The change is layout-scoped and does not alter the existing theme chooser files.


## 0.3.90 MyBB colour themes

- `ia-discuss.legacy.css` now carries the whole classic-forum theme family instead of only the original legacy blue variant.
- The classic theme file now supports `legacy`, `black`, `calm`, `dawn`, `earth`, `flame`, `leaf`, `night`, `sun`, `twilight`, and `water` by applying shared forum-style selectors through `.iad-theme-classic` and changing colour variables per selected theme.
- Added `.iad-theme-picks` / `.iad-theme-pick` sidebar styles so the MyBB colour choices can be selected directly from the Discuss sidebar.

## 0.3.91 stronger MyBB schemes
- Renamed the Discuss sidebar theme subsection from `MyBB styles` to `Schemes`.
- Renamed the legacy blue picker label to `Blue` in the Discuss theme UI while keeping the stored theme key as `legacy` for compatibility.
- Expanded `assets/css/ia-discuss.legacy.css` so each MyBB-derived scheme now changes more of the forum chrome instead of mostly just accents: card backgrounds, alternate post rows, borders, sidebar gradients, modal header gradients, AgoraBB/topic header bars, and the Discuss topbar toggle all now vary by scheme.
- This change was based on reviewing the supplied screen recording frame-by-frame: the previous pass showed only small accent differences, so this patch intentionally broadens the visible scheme surfaces without changing Discuss routing or theme storage keys.

## 0.3.92 pagination visibility pass
- Classic/MyBB scheme rules now explicitly style feed pagination controls, load-more buttons, and sort controls so they remain visible across the scheme set.

## 0.3.94 pagination alignment polish
- `cards.feed_list_layout.css` now aligns the feed toolbar/jump-to layout more cleanly on desktop and mobile.
- `ia-discuss.legacy.css` now also styles the sort label text/icon in the MyBB-derived schemes so the toolbar remains readable there too.
## 0.3.95 pagination toolbar compaction
- Tightened feed toolbar alignment into compact icon controls, cleaner summary spacing, and a right-aligned jump form so pagination no longer breaks into oversized pills.
- Legacy/MyBB visibility rules still apply to the compact controls.


## 0.3.96 pagination visibility repair
- Added the missing `.iad-screen-reader-text` utility in `ia-discuss.base.css` so pagination toolbar buttons stay SVG-only visually while keeping accessible labels.
- Added `.iad-feed-jump[hidden]{display:none !important;}` and tightened toolbar/pager spacing in `split/cards/cards.feed_list_layout.css` so Jump To stays collapsed until clicked and the numbered pager sits closer to the top controls.

## 0.3.97 pagination row alignment follow-up
- Removed the extra sort icon button so sorting is controlled only by the existing dropdown pill.
- Moved the top pager into the main toolbar row and kept the jump form collapsed below the right side until explicitly toggled.
