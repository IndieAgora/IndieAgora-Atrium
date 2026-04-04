# IA Online

Atrium-aware online presence tracker for WordPress.

## Purpose

Provides a simple online/guest presence layer that fits an Atrium-style stack:
- tracks logged-in WordPress users
- resolves phpBB ids through `ia_identity_map` or user meta
- tracks guests with a cookie-backed session key
- records IP address, current route, current URL, and last seen time
- shows live sessions in wp-admin
- captures lightweight per-minute history for recent analytics

## Current scope

- Database table: `{$wpdb->prefix}ia_online_presence`
- History tables: `{$wpdb->prefix}ia_online_presence_history` and `{$wpdb->prefix}ia_online_presence_route_history`
- Front-end ping for SPA route changes
- Admin page under Atrium menu when present, otherwise under Tools
- Admin tabs for Overview, Analytics, and Live Sessions
- Hourly cleanup of stale sessions and retained history
- Lightweight SVG charts with no extra JS chart dependency

## Notes

This plugin is intentionally narrow. It does not modify Connect, Discuss, or login flows. It observes presence and stores it separately.


### 0.2.2
Admin sections now use visible plugin-owned tabs and page-aware links, so Overview, Analytics, and Live sessions remain reachable under Atrium admin menus and on mobile layouts.

### 0.2.6
Analytics now supports explicit date ranges (`24h`, `7d`, `30d`, or custom from/to), richer chart labels, and a captured-samples table so times and dates are visible without leaving the plugin.
