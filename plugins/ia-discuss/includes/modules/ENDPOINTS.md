# includes/modules endpoint notes

This folder contains the feature modules that own the main Discuss AJAX surface.

## Dispatcher model

Each module declares actions through `ajax_routes()`. Those actions are registered as `wp_ajax_{action}` and, when marked public, `wp_ajax_nopriv_{action}` by `includes/support/ajax.php`.

## Module-to-endpoint map

### `agora-create.php`
- `ia_discuss_create_agora` → `ajax_create_agora()` → authenticated

### `agoras.php`
- `ia_discuss_agoras` → `ajax_agoras()` → public
- `ia_discuss_agora_meta` → `ajax_agora_meta()` → public

### `diag.php`
- `ia_discuss_diag` → `ajax_diag()` → authenticated
- `ia_discuss_probe` → `ajax_probe()` → authenticated
- `ia_discuss_repair_agora_mods` → `ajax_repair_agora_mods()` → authenticated

### `feed.php`
- `ia_discuss_feed` → `ajax_feed()` → public
- `ia_discuss_random_topic` → `ajax_random_topic()` → public

### `forum-meta.php`
- `ia_discuss_forum_meta` → `ajax_forum_meta()` → public

### `membership.php`
- `ia_discuss_agora_join` → `ajax_join()` → authenticated
- `ia_discuss_agora_leave` → `ajax_leave()` → authenticated
- `ia_discuss_agora_notify_set` → `ajax_notify_set()` → authenticated
- `ia_discuss_agora_cover_set` → `ajax_cover_set()` → authenticated

### `moderation.php`
- `ia_discuss_my_moderation` → `ajax_my_moderation()` → authenticated
- `ia_discuss_agora_settings_get` → `ajax_agora_settings_get()` → authenticated
- `ia_discuss_agora_settings_save` → `ajax_agora_settings_save()` → authenticated
- `ia_discuss_agora_setting_save_one` → `ajax_agora_setting_save_one()` → authenticated
- `ia_discuss_cover_set` → `ajax_cover_set()` → authenticated
- `ia_discuss_agora_unban` → `ajax_agora_unban()` → authenticated
- `ia_discuss_agora_delete` → `ajax_agora_delete()` → authenticated

### `search.php`
- `ia_discuss_search_suggest` → `ajax_suggest()` → public
- `ia_discuss_search` → `ajax_search()` → public

### `topic.php`
- `ia_discuss_topic` → `ajax_topic()` → public
- `ia_discuss_mark_read` → `ajax_mark_read()` → public

### `upload.php`
- `ia_discuss_upload` → `ajax_upload()` → authenticated

### `write.php`
- `ia_discuss_forum_meta` → `ajax_forum_meta()` → public
- `ia_discuss_new_topic` → `ajax_new_topic()` → authenticated
- `ia_discuss_reply` → `ajax_reply()` → authenticated
- `ia_discuss_share_to_connect` → `ajax_share_to_connect()` → authenticated
- `ia_discuss_topic_notify_set` → `ajax_topic_notify_set()` → authenticated
- `ia_discuss_edit_post` → `ajax_edit_post()` → authenticated
- `ia_discuss_delete_post` → `ajax_delete_post()` → authenticated
- `ia_discuss_ban_user` → `ajax_ban_user()` → authenticated
- `ia_discuss_unban_user` → `ajax_unban_user()` → authenticated

## Maintenance warning

`ia_discuss_forum_meta` appears in both `forum-meta.php` and `write.php`. Treat that as a potential collision and verify boot order before changing either handler.
