# IA Connect CSS notes

- 2026-04-06: Started imported style rollout in Connect only. Added the first alternate palette, `black`, alongside the existing `default` Atrium palette.
- Scope of this pass is colour only. Do not change spacing rhythm, component structure, or layout when adding style variants.
- The imported `black` pass uses light surfaces with darker text and controls so contrast stays readable across cards, settings, search, composer, comments, modal surfaces, and action buttons.
- Frontend naming stays generic (`Default`, `Black`). Do not expose MyBB-origin terminology in Connect UI.
- Style selection is user-scoped through Connect Settings for surgical rollout and comparison before extending the same palette to Discuss, Stream, and messages.

- 2026-04-06 follow-up BLACK calibration: Connect panel background and Settings/Stream result surfaces now use the lighter black-theme greys instead of the earlier near-black shell. Feed cards now follow a black-header/grey-body treatment, profile meta keeps white-on-black contrast, post-view comments alternate grey rows, SVG/icon actions were darkened for light surfaces, and Connect-scoped shell overrides now recolour the top nav, bottom nav, and Atrium search overlay only while Connect is the active tab.

- 2026-04-06 black palette follow-up: kept layout rhythm unchanged and only corrected low-contrast light-theme regressions. Recent-search chips, overlay result text, profile dropdown, Discuss embeds/activity rows, and the profile identity block were retuned toward dark text on light surfaces.

- 2026-04-06 black palette follow-up 2: removed the boxed profile identity treatment in Connect, restored bio-signature contrast on the lighter surface, darkened shared Discuss attachment/signature text, retuned Connect→Discuss agoras/topics/replies to dark-on-light card surfaces, and deepened destructive profile-menu actions for the Black palette.

## 0.5.39 Black palette discuss/card contrast tidy

- Darkened the profile-menu danger actions in the bottom-nav profile menu only, keeping the Settings-screen danger styling unchanged.
- Retuned Connect → Discuss agora rows so joined/created lists no longer wash out to white-on-light and now hold dark text on the light black-theme surfaces.
- Retuned Connect → Discuss topic/reply cards to match the lighter Stream-card body more closely, with darker titles/excerpts and the same narrow patch-only scope in CSS.


## 0.5.40 Black palette final Discuss contrast pass

- Darkened the bottom-nav profile-menu deactivate/delete entries again, including their icons, without changing the Settings-tab danger buttons.
- Forced darker text/metadata on Connect → Discuss `Agoras created` and `Agoras joined` rows under the Black palette so the row titles and counts cannot wash out on the light surface.
- Lightened Connect → Discuss `Topics` and `Replies` cards another step while keeping a darker slate meta strip and dark body copy for readability.
- Corrected the shared Discuss signature block rendered inside Connect wall cards by targeting the real topic-signature selectors (`.iad-post-signature`, `.iad-post-sig-body`, `.iad-post-sig-divider`) and giving that nested signature area a dark-grey treatment.


## 0.5.41 Black palette Connect Discuss selector correction

- Corrective pass after live verification showed earlier selectors were still too broad/misplaced.
- Connect → Discuss activity styling now targets `.iac-activity-list` directly for agoras/topics/replies instead of relying on broader `.iac-discuss-root` overrides.
- Shared Discuss signature styling now removes the added grey box and keeps only dark-grey signature text/divider treatment on the existing light embed surface.


- 2026-04-06 follow-up: added `assets/css/BLACK-STYLE-REFERENCE.md` as the behaviour reference for future Connect themes. New styles should preserve the same component hierarchy, contrast roles, signature restraint, and plugin ownership confirmed by the Black style rather than guessing at broad selectors.
