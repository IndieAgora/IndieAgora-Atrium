# Notes: includes

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `modules/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `render/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `services/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `support/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `functions.php` — Functions/methods: ia_discuss_log, ia_discuss_repair_agora_moderator_cache, ia_discuss_is_atrium_page, ia_discuss_clean_out_buffer, ia_discuss_json_ok, ia_discuss_json_err, ia_discuss_wp_user_id_from_phpbb, ia_discuss_phpbb_user_id_from_wp, ia_discuss_display_name_from_phpbb, ia_discuss_avatar_url_from_phpbb, ia_discuss_signature_html_from_phpbb, ia_user_rel_table, ia_user_rel_ensure_table, ia_user_rel_is_following, ia_user_rel_toggle_follow, ia_user_rel_is_blocked_any, ia_user_rel_is_blocked_by_me, ia_user_rel_toggle_block, ia_user_rel_blocked_ids_for
- `ia-discuss.php` — Functions/methods: ia_discuss_require_if_exists, ia_discuss_admin_notice, ia_discuss_boot

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.72 personal feed support

- `functions.php` now includes lightweight topic-history helpers backed by WP user meta.
- `modules/topic.php` records a topic visit for the logged-in WP user whenever a topic is opened.
- `services/phpbb.php` now supports `my_topics`, `my_replies`, and `my_history` feed tabs while leaving the existing global feed tabs unchanged.


## 0.3.74 private Agora support

- Added `services/agora-privacy.php` for private/public Agora state and invite acceptance records.
- Moderation, membership, feed, topic, forum-meta, agoras, and write modules now consult the privacy service instead of relying on UI-only hiding.


## 0.3.75 private Agora follow-up

- `modules/feed.php` now resolves the viewer through `IA_Discuss_Service_Auth` to keep private-Agora feed gating consistent with topic, forum-meta, membership, and write paths.
- `modules/moderation.php` invite notifications now deep-link to Discuss explicitly.


## 0.3.89 mode boundary note

- No PHP endpoints were changed for AgoraBB mode. The new board-style navigation uses the existing Agoras, forum-meta, and feed endpoints.
