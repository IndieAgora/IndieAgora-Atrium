# Notes: assets / js / split

## What changed in the 0.3.59 architecture pass
- Rebuilt the split source tree into clean contiguous slices taken from the stable runtime bundles.
- Established the split folders as the maintenance source-of-truth for the largest JS bundles.

## File/function index
- `composer/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `feed/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `router/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `search/` — See its local README.md and NOTES.md for its own file tree and symbol index.
- `topic/` — See its local README.md and NOTES.md for its own file tree and symbol index.

## Editing rules for this folder
- Do not edit the generated runtime bundle first. Update the split slices and rebuild.
- Keep slice order stable because the build script concatenates these files into a single IIFE runtime bundle.
- Do not treat the split slices as standalone browser scripts; the generated bundle is the runtime artifact.
