# Endpoint and Hook Notes

All AJAX actions below are authenticated `wp_ajax_*` endpoints and use the localised nonce `IA_MESSAGE.nonceBoot` unless stated otherwise.

## AJAX actions

### `ia_message_threads`
Purpose: fetch thread list for current phpBB user.
Input: `nonce`
Output: `threads` array rendered for UI.
Handler: `includes/support/ajax/threads.php`

### `ia_message_thread`
Purpose: fetch a single thread with paginated messages and mark returned range as read.
Input: `nonce`, `thread_id`, optional `limit`, optional `offset`
Output: `thread` object with messages, DM convenience meta, pagination fields.
Handler: `includes/support/ajax/threads.php`

### `ia_message_send`
Purpose: add a plain message to an existing thread.
Input: `nonce`, `thread_id`, `body`, optional `reply_to_message_id`
Output: `message_id`
Side effects: touches thread, marks sender read, notifies members, fires `ia_message_sent`.
Handler: `includes/support/ajax/messages.php`

### `ia_message_new_dm`
Purpose: create or reuse DM thread for target user and optionally send first message.
Input: `nonce`, `to_phpbb`, optional `body`
Compatibility: `to_phpbb` may also be a WP user id and is resolved via identity mapping.
Output: `thread_id`
Handler: `includes/support/ajax/messages.php`

### `ia_message_new_group`
Purpose: create a group thread, optionally with first message.
Input: `nonce`, `title`, `avatar_url`, `members` (comma-separated phpBB ids), optional `body`
Output: `thread_id`
Side effects: fires `ia_message_group_member_added`; may fire `ia_message_sent` for first message.
Handler: `includes/support/ajax/groups.php`

### `ia_message_user_search`
Purpose: search/browse candidate recipients.
Input: `nonce`, `q`, optional `limit`, optional `offset`
Output: `results` array with WP/phpBB identity payload and avatar URL where available.
Handler: `includes/support/ajax/users.php`

### `ia_message_group_invites`
Purpose: fetch pending invites for current user.
Input: `nonce`
Output: `invites`
Handler: `includes/support/ajax/groups.php`

### `ia_message_group_invite_send`
Purpose: invite a user to a group thread.
Input: `nonce`, `thread_id`, `to_phpbb`
Output: `invite_id`, and possibly `already_member`
Side effects: fires `ia_message_group_invited`
Handler: `includes/support/ajax/groups.php`

### `ia_message_group_invite_respond`
Purpose: accept or ignore an invite.
Input: `nonce`, `invite_id`, `response` (`accept` or `ignore`)
Output: `accepted` with `thread_id`, or `ignored`
Side effects: may upsert membership; fires accepted/ignored hooks.
Handler: `includes/support/ajax/groups.php`

### `ia_message_group_members`
Purpose: fetch group membership list and current-user mod state.
Input: `nonce`, `thread_id`
Output: `members`, `me_is_mod`
Handler: `includes/support/ajax/groups.php`

### `ia_message_group_kick`
Purpose: remove a member from a group.
Input: `nonce`, `thread_id`, `kick_phpbb`
Output: `kicked`
Side effects: fires `ia_message_group_member_kicked`
Handler: `includes/support/ajax/groups.php`

### `ia_message_unread_count`
Purpose: fetch total unread count for badge UI.
Input: `nonce`
Output: `count`
Handler: `includes/support/ajax/users.php`

### `ia_message_prefs_get`
Purpose: fetch WP-side messaging preferences.
Input: `nonce`
Output: `email`, `popup`
Storage: user meta key `ia_message_prefs`
Handler: `includes/support/ajax/users.php`

### `ia_message_prefs_set`
Purpose: persist WP-side messaging preferences.
Input: `nonce`, `prefs` JSON string
Output: `saved`
Handler: `includes/support/ajax/users.php`

### `ia_message_upload`
Purpose: upload a composer file and return a public URL + attachment metadata.
Input: `nonce`, uploaded `file`
Output: `url`, `mime`, `kind`, `name`, `attachment_id`
Handler: `includes/support/ajax/messages.php`

### `ia_message_forward`
Purpose: forward one message body into one or more DM threads.
Input: `nonce`, `source_thread_id`, `message_id`, `to` (comma-separated ids)
Output: `thread_ids`
Side effects: touches destination threads, notifies members, fires `ia_message_sent`
Handler: `includes/support/ajax/messages.php`

### `ia_message_user_rel_status`
Purpose: read follow/block state for a target user.
Input: `nonce`, `target_phpbb`
Output: `following`, `blocked_any`, `blocked_by_me`
Handler: `includes/support/ajax/users.php`

### `ia_message_user_follow_toggle`
Purpose: toggle follow state.
Input: `nonce`, `target_phpbb`
Output: `following`
Notes: blocked relationships short-circuit with 403.
Handler: `includes/support/ajax/users.php`

### `ia_message_user_block_toggle`
Purpose: toggle block state.
Input: `nonce`, `target_phpbb`
Output: `blocked_by_me`, `blocked_any`
Handler: `includes/support/ajax/users.php`

## Cross-plugin filters/actions currently observed

Filters:
- `ia_message_current_phpbb_user_id`
- `ia_message_phpbb_user_id_by_email`
- `ia_message_phpbb_lookup_email_callable`
- `ia_message_email_subject`
- `ia_message_email_body`
- `ia_message_phpbb_users_table`

Actions:
- `ia_user_follow_created`
- `ia_message_sent`
- `ia_message_group_member_added`
- `ia_message_group_invited`
- `ia_message_group_invite_accepted`
- `ia_message_group_invite_ignored`
- `ia_message_group_member_kicked`
- `ia_atrium_panel_{panelKey}`

## Front-end event surface in use

- `ia_atrium:tabChanged`
- `ia_atrium:navigate`
- `ia_atrium:requestTab`
- `ia_message:open_thread`

These should be treated as compatibility surfaces during refactors.
