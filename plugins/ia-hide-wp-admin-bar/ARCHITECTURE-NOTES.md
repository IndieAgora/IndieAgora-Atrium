# Architecture Notes: IA Hide WP Admin Bar (Atrium Only)

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-hide-wp-admin-bar`
- Version in header: `1.0.0`
- Main entry file: `ia-hide-wp-admin-bar.php`
- Declared purpose: Hides the WordPress admin bar on pages that contain the [ia-atrium] shortcode (front-end only).

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
- `ia-hide-wp-admin-bar.php` — Admin settings or dashboard logic.
