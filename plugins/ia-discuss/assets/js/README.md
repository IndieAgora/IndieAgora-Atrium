# assets / js

Runtime JavaScript bundles and already-modular UI files that are loaded by WordPress.

## File tree
```text
├── split/
├── topic/
├── ia-discuss.agora.create.js
├── ia-discuss.api.js
├── ia-discuss.audio.js
├── ia-discuss.boot.js
├── ia-discuss.core.js
├── ia-discuss.modtools.js
├── ia-discuss.router.js
├── ia-discuss.state.js
├── ia-discuss.ui.agora.js
├── ia-discuss.ui.agora.membership.js
├── ia-discuss.ui.composer.js
├── ia-discuss.ui.feed.js
├── ia-discuss.ui.moderation.js
├── ia-discuss.ui.moderation.js.bak
├── ia-discuss.ui.rules.js
├── ia-discuss.ui.search.js
├── ia-discuss.ui.shell.js
├── ia-discuss.ui.shell.js.bak2
└── ia-discuss.ui.topic.js
```

## File roles
- `split/` — Source-of-truth JS slices for the largest runtime bundles. Edit here, then rebuild the generated bundles.
- `topic/` — Topic page runtime modules that already ship as separate files.
- `ia-discuss.agora.create.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.api.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.audio.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.boot.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.core.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.modtools.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.router.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.state.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.agora.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.agora.membership.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.composer.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.feed.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.moderation.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.moderation.js.bak` — Project file.
- `ia-discuss.ui.rules.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.search.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.shell.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.ui.shell.js.bak2` — Project file.
- `ia-discuss.ui.topic.js` — Runtime JS file enqueued or consumed by the front end.

## Maintenance entry point
Use this folder README together with the sibling NOTES file before changing anything in the folder.

Update note: `ia-discuss.youtube.js` is the shared YouTube parser/embed helper used by feed and topic media runtime code.
