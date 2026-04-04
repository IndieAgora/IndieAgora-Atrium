# assets/js/split endpoint notes

This folder contains the maintenance source slices for the generated runtime JS bundles. These files do not register server endpoints, but they do contain the endpoint caller logic that is concatenated into the live runtime bundles.

## Subfolder ownership

- `composer/` → composer state only; no server endpoint ownership
- `feed/` → feed loading and share-to-connect callers
- `router/` → browser route management plus topic/new/reply/agora caller flows
- `search/` → suggest and search callers
- `topic/` → post moderation action callers

After changing endpoint-calling logic here, rebuild the generated runtime bundles with `tools/build-assets.sh`.
