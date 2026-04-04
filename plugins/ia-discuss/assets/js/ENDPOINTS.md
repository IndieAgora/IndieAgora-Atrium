# assets/js endpoint notes

This folder contains the live front-end callers for the plugin's AJAX surface. It does not register server endpoints, but it is the primary client of `admin-ajax.php` actions defined under `includes/modules/` and `includes/support/`.

## Transport bootstrap

- `window.IA_DISCUSS.ajaxUrl` → WordPress `admin-ajax.php`
- `window.IA_DISCUSS.nonce` → nonce used by the API wrapper and direct `fetch()`/`FormData` calls
- Optional Connect integration also uses a localised AJAX URL and user-search nonce

## Runtime caller map by file

| File | Endpoint usage |
|---|---|
| `ia-discuss.api.js` | Base `post()` wrapper and upload transport for `ia_discuss_upload` |
| `ia-discuss.agora.create.js` | `ia_discuss_create_agora` |
| `ia-discuss.ui.agora.js` | `ia_discuss_forum_meta`, `ia_discuss_feed` |
| `ia-discuss.ui.agora.membership.js` | `ia_discuss_agora_join`, `ia_discuss_agora_leave`, `ia_discuss_agora_notify_set`, `ia_discuss_agora_cover_set` |
| `ia-discuss.ui.feed.js` | `ia_discuss_feed`, `ia_discuss_share_to_connect` |
| `ia-discuss.ui.search.js` | `ia_discuss_search_suggest`, `ia_discuss_search` |
| `ia-discuss.ui.topic.js` | `ia_discuss_topic`, `ia_discuss_topic_notify_set`, `ia_discuss_feed`, `ia_discuss_random_topic` |
| `ia-discuss.ui.moderation.js` | `ia_discuss_my_moderation`, `ia_discuss_agora_settings_get`, `ia_discuss_agora_setting_save_one`, `ia_discuss_agora_delete`, `ia_discuss_agora_unban`, `ia_discuss_upload`, `ia_discuss_cover_set` |
| `ia-discuss.router.js` | `ia_discuss_agoras`, `ia_discuss_forum_meta`, `ia_discuss_random_topic`, `ia_discuss_edit_post`, `ia_discuss_new_topic`, `ia_discuss_reply` and browser query-route control |
| `topic/ia-discuss.topic.actions.js` | `ia_discuss_delete_post`, `ia_discuss_ban_user`, `ia_discuss_unban_user` |

## Browser route ownership

The main browser route contract is implemented in `ia-discuss.router.js`.

Observed route/query state:

- `iad_view`
- `iad_q`
- `iad_topic`
- `iad_post`

## Split-source note

The runtime bundles above are mirrored by maintenance slices under `split/`. When endpoint caller logic changes, keep both the split source and generated runtime bundle aligned.

- Browser route/query-state readers now also recognise `iad_view=mytopics`, `iad_view=myreplies`, and `iad_view=myhistory` through the existing router/feed path.

