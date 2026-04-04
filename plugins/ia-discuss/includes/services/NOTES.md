# Notes: includes / services

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `auth.php` — Classes: IA_Discuss_Service_Auth Functions/methods: __construct, is_logged_in, current_wp_user_id, current_phpbb_user_id, lookup_phpbb_user_id_by_identity_map, try_external_mapper, lookup_phpbb_user_id_by_username
- `membership.php` — Classes: IA_Discuss_Service_Membership Functions/methods: __construct, boot, t_members, t_covers, ensure_tables, current_user_phpbb_id, is_joined, join, leave, touch, get_notify_agora, set_notify_agora, list_notify_users, cover_url, set_cover_url, ensure_cron, cron_inactivity_tick, most_active_topic_last_month
- `notify.php` — Classes: IA_Discuss_Service_Notify Functions/methods: __construct, on_topic_created, notify_new_topic, set_membership_service, boot, ensure_tables, table_topic_notify, topic_notify_enabled, topic_notify_state, set_topic_notify, notify_reply, send_agora_inactivity_popular, ensure_actor_subscribed, get_topic_participant_ids, list_topic_participants, is_topic_participant, get_topic_optin_ids, resolve_canonical_phpbb_user_id, get_phpbb_user_row, make_topic_url …
- `phpbb-write.php` — Classes: IA_Discuss_Service_PhpBB_Write Functions/methods: __construct, boot, to_plain_text, ensure_bans_table, ban_user_in_forum, unban_user_in_forum, is_user_banned, edit_post, delete_post, create_topic, reply
- `phpbb.php` — Classes: IA_Discuss_Service_PhpBB Functions/methods: __construct, is_ready, db, prefix, table, detect_forum_counter_mode, detect_topic_last_post_id_mode, forum_counter_select_sql, diagnostics, probe, get_forum_row, get_feed_rows, get_random_topic_id, get_agoras_rows, get_topic_row, get_topic_posts_rows, user_is_forum_moderator, list_forum_moderator_ids, list_moderated_forums, user_has_global_acl_like, user_has_local_acl_like …
- `text.php` — Classes: IA_Discuss_Service_Text Functions/methods: squash_ws, limit_chars
- `upload.php` — Classes: IA_Discuss_Service_Upload Functions/methods: handle

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.72 service note

- `phpbb.php` now supports the personal feed tabs used by the Discuss sidebar: authored topics, replied-to topics, and visit history.
- `my_replies` is based on the logged-in user's own visible reply posts and excludes topics where the user only created the opening post.


## 0.3.74 private Agora service

- Added `agora-privacy.php` to own private/public Agora state and invite lifecycle storage.

- `agora-privacy.php` — Added `revoke_user_access()` so leaving a private Agora can remove accepted-invite access without altering moderator access rules.


## 0.3.79 service note

- `phpbb.php` now exposes `list_forum_moderator_ids()` so moderator-targeted actions such as private-Agora post reports can notify forum moderators resolved through either `moderator_cache` or phpBB ACL grants.

## 0.3.92 feed count/query helpers
- Added `list_forum_ids()`, `build_feed_query_parts()`, and `count_feed_rows()` in `phpbb.php`.
- Feed topic queries can now apply accessible-forum and blocked-poster filters in SQL so pagination totals and page fills stay aligned.
