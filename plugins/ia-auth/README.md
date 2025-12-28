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

