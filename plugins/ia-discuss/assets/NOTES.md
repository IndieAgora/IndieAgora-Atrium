# Notes: assets

## What changed in the 0.3.59 architecture pass
- This folder was documented and indexed as part of the architecture pass.

## File/function index
- `css/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `img/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `js/` — See its local README.md and NOTES.md for its own file tree and symbol index.

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.72 sidebar feed note

- The Discuss sidebar now includes a `My` subsection for personal topic/reply/history feeds.


## 0.3.74 private Agora UI

- Moderation settings modal now includes private/public toggle and invite search/send controls.
- Agora view now intercepts invite URLs and shows an accept/decline modal before loading private content.
- Feed/topic action rows hide Copy link and Share to Connect for private Agora content.


## 0.3.75 invite-route follow-up

- Agora invite acceptance now normalises the URL to the Discuss Agora route and removes the one-time invite query parameter after response.

- `ia-discuss.ui.agora.membership.js` — Leaving a private Agora now redirects non-moderators to the global Discuss feed after access is revoked.

- 0.3.77: Moderation invite search results now use dedicated readable dark-mode rows instead of borrowed kicked-user styling.

## 0.3.80 theme UI

- Discuss sidebar theme control now opens a chooser modal with Dark, Light, and Legacy style options.
- Legacy style is a phpBB-inspired theme aimed at improving post readability for users who prefer the older forum look.


## 0.3.89 AgoraBB mode

- Added a separate navigation/layout mode for Discuss. AgoraBB mode is not tied to the existing dark/light/legacy theme chooser.
