# Notes: includes / modules

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `agora-create.php` — Classes: IA_Discuss_Module_Agora_Create Functions/methods: __construct, boot, ajax_routes, ajax_create_agora
- `agoras.php` — Classes: IA_Discuss_Module_Agoras Functions/methods: __construct, boot, ajax_routes, ajax_agoras, ajax_agora_meta
- `diag.php` — Classes: IA_Discuss_Module_Diag Functions/methods: __construct, boot, ajax_routes, ajax_diag, ajax_probe, ajax_repair_agora_mods
- `feed.php` — Classes: IA_Discuss_Module_Feed Functions/methods: __construct, boot, ajax_routes, ajax_random_topic, ajax_feed
- `forum-meta.php` — Classes: IA_Discuss_Module_Forum_Meta Functions/methods: __construct, boot, ajax_routes, ajax_forum_meta
- `membership.php` — Classes: IA_Discuss_Module_Membership Functions/methods: __construct, boot, ajax_routes, ajax_join, ajax_leave, ajax_notify_set, ajax_cover_set
- `moderation.php` — Classes: IA_Discuss_Module_Moderation Functions/methods: __construct, boot, ajax_routes, current_phpbb_id, is_global_admin, can_manage_forum, ajax_my_moderation, list_kicked_users, ajax_cover_set, ajax_agora_settings_get, ajax_agora_settings_save, ajax_agora_setting_save_one, ajax_agora_unban, ajax_agora_delete
- `module-interface.php` — Functions/methods: boot, ajax_routes
- `panel.php` — Functions/methods: ia_discuss_module_panel_boot, ia_discuss_render_panel
- `search.php` — Classes: IA_Discuss_Module_Search Functions/methods: __construct, boot, ajax_routes, norm_q, clip, ajax_suggest, ajax_search
- `topic.php` — Classes: IA_Discuss_Module_Topic Functions/methods: __construct, boot, effective_topic_notify, ajax_routes, ajax_topic, ajax_mark_read
- `upload.php` — Classes: IA_Discuss_Module_Upload Functions/methods: __construct, boot, ajax_routes, ajax_upload
- `write.php` — Classes: IA_Discuss_Module_Write Functions/methods: __construct, boot, ajax_routes, extract_mentions, emit_mentions, ajax_forum_meta, ajax_new_topic, ajax_reply, ajax_share_to_connect, ajax_topic_notify_set, ajax_edit_post, ajax_delete_post, ajax_ban_user, ajax_unban_user

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.72 module note

- `topic.php` now records topic visits into the Discuss topic-history helper when a logged-in user opens a topic.


- `membership.php` — Private Agora leave now revokes accepted-invite access for non-moderators and returns redirect flags for the client to send the user back to global feeds.
