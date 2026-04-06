- 2026-04-06 deep-link correction patch: notification click routing now handles Stream in-SPA instead of falling through to generic navigation, pushing the full `?tab=stream&video=...&stream_comment=...&stream_reply=...` URL and calling the Stream modal opener so video/comment targets open directly in fullscreen mode.
# AJAX notes for ia-notify / .

- 2026-04-06 deep-link follow-up patch: Stream notification URLs now convert documented PeerTube notification video/comment payloads into local Atrium Stream deep links (`?tab=stream&video=<id>&stream_comment=<id>&stream_reply=<id>` when available) instead of dropping users onto generic remote URLs.
- Message notifications now carry `ia_msg_mid` in addition to `ia_msg_thread`, so clicking a message notification can open the thread and jump to the specific message that triggered it. Legacy rows are backfilled from stored notification meta.
- Read-state countdown after visiting a notification remains intentional and should be preserved.

# AJAX notes for ia-notify / .

- 2026-04-06 notification consolidation patch: ia-notify now groups repeated notifications client-side by person + surface (Messages / Connect / Discuss / Stream / System), shows expandable child rows with richer detail where a confirmed preview is available, adds a true `Clear all` action for local notifications, and merges documented PeerTube `My Notifications` reads into the inbox/read-count surface when a canonical user token is available.
- Local clear removes rows from `wp_ia_notify_items`; PeerTube clear is best-effort `read-all` because the uploaded PeerTube 8.1 spec documents read/read-all endpoints, not delete endpoints.
- Message notification detail now prefers the actual latest thread message preview and thread title from ia-message tables instead of a repeated generic sentence.
- Connect notification detail now prefers stored wall post/comment excerpts from confirmed `wp_ia_connect_posts` / `wp_ia_connect_comments` tables.
- Keep Stream notification parsing defensive: only call documented `GET /api/v1/users/me/notifications` plus documented read/read-all endpoints, and tolerate shape differences by checking fields before using them.

# AJAX notes for ia-notify / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `ia-notify.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.
