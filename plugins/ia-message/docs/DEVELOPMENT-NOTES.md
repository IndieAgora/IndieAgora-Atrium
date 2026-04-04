# Development Notes

## Current structure intent

`ia-message.php` is the WordPress plugin entrypoint. It defines constants and calls `ia_message_boot()`.

`includes/ia-message.php` is the safe boot orchestrator. It loads support files, services, renderers, and the panel module.

`includes/support/ajax.php` is now a stable loader only. Actual AJAX callback implementations have been moved into `includes/support/ajax/` by intent:
- `threads.php` for thread list/view reads
- `messages.php` for send/new-DM/forward/upload actions
- `groups.php` for group creation, invites, membership reads, moderation actions
- `users.php` for user search, unread count, preference reads/writes, and relationship toggles

`includes/services/*.php` remains the data/service layer. These files are the correct place for reusable logic that should not live in endpoint callbacks.

`assets/js/ia-message.boot.js` is still the primary UI orchestrator, but helper concerns have started to move out into intent-labelled files:
- `ia-message.api.js` for AJAX/upload helpers
- `ia-message.state.js` for per-shell state memoization
- `ia-message.ui.modals.js` for relationship modal UI
- `ia-message.ui.composer.js` for autosize behaviour on send/new/group textareas

## Identity assumptions currently in use

Primary messaging identity is phpBB user id. Several compatibility paths exist because callers may only know a WordPress user id:
- `{$wpdb->prefix}phpbb_user_map`
- `{$wpdb->prefix}ia_identity_map`
- WP user meta keys such as `ia_phpbb_user_id`, `phpbb_user_id`, `ia_phpbb_uid`, `phpbb_uid`, `ia_identity_phpbb`

Do not narrow these without an explicit migration plan.

## Runtime contracts worth preserving

- AJAX actions under `wp_ajax_ia_message_*`
- localised JS object `IA_MESSAGE`
- Atrium panel key `IA_MESSAGE_PANEL_KEY`
- custom events such as `ia_message:open_thread`, `ia_atrium:tabChanged`, `ia_atrium:navigate`
- action hooks such as `ia_message_sent`, `ia_message_group_member_added`, `ia_message_group_invited`, `ia_user_follow_created`

## Next monolith targets

The largest remaining concentration is `assets/js/ia-message.boot.js`. It should continue to be reduced by extraction into already-present intent files rather than by behavioural rewrite.

Good next extractions:
- thread list rendering and filtering
- chat rendering and reply/forward handling
- deep-link and Atrium tab integration
- media viewer

Keep the boot file as an orchestrator/entrypoint rather than a catch-all.


## Composer behaviour

Composer autosize is handled in `assets/js/ia-message.ui.composer.js`. Keep it separate from `ia-message.boot.js` so textarea sizing fixes do not require editing the main runtime orchestrator.

The cap is deliberate: the textarea should grow naturally, but it must stop before it consumes most of the viewport and hides message history.

The composer should open at a compact two-line baseline. Autosize should expand only after content exceeds that baseline, then stop at the viewport cap.

Clipboard file paste is also handled in `assets/js/ia-message.ui.composer.js`. Preserve the existing upload endpoint and returned-URL insertion path rather than creating a separate clipboard-only transport.
