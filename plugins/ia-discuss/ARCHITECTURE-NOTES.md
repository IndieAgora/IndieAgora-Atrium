# Architecture Notes: IA Discuss

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-discuss`
- Version in header: `0.3.89`
- Main entry file: `ia-discuss.php`
- Declared purpose: Atrium Discuss panel (phpBB-backed) with mobile-first feed + agoras + modal topic view.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options.
- Nonce strings seen in code: ia_discuss.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_discuss_agora_cover_set` — logged-in only; declared in `includes/modules/membership.php (module)`.
- `ia_discuss_agora_delete` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agora_invite_get` — logged-in only; declared in `includes/modules/membership.php (module)`.
- `ia_discuss_agora_invite_respond` — logged-in only; declared in `includes/modules/membership.php (module)`.
- `ia_discuss_agora_invite_user` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agora_join` — logged-in only; declared in `includes/modules/membership.php (module)`.
- `ia_discuss_agora_leave` — logged-in only; declared in `includes/modules/membership.php (module)`.
- `ia_discuss_agora_meta` — public/nopriv; declared in `includes/modules/agoras.php (module)`.
- `ia_discuss_agora_notify_set` — logged-in only; declared in `includes/modules/membership.php (module)`.
- `ia_discuss_agora_privacy_set` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agora_setting_save_one` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agora_settings_get` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agora_settings_save` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agora_unban` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_agoras` — public/nopriv; declared in `includes/modules/agoras.php (module)`.
- `ia_discuss_ban_user` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_cover_set` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_create_agora` — logged-in only; declared in `includes/modules/agora-create.php (module)`.
- `ia_discuss_delete_post` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_diag` — logged-in only; declared in `includes/modules/diag.php (module)`.
- `ia_discuss_edit_post` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_feed` — public/nopriv; declared in `includes/modules/feed.php (module)`.
- `ia_discuss_forum_meta` — public/nopriv; declared in `includes/modules/forum-meta.php (module)`.
- `ia_discuss_forum_meta` — public/nopriv; declared in `includes/modules/write.php (module)`.
- `ia_discuss_mark_read` — public/nopriv; declared in `includes/modules/topic.php (module)`.
- `ia_discuss_my_moderation` — logged-in only; declared in `includes/modules/moderation.php (module)`.
- `ia_discuss_new_topic` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_probe` — logged-in only; declared in `includes/modules/diag.php (module)`.
- `ia_discuss_random_topic` — public/nopriv; declared in `includes/modules/feed.php (module)`.
- `ia_discuss_repair_agora_mods` — logged-in only; declared in `includes/modules/diag.php (module)`.
- `ia_discuss_reply` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_report_post` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_search` — public/nopriv; declared in `includes/modules/search.php (module)`.
- `ia_discuss_search_suggest` — public/nopriv; declared in `includes/modules/search.php (module)`.
- `ia_discuss_share_to_connect` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_topic` — public/nopriv; declared in `includes/modules/topic.php (module)`.
- `ia_discuss_topic_notify_set` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_unban_user` — logged-in only; declared in `includes/modules/write.php (module)`.
- `ia_discuss_upload` — logged-in only; declared in `includes/modules/upload.php (module)`.
- `ia_user_block_toggle` — logged-in only; declared in `includes/support/user-rel-ajax.php`.
- `ia_user_follow_toggle` — logged-in only; declared in `includes/support/user-rel-ajax.php`.
- `ia_user_rel_status` — logged-in only; declared in `includes/support/user-rel-ajax.php`.

## API and integration notes

- `https://www.youtube.com/embed/videoseries?${params.toString()}`;
    }

    if (!meta.id) return null;
    if (meta.start) params.set(` referenced in `assets/js/ia-discuss.youtube.js`.
- `https://www.youtube-nocookie.com/embed/${encodeURIComponent(meta.id)}?${params.toString()}`;
  }

  function thumbUrl(meta) {
    if (!meta || !meta.id) return ` referenced in `assets/js/ia-discuss.youtube.js`.
- `https://www.youtube.com/embed/videoseries?` referenced in `includes/render/bbcode.php`.
- `https://www.youtube-nocookie.com/embed/` referenced in `includes/render/bbcode.php`.
- `https://www.google.com/s2/favicons?sz=64&domain_url=` referenced in `includes/render/bbcode.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `DEVELOPMENT-NOTES.md` — Local maintenance notes/documentation.
- `ENDPOINTS.md` — Local maintenance notes/documentation.
- `ERRORS-TO-AVOID.md` — Local maintenance notes/documentation.
- `NOTES.md` — Local maintenance notes/documentation.
- `README.md` — Local maintenance notes/documentation.
- `ia-discuss.php` — Runtime file for ia discuss.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- `assets/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/NOTES.md` — Local maintenance notes/documentation.
- `assets/README.md` — Local maintenance notes/documentation.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/css/NOTES.md` — Local maintenance notes/documentation.
- `assets/css/README.md` — Local maintenance notes/documentation.
- `assets/css/ia-discuss.agora.create.css` — Stylesheet for ia discuss agora create.
- `assets/css/ia-discuss.agora.css` — Stylesheet for ia discuss agora.
- `assets/css/ia-discuss.audio.css` — Stylesheet for ia discuss audio.
- `assets/css/ia-discuss.base.css` — Stylesheet for ia discuss base.
- `assets/css/ia-discuss.cards.css` — Stylesheet for ia discuss cards.
- `assets/css/ia-discuss.composer.css` — Stylesheet for ia discuss composer.
- `assets/css/ia-discuss.layout.css` — Stylesheet for ia discuss layout.
- `assets/css/ia-discuss.legacy.css` — Stylesheet for ia discuss legacy.
- `assets/css/ia-discuss.light.css` — Stylesheet for ia discuss light.
- `assets/css/ia-discuss.modal.css` — Stylesheet for ia discuss modal.
- `assets/css/ia-discuss.moderation.css` — Stylesheet for ia discuss moderation.
- `assets/css/ia-discuss.rules.css` — Stylesheet for ia discuss rules.
- `assets/css/ia-discuss.search.css` — Stylesheet for ia discuss search.
- `assets/css/ia-discuss.topic.css` — Stylesheet for ia discuss topic.

### `assets/css/split`

- Purpose: Directory used by this plugin.
- `assets/css/split/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/css/split/NOTES.md` — Local maintenance notes/documentation.
- `assets/css/split/README.md` — Local maintenance notes/documentation.

### `assets/css/split/cards`

- Purpose: Directory used by this plugin.
- `assets/css/split/cards/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/css/split/cards/NOTES.md` — Local maintenance notes/documentation.
- `assets/css/split/cards/README.md` — Local maintenance notes/documentation.
- `assets/css/split/cards/cards.card_body_actions.css` — Stylesheet for cards card_body_actions.
- `assets/css/split/cards/cards.feed_list_layout.css` — Stylesheet for cards feed_list_layout.
- `assets/css/split/cards/cards.icon_buttons.css` — Stylesheet for cards icon_buttons.
- `assets/css/split/cards/cards.links_modal_items.css` — Stylesheet for cards links_modal_items.
- `assets/css/split/cards/cards.media_thumbs.css` — Stylesheet for cards media_thumbs.
- `assets/css/split/cards/cards.pills_and_links_modal.css` — Stylesheet for cards pills_and_links_modal.
- `assets/css/split/cards/cards.video_modal.css` — Stylesheet for cards video_modal.

### `assets/css/split/search`

- Purpose: Directory used by this plugin.
- `assets/css/split/search/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/css/split/search/NOTES.md` — Local maintenance notes/documentation.
- `assets/css/split/search/README.md` — Local maintenance notes/documentation.
- `assets/css/split/search/search.results_list.css` — Stylesheet for search results_list.
- `assets/css/split/search/search.results_row_details.css` — Stylesheet for search results_row_details.
- `assets/css/split/search/search.suggestions_dropdown.css` — Stylesheet for search suggestions_dropdown.

### `assets/img`

- Purpose: Static image assets.
- `assets/img/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/img/NOTES.md` — Local maintenance notes/documentation.
- `assets/img/README.md` — Local maintenance notes/documentation.
- `assets/img/agora-player-logo.png` — Static image asset.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/README.md` — Local maintenance notes/documentation.
- `assets/js/ia-discuss.agora.create.js` — JavaScript for ia discuss agora create.
- `assets/js/ia-discuss.api.js` — JavaScript for ia discuss api.
- `assets/js/ia-discuss.audio.js` — JavaScript for ia discuss audio.
- `assets/js/ia-discuss.boot.js` — JavaScript for ia discuss boot.
- `assets/js/ia-discuss.core.js` — JavaScript for ia discuss core.
- `assets/js/ia-discuss.modtools.js` — JavaScript for ia discuss modtools.
- `assets/js/ia-discuss.router.js` — JavaScript for ia discuss router.
- `assets/js/ia-discuss.state.js` — JavaScript for ia discuss state.
- `assets/js/ia-discuss.ui.agora.js` — JavaScript for ia discuss ui agora.
- `assets/js/ia-discuss.ui.agora.membership.js` — JavaScript for ia discuss ui agora membership.
- `assets/js/ia-discuss.ui.composer.js` — JavaScript for ia discuss ui composer.
- `assets/js/ia-discuss.ui.feed.js` — JavaScript for ia discuss ui feed.
- `assets/js/ia-discuss.ui.moderation.js` — JavaScript for ia discuss ui moderation.
- `assets/js/ia-discuss.ui.moderation.js.bak` — Runtime file for ia discuss ui moderation js.
- `assets/js/ia-discuss.ui.rules.js` — JavaScript for ia discuss ui rules.
- `assets/js/ia-discuss.ui.search.js` — JavaScript for ia discuss ui search.
- `assets/js/ia-discuss.ui.shell.js` — JavaScript for ia discuss ui shell.
- `assets/js/ia-discuss.ui.shell.js.bak2` — Runtime file for ia discuss ui shell js.
- `assets/js/ia-discuss.ui.topic.js` — JavaScript for ia discuss ui topic.
- `assets/js/ia-discuss.youtube.js` — JavaScript for ia discuss youtube.

### `assets/js/split`

- Purpose: Directory used by this plugin.
- `assets/js/split/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/split/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/split/README.md` — Local maintenance notes/documentation.

### `assets/js/split/composer`

- Purpose: Directory used by this plugin.
- `assets/js/split/composer/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/split/composer/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/split/composer/README.md` — Local maintenance notes/documentation.
- `assets/js/split/composer/_composer.body.js` — JavaScript for _composer body.
- `assets/js/split/composer/composer.attachments_render.js` — JavaScript for composer attachments_render.
- `assets/js/split/composer/composer.bind_attachments.js` — JavaScript for composer bind_attachments.
- `assets/js/split/composer/composer.bind_state_and_files.js` — JavaScript for composer bind_state_and_files.
- `assets/js/split/composer/composer.export.js` — JavaScript for composer export.
- `assets/js/split/composer/composer.modal_state_and_files.js` — JavaScript for composer modal_state_and_files.
- `assets/js/split/composer/composer.shell_and_bind.js` — JavaScript for composer shell_and_bind.

### `assets/js/split/feed`

- Purpose: Directory used by this plugin.
- `assets/js/split/feed/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/split/feed/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/split/feed/README.md` — Local maintenance notes/documentation.
- `assets/js/split/feed/_feed.body.js` — JavaScript for _feed body.
- `assets/js/split/feed/feed.bind_card_actions.js` — JavaScript for feed bind_card_actions.
- `assets/js/split/feed/feed.card_template.js` — JavaScript for feed card_template.
- `assets/js/split/feed/feed.export.js` — JavaScript for feed export.
- `assets/js/split/feed/feed.links_modal.js` — JavaScript for feed links_modal.
- `assets/js/split/feed/feed.load_request.js` — JavaScript for feed load_request.
- `assets/js/split/feed/feed.media_blocks_and_pills.js` — JavaScript for feed media_blocks_and_pills.
- `assets/js/split/feed/feed.render.body.js` — JavaScript for feed render body.
- `assets/js/split/feed/feed.render.clicks_and_boot.js` — JavaScript for feed render clicks_and_boot.
- `assets/js/split/feed/feed.render.loading.js` — JavaScript for feed render loading.
- `assets/js/split/feed/feed.render_entry.js` — JavaScript for feed render_entry.
- `assets/js/split/feed/feed.utils.js` — JavaScript for feed utils.
- `assets/js/split/feed/feed.video_open_and_attachments.js` — JavaScript for feed video_open_and_attachments.
- `assets/js/split/feed/feed.video_parsers.js` — JavaScript for feed video_parsers.

### `assets/js/split/router`

- Purpose: Directory used by this plugin.
- `assets/js/split/router/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/split/router/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/split/router/README.md` — Local maintenance notes/documentation.
- `assets/js/split/router/_router.body.js` — JavaScript for _router body.
- `assets/js/split/router/router.event_bus.js` — JavaScript for router event_bus.
- `assets/js/split/router/router.export.js` — JavaScript for router export.
- `assets/js/split/router/router.feed_scroll_state.js` — JavaScript for router feed_scroll_state.
- `assets/js/split/router/router.open_pages.js` — JavaScript for router open_pages.
- `assets/js/split/router/router.render.agoras.js` — JavaScript for router render agoras.
- `assets/js/split/router/router.render.default_views.js` — JavaScript for router render default_views.
- `assets/js/split/router/router.render.entry.js` — JavaScript for router render entry.
- `assets/js/split/router/router.reply_submit_hook.js` — JavaScript for router reply_submit_hook.
- `assets/js/split/router/router.route_from_url.js` — JavaScript for router route_from_url.

### `assets/js/split/search`

- Purpose: Directory used by this plugin.
- `assets/js/split/search/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/split/search/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/split/search/README.md` — Local maintenance notes/documentation.
- `assets/js/split/search/_search.body.js` — JavaScript for _search body.
- `assets/js/split/search/search.export.js` — JavaScript for search export.
- `assets/js/split/search/search.results_clicks.js` — JavaScript for search results_clicks.
- `assets/js/split/search/search.results_load_and_page.js` — JavaScript for search results_load_and_page.
- `assets/js/split/search/search.results_render.js` — JavaScript for search results_render.
- `assets/js/split/search/search.suggestions_box.js` — JavaScript for search suggestions_box.
- `assets/js/split/search/search.suggestions_interactions.js` — JavaScript for search suggestions_interactions.
- `assets/js/split/search/search.utils.js` — JavaScript for search utils.

### `assets/js/split/topic`

- Purpose: Directory used by this plugin.
- `assets/js/split/topic/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/split/topic/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/split/topic/README.md` — Local maintenance notes/documentation.
- `assets/js/split/topic/_ta.body.js` — JavaScript for _ta body.
- `assets/js/split/topic/topic_actions.bind_actions.js` — JavaScript for topic_actions bind_actions.
- `assets/js/split/topic/topic_actions.clipboard_and_share.js` — JavaScript for topic_actions clipboard_and_share.
- `assets/js/split/topic/topic_actions.confirm_modal.js` — JavaScript for topic_actions confirm_modal.
- `assets/js/split/topic/topic_actions.export.js` — JavaScript for topic_actions export.
- `assets/js/split/topic/topic_actions.quote_insert.js` — JavaScript for topic_actions quote_insert.

### `assets/js/topic`

- Purpose: Directory used by this plugin.
- `assets/js/topic/ENDPOINTS.md` — Local maintenance notes/documentation.
- `assets/js/topic/NOTES.md` — Local maintenance notes/documentation.
- `assets/js/topic/README.md` — Local maintenance notes/documentation.
- `assets/js/topic/ia-discuss.topic.actions.js` — JavaScript for ia discuss topic actions.
- `assets/js/topic/ia-discuss.topic.media.js` — JavaScript for ia discuss topic media.
- `assets/js/topic/ia-discuss.topic.modal.js` — JavaScript for ia discuss topic modal.
- `assets/js/topic/ia-discuss.topic.render.js` — JavaScript for ia discuss topic render.
- `assets/js/topic/ia-discuss.topic.utils.js` — JavaScript for ia discuss topic utils.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/ENDPOINTS.md` — Local maintenance notes/documentation.
- `includes/NOTES.md` — Local maintenance notes/documentation.
- `includes/README.md` — Local maintenance notes/documentation.
- `includes/functions.php` — Runtime file for functions.
- `includes/ia-discuss.php` — Runtime file for ia discuss.

### `includes/modules`

- Purpose: Module classes or controller-like entry points.
- `includes/modules/ENDPOINTS.md` — Local maintenance notes/documentation.
- `includes/modules/NOTES.md` — Local maintenance notes/documentation.
- `includes/modules/README.md` — Local maintenance notes/documentation.
- `includes/modules/agora-create.php` — Runtime file for agora create.
- `includes/modules/agoras.php` — Runtime file for agoras.
- `includes/modules/diag.php` — Runtime file for diag.
- `includes/modules/feed.php` — Runtime file for feed.
- `includes/modules/forum-meta.php` — Runtime file for forum meta.
- `includes/modules/membership.php` — Runtime file for membership.
- `includes/modules/moderation.php` — Runtime file for moderation.
- `includes/modules/module-interface.php` — Runtime file for module interface.
- `includes/modules/panel.php` — Main panel renderer or mount point.
- `includes/modules/search.php` — Runtime file for search.
- `includes/modules/topic.php` — Runtime file for topic.
- `includes/modules/upload.php` — Runtime file for upload.
- `includes/modules/write.php` — Runtime file for write.

### `includes/render`

- Purpose: Rendering helpers for HTML, media, and text.
- `includes/render/ENDPOINTS.md` — Local maintenance notes/documentation.
- `includes/render/NOTES.md` — Local maintenance notes/documentation.
- `includes/render/README.md` — Local maintenance notes/documentation.
- `includes/render/attachments.php` — Runtime file for attachments.
- `includes/render/bbcode.php` — Runtime file for bbcode.
- `includes/render/media.php` — Runtime file for media.

### `includes/services`

- Purpose: Service-layer logic, data access, or integrations.
- `includes/services/ENDPOINTS.md` — Local maintenance notes/documentation.
- `includes/services/NOTES.md` — Local maintenance notes/documentation.
- `includes/services/README.md` — Local maintenance notes/documentation.
- `includes/services/agora-privacy.php` — Runtime file for agora privacy.
- `includes/services/auth.php` — Authentication-related logic.
- `includes/services/membership.php` — Runtime file for membership.
- `includes/services/notify.php` — Notification-related logic.
- `includes/services/phpbb-write.php` — phpBB integration logic.
- `includes/services/phpbb.php` — phpBB integration logic.
- `includes/services/reports.php` — Runtime file for reports.
- `includes/services/text.php` — Runtime file for text.
- `includes/services/upload.php` — Runtime file for upload.

### `includes/support`

- Purpose: Shared support code such as assets, security, install, and AJAX bootstrapping.
- `includes/support/ENDPOINTS.md` — Local maintenance notes/documentation.
- `includes/support/NOTES.md` — Local maintenance notes/documentation.
- `includes/support/README.md` — Local maintenance notes/documentation.
- `includes/support/ajax.php` — AJAX endpoint registration or callback logic.
- `includes/support/assets.php` — Asset enqueue and localization logic.
- `includes/support/security.php` — Security helpers such as nonce or permission checks.
- `includes/support/user-rel-ajax.php` — AJAX endpoint registration or callback logic.

### `tools`

- Purpose: Developer utilities or build scripts.
- `tools/ENDPOINTS.md` — Local maintenance notes/documentation.
- `tools/NOTES.md` — Local maintenance notes/documentation.
- `tools/README.md` — Local maintenance notes/documentation.
- `tools/build-assets.sh` — Asset enqueue and localization logic.
