# Architecture Notes: IA Goodbye

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-goodbye`
- Version in header: `0.1.1`
- Main entry file: `ia-goodbye.php`
- Declared purpose: Central account lifecycle authority for Atrium. Enforces delete/deactivate rules, blocks deleted re-login, and neutralises PeerTube→phpBB resurrection paths.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Contains WordPress user deletion logic.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-goodbye.php` — Runtime file for ia goodbye.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-goodbye.php` — Runtime file for class ia goodbye.


## Maintenance note

- Current delete flow now purges first-party Connect/Message content, scrubs phpBB-authored Discuss content, tombstones identifiers, and retains deleted identity-map rows in `status=deleted` form.


Update 2026-03-15
- Account deletion scrub path reads phpBB DB credentials via `IA_Engine::phpbb_db()`; older reference to `IA_Engine::phpbb()` was invalid in this stack and could fatal during delete.

- Update 2026-03-15 (v2): phpBB user tombstoning now depends on IA Auth's schema-tolerant delete path, which checks phpBB topic/user columns before updating them and emits a concrete PHP error-log entry if the tombstone write fails.

## Detailed delete-system architecture note (2026-03-15)

This plugin is the central orchestrator for irreversible frontend account deletion in the current Atrium stack.

### System role in the wider identity model

A single user can exist simultaneously as:

- a WordPress shadow user used for session cookies and frontend runtime,
- a phpBB canonical identity used as the main account authority,
- a PeerTube-linked identity and token source,
- an `ia_identity_map` row that binds those records together.

Because of that, deleting only the WordPress user is not sufficient. Other plugins can legitimately recreate the shadow user during future login or fallback-link flows unless a deletion marker exists first.

### Authority split

- Connect owns the existing frontend delete route and password-confirm UX.
- IA Goodbye owns irreversible lifecycle orchestration.
- IA Auth owns the final schema-tolerant phpBB tombstone write.
- The PeerTube fallback/login plugins must respect the tombstone and not recreate local accounts for deleted identifiers.

### Tombstone-first design

The critical design rule in the current stack is: write the tombstone before destructive cleanup begins.

Reason:
If the cleanup fails halfway through, a local resurrection block still exists. That is safer than deleting content first and leaving the login bridges free to recreate the account.

### Current deletion sequence

1. Connect AJAX calls into IA Goodbye.
2. IA Goodbye resolves the linked identity through `IA_Auth::instance()->db->get_identity_by_wp_user_id()` with fallbacks to WordPress usermeta and user data.
3. IA Goodbye writes `wp_ia_goodbye_tombstones`.
4. IA Goodbye deletes first-party Connect content.
5. IA Goodbye deletes IA Message content/memberships/invites.
6. IA Goodbye scrubs phpBB-authored post/topic content.
7. IA Goodbye calls into IA Auth for phpBB-user tombstoning.
8. IA Goodbye updates `wp_ia_identity_map` to `status='deleted'` and clears `wp_user_id`.
9. IA Goodbye deletes PeerTube token rows from local token tables.
10. IA Goodbye deletes the WordPress shadow user last.

### Local data stores touched by this plugin during delete

- `wp_ia_goodbye_tombstones`
- `wp_ia_identity_map`
- `wp_ia_connect_posts`
- `wp_ia_connect_attachments`
- `wp_ia_connect_comments`
- `wp_ia_connect_follows`
- `wp_ia_user_relations`
- `wp_ia_msg_threads`
- `wp_ia_msg_thread_members`
- `wp_ia_msg_messages`
- `wp_ia_msg_thread_invites`
- `wp_ia_peertube_tokens`
- `wp_ia_peertube_user_tokens`
- phpBB `posts/topics/users` tables via direct PDO and IA Auth phpBB bridge

### What this plugin intentionally does not do

- It does not expose a separate public API route of its own.
- It does not currently remote-delete the PeerTube account.
- It does not own the frontend modal/UI.
- It does not remove the identity-map row entirely; it converts it into a durable deleted marker.

### Password confirmation note

The current frontend delete path already requires password entry before deletion is accepted. In other words, accidental misclick is not the problem this plugin is solving. The problem this plugin is solving is cross-system resurrection after deletion.

