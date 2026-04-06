# IA Stream Black Style Reference

Purpose: preserve the implementation pattern that finally made Stream match the approved Black style without breaking shell ownership or rebuilding Stream templates.

## Source of truth and ownership
- Connect owns the saved user style preference.
- Atrium owns shared chrome and page-level shell response.
- IA Stream owns Stream-local repainting only.

Do not collapse these roles together.

## Final implementation pattern
1. Connect emits/maintains the active style state.
2. Atrium applies that state to shared shell surfaces.
3. Stream mirrors the resolved style onto `#ia-stream-shell[data-ia-stream-theme]`.
4. `ia-stream.theme.black.css` repaints Stream-owned internals from that local marker.

This local marker exists because relying only on outer `data-iac-style` selectors was not stable enough through SPA/tab transitions.

## What Black means in Stream
Black does **not** mean the old all-dark Stream cards.

For the approved site-wide Black style, Stream follows the same direction as Connect/Discuss/Post inside the black shell:
- light grey content surfaces,
- dark readable text,
- darker but restrained borders/framing,
- darkened SVG/icon controls where old white-on-dark assumptions would disappear on light surfaces,
- dark media/player stage retained where the surface is genuinely a video/player surface.

## Surface mapping
### Atrium-owned
- page background
- top nav
- bottom nav
- shell framing

### Stream-owned
- internal Stream tab strip
- section headers and feed framing
- video cards and channel/meta rows
- views/count/action controls
- search-result rows
- video detail surface next to/below the player
- comment composer
- comment/reply cards

### Special case
- The player/media stage stays dark.
- The metadata and action surfaces around the player are treated as readable light surfaces under Black.

## Files involved
- `assets/js/ia-stream.ui.shell.js`
  - Mirrors the active style onto the Stream shell.
  - Watches the existing Connect bridge event and shell mutations.
- `assets/css/ia-stream.theme.black.css`
  - Late override layer for Black-mode repainting.
- `assets/js/ia-stream.ui.comments.js`
  - Applies alternation classes to the flat live comment sequence.
- `includes/support/assets.php`
  - Keeps the Black bridge stylesheet enqueued after the normal Stream CSS stack.

## Why the later fixes were needed
### First issue: Stream still looked like old Stream
Outer shell selectors alone were not enough on some tab transitions. Stream needed a local mirrored marker.

### Second issue: video view channel/meta/icons stayed too faint
The video/detail view mixes a dark player with lighter detail surfaces. Generic feed-card overrides were not enough; modal/detail-level selectors had to be stronger.

### Third issue: reply alternation did not hold
The live markup is a flatter `.ia-stream-comment` sequence than the original nth-child CSS expected. JS-applied alternation classes are the stable fix under current markup.

### Fourth issue: Search results reopened as the default tab
Stored search state was being treated like route ownership. Stream should open to Discover unless the URL explicitly owns a Stream search route.

## Guardrails
- Do not move shared shell skinning into Stream.
- Do not add a second style preference store to Stream.
- Do not rebuild templates just to solve colour/contrast issues.
- Prefer late bridge CSS with narrow selectors over broad rewrites.
- Prefer JS-added semantic classes when live structure is too flat for reliable CSS-only targeting.
- For future styles, reuse the same pattern: preference owner -> shell owner -> plugin-local mirrored marker -> late plugin-local bridge CSS.

## Painful-experience note: how to stop future style work taking hours
- Do not start with a generic “darken Stream text” pass. First classify the failing surface: feed card, opened video modal, or threaded comment area.
- Always inspect the exact broken surface in-browser and write down the concrete selectors before editing CSS.
- If the problem appears only after opening a video, assume the deep-link/full-screen modal has its own selector path until proven otherwise.
- Compare the broken style against the approved Black behaviour by surface role, not by theme name. The rule is simple: light surface = dark readable text/icons; dark media stage can stay dark.
- Add fixes as the narrowest late bridge possible. Do not widen to unrelated Stream cards once the actual modal selectors are known.
- After each patch, verify these exact items in order:
  1. channel/subtitle text (`.iad-sub` / modal meta)
  2. video title (`.iad-card-title`)
  3. views/count/meta rows
  4. action SVGs/buttons
  5. comment author/time/text
- Record the final selector path in notes immediately after success so the next imported style does not restart the same hunt.
