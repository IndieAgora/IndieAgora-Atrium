# Architecture Notes: IA Cache Control

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-cache-control`
- Version in header: `0.1.0`
- Main entry file: `ia-cache-control.php`
- Declared purpose: Deterministic cache-busting + diagnostics for Atrium surface assets (admin-facing).

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
- `ia-cache-control.php` — Runtime file for ia cache control.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/admin.css` — Stylesheet for admin.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-cache-control.php` — Runtime file for class ia cache control.

### `includes/admin`

- Purpose: Admin-only UI or settings logic.
- `includes/admin/class-ia-cache-control-admin.php` — Admin settings or dashboard logic.
