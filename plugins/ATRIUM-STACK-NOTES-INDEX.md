# Atrium Stack Notes Index

This index lists the per-plugin architecture note file added in this pass. Each plugin now has an `ARCHITECTURE-NOTES.md` file at plugin root.

- `ia-atrium/ARCHITECTURE-NOTES.md` — IA Atrium
- `ia-auth/ARCHITECTURE-NOTES.md` — IA Auth
- `ia-auth-peertube-fallback/ARCHITECTURE-NOTES.md` — IA Auth PeerTube Fallback
- `ia-cache-control/ARCHITECTURE-NOTES.md` — IA Cache Control
- `ia-connect/ARCHITECTURE-NOTES.md` — IA Connect
- `ia-context-menu-override/ARCHITECTURE-NOTES.md` — IndieAgora Context Menu Override
- `ia-discuss/ARCHITECTURE-NOTES.md` — IA Discuss
- `ia-discuss-richcards-fix/ARCHITECTURE-NOTES.md` — IA Discuss Richcards Fix
- `ia-engine/ARCHITECTURE-NOTES.md` — IA Engine
- `ia-goodbye/ARCHITECTURE-NOTES.md` — IA Goodbye
- `ia-hide-wp-admin-bar/ARCHITECTURE-NOTES.md` — IA Hide WP Admin Bar (Atrium Only)
- `ia-login/ARCHITECTURE-NOTES.md` — IA Login
- `ia-mail-suite/ARCHITECTURE-NOTES.md` — IA Mail Suite
- `ia-message/ARCHITECTURE-NOTES.md` — IA Message
- `ia-notify/ARCHITECTURE-NOTES.md` — IA Notify
- `ia-peertube-login-sync/ARCHITECTURE-NOTES.md` — IA PeerTube Login Sync
- `ia-peertube-token-mint-users/ARCHITECTURE-NOTES.md` — IA PeerTube Token Mint Users
- `ia-post/ARCHITECTURE-NOTES.md` — IA Post
- `ia-profile-menu/ARCHITECTURE-NOTES.md` — IA Profile Menu
- `ia-reset-modal-fix/ARCHITECTURE-NOTES.md` — IA Reset Modal Fix
- `ia-seo/ARCHITECTURE-NOTES.md` — IA SEO
- `ia-server-diagnostics/ARCHITECTURE-NOTES.md` — IA Server Diagnostics
- `ia-stream/ARCHITECTURE-NOTES.md` — IA Stream
- `ia-user/ARCHITECTURE-NOTES.md` — IA User
- `ia-user-peertube-fallback-clean/ARCHITECTURE-NOTES.md` — IA User PeerTube Fallback (Clean)

## Account deletion documentation focus

The current account-deletion system is documented primarily in:

- `ia-goodbye/NOTES.md`
- `ia-goodbye/ARCHITECTURE-NOTES.md`
- `ia-auth/README.md`
- `ia-connect/NOTES.md`

Supporting deletion/resurrection-boundary notes also exist in:

- `ia-auth-peertube-fallback/ARCHITECTURE-NOTES.md`
- `ia-peertube-login-sync/ARCHITECTURE-NOTES.md`
- `ia-user/ARCHITECTURE-NOTES.md`
- `ia-user-peertube-fallback-clean/ARCHITECTURE-NOTES.md`


## IA User admin-control note

The current stack now also includes an admin user-control surface in `ia-user`, documented in `ia-user/ARCHITECTURE-NOTES.md`. It is intended to let administrators edit the main frontend account fields and lifecycle actions from the backend while still using the same linked phpBB / WordPress / PeerTube logic where available.


## 2026-04-04 canonical auth documentation map

The final canonical auth diagram and the one-plugin consolidation planning notes are currently documented primarily in:

- `ia-auth/ARCHITECTURE-NOTES.md` — master canonical ladder, live-trace conclusions, and consolidation plan
- `ia-user-peertube-fallback-clean/ARCHITECTURE-NOTES.md` — current ladder owner / route-owner notes
- `ia-login/ARCHITECTURE-NOTES.md` — visible entry-surface notes
- `ia-auth-peertube-fallback/ARCHITECTURE-NOTES.md` — compatibility-surface notes
- `ia-peertube-login-sync/ARCHITECTURE-NOTES.md` — legacy/auxiliary-surface notes
- `ia-peertube-token-mint-users/ARCHITECTURE-NOTES.md` — token-maintenance role in the wider chain

Pithy stack summary preserved from this debugging cycle:
- today: auth braid with a confirmed dominant ladder
- target: one canonical ladder under one plugin owner


## 2026-04-04 Stream token-read hardening note

Latest confirmed position in the stack:

- Stream write actions now have one canonical token-read owner: `ia-peertube-token-mint-users`
- That owner now exposes a structured status contract to callers
- The wider stack still has two token stores, but Stream no longer needs to infer behaviour by mixing them during write actions


## 2026-04-04 open_basedir follow-up note

Latest confirmed position after the `ia-server-diagnostics` cleanup:

- `ia-server-diagnostics` sampler-path warning spam is no longer the dominant log source
- the remaining repeated `open_basedir` noise is WordPress core probing `wp-content/db.php` from `wp-includes/load.php`
- no further auth-plugin changes are planned before that path/config mismatch is understood
- the next required inspection is configuration-level, not plugin-level: `WP_CONTENT_DIR`, any custom content-dir/`ABSPATH` definitions, and the effective PHP `open_basedir` scope for the vhost / PHP-FPM pool
- token-store consolidation remains the next structural phase only after the remaining `db.php` warning is either removed or explicitly documented as benign in this hosting layout


## 2026-04-04 Stream status update

- Stream comment/rate auth recovery is working in production.
- `ia-server-diagnostics` open_basedir spam was reduced.
- The remaining `db.php` open_basedir warning was investigated and is documented as separate benign infrastructure noise.
- Stream now treats `wp_ia_peertube_user_tokens` as the sole authoritative token table for Stream reads/writes, while the legacy login-era table remains only for compatibility and observation.
