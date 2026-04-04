# IA Discuss Endpoint Index

This file is the master endpoint map for the plugin. Update it whenever an AJAX action, browser route, upload target, or asset-localised endpoint changes.

## Server transport model

IA Discuss currently exposes **WordPress AJAX** actions through `wp-admin/admin-ajax.php`.

- Base transport: `IA_DISCUSS.ajaxUrl` localised in `includes/support/assets.php`
- Security nonce: `IA_DISCUSS.nonce`
- Dispatcher: `includes/support/ajax.php`
- Direct AJAX handlers outside the module dispatcher: `includes/support/user-rel-ajax.php`
- REST API routes: **none registered in this plugin**
- `admin-post.php` routes: **none registered in this plugin**

## Master AJAX action map

| Action | Public | Handler | Source file |
|---|---:|---|---|
| `ia_discuss_agoras` | yes | `IA_Discuss_Module_Agoras::ajax_agoras()` | `includes/modules/agoras.php` |
| `ia_discuss_agora_meta` | yes | `IA_Discuss_Module_Agoras::ajax_agora_meta()` | `includes/modules/agoras.php` |
| `ia_discuss_create_agora` | no | `IA_Discuss_Module_Agora_Create::ajax_create_agora()` | `includes/modules/agora-create.php` |
| `ia_discuss_diag` | no | `IA_Discuss_Module_Diag::ajax_diag()` | `includes/modules/diag.php` |
| `ia_discuss_probe` | no | `IA_Discuss_Module_Diag::ajax_probe()` | `includes/modules/diag.php` |
| `ia_discuss_repair_agora_mods` | no | `IA_Discuss_Module_Diag::ajax_repair_agora_mods()` | `includes/modules/diag.php` |
| `ia_discuss_feed` | yes | `IA_Discuss_Module_Feed::ajax_feed()` | `includes/modules/feed.php` |
| `ia_discuss_random_topic` | yes | `IA_Discuss_Module_Feed::ajax_random_topic()` | `includes/modules/feed.php` |
| `ia_discuss_forum_meta` | yes | `IA_Discuss_Module_Forum_Meta::ajax_forum_meta()` and also `IA_Discuss_Module_Write::ajax_forum_meta()` | `includes/modules/forum-meta.php`, `includes/modules/write.php` |
| `ia_discuss_agora_join` | no | `IA_Discuss_Module_Membership::ajax_join()` | `includes/modules/membership.php` |
| `ia_discuss_agora_leave` | no | `IA_Discuss_Module_Membership::ajax_leave()` | `includes/modules/membership.php` |
| `ia_discuss_agora_notify_set` | no | `IA_Discuss_Module_Membership::ajax_notify_set()` | `includes/modules/membership.php` |
| `ia_discuss_agora_cover_set` | no | `IA_Discuss_Module_Membership::ajax_cover_set()` | `includes/modules/membership.php` |
| `ia_discuss_my_moderation` | no | `IA_Discuss_Module_Moderation::ajax_my_moderation()` | `includes/modules/moderation.php` |
| `ia_discuss_agora_settings_get` | no | `IA_Discuss_Module_Moderation::ajax_agora_settings_get()` | `includes/modules/moderation.php` |
| `ia_discuss_agora_settings_save` | no | `IA_Discuss_Module_Moderation::ajax_agora_settings_save()` | `includes/modules/moderation.php` |
| `ia_discuss_agora_setting_save_one` | no | `IA_Discuss_Module_Moderation::ajax_agora_setting_save_one()` | `includes/modules/moderation.php` |
| `ia_discuss_cover_set` | no | `IA_Discuss_Module_Moderation::ajax_cover_set()` | `includes/modules/moderation.php` |
| `ia_discuss_agora_unban` | no | `IA_Discuss_Module_Moderation::ajax_agora_unban()` | `includes/modules/moderation.php` |
| `ia_discuss_agora_delete` | no | `IA_Discuss_Module_Moderation::ajax_agora_delete()` | `includes/modules/moderation.php` |
| `ia_discuss_search_suggest` | yes | `IA_Discuss_Module_Search::ajax_suggest()` | `includes/modules/search.php` |
| `ia_discuss_search` | yes | `IA_Discuss_Module_Search::ajax_search()` | `includes/modules/search.php` |
| `ia_discuss_topic` | yes | `IA_Discuss_Module_Topic::ajax_topic()` | `includes/modules/topic.php` |
| `ia_discuss_mark_read` | yes | `IA_Discuss_Module_Topic::ajax_mark_read()` | `includes/modules/topic.php` |
| `ia_discuss_upload` | no | `IA_Discuss_Module_Upload::ajax_upload()` | `includes/modules/upload.php` |
| `ia_discuss_new_topic` | no | `IA_Discuss_Module_Write::ajax_new_topic()` | `includes/modules/write.php` |
| `ia_discuss_reply` | no | `IA_Discuss_Module_Write::ajax_reply()` | `includes/modules/write.php` |
| `ia_discuss_share_to_connect` | no | `IA_Discuss_Module_Write::ajax_share_to_connect()` | `includes/modules/write.php` |
| `ia_discuss_topic_notify_set` | no | `IA_Discuss_Module_Write::ajax_topic_notify_set()` | `includes/modules/write.php` |
| `ia_discuss_edit_post` | no | `IA_Discuss_Module_Write::ajax_edit_post()` | `includes/modules/write.php` |
| `ia_discuss_delete_post` | no | `IA_Discuss_Module_Write::ajax_delete_post()` | `includes/modules/write.php` |
| `ia_discuss_ban_user` | no | `IA_Discuss_Module_Write::ajax_ban_user()` | `includes/modules/write.php` |
| `ia_discuss_unban_user` | no | `IA_Discuss_Module_Write::ajax_unban_user()` | `includes/modules/write.php` |
| `ia_user_rel_status` | no direct `nopriv` hook | `ia_discuss_ajax_user_rel_status()` | `includes/support/user-rel-ajax.php` |
| `ia_user_follow_toggle` | no direct `nopriv` hook | `ia_discuss_ajax_user_follow_toggle()` | `includes/support/user-rel-ajax.php` |
| `ia_user_block_toggle` | no direct `nopriv` hook | `ia_discuss_ajax_user_block_toggle()` | `includes/support/user-rel-ajax.php` |

## Notes on duplicated action names

`ia_discuss_forum_meta` is declared in both `includes/modules/forum-meta.php` and `includes/modules/write.php`. The dispatcher builds one action map at boot, so this duplicated declaration should be treated as a maintenance hazard and checked carefully before future route work.

## Browser route/query-state surface

These are not server endpoints, but they are part of the plugin's externally visible navigation contract because the SPA router reads and writes them.

| Query param | Meaning |
|---|---|
| `iad_view` | Current Discuss view. Observed values include `new`, `replies`, `noreplies`, `mytopics`, `myreplies`, `myhistory`, `agoras`, `agora`, `topic`, `search`, and `moderation`. |
| `iad_q` | Search query text. |
| `iad_topic` | Topic id for the topic page. |
| `iad_post` | Post id to scroll/highlight on topic open. |

The router implementation lives in `assets/js/ia-discuss.router.js` and mirrored maintenance slices in `assets/js/split/router/`.

## Front-end endpoint bootstrap

The JavaScript runtime consumes the following localised values from `window.IA_DISCUSS`:

- `ajaxUrl`: WordPress `admin-ajax.php`
- `nonce`: nonce for action posts
- `connect.ajaxUrl`: WordPress `admin-ajax.php` for the optional Connect integration
- `connect.nonces.user_search`: Connect user-search nonce when that plugin is active

## Update rule

Whenever any of the following changes, update this file and the nearest folder-level `ENDPOINTS.md`:

- AJAX action names
- visibility (`public` vs authenticated)
- handler ownership or file location
- browser query routes or route semantics
- localised transport URLs or nonces
- any direct client caller of an action

`iad_view` now also accepts the personal feed values `mytopics`, `myreplies`, and `myhistory`.

