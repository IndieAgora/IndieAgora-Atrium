# Architecture Notes: IA SEO

Generated from the current plugin code on March 15, 2026. This is a working map of the plugin as shipped in this stack, with an emphasis on endpoints, authentication touchpoints, API links, and what each directory/file is for.

## Overview

- Plugin slug: `ia-seo`
- Version in header: `0.1.3`
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


## 2026-04-05 sitemap eligibility / route policy
- IA SEO now treats the sitemap as a curated list of public indexable Atrium routes, not a dump of every modal or stateful deep-link.
- Included by design: Connect landing, Discuss landing, Connect public profiles, Connect posts, public Discuss Agoras, public Discuss topics.
- Excluded by default: Discuss reply deep-links, Stream search/history/subscription surfaces, comment-focused deep-links, and any route that depends on logged-in or modal-only state.
- Discuss URLs are filtered against the local private-Agora table `wp_ia_discuss_agora_privacy` when present.
- Connect profile URLs are filtered through existing Connect privacy helpers rather than inventing new privacy logic inside IA SEO.
- Sitemap URLs are normalised by tab-specific allowed query parameters so polluted mixed-state URLs are not emitted.


## 2026-04-05 Stream sitemap inclusion policy
- IA SEO now includes Stream in the curated sitemap surface.
- Included by design for Stream: Discover, public channel browse routes, and public video routes.
- Excluded by design for Stream: subscriptions, history, search results, playlists, upload/editor state, and comment/reply-focus deep-links.
- Stream public entities are read from confirmed PeerTube public endpoints already used elsewhere in the stack: `/api/v1/video-channels` and `/api/v1/videos`.
- Canonical Stream sitemap routes keep only `tab`, `stream_channel`, and `video` query args. Older/local aliases such as `v`, plus stateful params such as `focus`, `stream_comment`, `stream_reply`, `stream_view`, `stream_q`, `stream_scope`, and `stream_sort`, are stripped from sitemap output.


## 2026-04-05 page-level metadata and analytics
- New service file: `includes/services/metadata.php`.
- New backend submenu page: Settings → IA SEO Metadata (`ia-seo-metadata`).
- Metadata runtime inspects Atrium query-param routes and builds page-level metadata only for public canonical surfaces already supported by the stack:
  - Connect profile and Connect post.
  - Discuss Agora, Discuss topic, and Discuss reply.
  - Stream channel and Stream video.
- Structured-data types now emitted by IA SEO when enabled:
  - `ProfilePage` for Connect profile routes.
  - `SocialMediaPosting` for Connect posts.
  - `CollectionPage` for Discuss Agoras and Stream channels.
  - `DiscussionForumPosting` for Discuss topics.
  - `Comment` for Discuss reply deep-links.
  - `VideoObject` for Stream videos.
- Stream sitemap rendering now supports the Google video sitemap namespace for Stream video entries when thumbnail/title/description data is available.
- IA SEO now records last sitemap render counts in the WordPress option `ia_seo_sitemap_stats_v1`.
