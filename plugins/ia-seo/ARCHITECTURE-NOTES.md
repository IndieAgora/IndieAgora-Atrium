# Architecture Notes: IA SEO

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-seo`
- Version in header: `0.1.1`
- Main entry file: `ia-seo.php`
- Declared purpose: Dynamic sitemap.xml generator for Atrium (Connect + Discuss).

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Uses capability checks: manage_options.

## Endpoint inventory

### Rewrite or pretty-URL routes

- Pattern `^sitemap\\.xml$` -> `index.php?` in `includes/support/rewrite.php`.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-seo.php` — Runtime file for ia seo.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/ia-seo.php` — Runtime file for ia seo.

### `includes/services`

- Purpose: Service-layer logic, data access, or integrations.
- `includes/services/connect.php` — Runtime file for connect.
- `includes/services/phpbb.php` — phpBB integration logic.
- `includes/services/sitemap.php` — Runtime file for sitemap.

### `includes/support`

- Purpose: Shared support code such as assets, security, install, and AJAX bootstrapping.
- `includes/support/admin.php` — Admin settings or dashboard logic.
- `includes/support/rewrite.php` — Runtime file for rewrite.
