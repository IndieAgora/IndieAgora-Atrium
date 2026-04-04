# Architecture Notes: IA Login

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-login`
- Version in header: `0.1.0`
- Main entry file: `ia-login.php`
- Declared purpose: Provides the Atrium auth modal (login/register) markup via the ia_atrium_auth_modal hook.

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
- `ia-login.php` — Runtime file for ia login.

### `assets`

- Purpose: Front-end assets loaded by the plugin.
- No files directly in this directory.

### `assets/css`

- Purpose: Stylesheets for the plugin UI.
- `assets/css/ia-login.css` — Stylesheet for ia login.

### `templates`

- Purpose: PHP templates rendered into the front end.
- `templates/auth-modal.php` — Authentication-related logic.

## 2026-04-04 structural cleanup: modal posts to canonical surface

Context from live debugging and user discussion:
- The user asked to reduce duplicate auth surfaces and preserve the details in notes.
- The visible/public modal should not keep posting to `ia_auth_login` once the canonical live ladder is `ia_user_login`.

What changed:
- The modal login form now posts to `ia_user_login`.
- Register/forgot remain on their existing actions.

Why:
- This reduces accidental forking between the visible modal and the working live ladder proven by trace logs.


## 2026-04-04 canonical auth diagram reference

This plugin is the visible/public entry surface, not the ladder owner.

Current role in the confirmed live auth chain:
- renders the visible login modal
- posts login submissions to `ia_user_login`
- intentionally does not own the auth ladder itself

Pithy summary: the modal is the door, not the engine.

Future consolidation note:
- in a one-plugin auth system, the UI surface and the ladder may live under one plugin namespace, but the current stack still keeps them separate for compatibility.
