- 2026-04-06 deep-link follow-up: IA Message now honours `ia_msg_thread` + optional `ia_msg_mid` and the `ia_message:open_thread` event can carry `message_id`, so notify clicks can jump to the exact message bubble after the thread loads.
# Live Notes

## 2026-03-08

- Added documentation scaffold for rules, development notes, error notes, live notes, and endpoint documentation.
- Converted `includes/support/ajax.php` into a stable loader and split callback implementations into `includes/support/ajax/threads.php`, `messages.php`, `groups.php`, and `users.php`.
- Preserved all existing public AJAX action names.
- Extracted front-end helper concerns into `assets/js/ia-message.api.js`, `assets/js/ia-message.state.js`, and `assets/js/ia-message.ui.modals.js`.
- Updated asset enqueue order so helper scripts load before `assets/js/ia-message.boot.js`.
- Normalised send-handler thread type initialisation before DM block/privacy checks.
- Left major UI orchestration in `assets/js/ia-message.boot.js` for now to avoid a broad behavioural rewrite in the same pass.

## 2026-03-08 Composer autosize patch

- Added `assets/js/ia-message.ui.composer.js` and enqueued it before boot.
- Message composer now grows with content while typing instead of staying fixed-height.
- Autosize is capped against viewport height so the chat log retains visible space during longer messages.
- Binding is MutationObserver-safe so the behaviour survives Atrium SPA panel reuse and modal insertion.

- Adjusted composer autosize so it starts compact at roughly two lines and only grows once content exceeds that baseline.
- Added clipboard paste-to-upload support for composer textareas. Pasted clipboard files are sent through the existing `ia_message_upload` route and the returned URLs are inserted into the composer.

## 2026-03-15 Display-name label path

- Patched IA Message so display labels pass through `ia_message_user_label` after its normal WP-shadow lookup.
- This addresses cases where IA Message still showed phpBB usernames even though the wider stack had a better display-name resolver.
- No authentication or message-authority behaviour changed in this pass.


## 2026-04-06 global style follow-up for IA Message

- Added a Connect-style bridge for IA Message so the saved Connect style now skins the Messages panel internals as well as the shared Atrium shell.
- Black style now switches IA Message from the legacy dark/purple look to the approved black-style palette: light grey shell surfaces, dark header bars, dark text, and neutral controls.
- Chat bubbles in black style are now flat rather than gradient-based, and alternate between two grey fills to match the wider style direction already approved in Connect/Stream work.
- Kept the default style unchanged. Only the black-style path was retuned in this patch.

- 2026-04-06: Black style message bubbles now key off sender/recipient side (.mine / data-ia-msg-side) instead of DOM row parity. Incoming bubbles stay light grey with dark text; outgoing bubbles stay dark with white text. This avoids same-person runs alternating colours when consecutive messages are rendered.


- 2026-04-06: Added future-theme guidance after Black-style approval. Black is now the baseline reference for later IA Message style ports: shared chrome stays Atrium-owned, IA Message styles only plugin-owned internals, non-default themes generally prefer flat bubbles over gradients, and bubble colours must stay side-based rather than alternating by DOM row.
