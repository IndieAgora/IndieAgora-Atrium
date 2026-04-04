# Architecture Notes: IndieAgora Context Menu Override

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-context-menu-override`
- Version in header: `0.2.3`
- Main entry file: `ia-context-menu-override.php`
- Declared purpose: Custom right-click menu across Atrium UI for opening destinations in new tabs/windows and copying links. Includes Quote Selection for Discuss.

## Authentication and user-state notes

- No standalone authentication flow was detected; plugin appears to rely on normal WordPress load context.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-context-menu-override.php` — Runtime file for ia context menu override.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- `assets/ia-context-menu-override.css` — Stylesheet for ia context menu override.
- `assets/ia-context-menu-override.js` — JavaScript for ia context menu override.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- No files directly in this directory.
