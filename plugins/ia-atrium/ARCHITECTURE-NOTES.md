# Architecture Notes: IA Atrium

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-atrium`
- Version in header: `0.1.11`
- Main entry file: `ia-atrium.php`
- Declared purpose: Core Atrium shell (Connect / Discuss / Stream tabs + bottom navigation). All features are added via micro-plugins.

## Authentication and user-state notes

- Contains WordPress logout/session termination logic.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Nonce strings seen in code: ia_connect:user_search, ia_connect:wall_search.

## Endpoint inventory

### Shortcodes

- `ia-atrium` — registered in `includes/class-ia-atrium.php`.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-atrium.php` — Runtime file for ia atrium.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/atrium.css` — Stylesheet for atrium.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/atrium.js` — JavaScript for atrium.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-atrium-assets.php` — Asset enqueue and localization logic.
- `includes/class-ia-atrium.php` — Runtime file for class ia atrium.

### `templates`

- Purpose: PHP templates rendered into the front end.
- `templates/atrium-shell.php` — Runtime file for atrium shell.
