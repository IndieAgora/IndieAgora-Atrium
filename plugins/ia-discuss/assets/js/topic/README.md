# assets / js / topic

Topic page runtime modules that already ship as separate files.

## File tree
```text
├── ia-discuss.topic.actions.js
├── ia-discuss.topic.media.js
├── ia-discuss.topic.modal.js
├── ia-discuss.topic.render.js
└── ia-discuss.topic.utils.js
```

## File roles
- `ia-discuss.topic.actions.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.topic.media.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.topic.modal.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.topic.render.js` — Runtime JS file enqueued or consumed by the front end.
- `ia-discuss.topic.utils.js` — Runtime JS file enqueued or consumed by the front end.

## Maintenance entry point
Use this folder README together with the sibling NOTES file before changing anything in the folder.
Update note: playlist URLs with both `v=` and `list=` now use the standard YouTube embed domain in topic media to avoid the unavailable-player case seen with some playlist posts.

Update note: topic media now depends on the shared `ia-discuss.youtube.js` helper for YouTube parsing and embed URL selection.
