# IA Discuss

IA Discuss is a WordPress plugin that mounts an Atrium Discuss panel backed by phpBB. The plugin is composed from four layers: WordPress bootstrap and support glue, PHP services and modules, front-end runtime assets, and developer notes/build tooling.

## Architecture map

- `ia-discuss.php` loads the orchestrator and defines the plugin constants used by every other layer.
- `includes/` holds the PHP runtime. `includes/support/` wires WordPress, `includes/services/` encapsulates phpBB/auth/notify/membership logic, `includes/render/` formats content, and `includes/modules/` exposes AJAX feature entry points.
- `assets/css/` and `assets/js/` hold the front-end assets. The live site still loads generated JS bundle files for stability, while the largest bundles are now maintained through atomised source slices in `assets/js/split/`.
- `tools/build-assets.sh` rebuilds the generated JS bundles from the split source slices.
- `DEVELOPMENT-NOTES.md` is the root maintenance policy and should be updated whenever behaviour or structure changes.
- `ENDPOINTS.md` is the master endpoint map and should be updated whenever the AJAX or browser-route surface changes.

## Runtime asset model

The live site still enqueues the generated runtime bundle files so WordPress handles remain stable and the browser only sees the tested runtime files. The source-of-truth for the large JS bundles is now the split source tree. Those split files are concatenation slices, not standalone script tags:

- `assets/js/split/feed/` → `assets/js/ia-discuss.ui.feed.js`
- `assets/js/split/search/` → `assets/js/ia-discuss.ui.search.js`
- `assets/js/split/composer/` → `assets/js/ia-discuss.ui.composer.js`
- `assets/js/split/router/` → `assets/js/ia-discuss.router.js`
- `assets/js/split/topic/` → `assets/js/topic/ia-discuss.topic.actions.js`

After editing those split slices, run `./tools/build-assets.sh` from the plugin root.

## Folder map

```text
├── assets/
├── includes/
├── tools/
├── DEVELOPMENT-NOTES.md
├── NOTES.md
├── README.md
└── ia-discuss.php
```

Read the local README.md and NOTES.md inside any folder before making changes there.
