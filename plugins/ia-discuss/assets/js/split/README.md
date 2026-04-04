# assets / js / split

Source-of-truth JS slices for the largest runtime bundles. These files are ordered concatenation slices inside a generated IIFE bundle, so they are maintained here and rebuilt into the live runtime bundles.

## File tree
```text
├── composer/
├── feed/
├── router/
├── search/
└── topic/
```

## File roles
- `composer/` — Split source slices for the composer runtime bundle.
- `feed/` — Split source slices for the feed runtime bundle.
- `router/` — Split source slices for the router runtime bundle.
- `search/` — Split source slices for the search runtime bundle.
- `topic/` — Split source slices for the topic action runtime bundle.

## Maintenance entry point
Edit the split JS slices here, then run `./tools/build-assets.sh` from the plugin root to rebuild the generated runtime bundles. Do not enqueue the split slices directly.
