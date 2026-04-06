- 2026-04-06 reply-post follow-up: PeerTube reply writes in Stream must target the thread id from `/comment-threads` list payloads, not the root comment id from the rendered node. The prior hotfix separated clicked-node id from write target correctly for UI placement, but still used the wrong write target. Token trace in debug during reproduction showed canonical token + refreshed token ready, so this follow-up keeps auth flow intact and only swaps reply write targeting to the thread id contract.
- 2026-04-06 comment reply hotfix: Stream reply actions now keep two IDs distinct in the video modal—clicked comment id for UI placement, and root thread comment id for the PeerTube write call. This matches the PeerTube comments contract where replies are added to the root comment thread, preventing failures when replying to another user's nested reply or any non-root node in an existing thread.
- 2026-04-06 style repair follow-up 2: imported MyBB Stream readability now also targets the explicit `#ia-stream-shell[data-ia-stream-theme]` bridge, because outer shell attributes can drop during SPA/modal transitions. This hotfix is limited to non-default/non-black styles and forces dark text/icons for video modal titles/channel/meta plus comment/reply author text on light surfaces.
- 2026-04-06 style repair follow-up: generic imported MyBB styles now apply the same readability checkpoints proven in Black for Stream video/feed cards—darker channel/meta text, darker SVG/action controls, and alternating reply fills in the video page/comment view.
- 2026-04-06 style repair: Stream theme bridge now preserves every imported style key locally instead of only mirroring Black.
- 2026-04-06 style repair: added a late generic MyBB Stream layer so non-default/non-black schemes now recolour stream cards, headers, tabs, player-side surfaces, and alternating reply cards.
- 2026-04-06 style import reset: Stream now keeps the Black layout treatment but swaps in each imported style's shell/card/header palette so cards and player-side surfaces visibly change with the selected style.
## 0.1.9 Black style bridge hardening
- Follow-up after live review: Stream still rendered in its old dark skin even while Connect Black and Atrium Black shell chrome were active.
- Root cause was style bridging that relied too heavily on outer `data-iac-style` selectors. On some SPA/tab transitions, Stream-owned internals needed an explicit plugin-local theme marker to repaint reliably.
- `assets/js/ia-stream.ui.shell.js` now mirrors the active Connect style onto `#ia-stream-shell` itself via `data-ia-stream-theme="black"`, listens for the existing `ia:connect-style-changed` event, and watches `data-iac-style` mutations on `html`, `body`, and `#ia-atrium-shell`.
- `assets/css/ia-stream.theme.black.css` now also targets that explicit Stream-local marker with stronger late bridge rules so Stream cards, headers, tabs, buttons, meta text, and other Stream-owned surfaces follow the approved Black treatment consistently.
- Scope remains patch-only and Stream-local. Shared shell chrome/background ownership stays with `ia-atrium`.

# AJAX notes for ia-stream / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `ia-stream.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

- 2026-04-05: Added Stream page-title resolution for direct routes and SPA transitions. Browser/meta titles now reflect Discover, Browse videos, Subscriptions, Search: <query>, channel deep-links, and the active video title when a Stream modal is open.
- 2026-04-05: Added a stack-level `DEEP-LINKS.md` map so Connect / Discuss / Stream route parameters are documented for the later SEO pass.

- 2026-04-05 follow-up: Added a Stream title-ownership guard so hidden Stream observers cannot overwrite Connect or Discuss titles inside the shared Atrium shell. Stream now only writes browser/meta titles while `tab=stream` (or the Stream panel is the active Atrium surface).

- 2026-04-05 second follow-up: title ownership detection was corrected again after live testing showed `A Tree Stump | IndieAgora` still persisting on Stream routes. The guard now trusts Atrium's active shell tab before the URL because tab switches briefly leave a stale `?tab=` in place while hidden observers are still alive. Stream falls back to URL and panel visibility only when the shell state is unavailable.

- 2026-04-05 SEO follow-up: IA SEO now emits canonical Stream sitemap URLs for Discover, public channels, and public videos only. Logged-in or duplicate-heavy Stream states (subscriptions/history/search/playlists/comment-focus/reply-focus) remain excluded on purpose.


## 2026-04-05 IA SEO metadata consumer note
- IA SEO now consumes confirmed public PeerTube read surfaces already present in IA Stream for SEO metadata work.
- Public channel metadata is read from `/api/v1/video-channels/{handle}`.
- Public video metadata is read from `/api/v1/videos/{id}`.
- Public comment thread sampling for Stream video metadata is read from `/api/v1/videos/{id}/comment-threads`.
- This is read-only SEO/metadata consumption. No IA Stream route shape or token flow was changed in this patch.


- 2026-04-05 entry-URL fix: hidden Stream boot no longer claims the browser URL on first load. `ia-stream.ui.shell.setTab()` now rewrites query params only when Stream is the active Atrium surface or the current route is already a Stream route. This prevents homepage entry from being silently rewritten to `?tab=stream` while Connect is the visible default panel.

- 2026-04-06 style planning note: Connect-selected style now flows into shared Atrium chrome globally. Future `ia-stream` style work should follow the same ownership split used for Discuss/Post: Atrium owns shell/chrome/background; `ia-stream` should only skin stream-owned cards, controls, feeds, and modal internals.

- 2026-04-06 Stream Black bridge: the saved Connect style now skins `ia-stream` internals when Black is active, using the same approved light-surface/dark-text direction as the rest of the stack. Scope stayed plugin-owned: Stream cards, tabs, feeds, channels, search rows, action controls, and modal/comment surfaces were retuned, while Atrium remains the owner of shared shell chrome/background.
- 2026-04-06 style guard: future Stream theme work should continue to read Connect/Atrium style notes first and map Stream surfaces by role (panel/card/meta/control/modal) rather than reintroducing the old hard-black gradients on shared-facing surfaces.
- 2026-04-06 black Stream follow-up: in video modal view, channel/meta text and SVG action icons were still too faint against the light-black surface, and comment/reply cards needed the same alternating fill rhythm used in Connect. Patch keeps the player surface dark but raises modal meta/icon contrast and alternates threaded comment card fills for readability.
- 2026-04-06 Stream tab default follow-up: entering Stream from another Atrium tab must land on Discover unless the current URL explicitly owns a Stream search route (`stream_q` / `stream_view=search`). Stored search text alone must not force the Search results tab on entry.

- 2026-04-06 0.1.11: Black Stream follow-up. Forced readable card/video meta + SVG/icon contrast for channel/count rows, and moved comment/reply alternation to a JS-applied class because live reply markup is a flat `.ia-stream-comment` sequence rather than a consistently wrapped nth-child structure.
## 2026-04-06 final Black style implementation map for Stream
- Confirmed implementation pattern for Stream under the approved Black theme is not a full dark reskin. It is a role-based remap to the same light-surface / dark-text treatment already approved in Connect and the Atrium shell.
- The saved Connect style remains the source of truth. Stream does not own the selected style value and must not introduce its own settings screen or parallel style storage.
- Runtime flow is now:
  1. Connect saves/announces the active style.
  2. Atrium paints shared shell chrome/background from that state.
  3. Stream listens and mirrors the active style onto `#ia-stream-shell[data-ia-stream-theme]`.
  4. Stream-owned CSS repaints only Stream-owned internals from that local marker.
- This split exists because relying only on outer shell selectors was not reliable enough during SPA/tab swaps. A plugin-local theme marker makes Stream repaint deterministic without moving ownership of shared shell chrome away from Atrium.
- `assets/css/ia-stream.theme.black.css` is a late bridge stylesheet, not a replacement skin. It overrides the historical dark-default Stream presentation only where Black mode requires it.
- Enqueue order matters. The Black bridge must load after the existing Stream base/layout/cards/modal/player files so the patch can override defaults instead of duplicating or rewriting the original asset stack.
- Surface mapping used during this pass:
  - Atrium-owned: page background, top nav, bottom nav, shared shell framing.
  - Stream-owned: tab strip inside Stream, section headers, feed cards, channel/meta rows, count/action controls, video detail surfaces, modal comment composer, comment/reply cards, search rows.
  - Player-owned behaviour preserved: the actual player/media stage stays dark so video playback still reads as a media surface rather than a light document card.
- Card treatment used for Black mode in Stream now follows the same approved pattern seen elsewhere in the stack: light grey body surface, dark readable text, darker but still restrained framing, and darkened SVG/icon controls where the old white-on-dark assumptions no longer hold.
- The first pass failed because card/modal internals were still inheriting old Stream defaults in some views. The corrective passes intentionally strengthened selectors and, where necessary, used `!important` on colour-only bridge rules rather than restructuring markup or touching unrelated behaviour.
- Video post view required separate follow-up work because it mixes a dark player stage with lighter metadata/action surfaces below and beside it. Channel name, counts, and action SVGs needed explicit modal/card-level overrides instead of the earlier generic feed-card rules.
- Reply alternation also required a separate implementation path. Pure CSS structural assumptions were not stable enough against the real live markup. The final implementation marks the flat `.ia-stream-comment` sequence in JS and lets CSS style the alternation classes, which is more reliable under the current render tree and safer than changing the render structure.
- Stream entry-state behaviour is now documented as part of style/readability expectations: opening Stream should land on Discover unless the URL explicitly owns a Stream search route. Stored prior search text must not make Search results look like the default home of the panel.
- Future style work for Stream should keep using surface-role mapping. Do not reintroduce the older all-dark gradients on content cards just because Stream historically started dark. Under the approved Black style, shared-facing content surfaces are intentionally light enough for readable text while still sitting inside a darker shell.
- Future plugin work should assume the same pattern is reusable for additional styles: Connect owns preference, Atrium owns shell response, feature plugins own their internals via a local mirrored theme marker and late bridge CSS.

- 2026-04-06 hotfix v3: narrowed the imported MyBB Stream fix to the video/modal page only. Added explicit `#ia-stream-shell[data-ia-stream-theme="..."]` palette variables plus direct readable text/icon overrides for modal titles, channel/meta text, comment authors, and action icons so light themes no longer inherit washed-out white on pale surfaces.

- 2026-04-06 hotfix v4: added a final non-default/non-black `#ia-stream-shell[data-ia-stream-theme]` readability safety net so any leftover white card/meta/comment text and action SVGs in Stream collapse to the dark MyBB text palette, not the old dark-card defaults.

- 2026-04-06 hotfix v5: The Stream video deep-link modal is appended directly to `document.body`, so shell-scoped MyBB readability rules do not reach modal-only descendants like `.iad-sub`, `.iad-card-title`, and modal comment/meta icon text. Added direct `:is(html, body, #ia-atrium-shell)[data-iac-style] ... .ia-stream-modal ...` dark-text overrides for non-default, non-black imported styles only.
- 2026-04-06 modal deep-link readability postmortem: the remaining unreadable Stream text was not in ordinary feed cards. It lived in the full-screen deep-link video modal, and inspector confirmed that modal descendants such as `.iad-sub`, `.iad-card-title`, and modal comment/meta rows can render outside the earlier shell-scoped repaint assumptions. Future fixes must inspect the live deep-link modal first, confirm where the node is mounted, and patch the exact modal selectors before widening any generic Stream colour sweep.
- 2026-04-06 style-work process rule for Stream: when a user reports “looks fine in feed, broken in opened video”, do not guess from the browse/feed view. Reproduce the deep-link modal route itself, inspect the real mounted node, record the exact selector path, and compare against the approved Black reference before changing anything. This is faster and safer than repeated broad CSS darkening.
- 2026-04-06 prevention note: for imported light MyBB styles, treat Stream readability in three checkpoints and sign off each separately: (1) feed cards, (2) opened video deep-link modal metadata/actions, (3) comment/reply author/meta/text. Do not mark Stream style work complete until all three checkpoints are verified against screenshots.
- 2026-04-06 hotfix v8: restored the Stream inline reply button after v7 accidentally left `openInlineReply()` referencing removed `threadRootId` state. The regression was client-side only: the click path aborted before opening the inline reply box or issuing any PeerTube request. Keep the current reply write target logic unchanged; this patch only repairs the inline reply opener.

- 2026-04-06: Stream comment UX hotfix: reply and delete actions are now reflected immediately in the modal DOM, then a delayed comment reload re-syncs with PeerTube. This avoids the user having to hard refresh after a successful reply/delete while still tolerating PeerTube/WP-AJAX read-after-write lag. Keep this patch-only; do not replace it with blind immediate reload-only behaviour unless the backend read path is proven strongly consistent.
