# Notes: tools

## What changed in the 0.3.59 architecture pass
- build-assets.sh now rebuilds the generated runtime bundles from the split source slices instead of acting as a placeholder.

## File/function index
- `build-assets.sh` — Shell script. Functions: build_js_bundle

## Editing rules for this folder
- Run ./tools/build-assets.sh after editing the split JS folders.

## 0.3.73 housekeeping split
- build-assets.sh was updated to concatenate the new smaller router/feed source slices in a fixed order.
- Preserve that order; several of the new files are contiguous slices of one larger function body.
