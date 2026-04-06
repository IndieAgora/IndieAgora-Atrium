- 2026-04-06 frontend wording cleanup follow-up: removed the remaining New chat helper reference to legacy canonical user-id wording so the composer stays platform-neutral on the frontend.
- 2026-04-06 deep-link correction patch: `ia_message_thread` now accepts an optional `message_id` and recenters the fetched window around that message so notification deep links can land on older messages, not just the newest page of a thread.
- 2026-04-06 deep-link follow-up: IA Message now honours `ia_msg_thread` + optional `ia_msg_mid` and the `ia_message:open_thread` event can carry `message_id`, so notify clicks can jump to the exact message bubble after the thread loads.
- 2026-04-06 style import reset: Message no longer guesses with generic dark recolours for imported styles. Non-default imported styles now take their own light shell plus themed outgoing/incoming bubble colours.
# AJAX notes for ia-message / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `LICENSE`
- `README.md`
- `ia-message.php`
- `uninstall.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

- 2026-04-06 style pass: IA Message now responds to the global Connect-selected style state. Black style uses light grey messaging surfaces with dark text, dark header bars, and alternating flat chat bubbles; default style keeps the original dark/purple treatment.

- 2026-04-06: Black style message bubbles now key off sender/recipient side (.mine / data-ia-msg-side) instead of DOM row parity. Incoming bubbles stay light grey with dark text; outgoing bubbles stay dark with white text. This avoids same-person runs alternating colours when consecutive messages are rendered.

- 2026-04-06 future-style baseline note: treat the approved Black style as the implementation reference for all later IA Message style work. The rule is not to re-invent message theming per theme. Reuse the same ownership split: Atrium owns shared shell chrome, IA Message owns message-internal surfaces, and the active Connect style is only consumed as a state flag.
- 2026-04-06 future-style baseline note: for non-default themes, gradients should generally stay off message bubbles unless a later style note explicitly calls for them. Default keeps the legacy gradient treatment; alt themes should prefer flatter fills and clearer contrast.
- 2026-04-06 future-style baseline note: message bubble colour decisions should be side-based, not row-parity based. Incoming/sender-side rules must remain stable across consecutive messages from the same person so future theme packs do not reintroduce alternating-per-row regressions.
- 2026-04-06 future-style baseline note: when porting imported forum-theme variants later, keep the semantic split before the palette split. First decide shell, panel, list, header, composer, incoming bubble, outgoing bubble, icon, and border roles from the Black baseline. Then map the new theme colours onto those same roles.
- 2026-04-06 hotfix: lighter imported styles now force the ia-message composer textarea, placeholder, and focus state onto the active light palette. This is CSS-only and prevents the legacy dark textarea skin from making typed text unreadable in Messages.
