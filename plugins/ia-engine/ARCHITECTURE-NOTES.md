# Architecture Notes: IA Engine

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-engine`
- Version in header: `0.2.1`
- Main entry file: `ia-engine.php`
- Declared purpose: Central config + services for IndieAgora Atrium micro-plugins. Stores secrets encrypted.

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Uses capability checks: manage_options.
- Nonce strings seen in code: ia_engine_nonce.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_engine_pt_refresh_now` — logged-in only; declared in `includes/class-ia-engine-pt-token.php`.
- `ia_engine_test_peertube_api` — logged-in only; declared in `includes/class-ia-engine-ajax.php`.
- `ia_engine_test_peertube_api` — logged-in only; declared in `includes/class-ia-engine.php`.
- `ia_engine_test_peertube_db` — logged-in only; declared in `includes/class-ia-engine-ajax.php`.
- `ia_engine_test_peertube_db` — logged-in only; declared in `includes/class-ia-engine.php`.
- `ia_engine_test_phpbb` — logged-in only; declared in `includes/class-ia-engine-ajax.php`.
- `ia_engine_test_phpbb` — logged-in only; declared in `includes/class-ia-engine.php`.

## API and integration notes

- `/api/v1/config` referenced in `includes/class-ia-engine-ajax.php`.
- `/api/v1/config` referenced in `includes/class-ia-engine.php`.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-engine.php` — Runtime file for ia engine.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- `assets/ia-engine-admin.css` — Stylesheet for ia engine admin.
- `assets/ia-engine-admin.js` — JavaScript for ia engine admin.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-engine-admin.php` — Admin settings or dashboard logic.
- `includes/class-ia-engine-ajax.php` — AJAX endpoint registration or callback logic.
- `includes/class-ia-engine-crypto.php` — PeerTube or token integration logic.
- `includes/class-ia-engine-pt-token.php` — PeerTube or token integration logic.
- `includes/class-ia-engine.php` — Runtime file for class ia engine.
