# Architecture Notes: IA Profile Menu

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-profile-menu`
- Version in header: `0.1.3`
- Main entry file: `ia-profile-menu.php`
- Declared purpose: Replaces Atrium's Profile dropdown items (zero-touch: no changes to ia-atrium).

## Authentication and user-state notes

- Uses capability checks: manage_options.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-profile-menu.php` — Runtime file for ia profile menu.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-profile-menu.css` — Stylesheet for ia profile menu.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-profile-menu.js` — JavaScript for ia profile menu.
