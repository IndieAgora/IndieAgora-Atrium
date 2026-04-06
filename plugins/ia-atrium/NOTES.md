- 2026-04-06 style repair follow-up: imported MyBB styles now keep shared Atrium search/history rows readable and pin the top-nav site name to the active theme accent instead of leaving recent-search controls/text washed out.
- 2026-04-06 style repair: added a late generic MyBB Atrium shell layer so imported Connect styles now repaint shared chrome, shell background, modal chrome, and bottom nav without touching Default or Black.
- 2026-04-06 style import reset: Atrium chrome now picks up imported Connect styles through a late palette bridge. Scope stays shell-only: top bar, menu, bottom nav, and modal chrome.
# AJAX notes for ia-atrium / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `ia-atrium.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

## 2026-04-05 search overlay snippets and per-user history

- Atrium overlay search items now render a third text line when the underlying result includes a snippet.
- Discuss topic/reply suggestions now surface their existing snippet payloads in the shared overlay.
- Connect post/comment suggestions now surface the new Connect search snippets in the shared overlay.
- Added per-user local recent-search history in the overlay, with single-item delete and clear-all controls.


## 2026-04-05 regression boundary for shared search overlay work

- Shared overlay work must stay inside Atrium search UI/rendering only.
- Do not patch Connect wall card rendering from Atrium search work.
- If Connect results need richer text, prefer adding search-specific `snippet` fields on the search endpoint rather than reusing or rewriting wall render/link/embed code.

## 2026-04-05 search overlay cleanup follow-up

- Search history chips were restyled inside Atrium search CSS so recent-search controls no longer fall back to browser-default button chrome.
- Shared overlay snippet rendering now sanitizes HTML-like/bbcode-like search text before display, clips around the typed match, and highlights the typed query in the preview line.
- Guard repeated: these fixes stay inside Atrium search UI/CSS only and do not touch Connect wall card rendering, URL/embed handling, or modal render paths.

- 2026-04-05 homepage preference support: Atrium default-tab resolution now runs through `ia_atrium_default_tab`, allowing a per-user homepage preference to decide the first visible surface only when the request has no explicit `tab` route.

- 2026-04-06 Discuss follow-up: when Connect Black is the selected style and Atrium is showing the real `ia-discuss` panel, Atrium shell chrome now follows the same approved Connect Black treatment for the top nav, tab menu, profile menu, and bottom nav instead of leaving the old hard black shell in place.

- 2026-04-06 global style ownership correction: Atrium now seeds the saved Connect style onto the shell/html/body at render time and owns the shared chrome response globally. Top nav, bottom nav, shell background, and composer shell now follow the selected Connect style across tabs instead of each feature plugin re-theming shared navigation separately.
- 2026-04-06 style architecture note: shared Atrium chrome is the global style owner. Connect style selection should flow into Atrium once, and Atrium should skin the shared shell everywhere from that single state. Do not restyle top nav, bottom nav, or page background independently inside feature plugins unless a surface is genuinely plugin-owned.

- 2026-04-06 style boundary note: plugin-owned content should still style its own internals under the shared Atrium style state. In practice that means Atrium owns shell/chrome/background, Discuss owns Discuss cards and pills, Post owns composer internals, and Profile Menu owns its destructive menu rows.

- 2026-04-06 cross-plugin style baseline note: approved feature-plugin theme work should use the same method proven in Black style. Atrium owns the global shell state and shared chrome; each tab plugin consumes that state only for plugin-owned internals. Future MyBB-style work should start from this ownership model before choosing colours or decorative treatment.

- 2026-04-06 hotfix v3: darkened the topbar tab selector label/caret for imported light MyBB themes so the current-tab dropdown remains readable against the light selector pill.
