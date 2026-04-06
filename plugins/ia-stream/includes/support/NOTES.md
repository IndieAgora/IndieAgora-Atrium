## 2026-04-06 asset note — black style bridge hardening
- Added explicit Stream-local theme synchronization in `assets/js/ia-stream.ui.shell.js` and corresponding late CSS bridge rules in `assets/css/ia-stream.theme.black.css`.
- Reason: live testing showed that relying only on outer `data-iac-style` selectors was not always enough to repaint Stream-owned internals after shell/tab transitions.
- Keep `ia-stream-theme-black.css` enqueued late, after the base/layout/cards/modal files, so the bridge can override the default dark Stream skin without restructuring existing assets.

# AJAX notes for ia-stream / includes/support

Files in this directory inspected for AJAX handling:

- `ajax.php`
- `assets.php`
- `security.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

- 2026-04-06 asset note: enqueue order now includes `ia-stream-theme-black` after the modal stylesheet so the Connect-selected Black theme can override Stream internals without duplicating shell styles into Atrium or Connect.
- 2026-04-06 black Stream follow-up: in video modal view, channel/meta text and SVG action icons were still too faint against the light-black surface, and comment/reply cards needed the same alternating fill rhythm used in Connect. Patch keeps the player surface dark but raises modal meta/icon contrast and alternates threaded comment card fills for readability.
- 2026-04-06 Stream tab default follow-up: entering Stream from another Atrium tab must land on Discover unless the current URL explicitly owns a Stream search route (`stream_q` / `stream_view=search`). Stored search text alone must not force the Search results tab on entry.

- 2026-04-06 0.1.11: Black Stream follow-up. Forced readable card/video meta + SVG/icon contrast for channel/count rows, and moved comment/reply alternation to a JS-applied class because live reply markup is a flat `.ia-stream-comment` sequence rather than a consistently wrapped nth-child structure.
## 2026-04-06 asset/style implementation detail
- `includes/support/assets.php` now treats `ia-stream-theme-black` as the final colour bridge layer for Stream-owned Black styling.
- Keep that stylesheet after the normal Stream stack (`base`, `layout`, `cards`, `channels`, `player`, `modal`) so it can remain a narrow override file instead of becoming a duplicate stylesheet.
- The supporting JS/CSS split used by this pass is intentional:
  - JS (`assets/js/ia-stream.ui.shell.js`) resolves and mirrors the active style locally onto the Stream shell.
  - CSS (`assets/css/ia-stream.theme.black.css`) paints Stream-owned internals from that local marker.
  - JS (`assets/js/ia-stream.ui.comments.js`) applies alternation classes where live markup shape is flatter than the original CSS assumptions.
- This is the preferred pattern for future Stream theme work because it avoids three failure modes seen in earlier attempts: relying only on outer shell selectors, overloading Atrium with plugin-owned skinning, and assuming nested comment markup that does not consistently exist live.
- Black-mode readability checkpoints confirmed during the final passes:
  - channel/meta row under cards and in video view must stay readable on light surfaces;
  - SVG/count/action controls must not inherit pale-on-light values;
  - player stays dark, but adjacent detail surfaces do not;
  - replies/comments should alternate for easier scanning, matching the broader Connect direction.
