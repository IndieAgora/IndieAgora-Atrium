# includes

PHP composition layer. Bootstraps support helpers, services, renderers, and AJAX modules.

## File tree
```text
├── modules/
├── render/
├── services/
├── support/
├── functions.php
└── ia-discuss.php
```

## File roles
- `modules/` — AJAX-facing module layer. Each module declares routes and feature entry points.
- `render/` — Server-side formatting helpers for BBCode, attachments, and media extraction.
- `services/` — Longer-lived service classes that encapsulate auth, phpBB access, notifications, uploads, and membership logic.
- `support/` — WordPress support glue for assets, AJAX dispatch, security checks, and relationship endpoints.
- `functions.php` — PHP source file used by the plugin runtime.
- `ia-discuss.php` — WordPress plugin header and root bootstrap loader.

## Maintenance entry point
Use this folder README together with the sibling NOTES file before changing anything in the folder.
