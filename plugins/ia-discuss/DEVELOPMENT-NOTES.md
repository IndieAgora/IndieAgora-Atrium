# IA Discuss Development Notes

This file is the persistent maintenance policy for the plugin. Update it whenever behaviour, structure, or workflow changes.

## Working rules

- Default to patch-first changes unless a broader refactor is explicitly requested.
- Preserve existing behaviour, providers, integrations, selectors, and UI paths unless a change note records a deliberate break.
- Assume SPA-style DOM replacement and remounts. Avoid one-shot bindings that only work on first render.
- Do not silently narrow functionality. If a change would alter visible behaviour elsewhere, record the impact here before shipping it.
- Keep assets enqueued through `includes/support/assets.php` with stable WordPress handles.
- For the large JS bundles, edit the split source slices in `assets/js/split/` first, then run `./tools/build-assets.sh`.
- Keep README.md and NOTES.md current in every folder that changes.

- Keep `ENDPOINTS.md` current at root and in the nearest affected folders whenever AJAX actions, browser query routes, upload targets, or localised transport values change.

## Current asset strategy

The live site continues to load generated runtime bundles because that is the safest way to preserve behaviour and WordPress dependency handles. The atomised source-of-truth now lives in the split folders. The split files are ordered concatenation slices and are not intended to be enqueued individually.

### Source folders to generated bundles

- `assets/js/split/feed/*` → `assets/js/ia-discuss.ui.feed.js`
- `assets/js/split/search/*` → `assets/js/ia-discuss.ui.search.js`
- `assets/js/split/composer/*` → `assets/js/ia-discuss.ui.composer.js`
- `assets/js/split/router/*` → `assets/js/ia-discuss.router.js`
- `assets/js/split/topic/*` → `assets/js/topic/ia-discuss.topic.actions.js`

## 0.3.60 change log

- Added folder-level README.md and NOTES.md coverage across the plugin tree.
- Cleaned the split JS source tree into contiguous slices that map directly to the generated runtime bundles.
- Implemented a real `tools/build-assets.sh` rebuild step for the generated JS assets.
- Documented the generated-runtime workflow inside `includes/support/assets.php`.
- Bumped the plugin version to `0.3.60`.

## When a new plugin version is uploaded later

1. Diff the new version against this documented tree before making behavioural changes.
2. Update the relevant local README.md and NOTES.md files when files move, appear, or disappear.
3. If a large JS bundle changes, mirror the change in the split source slices and rebuild the generated bundle.
4. Record any user-visible behavioural deltas here before final packaging.

## 0.3.60 change log

- Fixed YouTube playlist precedence so playlist URLs render through the playlist embed endpoint instead of being reduced to a single-video embed.
- Kept the patch confined to the existing YouTube parsing/rendering layers in PHP and JS.
- Updated notes in the affected folders to record the playlist-specific handling.


YouTube playlist URLs now preserve the selected `v=` video when one is present, embedding that video in playlist context (`/embed/{videoId}?list=...`) and falling back to the playlist-only `videoseries` endpoint only for list-only URLs. This avoids the unavailable-player case seen with some playlist posts while still keeping playlist behaviour.
## 0.3.61 change log

- Patched YouTube playlist embedding after validating that some playlist posts became unavailable when the selected `v=` item was discarded.
- Current rule: if a YouTube URL contains both `v=` and `list=`, keep the selected video and the playlist context together; only use `videoseries` for list-only URLs.
- Rebuilt the generated feed runtime bundle after updating the split source slices.
## 0.3.62 change log

- Narrow YouTube patch only.
- Playlist URLs that contain both `v=` and `list=` now use the standard YouTube embed domain in playlist context: `/embed/{videoId}?list=...`.
- List-only playlists still use `videoseries`.
- Single-video embeds were left on the existing no-cookie path to avoid widening the behavioural surface.

## 0.3.63 change log

- Rebuilt YouTube handling around one canonical parser/runtime helper in `assets/js/ia-discuss.youtube.js`.
- Playlist URLs are now treated as playlist embeds only and use the playlist embed form with `listType=playlist`.
- Topic media, feed media, and the PHP render layer now follow the same three-mode rule: single video, short, or playlist.
- Added a dedicated WordPress script handle for the shared YouTube helper and wired it as a dependency of feed/topic media assets.

## 0.3.64 playlist fallback cards
- YouTube playlist URLs no longer render as inline iframes. They now render as simple playlist cards that open the playlist on YouTube.
- Single videos and Shorts keep inline embed behaviour.

## 0.3.65 change log

- Narrow CSS-only patch for the Discuss sidebar in light mode.
- Restored readable dark-on-light sidebar tab and action button text.
- Kept the light-mode theme toggle labelled `Dark` visually dark so the control state reads clearly.

## 0.3.66 playlist iframe restore

- Restored inline YouTube playlist iframes so playlist URLs are not downgraded to click-out cards in topic rendering paths.
- Playlist URLs with both `v=` and `list=` now embed the selected video on `youtube.com/embed/{videoId}?list=...`.
- List-only playlist URLs still use the playlist embed form with `listType=playlist`.
- Feed and topic JavaScript now use the same shared helper output for playlist embeds.

## 0.3.67 playlist iframe endpoint correction

- Switched playlist iframe generation from mixed video-or-playlist embed URLs to the dedicated YouTube playlist iframe endpoint: `https://www.youtube.com/embed/videoseries?list=...`.
- Kept playlist rendering inline in topic/feed views, but stopped forcing the selected `v=` item into the iframe path because that form was producing the unavailable-player state.
- Playlist `index` and `start` values are still preserved when present in the pasted URL.


## 0.3.68 change log

- Narrow render-layer patch only.
- `[code]...[/code]` content is now treated as literal code content all the way through the BBCode render pipeline.
- Protected rendered code blocks from the later URL auto-embed and auto-link passes in `includes/render/bbcode.php`.

## 0.3.69 change log
- Patch-only fix in `includes/render/bbcode.php`.
- Root cause: `embed_video_links_in_html()` was still scanning inside rendered `<pre><code>...</code></pre>` blocks, so URLs wrapped in `[code]...[/code]` were converted into embeds before final output.
- Fix: stash `<pre>` and `<code>` blocks inside the HTML-phase embed pass, then restore them unchanged after scanning.

- 0.3.69a: Fixed a PHP parse error introduced in `includes/render/bbcode.php` during the code-block URL literal patch. Cause was unsafe regex quoting in single-quoted PHP strings inside `embed_video_links_in_html()`. Added `ERRORS-TO-AVOID.md` with the failure mode and prevention rule.

## 0.3.70 critical-error hotfix

- Fixed PHP parse error in `includes/render/bbcode.php` caused by unsafe regex quoting inside `embed_video_links_in_html()`.
- Added `ERRORS-TO-AVOID.md` documenting the failure mode and prevention rule.
- Purpose: restore plugin activation/runtime while keeping the code-block URL literal behavior.

- 0.3.71: Topic fullscreen view now starts below the fixed top bar (72px offset) so the topic title/header are visible in topic view. Patch-only CSS change in `assets/css/ia-discuss.modal.css`.

## 0.3.72 maintenance note

- Personal sidebar feeds were added as separate feed tabs (`mytopics`, `myreplies`, `myhistory`) rather than overloading the existing `Topics`, `Replies`, `0 replies`, or `Random` paths.
- Topic-visit history is recorded on topic open into WP user meta so the history feed survives SPA remounts without changing the phpBB schema.
- Random was intentionally left on the pre-change path to avoid coupling it to the new personal feed state.

## 0.3.73 maintenance note

- Earlier regressions were caused by state leakage between new personal feeds and older global feed/random paths, plus a too-broad runtime edit while trying to fix one feature.
- Rule reinforced: when adding a new view, keep it on its own view key from sidebar click -> router -> API tab mapping -> render semantics. Do not alias it onto an older global path unless the behaviour is intentionally identical.
- Rule reinforced: Random is a standalone action, not a personal-feed mode. Do not let active feed/history state narrow its topic pool unless that change is explicitly requested and documented.
- Housekeeping-only refactor: split `assets/js/split/router/router.mount_and_view.js` into three ordered `router.render.*.js` slices and split `assets/js/split/feed/feed.load_and_render.js` into four ordered `feed.*` render/load slices.
- Generated runtime bundle filenames were kept stable; only the split source tree and builder ordering changed.


## 0.3.75 maintenance note

- Private-Agora feed visibility regression cause: feed/random used `apply_filters('ia_current_phpbb_user_id', ...)` directly instead of the shared auth resolver, while topic/forum-meta used `IA_Discuss_Service_Auth::current_phpbb_user_id()`. In stacks where the filter is absent or partial, private-topic feed filtering could treat invited users or moderators as viewer `0` and hide content even though topic access succeeded.
- Private-Agora invite deeplink fix: invite notifications now include the Discuss tab query vars so Notify clicks open Discuss directly instead of whichever panel owns the current page context.

## 0.3.89 AgoraBB mode

- Kept the change patch-only against the pre-existing theme chooser build.
- The earlier mistake was treating the request as a theme variant. This pass keeps theme and layout mode separate: theme stays under `data-iad-theme`, while the optional phpBB-like navigation mode uses `data-iad-layout="agorabb"`.
- `assets/js/ia-discuss.ui.shell.js` now owns the optional mode toggle and persists it in localStorage.
- `assets/js/ia-discuss.router.js` uses the mode only for route defaults and the Agoras index renderer.
- `assets/js/ia-discuss.ui.agora.js` uses the mode only for the single-Agora forum/topic list renderer; topic pages themselves are unchanged.
