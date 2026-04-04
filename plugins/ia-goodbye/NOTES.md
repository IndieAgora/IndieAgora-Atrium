# Notes: IA Goodbye

## 0.1.2 account-deletion hard block + content purge

- Added a stronger delete path for frontend account deletion.
- Deletion now writes/keeps tombstones, purges first-party Atrium Connect content, removes authored IA Message content and memberships, and scrubs phpBB Discuss post/topic text before tombstoning the phpBB user record.
- Identity-map rows are retained in deleted state instead of being fully removed, so resurrection checks have a stable local marker.
- Kept the change centralized in IA Goodbye so existing Connect account-delete UI and routes do not need to move.

- 2026-03-15: Fixed account-delete fatal introduced by calling nonexistent `IA_Engine::phpbb()`. `scrub_phpbb_content()` now uses `IA_Engine::phpbb_db()` and preserves the existing phpBB scrub path.

- 2026-03-15: Kept IA Goodbye delete orchestration intact, but the underlying phpBB tombstone write is now handled by a schema-tolerant IA Auth path so delete does not fail just because one phpBB inactivity/name column is missing on this host.

## 2026-03-15 detailed account deletion system note

This plugin is now the deletion orchestrator for the current Atrium identity stack. The important point is that account deletion is not a simple WordPress-user delete. In this stack, a person may exist across four linked layers at once:

- WordPress shadow user for session/cookie/UI purposes
- phpBB canonical user for forum authority and shared identity
- PeerTube account and/or PeerTube token linkage
- `wp_ia_identity_map` row that ties the systems together

Deletion therefore has to block resurrection before it starts removing anything.

### Current deletion rule

Frontend deletion requires the user to provide their current password. There is no one-click accidental delete path in the current UI. The intended behaviour is irreversible deletion.

### Current live deletion order

1. Connect frontend AJAX calls the existing Connect account-delete endpoint.
2. Connect delegates lifecycle work to `IA_Goodbye->delete_account()`.
3. IA Goodbye resolves the current user across:
   - WordPress user ID
   - `ia_identity_map`
   - phpBB user ID fallback from usermeta if needed
   - email / username-clean identifiers
   - PeerTube user ID if present in the identity map
4. IA Goodbye writes a tombstone first in `wp_ia_goodbye_tombstones`.
5. Only after the tombstone exists does cleanup begin.
6. Connect-owned content is deleted.
7. IA Message-owned content and memberships are deleted.
8. phpBB-authored Discuss content is scrubbed/anonymised.
9. IA Auth performs schema-tolerant phpBB user tombstoning via `delete_user_preserve_posts()`.
10. `ia_identity_map` is retained but converted to `status='deleted'` with `wp_user_id=NULL`.
11. PeerTube token rows are deleted.
12. WordPress shadow user is deleted last.

### Why the tombstone must happen first

Without a pre-delete tombstone, this stack can recreate a deleted account through multiple fallback routes:

- phpBB recreation path
- PeerTube fallback login path
- WP shadow-user recreation path
- old identity-map relinking path

The tombstone is the local do-not-recreate marker. It exists to stop the loop where deleting only the WordPress shadow user allows the person to log in again and silently rebuild the local account chain.

### What the tombstone stores

`wp_ia_goodbye_tombstones` stores:

- `identifier_email`
- `identifier_username_clean`
- `phpbb_user_id`
- `peertube_user_id`
- deletion timestamp
- reason

The table is not the user record itself. It is a local resurrection block.

### Identity-map behaviour after deletion

The identity-map row is intentionally kept. It is not removed outright. Instead it is turned into a deletion marker so the local stack still has a stable record that this cross-system identity existed and was deliberately deleted.

Current behaviour:

- `wp_user_id` set to `NULL`
- `status` set to `deleted`
- `last_error` stamped with a deletion marker and time

This is important because full row removal makes debugging and resurrection prevention harder.

### Content handling policy in this plugin

Current deletion behaviour is split by subsystem:

#### Connect
- Deletes authored Connect posts
- Deletes related Connect attachments for those posts
- Deletes related Connect comments for those posts
- Deletes authored comments made by that user elsewhere
- Deletes follow rows owned by that user
- Deletes relevant relation rows

#### Message
- Deletes authored messages for the phpBB identity
- Deletes invite rows where the user is inviter or invitee
- Deletes thread-membership rows for the user
- Deletes orphaned threads only where membership removal leaves no remaining members

#### phpBB / Discuss
- Scrubs phpBB post subject/body and username marker for authored posts
- Scrubs topic titles for topics first-authored by that user
- Then calls IA Auth phpBB user tombstoning instead of attempting a hard destructive phpBB account removal that would risk referential breakage

### phpBB deletion note

The phpBB portion is deliberately schema-tolerant. This matters because the live host schema can differ from assumptions in plugin code. The current stack uses IA Auth to perform the final phpBB-user tombstoning so the update only touches columns that actually exist. This avoids the earlier failure mode where one missing phpBB column caused the entire delete path to fail.

### PeerTube note

This delete flow does not currently hard-delete the remote PeerTube account itself. What it does do is:

- preserve the local deletion marker
- remove local PeerTube token rows
- prevent the same deleted identifiers from automatically relinking or recreating the local account

That means the deleted local Atrium account cannot simply resurrect through the old fallback path using the same local identity.

### Re-registration rule

Deleted identifiers are not silently cleared for reuse. The current rule is: if the local identity has been deleted, the same identifier set should not auto-recreate the account. The user would need to return with genuinely new credentials/identity data that do not match the deleted local tombstone path.

### Frontend behaviour expectation

Connect should continue to own the existing deletion UI and endpoint. IA Goodbye should continue to own the destructive orchestration. This keeps the delete flow centralised rather than spreading deletion logic across Connect, Auth, Message, and the PeerTube fallback plugins.

