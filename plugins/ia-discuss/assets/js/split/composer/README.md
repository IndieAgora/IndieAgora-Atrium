# assets / js / split / composer

Split source slices for the composer runtime bundle.

## File tree
```text
├── _composer.body.js
├── composer.bind_attachments.js
├── composer.bind_state_and_files.js
└── composer.export.js
```

## File roles
- `_composer.body.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `composer.bind_attachments.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `composer.bind_state_and_files.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `composer.export.js` — Split JS source slice used to rebuild a generated runtime bundle.

## Maintenance entry point
Edit the split JS slices here, then run `./tools/build-assets.sh` from the plugin root to rebuild the generated runtime bundle.
