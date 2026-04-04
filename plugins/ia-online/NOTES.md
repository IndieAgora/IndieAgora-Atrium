# Notes: IA Online

## Core entry points

- `ia-online.php` — plugin header, constants, activation, boot wiring.
- `includes/bootstrap.php` — shared helpers, settings, table naming, IP/session helpers.
- `includes/db/schema.php` — presence table install and cleanup event.
- `includes/runtime/presence.php` — request tracking, identity resolution, route/url capture.
- `includes/runtime/ajax.php` — lightweight presence ping endpoint for SPA route changes.
- `includes/admin/page.php` — admin UI listing live user and guest sessions.

## Operating rule

Keep changes patch-only. Preserve existing Atrium routing and identity mapping. Prefer compatibility fallbacks over assumptions.

## 0.1.0 initial baseline

- Added `IA Online` as a standalone Atrium-aware presence plugin.
- Tracks logged-in users and guests separately in `wp_ia_online_presence`.
- Resolves phpBB user ids through `ia_phpbb_user_id`, `phpbb_user_id`, or `wp_ia_identity_map` when available.
- Records IP address, user agent, current route, current URL, and last seen.
- Adds a lightweight front-end ping so SPA navigation keeps presence fresh.
- Adds an admin page under the Atrium menu when present, otherwise under Tools.
- Keeps the plugin self-contained and avoids changing Connect/Discuss internals.


## 0.1.1 guest visibility admin patch

- Split the admin live-session view into separate `Users online` and `Guests online` tables.
- Keeps guest IPs visible in their own list instead of relying on one combined table.
- Patch-only admin UI change; tracking/storage logic unchanged.


## 0.1.2 admin layout tidy patch
- Wrapped admin tables in a horizontal scroll container so long URLs do not break the layout on narrow screens.
- Adjusted summary cards to stack cleanly on mobile-width admin views.
- Kept tracking and counts unchanged; this is a display-only tidy-up.

## 0.1.3 guest dedupe patch
- Tightened guest session handling so repeat guest pings update an existing recent guest row instead of inserting duplicates.
- Kept the existing cookie-backed guest session key, and made cookie setting more robust with `SameSite=Lax`.
- Added a narrow fallback for guests: if the cookie key is not yet available, update the most recent active guest row matching the same IP and user agent within the online window.


## 0.1.4 guest identity persistence patch
- Ensured the guest cookie is attempted early on `init` before request tracking runs.
- Changed guest fallback identity from a fresh random value to a stable fingerprint when a cookie cannot be read or set, so repeated no-cookie requests do not create endless duplicate guest rows.
- Added a narrow guest dedupe cleanup after upsert so concurrent rapid guest hits with the same session key or same recent IP+user-agent collapse back to one live row.
- Kept user tracking and admin UI behavior otherwise unchanged.

## 0.2.0 analytics baseline
- Added lightweight history capture tables for per-minute online samples and sampled route popularity.
- Added lazy history aggregation after tracked requests so analytics can populate without adding a JS chart dependency or a separate cron-only pipeline.
- Added Last 24 hours analytics cards, simple SVG line charts for users/guests/total sessions, and a Popular routes (24h) table.
- Kept this patch Atrium-aware and lightweight: no Connect/Discuss changes, no external chart libraries, and no per-hit event logging.


## 0.2.1 analytics tabs admin patch
- Added explicit admin tabs for `Overview`, `Analytics`, and `Live sessions` so the new analytics section is directly reachable instead of sitting further down a long single page.
- Kept history capture, chart rendering, and live tracking logic unchanged.


## 0.2.2
- Replaced WP nav-tab markup with plugin-owned visible tabs so section switching is visible in Atrium admin contexts and on mobile.
- Tab links now derive from `menu_page_url('ia-online', false)` instead of hardcoded `tools.php`, so Analytics/Live links work under Atrium parent menus too.
- Added explicit Overview action buttons to open Analytics and Live sessions.


## 0.2.4
- Rebuilt analytics/tabs/features on top of the active 0.1.4 guest-identity-fix baseline after inactive copies caused no visible change on the live site.
- Includes analytics history capture, overview/analytics/live tabs, lightweight SVG charts, and popular route stats.
- Diagnostic note: when multiple plugin copies exist in WordPress, patch the active installed copy only and remove/deactivate older duplicates before judging whether a change landed.

- 0.2.4 packaging fix: rebuilt analytics update zip with the correct `ia-online/` root folder so WordPress updates the active plugin instead of installing a separate copy.


## 0.2.6 analytics range/detail patch
- Added analytics date-range controls (`24h`, `7d`, `30d`, custom from/to) on the existing Analytics tab.
- Expanded chart detail with range labels, start/mid/end time markers, and peak timestamp context.
- Added a captured-samples table so exact bucket times and counts are visible alongside the graphs.
- Built this directly on the active `ia-online/` update package line to preserve WordPress in-place updates.


## 0.2.6 live-route detail patch
- Improved live session detail so frontend locations survive noisy `wp-admin`/`admin-ajax.php` refreshes instead of being overwritten as plain `admin`.
- Added request context labeling in the live sessions table (`frontend`, `ajax`, `admin`, `login`) to distinguish real page views from background requests.
- Expanded Atrium route labels for live sessions so Discuss and Connect views show more detail such as topic, agora, profile, or post ids when those are present in the URL.
- Shortened displayed URLs in the admin table to compact path-style labels so mobile live-session rows remain readable.
