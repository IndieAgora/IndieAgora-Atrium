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
