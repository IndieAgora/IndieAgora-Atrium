# Architecture Notes: IA Mail Suite

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-mail-suite`
- Version in header: `0.2.1`
- Main entry file: `ia-mail-suite.php`
- Declared purpose: Manage WordPress email sender, SMTP, templates, overrides, and one-off user emails (IndieAgora Atrium).

## Authentication and user-state notes

- Touches phpBB-related code or identity mapping.
- Touches PeerTube configuration, tokens, or API integration.
- Nonce strings seen in code: ia_mail_suite_ajax.

## Endpoint inventory

### AJAX actions (via `admin-ajax.php`)

- `ia_mail_suite_send_user` — logged-in only; declared in `includes/class-ia-mail-suite.php`.
- `ia_mail_suite_test_send` — logged-in only; declared in `includes/class-ia-mail-suite.php`.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `README.txt` — Local maintenance notes/documentation.
- `ia-mail-suite.php` — Runtime file for ia mail suite.

### `includes`

- Purpose: PHP runtime code loaded by the plugin bootstrap.
- `includes/class-ia-mail-suite.php` — Runtime file for class ia mail suite.
