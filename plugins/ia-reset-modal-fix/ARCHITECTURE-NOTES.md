# Architecture Notes: IA Reset Modal Fix

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-reset-modal-fix`
- Version in header: `0.1.1`
- Main entry file: `ia-reset-modal-fix.php`
- Declared purpose: Ensures /ia-reset/ links reliably open a password reset panel in the Atrium auth modal.

## Authentication and user-state notes

- Nonce strings seen in code: ia_user_nonce.

## Endpoint inventory

### Rewrite or pretty-URL routes

- Pattern `^ia-reset/?$` -> `index.php?ia_reset_route=1` in `ia-reset-modal-fix.php`.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-reset-modal-fix.php` — Runtime file for ia reset modal fix.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-reset-fix.css` — Stylesheet for ia reset fix.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-reset-fix.js` — JavaScript for ia reset fix.
