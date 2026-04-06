- 2026-04-06 deep-link follow-up: IA Message now honours `ia_msg_thread` + optional `ia_msg_mid` and the `ia_message:open_thread` event can carry `message_id`, so notify clicks can jump to the exact message bubble after the thread loads.
- 2026-04-06 style repair: added a late generic MyBB message layer so imported styles now recolour the shell, header, controls, list rows, and in/out bubbles instead of falling back to the default path.
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


## 2026-04-06 style ownership follow-up

- IA Message now treats Connect's saved style as the owner for its internal panel styling as well as Atrium shell chrome.
- For `data-iac-style="black"`, IA Message swaps its default dark/purple treatment for the approved black-style palette already used elsewhere in the stack: light grey surfaces, dark text, dark header bars, and neutral controls.
- Message-bubble gradients remain a default-style behaviour only. The black style uses flat alternating bubble fills so long conversations read like the approved Connect/Stream alternating reply pattern.
- This is intentionally CSS-only. No panel routing, AJAX surface, or message rendering contracts changed.

- 2026-04-06: Black style message bubbles now key off sender/recipient side (.mine / data-ia-msg-side) instead of DOM row parity. Incoming bubbles stay light grey with dark text; outgoing bubbles stay dark with white text. This avoids same-person runs alternating colours when consecutive messages are rendered.


## 2026-04-06 theme-baseline guidance for later style packs

The approved Black style is now the reference implementation for IA Message theming outside the default skin.

Keep these rules when adding later MyBB-style themes:

- Style ownership stays split exactly as it is now. Atrium owns the shared shell and navigation chrome. IA Message owns only message-internal surfaces such as thread list cards, chat header, message viewport, composer, bubbles, and message-side affordances.
- Consume the current Connect style as a state input only. Do not add a separate IA Message theme selector or a second source of truth.
- Preserve the default theme as the only gradient-led path unless a later design note explicitly says otherwise. Alternate themes should usually use flatter surfaces so text contrast and icon readability stay predictable.
- Bubble treatment is semantic before decorative: incoming and outgoing sides must be visually distinct, but same-person consecutive messages must not flip colours because of DOM order. Side identity is the contract; alternation-by-row is not.
- Future ports should start by mapping the Black baseline roles, not by copying MyBB colours blindly. The role map is: shared shell, local panel surface, card/list surface, section header, input/composer surface, incoming bubble, outgoing bubble, secondary text, icon stroke/fill, border/divider, and destructive/accent actions.
- Once those roles are mapped, a future theme can swap palette values, radius, borders, and subtle texture while preserving the same behavioural semantics and contrast expectations.
- Keep SPA-safe behaviour. Theme appearance must continue to survive panel reuse, tab switches, and re-rendered message rows without relying on one-time DOM parity assumptions.

This should be treated as the decision baseline for the next MyBB-style pass so the work stays consistent with the already-approved Black implementation rather than becoming a fresh redesign per theme.
