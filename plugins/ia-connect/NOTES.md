# Notes: IA Connect

## Core entry points

- `ia-connect.php` — plugin header, constants, activation, boot wiring.
- `includes/functions.php` — shared helpers, phpBB identity mapping, privacy helpers, and page-level metadata helpers.
- `includes/modules/panel.php` — profile/wall renderer and Connect panel output.
- `includes/support/assets.php` — asset enqueue and front-end config payload.
- `includes/support/ajax.php` — Connect AJAX actions.

## Operating rule

Keep changes patch-only. Preserve existing routing, UI paths, AJAX actions, and phpBB/WordPress identity mapping.

## 0.5.10 page-level titles and share metadata groundwork

- Added server-side document-title resolution for Connect so shared links do not fall back to the global site title on Connect routes.
- Profile routes now use the resolved display name where available.
- Post routes (`ia_post`) now use the stored post title, or a short body excerpt when the post has no title.
- Added narrow `og:title` and `twitter:title` output in `wp_head` so page-level titles can start flowing into shared-link metadata.
- Kept the change confined to `includes/functions.php` and the root loader in `ia-connect.php`; no UI behaviour was changed.

## 0.5.11 client-side page title sync

- Fixed Connect page titles so modal/deep-link navigation updates the browser title immediately instead of waiting for a manual refresh.
- Added client-side `document.title` + `og:title` / `twitter:title` syncing for profile and post-modal states.
- Kept the patch narrow in `assets/js/ia-connect.js`; no feed, modal, or routing behavior was widened beyond title/meta refresh timing.


## 0.5.12 live title consistency fix

- Fixed client-side title drift on Connect where post routes could remain at `Post` and profile routes could keep an older title until refresh.
- Post-title resolution now falls back through modal title, Discuss-share title, embed title, then body excerpt.
- Server-side title resolution for shared Discuss posts now looks up the original Discuss topic title when the Connect post itself has no stored title.
- Profile resolution now also checks WordPress user slug when `ia_profile_name` is present.
- Added mutation/history observers so `document.title`, `og:title`, and `twitter:title` stay in sync during Connect SPA navigation.

- 0.5.13: tightened Connect title routing so modal/profile loading states now fall back to `Connect | IndieAgora` until the requested post/profile content has actually loaded, and pinned the client/server site-title source to `IndieAgora` so transient `| Free` suffixes cannot leak in during SPA navigation.

## 0.5.14 account deletion delegation retained

- Kept Connect's existing account-delete endpoint and UI path intact.
- Delete flow now relies on IA Goodbye for deeper purge/tombstone enforcement across phpBB, Message, and Connect data.

- 2026-03-15: Wrapped `ia_connect_ajax_account_delete()` in a `try/catch` so frontend delete failures return JSON instead of fatal HTML/non-JSON responses.

- 2026-03-15: Connect delete endpoint still delegates lifecycle work to IA Goodbye, but phpBB tombstoning underneath is now schema-tolerant and surfaces the underlying phpBB failure text instead of only the generic `phpBB delete failed.` string.

## 2026-03-15 detailed account-delete delegation note

Connect still owns the visible frontend delete action, but Connect is not the system of record for deletion. The current design is intentionally split:

- Connect owns the UI, password-confirm form, and AJAX entry point.
- IA Goodbye owns irreversible delete orchestration.
- IA Auth owns the final phpBB-user tombstone/update path.

This matters because a Connect-only delete would be incomplete in this stack. The same person can exist as:

- a Connect-visible WordPress shadow user,
- a phpBB canonical account,
- a PeerTube-linked identity,
- an `ia_identity_map` binding across systems.

So the Connect endpoint should continue to delegate rather than attempting to directly delete users itself.

### Frontend expectation

The current frontend expectation is:

- user enters current password,
- Connect validates/delegates,
- Connect receives structured JSON success/failure,
- deeper lifecycle work happens outside Connect.

### Why the JSON handling note matters

The earlier non-JSON error was caused by a deeper delete fatal bubbling up as HTML. The current Connect-side note is important because the endpoint should remain a clean JSON boundary even when a lower layer fails.

### Practical maintenance rule

Do not move destructive cross-system delete logic into Connect. Keep Connect as the frontend gate and response boundary only.


## 0.5.25 non-embeddable URL post/render fix

- Removed the stray `iaConnectRefreshPageTitle(root, postModal);` call left inside `renderVideoEmbedsFromText()`.
- That call referenced out-of-scope variables, so ordinary non-embeddable URLs in post bodies could throw during `renderCard()` on create/load.
- Resulting symptom matched the reported behavior: the post insert succeeded server-side, the frontend showed `Post failed`, and wall rendering could blank when that post was present.
- Patch kept narrow to `assets/js/ia-connect.js` plus version/notes sync.


## 0.5.26 render guard + stable baseline note

- Added a narrow defensive guard at the top of `renderCard()` so non-object values return early before any field access.
- Marked the current `0.5.25` URL-post/render fix line as the working stable baseline for this issue family.
- Diagnostic note for future incidents: avoid long trial-and-error by checking in this order:
  1. confirm the wall SQL returns the expected post IDs,
  2. confirm `ia_connect_post_list` returns real post payloads rather than empty arrays,
  3. only then inspect `renderCard()`/embed rendering for the first thrown client-side error.
- Practical rule: when AJAX returns matched IDs and non-empty payloads but the wall is blank, stop changing SQL/PHP query logic and inspect the first frontend render exception instead.
