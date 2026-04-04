# Notes: includes / render

## What changed in the 0.3.60 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `attachments.php` — Classes: IA_Discuss_Render_Attachments Functions/methods: extract, strip_payload
- `bbcode.php` — Classes: IA_Discuss_Render_BBCode, so Functions/methods: sanitize_youtube_id, parse_youtube_embed_meta, sanitize_linkmeta_image, format_post_html, link_mentions_in_html, strip_legacy_preview_codeblocks, strip_legacy_preview_preblocks, collapse_legacy_richcard_codeblocks_in_html, embed_video_links_in_html, video_embed_html, excerpt_html, to_plaintext, strip_phpbb_internal_tags, bbcode_to_html, abs_url, link_meta, nl2p, allowed_tags
- `media.php` — Classes: IA_Discuss_Render_Media Functions/methods: extract_urls, pick_video_url, extract_media

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.60 playlist embed fix
- `bbcode.php` now preserves the YouTube playlist id separately from the video id so playlist URLs can render in playlist context without losing the chosen `v=` item.
- `video_embed_html()` now prefers `https://www.youtube-nocookie.com/embed/{videoId}?list=...` when a playlist URL includes a specific video, and falls back to `https://www.youtube.com/embed/videoseries?list=...` only for list-only playlist URLs.
- This keeps single-video behaviour intact while preventing playlist URLs from collapsing down to one video.
## 0.3.62 playlist domain follow-up
- `bbcode.php` now uses `https://www.youtube.com/embed/{videoId}?list=...` when a playlist URL includes both a selected `v=` item and a `list=` id.
- List-only playlist URLs still fall back to `https://www.youtube.com/embed/videoseries?list=...`.
- Single-video embeds remain on the no-cookie domain.

## 0.3.63 playlist rebuild
- `bbcode.php` now has a dedicated `build_youtube_embed_url()` method.
- Playlist URLs render with `https://www.youtube.com/embed?listType=playlist&list=...` and optional `index`/`start` values.
- Non-playlist YouTube URLs stay on the existing no-cookie single-video path.

## 0.3.64 playlist fallback cards
- YouTube playlist URLs no longer render as inline iframes. They now render as simple playlist cards that open the playlist on YouTube.
- Single videos and Shorts keep inline embed behaviour.

## 0.3.66 playlist iframe restore

- Restored inline YouTube playlist iframes so playlist URLs are not downgraded to click-out cards in topic rendering paths.
- Playlist URLs with both `v=` and `list=` now embed the selected video on `youtube.com/embed/{videoId}?list=...`.
- List-only playlist URLs still use the playlist embed form with `listType=playlist`.
- Feed and topic JavaScript now use the same shared helper output for playlist embeds.


## 0.3.68 code block literal URL fix
- `bbcode.php` no longer collapses `[code]...[/code]` content into standalone URLs for later rich-card rendering.
- Rendered `.iad-code-block` output is now stashed before the standalone URL embed/link passes and restored afterwards.
- Result: URLs inside code blocks remain plain text only.

## 0.3.69 code block HTML embed fix
- `embed_video_links_in_html()` now stashes `<pre>` and `<code>` blocks before replacing video links, preventing embeds inside rendered code samples.

- 0.3.69a: Fixed a PHP parse error introduced in `includes/render/bbcode.php` during the code-block URL literal patch. Cause was unsafe regex quoting in single-quoted PHP strings inside `embed_video_links_in_html()`. Added `ERRORS-TO-AVOID.md` with the failure mode and prevention rule.

## 0.3.70 critical-error hotfix

- Fixed PHP parse error in `includes/render/bbcode.php` caused by unsafe regex quoting inside `embed_video_links_in_html()`.
- Added `ERRORS-TO-AVOID.md` documenting the failure mode and prevention rule.
- Purpose: restore plugin activation/runtime while keeping the code-block URL literal behavior.
