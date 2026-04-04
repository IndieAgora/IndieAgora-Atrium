# Notes: assets / js / topic

## What changed in the 0.3.60 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `ia-discuss.topic.actions.js` — Functions: findComposerTextarea, extractQuoteTextFromPost, decodeB64Utf8, copyToClipboard, makePostUrl, ensureConfirmModal, close, confirmModal, openReplyModal, bindTopicActions Window exports: IA_DISCUSS_TOPIC_ACTIONS
- `ia-discuss.topic.media.js` — Functions: isImageMime, isVideoMime, isAudioMime, isLikelyAudioUrl, agoraPlayerHTML, decodeHtmlUrl, parseYouTubeMeta, parsePeerTubeUuid, isLikelyVideoUrl, buildVideoEmbedHtml, attachmentPillsHTML, inlineMediaHTML Window exports: IA_DISCUSS_TOPIC_MEDIA
- `ia-discuss.topic.modal.js` — Functions: ensureModal, close, openComposerModal, closeComposerModal, getComposerMount Window exports: IA_DISCUSS_TOPIC_MODAL
- `ia-discuss.topic.render.js` — Functions: renderError, bindBack, b64utf8, renderPostHTML, renderTopicHTML Window exports: IA_DISCUSS_TOPIC_RENDER
- `ia-discuss.topic.utils.js` — Functions: qs, esc, timeAgo, ensureListModal, openListModal Window exports: IA_DISCUSS_TOPIC_UTILS

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.60 playlist embed fix
- `ia-discuss.topic.media.js` now carries `playlistId` through the YouTube parser and preserves the selected `v=` id for playlist URLs, using the single-video embed path in playlist context when available and falling back to `videoseries` for list-only URLs.
- Topic inline media should now preserve playlist rendering instead of falling back to a single-video iframe.
## 0.3.62 playlist domain follow-up
- `ia-discuss.topic.media.js` now builds playlist-with-video embeds on `youtube.com/embed/{videoId}` while keeping `videoseries` for list-only playlists.
- Topic media keeps the existing no-cookie path for non-playlist single-video embeds.

## 0.3.63 playlist rebuild
- `ia-discuss.topic.media.js` now consumes the shared `window.IA_DISCUSS_YOUTUBE` helper.
- Topic media treats playlist URLs as playlist embeds only and no longer builds hybrid selected-video playlist iframes.

## 0.3.64 playlist fallback cards
- YouTube playlist URLs no longer render as inline iframes. They now render as simple playlist cards that open the playlist on YouTube.
- Single videos and Shorts keep inline embed behaviour.

## 0.3.66 playlist iframe restore
- `ia-discuss.topic.media.js` no longer converts YouTube playlists into click-out cards.
- Topic attachment video rendering now uses the shared helper iframe output for both single videos and playlists.
