# IA Auth (WordPress plugin)

IA Auth is Atrium's identity + session layer.

**Canonical user = phpBB (`phpbb_users`)**  
**WordPress users = shadow accounts (sessions only)**  
**PeerTube = backend via API + OAuth tokens**

This implementation follows your spec: identity map table + encrypted token store + optional queue. fileciteturn1file1

## What this plugin does right now

- `/ia-login/` and `/ia-register/` routes (no theme templates required)
- Shortcodes:
  - `[ia_auth_login]`
  - `[ia_auth_register]`
  - `[ia_auth_gate]` (for other Atrium features to block actions until login)
- phpBB credential verification (supports bcrypt + phpBB portable `$H$/$P$` + legacy md5)
- Creates/links a WP shadow user and logs them in (WP cookie session)
- Best-effort PeerTube token minting:
  - `GET /api/v1/oauth-clients/local`
  - `POST /api/v1/users/token`
  - refresh flow supported if a refresh token exists fileciteturn1file3
- Admin UI under **WP Admin → IA Auth**
  - Status
  - Configuration (phpBB DB creds, prefix, PeerTube URLs, policy toggles)
  - Identity map viewer
  - Migration preview + apply (WP shadow users + map rows)
  - Logs + audit

## Install

1. Upload the `ia-auth/` folder into:
   `wp-content/plugins/ia-auth/`
2. Activate **IA Auth** in WP Admin → Plugins
3. Go to **WP Admin → IA Auth → Configuration**
   - Fill in phpBB DB credentials and table prefix (`phpbb_` by default)
   - Fill in PeerTube base URL(s) if you want Stream tokens working

## Notes / intentional limitations (for safety)

- PeerTube user creation is not implemented yet (admin endpoint needs an admin token or a separate provisioning flow). The plugin currently assumes either:
  - PeerTube has public registrations enabled (`/api/v1/users/register`), or
  - a matching PeerTube account already exists and password grant will succeed.
- “Token-only (admin minted)” is a placeholder in the UI (future enhancement).
- Queue runner currently only implements `refresh_token`.

## Next steps (when you’re ready)

1) Add PeerTube user provisioning policy (public registration vs admin add-user).  
2) Extend migration tool to also link PeerTube accounts by email.  
3) Add “change password” UI in Atrium that updates phpBB + PeerTube.


## March 15, 2026 account-deletion enforcement

- Registration and shadow-user creation now respect IA Goodbye tombstones. Deleted identifiers are blocked from automatic recreation.
- Re-registration no longer clears tombstones for the same email/username. A deleted account must come back with different credentials.

- 2026-03-15: Hardened phpBB tombstone delete against schema drift by checking topic/user columns before updating them, and now logs the concrete SQL/column error to PHP error log when phpBB tombstoning fails.

## Detailed note: why IA Auth matters in account deletion

IA Auth is part of the delete system even though the delete button does not live here.

### Identity role

This plugin owns the main local identity machinery used by the delete flow:

- `ia_identity_map` lookup by WordPress user ID
- phpBB bridge methods used to deactivate or tombstone phpBB users
- shadow-user creation/linking during login and registration
- guard points where deleted identifiers must now be refused

### Why deletion cannot be treated as a simple WordPress delete

In this stack, WordPress is not the canonical account authority. A user can be rebuilt from phpBB or PeerTube-linked flows. That means deleting the WordPress shadow user on its own is not enough. If IA Auth still allows normal linking/creation for the same identifiers, the user can come straight back.

### Current deletion-related responsibilities in IA Auth

- resolve identity-map data for the WordPress user being deleted
- perform the final phpBB-user tombstone/update path
- keep that phpBB delete path schema-tolerant so host column drift does not fatal the delete flow
- refuse shadow-user recreation for tombstoned/deleted identifiers
- refuse registration flows that would silently clear a deleted identity marker for the same credentials

### Current live rule

Deletion is orchestrated by IA Goodbye, but IA Auth is the plugin that makes the local identity bridges obey deletion.

### phpBB tombstone note

The live stack does not assume every phpBB host has identical column availability. The delete bridge therefore checks which phpBB user/topic columns exist and only updates the columns actually present. This is why the current delete path is more reliable than the earlier fixed-schema attempt.

### Practical reading rule for future work

When debugging login resurrection, registration recreation, or delete failures, treat IA Auth as part of the delete system, not just the login system.


## Canonical login note (April 2026)

The preferred live login route is now `ia_user_login`, not `ia_auth_login`.
`ia_auth_login` remains as a compatibility shim so older forms do not fork the auth flow.
When `ia-user-peertube-fallback-clean` is active, IA Auth delegates login handling to that canonical ladder.
