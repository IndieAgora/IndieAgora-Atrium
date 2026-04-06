# Black style reference for future Connect themes

Purpose: use the confirmed Black style as the behavioural reference when adding new Connect themes. This file is about behaviour, hierarchy, contrast, and selector-targeting, not about copying the same colours into every theme.

## Rule 1: target the real render trees

Do not guess at broad card or menu selectors. Confirm the actual plugin/render path first.

- Connect > Discuss activity lists are rendered through `.iac-activity-list` and then into real Discuss structures such as `.iad-agora-row` and `.iad-card`.
- The actual `ia-discuss` plugin owns its own shell and theme selectors; if Connect style is meant to affect the Discuss panel, bridge into Discuss's existing `data-iad-theme` system instead of restyling Discuss from Connect-owned selectors.
- Bottom-nav profile menu destructive items belong to `ia-profile-menu`, not `ia-connect`.
- Shared Discuss content inside Connect posts should be styled from the nested Discuss-share selectors already present in the wall card, not by adding replacement containers.

Maintenance rule: if a future theme change is not visibly landing, stop and confirm the owning plugin and exact selector path before widening CSS.

## Rule 2: preserve the same visual hierarchy proven in Black

Every future theme should preserve these relative relationships even when the palette changes:

- Activity subtabs are clearly separated from the panel background.
- Active subtabs have the strongest contrast in the row.
- Search inputs remain quieter than the active subtab, but clearer than the panel background.
- Agora list rows read as light list surfaces with strong title contrast and softer meta text.
- Topic and reply cards read as card surfaces that are lighter than the older dark-grey version, while title/body/meta remain immediately legible.
- Shared Discuss signatures inside Connect remain text-level elements on the existing nested card surface; do not turn signatures into separate blocks unless explicitly requested.
- Destructive actions in menus remain visibly warning-coloured and darker than surrounding neutral menu items.

## Rule 3: use contrast roles, not one-off colour guesses

When porting this behaviour to another theme, map each element into one of these roles first:

- Page/panel background
- Subtab default surface
- Subtab active surface
- Input surface
- List row/card surface
- Primary text
- Secondary/meta text
- Divider/border
- Destructive text
- Destructive hover background

Do not start with arbitrary hex changes. Start by deciding which role the element belongs to, then assign the new theme's palette token/value to that role.

## Rule 4: signatures and meta text must stay restrained

The user-approved Black behaviour is:

- signature text is present and readable
- signature treatment is restrained
- meta text is softer than titles/body but still readable at a glance

So in future themes:

- avoid high-emphasis boxes around signatures
- avoid making meta text so faint that rows/cards look empty
- keep title/body contrast ahead of meta contrast

## Rule 5: keep Topics / Replies aligned with Stream card quality

The user-approved direction is that Connect > Discuss cards should feel comparable in finish to Stream cards, while still respecting Discuss content structure.

That means future themes should keep:

- clear card edges
- readable title/body/meta split
- restrained shadows or no shadow, depending on theme
- no muddy mid-grey text against mid-grey cards

## Rule 6: patch-only changes for themes

When adding or adjusting a theme:

- do not replace markup to solve a styling problem
- do not move ownership of a style problem into the wrong plugin
- do not change working selectors for unrelated themes
- prefer narrowly scoped theme selectors such as `html[data-iac-style="..."]` / `body[data-iac-style="..."]` plus the confirmed component tree

## Rule 7: first-load performance

Keep appearance exactly as approved, but reduce avoidable style work where possible:

- do not enqueue the same stylesheet handle twice
- do not add duplicate override blocks for the same state unless required
- keep theme overrides grouped near the relevant component file so the browser resolves fewer broad late overrides
- avoid ultra-broad selectors when a confirmed component selector exists

## Rule 8: acceptance check for future themes

Before shipping a new theme, verify these exact surfaces:

1. Connect > Discuss > Agoras created
2. Connect > Discuss > Agoras joined
3. Connect > Discuss > Topics created
4. Connect > Discuss > Replies
5. Bottom-nav profile menu deactivate/delete items
6. Shared Discuss post inside Connect wall card, including signature visibility

If any of those six surfaces fail, the theme is not ready.

## Rule 9: global owner chain for stack-wide themes

For the current stack, the confirmed owner chain is:

- Connect = selected style source of truth
- Atrium = shared shell/chrome/background owner
- Discuss = Discuss content/theme internals
- Post = composer/upload internals
- Profile Menu = bottom-nav profile menu internals
- Stream = stream-owned internals when that pass is tackled

Do not copy shared nav/background overrides into every plugin. Flow the selected style into the owner chain above.

## Rule 10: approved Black behaviour beyond Connect

The approved Black reference is not a hard-black site shell. Across the stack it means:

- shared Atrium chrome moves onto the lighter grey treatment already approved in Connect
- plugin internals keep their own structure but follow the same light-surface / dark-text direction where that theme is intended to apply
- old hard-black backgrounds should be treated as legacy shell behaviour and removed from shared surfaces when Black is active
