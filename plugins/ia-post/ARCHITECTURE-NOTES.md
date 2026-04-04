# Architecture Notes: IA Post

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-post`
- Version in header: `0.1.5`
- Main entry file: `ia-post.php`
- Declared purpose: Global Atrium post composer (Connect + Discuss). Hooks into the Atrium bottom nav "Post" button.

## Authentication and user-state notes

- Checks logged-in WordPress user state before serving UI or AJAX.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-post.php` — Runtime file for ia post.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-post.css` — Stylesheet for ia post.

### `assets/js`

- Purpose: Browser-side runtime and UI behaviour.
- `assets/js/ia-post.js` — JavaScript for ia post.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-post-assets.php` — Asset enqueue and localization logic.
- `includes/class-ia-post.php` — Runtime file for class ia post.

### `templates`

- Purpose: PHP templates rendered into the front end.
- `templates/composer-mount.php` — Runtime file for composer mount.
