# Architecture Notes: IA Post

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-post`
- Version in header: `0.1.5`
- Main entry file: `ia-post.php`
- Declared purpose: Global Atrium post composer (Connect + Discuss). Hooks into the Atrium bottom nav "Post" button.

## Authentication and user-state notes

- Checks logged-in WordPress user state before serving UI or AJAX.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-post.php` — Runtime file for ia post.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-post.css` — Stylesheet for ia post.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-post.js` — JavaScript for ia post.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-post-assets.php` — Asset enqueue and localization logic.
- `includes/class-ia-post.php` — Runtime file for class ia post.

### `templates`

- Purpose: PHP templates rendered into the front end.
- `templates/composer-mount.php` — Runtime file for composer mount.


## April 4, 2026 — IA Post Stream upload modal

- Added Stream as a first-class destination inside the global Atrium composer so ia-post can initiate PeerTube video uploads.
- New ia-post AJAX endpoints: `ia_post_stream_bootstrap` and `ia_post_stream_upload`.
- Bootstrap loads the current user's PeerTube account context, owned/collaborator channels, account playlists, and upload dictionaries for categories, licences, languages, and privacies.
- Upload flow is patch-only and keeps the existing token contract: ia-post resolves the current user token through `IA_PeerTube_Token_Helper` and does not introduce a parallel auth path.
- Upload UI now opens a modal after file selection. The modal allows channel selection, title, description, tags, privacy, playlist assignment, comments policy, category, licence, language, sensitive-content flags, support text, optional thumbnail, optional password, and upload-progress display.
- The browser progress bar currently tracks the browser-to-WordPress upload leg and then transitions to finalization while WordPress forwards the file to PeerTube. No direct browser-to-PeerTube token exposure was added in this patch.
- On success the modal offers open-in-Stream and open-on-PeerTube actions using the returned uploaded video identifiers.
- Discussion captured from this request: the user asked for ia-post video uploading now that user-token issues are sorted, with channel selection, tags, description, privacy/sensitive-content settings, playlist assignment, and a diverted modal that shows progress while allowing those settings to be edited.


## 2026-04-04 stream upload modal fullscreen patch
- Patch-only UI hardening after live test feedback that the Stream upload modal did not expose all controls on screen.
- Changed the Stream upload modal panel to fill the viewport instead of using the smaller centered card layout.
- Made the modal header sticky and the action row sticky so Cancel / Upload remain reachable while scrolling long PeerTube metadata forms.
- Kept the existing fields, upload flow, and token path unchanged.
- Included discussion context: user reported that some controls were not visible and requested the upload modal be full screen.


## 2026-04-05 token-contract confirmation

Rechecked against the current stack during auth/token cleanup:

- ia-post already resolves upload/bootstrap token state through a local `current_token_status()` wrapper.
- That wrapper prefers `IA_PeerTube_Token_Helper::get_token_status_for_current_user()` and only falls back to the older token getter if the structured status method is unavailable.
- No patch was needed to invent a separate ia-post token path in this pass.
- This note is recorded so the code and the broader token-authority plan match.


## 2026-04-05 live stability confirmation

User-reported post-deploy test result:

- ia-post bootstrap works
- the broader stack is stable

Interpretation:

- ia-post continued to behave correctly without needing a separate token-path rewrite in this pass
- the local wrapper around the canonical token helper remains sufficient for the current upload/bootstrap flow
- no visible regression was reported after the auth/token cleanup patch
