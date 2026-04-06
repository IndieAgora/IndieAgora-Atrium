# AJAX notes for ia-seo / .

Files in this directory inspected for AJAX handling:

- `AJAX-HANDLERS.md`
- `ARCHITECTURE-NOTES.md`
- `ia-seo.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.


## 2026-04-05 sitemap cleanup for Search Console
- Reworked IA SEO so the sitemap now focuses on indexable public Atrium routes instead of dumping every deep-link shape.
- Added Connect public profile URLs to the sitemap, but only when the target profile still has Connect privacy flags `searchable=1` and `seo=1`.
- Added Discuss public Agora URLs and filtered Discuss topics/replies against the private-Agora table so private spaces are excluded from sitemap output.
- Changed the default stance on Discuss reply deep-links to off, because reply URLs are lower-value duplicate-style fragments for Search Console compared with topic URLs.
- Added route sanitising inside sitemap generation so each `tab` keeps only its own known parameters. This prevents junk carry-over such as Discuss params leaking into Stream/other route shapes when a base URL or deep-link seed is messy.
- Kept the sitemap query-param contract. No pretty permalink scheme was invented in this patch.


## 2026-04-05 Stream sitemap expansion for Search Console
- Added Stream sitemap support to IA SEO so `/sitemap.xml` now emits clean Stream routes as well as Connect/Discuss routes.
- Included only indexable public Stream surfaces: Discover (`?tab=stream`), public channels (`?tab=stream&stream_channel=<handle>`), and public videos (`?tab=stream&video=<uuid>`).
- Explicitly kept logged-in or duplicate-heavy Stream states out of the sitemap: subscriptions, history, search results, playlists, upload/account screens, and comment/reply focus deep-links.
- Reused confirmed PeerTube public read surfaces already present elsewhere in the stack (`/api/v1/video-channels` and `/api/v1/videos`) via the canonical IA Engine public base URL when available.
- Kept Stream channel sitemap URLs canonical by emitting `stream_channel` only; no `stream_channel_name`, `focus`, `stream_comment`, `stream_reply`, `stream_view`, `stream_q`, or compatibility alias params are emitted.
- Expanded IA SEO admin settings with Stream toggles, limits, priorities, and changefreq controls.


## 2026-04-05 metadata controls and analytics
- Added page-level metadata output in IA SEO for Connect, Discuss, and Stream public routes.
- IA SEO now emits `<meta name="description">`, Open Graph fields, Twitter title/description fields, and JSON-LD when enabled.
- Added a separate backend page under Settings → IA SEO Metadata so metadata policy can be edited without mixing it into the sitemap-only screen.
- Metadata controls include per-surface enablement, JSON-LD toggle, max description length, whether Discuss replies can contribute to topic descriptions, whether Stream comments/replies can contribute to video descriptions, and how many Stream comment threads may be sampled.
- Added basic analytics on the metadata page: current visible counts under the active caps for Connect profiles/posts, Discuss Agoras/topics, Stream channels/videos, plus live preview cards showing generated titles, URLs, and descriptions.
- Stream video sitemap entries can now emit valid video sitemap extension fields (thumbnail, title, description, publication date) when enabled.
- IA SEO stores lightweight sitemap render stats in `ia_seo_sitemap_stats_v1` so the backend can report counts without reading raw XML.
