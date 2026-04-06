## 0.3.99 sidebar housekeeping

- `ia-discuss.ui.shell.js` no longer exposes the `AgoraBB Mode` toggle, Discuss theme modal button, or direct sidebar scheme buttons.
- Shell boot now clears the old per-browser theme/layout keys and reapplies the baseline `dark` theme with `atrium` layout so previous local selections do not survive this housekeeping pass.

# Notes: assets / js

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `split/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `topic/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `ia-discuss.agora.create.js` — Functions: qs, esc, ensureModal, openModal, closeModal, submit, wireOnce
- `ia-discuss.api.js` — Functions: safeJsonFromResponse, fetchWithTimeout, post, uploadFile, uploadFileWithProgress, upload Window exports: IA_DISCUSS_API
- `ia-discuss.audio.js` — Functions: fmtTime, ensureWaves, pauseOthers, initPlayer, syncPlayState, syncTime, init, boot Window exports: IA_DISCUSS_AUDIO
- `ia-discuss.boot.js` — Functions: depsReady, safeQS, bootWhenReady, tick
- `ia-discuss.core.js` — Functions: qs, qsa, esc, timeAgo Window exports: IA_DISCUSS_CORE
- `ia-discuss.modtools.js` — Functions: ready, patchModerationReturnShape, removeCoverButtons, patchCoverPrompt, init
- `ia-discuss.router.js` — Functions: depsReady, safeQS, setParam, setParams, mount, render, setModerationContext, agRowHTML, previewHTML, renderShell, renderMoreButton, appendFromCache, loadAgorasNext, getDiscussScroller, getFeedMount, computeFeedAnchor, saveFeedScroll, restoreFeedScrollAfterFeed, openTopicPage, openSearchPage, viewToTab, bindRandomTopic, composerError, routeFromURL Window exports: IA_DISCUSS_ROUTER
- `ia-discuss.state.js` — Functions: load, save, get, set, markRead, isRead Window exports: IA_DISCUSS_STATE
- `ia-discuss.ui.agora.js` — Functions: loadMeta, renderAgora Window exports: IA_DISCUSS_UI_AGORA
- `ia-discuss.ui.agora.membership.js` — Functions: stop, isLoggedIn, setBtnState, setJoinState, persistState, setBellState, getBellEnabled, toggleJoin, toggleBell, changeCover, findRootFor, bind
- `ia-discuss.ui.composer.js` — Functions: htmlToBbcode, walk, wrapSelection, insertAtCursor, isImage, isVideo, makeToolbar, composerHTML, bindComposer, draftLoad, draftSave, draftClear, setError, openFilePicker, setOpen, renderAttachList Window exports: IA_DISCUSS_UI_COMPOSER
- `ia-discuss.ui.feed.js` — Functions: ensureShareModal, isShareModalOpen, closeShareModal, openShareModal, togglePicked, runShareSearch, submitShare, currentUserId, makeTopicUrl, copyToClipboard, openConnectProfile, ico, ensureLinksModal, openLinksModal, lockPageScroll, openAttachmentsModal, ensureVideoModal, closeVideoModal, decodeHtmlUrl, parseYouTubeMeta, cleanId, parseStartSeconds, parsePeerTubeUuid, buildVideoMeta … Window exports: IA_DISCUSS_UI_FEED
- `ia-discuss.ui.moderation.js` — Functions: ensureModerationModal, showModal, hideModal, loadMyModeration, rowHTML, renderModerationView, settingsFormHTML, applyKickFilter, openSettings, setInlineStatus, saveSettingsInline, deleteAgora, flash, unbanUser, uploadCover, bind Window exports: IA_DISCUSS_UI_MODERATION
- `ia-discuss.ui.moderation.js.bak` — File present in this folder.
- `ia-discuss.ui.rules.js` — Functions: ensureModal, show, hide, bind Window exports: IA_DISCUSS_UI_RULES
- `ia-discuss.ui.search.js` — Functions: debounce, stripMarkup, avatarHTML, iconSVG, openConnectProfile, ensureSuggestBox, positionSuggestBox, hideSuggest, showSuggest, suggestGroup, bindSearchBox, resultsShellHTML, setActiveType, iconBubble, renderResultRow, bindResultsClicks, loadResults, renderSearchPageInto Window exports: IA_DISCUSS_UI_SEARCH
- `ia-discuss.ui.shell.js` — Functions: ensureImageViewer, lockScroll, getOrCreateViewer, openViewer, closeViewer, tryOpenFromEvent, clearPress, dist2, getDiscussRoot, getSidebar, getTopbarToggle, setSidebarOpen, closeSidebar, installTopbarToggle, syncVisibility, bindSidebar, shell, setActiveTab Window exports: IA_DISCUSS_UI_SHELL
- `ia-discuss.ui.shell.js.bak2` — File present in this folder.
- `ia-discuss.ui.topic.js` — Functions: iaRelModal, fetchTopic, openConnectProfile, renderInto, apply, bindAttachmentsModal, updateCount, appendPosts, ensurePostVisible, applyScrollAndHighlight, bindBackTop, resolveScroller, isGoodScroller, getTop, scrollToTop, setVisible, onScroll, bindTopicNav, resolveScroller, isGoodScroller, navFromState, computeIdsAndIndex, tabForView, syncButtons … Window exports: IA_DISCUSS_UI_TOPIC

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.64 playlist fallback cards
- YouTube playlist URLs no longer render as inline iframes. They now render as simple playlist cards that open the playlist on YouTube.
- Single videos and Shorts keep inline embed behaviour.

## 0.3.66 playlist iframe restore

- Restored inline YouTube playlist iframes so playlist URLs are not downgraded to click-out cards in topic rendering paths.
- Playlist URLs with both `v=` and `list=` now embed the selected video on `youtube.com/embed/{videoId}?list=...`.
- List-only playlist URLs still use the playlist embed form with `listType=playlist`.
- Feed and topic JavaScript now use the same shared helper output for playlist embeds.

## 0.3.72 JS note

- `ia-discuss.ui.shell.js` now renders the personal `My` sidebar subsection.
- Feed loading now maps `mytopics`, `myreplies`, and `myhistory` to dedicated server-side feed tabs.


## 0.3.80 theme chooser

- `ia-discuss.ui.shell.js` no longer hard-toggles only dark/light. The sidebar theme control now opens a theme modal and persists `dark`, `light`, or `legacy` in localStorage.
- Kept the change local to the shell runtime so feed/router/topic behaviour stays untouched.

- 0.3.82: `ia-discuss.ui.topic.js` now auto-hides the Atrium top nav/header while scrolling down in topic view, then restores it on scroll-up, topic close, or tab change.
- 0.3.83: `ia-discuss.ui.topic.js` now adds/removes a topic-modal state class while the Atrium top nav is auto-hidden so the topic sheet can lift to the viewport top without affecting other views.


## 0.3.89 AgoraBB layout mode

- `ia-discuss.ui.shell.js` now persists a separate `ia_discuss_layout_mode` flag and exposes an `AgoraBB Mode` sidebar toggle.
- `ia-discuss.router.js` now defaults Discuss to the forum index when AgoraBB mode is enabled and no explicit Discuss deep link is present.
- `ia-discuss.ui.agora.js` now renders a board-style single-forum topic list in AgoraBB mode while leaving topic view unchanged.


## 0.3.90 MyBB colour theme picker

- `ia-discuss.ui.shell.js` now recognises the MyBB default colour-scheme names and exposes them both in the theme modal and as direct buttons in the Discuss sidebar.
- `applyTheme()` now marks the root/modal/search portal with a shared classic-theme flag so the phpBB/MyBB-style CSS can be reused across multiple colour variants without changing feed/router/topic behaviour.
- `ia-discuss.ui.search.js` now mirrors the classic-theme state onto the floating suggestions portal so search respects the selected MyBB-style colour scheme.

## 0.3.91 stronger MyBB schemes
- Renamed the Discuss sidebar theme subsection from `MyBB styles` to `Schemes`.
- Renamed the legacy blue picker label to `Blue` in the Discuss theme UI while keeping the stored theme key as `legacy` for compatibility.
- Expanded `assets/css/ia-discuss.legacy.css` so each MyBB-derived scheme now changes more of the forum chrome instead of mostly just accents: card backgrounds, alternate post rows, borders, sidebar gradients, modal header gradients, AgoraBB/topic header bars, and the Discuss topbar toggle all now vary by scheme.
- This change was based on reviewing the supplied screen recording frame-by-frame: the previous pass showed only small accent differences, so this patch intentionally broadens the visible scheme surfaces without changing Discuss routing or theme storage keys.
