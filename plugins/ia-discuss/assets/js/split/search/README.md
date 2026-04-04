# assets / js / split / search

Split source slices for the search runtime bundle.

## File tree
```text
├── _search.body.js
├── search.export.js
├── search.results_clicks.js
├── search.results_load_and_page.js
├── search.results_render.js
├── search.suggestions_box.js
└── search.suggestions_interactions.js
```

## File roles
- `_search.body.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `search.export.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `search.results_clicks.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `search.results_load_and_page.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `search.results_render.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `search.suggestions_box.js` — Split JS source slice used to rebuild a generated runtime bundle.
- `search.suggestions_interactions.js` — Split JS source slice used to rebuild a generated runtime bundle.

## Maintenance entry point
Edit the split JS slices here, then run `./tools/build-assets.sh` from the plugin root to rebuild the generated runtime bundle.
