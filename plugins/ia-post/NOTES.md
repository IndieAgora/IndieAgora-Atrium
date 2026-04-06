- 2026-04-06 follow-up: lighter imported styles now force readable Discuss-composer action buttons inside `ia-post` (`+ Add attachment` / `Post`) so the footer controls do not wash out against the light modal surface. CSS-only in `assets/css/ia-post.theme.mybb.css`; no composer logic changed.
- 2026-04-06 style repair follow-up: generic imported MyBB styles now push embedded Discuss composer fields/toolbars/buttons onto the same light-surface/dark-text treatment as the approved Black reference, removing the leftover hard-black field blocks.
- 2026-04-06 style repair: added a late generic MyBB composer layer so imported styles now recolour the post modal body, header, pills, and joined-agora surfaces.
- 2026-04-06 style import reset: Composer modal follows imported style palettes without changing layout or upload flow.
# AJAX notes for ia-post / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `ia-post.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.

- 2026-04-06 Connect Black follow-up: `ia-post` now follows the selected Connect Black style inside the Atrium composer and stream-upload modal with the same light-grey surfaces / dark-text treatment used by the approved Connect Black reference, without changing posting flows or composer structure.

- 2026-04-06 black-style composer correction: kept scope patch-only inside `ia-post` CSS. The Atrium composer now forces the embedded Discuss composer controls onto the approved light-surface/dark-text Black treatment so `ia-post` no longer leaves dark Discuss form blocks inside the light Black-style modal.

- 2026-04-06 style ownership note: `ia-post` owns composer internals only. Under shared Connect/Atrium style state, keep post/composer controls aligned with the approved style reference, but do not try to restyle shared top/bottom navigation here.

- 2026-04-06 Black reference note: when the stack is on the approved Black style, `ia-post` should read as the same light-surface/dark-text family as Connect rather than leaving old dark Discuss blocks inside the composer shell.

- 2026-04-06 follow-up: composer draft memory now persists `ia-post` Connect and Discuss composer text in localStorage so closing the Atrium composer or leaving the site does not discard unsent work. Connect stores title/body; Discuss stores title/body/notify and selected Agora. Drafts are cleared only after successful submit.

- 2026-04-06 hotfix: restored Connect composer draft persistence in ia-post. v18 only persisted the Discuss composer; Connect title/body now save to localStorage on input/change, restore on reopen/revisit, and clear only after a successful post.
