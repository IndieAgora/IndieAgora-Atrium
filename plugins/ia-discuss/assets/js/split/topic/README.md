# assets / js / split / topic

Split source slices for the topic action runtime bundle.

## File tree
```text
├── _ta.body.js
├── topic_actions.bind_actions.js
└── topic_actions.export.js
```

## File roles
- `_ta.body.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `topic_actions.bind_actions.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `topic_actions.export.js` — Split JS source slice used to rebuild a generated runtime bundle.

## Maintenance entry point
Edit the split JS slices here, then run `./tools/build-assets.sh` from the plugin root to rebuild the generated runtime bundle.
