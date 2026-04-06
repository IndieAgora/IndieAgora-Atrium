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

- 2026-04-04: Stream token recovery follow-up (`password_required` frontend handling) documented in `ia-stream/ARCHITECTURE-NOTES.md`.

- 2026-04-04: Added Stream follow-up notes for the active comment JS recovery gap, response-shape normalization, and missing local recoverable-token helpers in comments/feed/video surfaces.


- 2026-04-04: Production confirmation added that both admin and non-admin users now recover Stream comment posting through the password prompt ladder after invalid/expired token state. Remaining verification targets are reply and video-rate flows in the same normalized JS recovery build.


- 2026-04-04: Stream hardening updated again. Live testing confirmed prompted recovery now works for comment posts, replies, comment votes, and video ratings. Legacy `wp_ia_peertube_tokens` usage was reduced from compatibility import to observation-only, and trace logging was expanded around canonical hit/miss, refresh recovery, lazy mint, and Stream password-prompt recovery.


## 2026-04-05 route map

- `DEEP-LINKS.md` — stack-level deep-link map for Connect / Discuss / Stream. Intended as source material for the later SEO pass so route-aware titles and metadata use the already-confirmed query-state contract.


## 2026-04-05 title ownership rule in the shared Atrium shell

- Connect, Discuss, and Stream all keep client-side title sync code, but hidden panels must not keep writing `document.title` after the user switches tabs.
- In the shared shell, page-title writers must always confirm the active surface first (`tab=<surface>` and/or Atrium's current active panel state) before mutating browser/meta titles.
- This rule exists specifically to stop cross-tab title stomping such as Connect's profile title persisting over Discuss or Stream views.

## 2026-04-05 title ownership race note

- Live failure captured: browser title remained `A Tree Stump | IndieAgora` across Stream/Discuss even after the first title guard patch.
- Deeper root cause: Atrium tab switches update `data-active-tab` before the `?tab=` query string. Hidden surface timers can therefore wake inside a short stale-URL window.
- Rule tightened: title ownership checks must prefer Atrium's current active shell surface first, then fall back to URL state, then visible-panel state. Do not trust URL-first gating inside the shared shell.

## 2026-04-05 per-user homepage preference

- Connect settings now expose a per-user homepage selector for Connect / Discuss / Stream.
- Atrium default-tab resolution now accepts a filtered default so homepage entry can respect that preference without overriding explicit deep links.


## 2026-04-06 style baseline map for future MyBB-theme work

Use these note files first before implementing later style packs:

- `ia-atrium/NOTES.md` — global style ownership rules for shared shell/chrome
- `ia-message/NOTES.md` — concise IA Message style rules and regression boundaries
- `ia-message/docs/DEVELOPMENT-NOTES.md` — implementation baseline for future IA Message theme ports using Black as the reference model
- `ia-message/docs/LIVE-NOTES.md` — live confirmation of the Black-style behaviour and the side-based bubble rule

Decision order for future theme ports:
1. confirm ownership boundary
2. preserve behavioural semantics from Black
3. map visual roles
4. then apply new palette/texture choices
