# Architecture Notes: IA Server Diagnostics

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-server-diagnostics`
- Version in header: `0.1.2`
- Main entry file: `ia-server-diagnostics.php`
- Declared purpose: Logs slow WordPress requests and correlates them with server snapshots.

## Authentication and user-state notes

- Touches PeerTube configuration, tokens, or API integration.
- Checks logged-in WordPress user state before serving UI or AJAX.
- Uses capability checks: manage_options.

## Endpoint inventory

- No standalone AJAX, shortcode, rewrite, or REST registrations were detected in this plugin.

## API and integration notes

- No outbound API path or explicit external endpoint string was detected.

## Directory map

### `.`

- Purpose: Plugin root.
- `ARCHITECTURE-NOTES.md` — Local maintenance notes/documentation.
- `ia-server-diagnostics.php` — Runtime file for ia server diagnostics.
- `ia-server-diagnostics.php.bak` — Runtime file for ia server diagnostics php.
- `readme.txt` — Local maintenance notes/documentation.


## Production notes update — April 4, 2026

- Version bumped to `0.1.3`.
- Patch-only change made to reduce `open_basedir` log noise from the sampler auto-import path.
- The plugin previously called `is_dir()` and `is_readable()` directly against the configured sampler directory even when that directory lived outside PHP's active `open_basedir` allowance on production (`/var/www/vhosts/indieagora.com/opt/ia-server-diagnostics` vs allowed `/var/www/vhosts/indieagora.com/httpdocs:/tmp:/tmp`).
- That direct probe generated repeated warnings in logs during normal admin/bootstrap traffic, obscuring the useful Stream/PeerTube auth traces.
- The plugin now gates sampler-directory checks through an internal `open_basedir` compatibility guard before any filesystem probe. If the configured path is outside the active allowance, auto-import simply no-ops for that request instead of emitting warnings.
- This change does not move the sampler, widen server policy, or alter the import format. It only prevents avoidable warning spam from this plugin.
- WordPress core `file_exists(.../wp-content/db.php)` warnings seen in the same logs are separate from this plugin and remain outside the scope of this patch.


## Production follow-up — April 4, 2026 (post-deploy retest)

- Production retest confirms the plugin-side `open_basedir` cleanup is effective.
- The new debug capture no longer shows the earlier repeated warnings from `ia-server-diagnostics` probing `/var/www/vhosts/indieagora.com/opt/ia-server-diagnostics`.
- The remaining repeated warning source is WordPress core `file_exists(.../wp-content/db.php)` from `wp-includes/load.php`.
- That remaining warning is outside this plugin and should be investigated at configuration/bootstrap level, not by further changes here.
- Recommended inspection targets preserved for operations notes: `WP_CONTENT_DIR`, any custom content-dir or `ABSPATH` definitions, and the effective `open_basedir` value applied by the vhost / PHP-FPM pool.
