# Atrium Deep Links

This file records the route shapes already present in the stack so later SEO work can reuse confirmed deep-link semantics instead of inventing new ones.

## Connect

Base surface:
- `?tab=connect`

Profile routes:
- `?tab=connect&ia_profile_name=<username>` — open a Connect profile by username/slug.

Post routes:
- `?tab=connect&ia_post=<post_id>` — open a Connect post modal.
- `?tab=connect&ia_post=<post_id>&ia_comment=<comment_id>` — open a Connect post modal and focus/highlight a specific comment.

Expected title targets:
- profile route → viewed profile display name when available
- post route → post title, Discuss-share title fallback, or post excerpt fallback
- comment deep-link → same post title context, with comment as focused entity inside the post modal

## Discuss

Base surface:
- `?tab=discuss`

Feed/list routes:
- `?tab=discuss&iad_view=new` — latest topics/posts feed
- `?tab=discuss&iad_view=replies` — replies feed
- `?tab=discuss&iad_view=noreplies` — 0 replies feed
- `?tab=discuss&iad_view=agoras` — agora list
- `?tab=discuss&iad_view=search&iad_q=<query>` — Discuss search

Agora routes:
- `?tab=discuss&iad_view=agora&iad_forum=<forum_id>` — open one Agora feed
- `?tab=discuss&iad_view=agora&iad_forum=<forum_id>&iad_forum_name=<forum_name>` — same, with explicit display label for routing/title stability
- `iad_order=<order>` may accompany Agora/list routes for sort state, for example `most_replies`, `least_replies`, `oldest`, `created`

Topic/reply routes:
- `?tab=discuss&iad_topic=<topic_id>` — open a topic
- `?tab=discuss&iad_topic=<topic_id>&iad_post=<post_id>` — open a topic focused on a specific reply

Expected title targets:
- agora list → Agoras / Agora List
- agora route → `<Agora name> | <sort label>`
- topic route → topic title
- reply deep-link → reply/topic context using topic title
- search route → Search
- no-replies feed → 0 Replies

## Stream

Base surface:
- `?tab=stream`

Primary routes:
- `?tab=stream` — Discover
- `?tab=stream&stream_subscriptions=1` — Subscriptions feed
- `?tab=stream&stream_channel=<handle>` — channel browse
- `?tab=stream&stream_channel=<handle>&stream_channel_name=<display_name>` — channel browse with stable display label
- `?tab=stream&stream_view=search&stream_q=<query>` — Stream search
- `?tab=stream&video=<video_id>` — open a video modal

Comment/reply video deep-links:
- `?tab=stream&video=<video_id>&focus=comments` — open video modal with comment focus
- `?tab=stream&video=<video_id>&focus=comments&stream_comment=<comment_id>` — highlight a top-level comment
- `?tab=stream&video=<video_id>&focus=comments&stream_comment=<comment_id>&stream_reply=<reply_id>` — highlight a reply while preserving the root comment context when known
- in some older/local links `v=<video_id>` may still appear as a compatibility alias for `video=<video_id>`

Expected title targets:
- base route → Discover
- subscriptions route → Subscriptions
- channel route → channel display name/handle
- search route → `Search: <query>`
- video route → active video title

## Notes for later SEO work

Confirmed current behaviour to preserve:
- Connect, Discuss, and Stream all use query-parameter deep links inside the Atrium shell.
- Stream video/comment/reply routes are modal deep links, not separate standalone pages.
- Do not invent pretty-permalink structures until the existing query route contract is deliberately replaced stack-wide.


## Title ownership / shell rule

Because Atrium keeps multiple plugin panels mounted at once, deep-link title sync must be surface-scoped:
- Connect only owns the title while `tab=connect`
- Discuss only owns the title while `tab=discuss`
- Stream only owns the title while `tab=stream`

Do not let hidden-panel observers keep writing `document.title` after a tab switch, or the browser title will drift back to stale profile/topic/video text.

Addendum from live title-debugging:
- In the shared Atrium shell, deep-link ownership checks must prefer `#ia-atrium-shell[data-active-tab]` over `?tab=` because shell switches can briefly leave the old query value in place.
- URL state is still useful for direct loads / refreshes, but not as the first authority during in-shell tab changes.


## Stream sitemap canonical set

For sitemap output, keep Stream to one canonical URL per public entity:
- Discover: `?tab=stream`
- Channel: `?tab=stream&stream_channel=<handle>`
- Video: `?tab=stream&video=<video_id>`

Do not emit the following Stream route shapes in the sitemap:
- `stream_subscriptions=1`
- `stream_view=search&stream_q=...`
- playlist/history/account/upload states
- `focus=comments`
- `stream_comment=...`
- `stream_reply=...`
- legacy/local alias `v=<video_id>`


## 2026-04-05 IA SEO canonical metadata note
- IA SEO metadata and structured-data output only targets canonical public route shapes.
- Deep-link/state fragments such as Stream comment focus, Stream reply focus, subscriptions, history, and search state remain excluded from sitemap output and are not treated as canonical metadata targets.
- Discuss reply routes can still produce page-level metadata if visited, but they remain optional/low-priority sitemap entries under IA SEO settings.
