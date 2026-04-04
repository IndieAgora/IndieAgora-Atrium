#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

build_js_bundle() {
  local target="$1"
  shift
  local tmp
  tmp="$(mktemp)"
  {
    printf '(function () {\n'
    for part in "$@"; do
      cat "$ROOT/$part"
      printf '\n'
    done
    printf '})();\n'
  } > "$tmp"
  mv "$tmp" "$ROOT/$target"
  echo "[built] $target"
}

echo "[IA Discuss] Rebuilding generated runtime bundles from split source slices"
echo "Root: $ROOT"

build_js_bundle \
  "assets/js/ia-discuss.ui.feed.js" \
  "assets/js/split/feed/_feed.body.js" \
  "assets/js/split/feed/feed.links_modal.js" \
  "assets/js/split/feed/feed.video_parsers.js" \
  "assets/js/split/feed/feed.video_open_and_attachments.js" \
  "assets/js/split/feed/feed.media_blocks_and_pills.js" \
  "assets/js/split/feed/feed.card_template.js" \
  "assets/js/split/feed/feed.load_request.js" \
  "assets/js/split/feed/feed.render.body.js" \
  "assets/js/split/feed/feed.pagination.js" \
  "assets/js/split/feed/feed.render.loading.js" \
  "assets/js/split/feed/feed.render.clicks_and_boot.js" \
  "assets/js/split/feed/feed.export.js"

build_js_bundle \
  "assets/js/ia-discuss.ui.search.js" \
  "assets/js/split/search/_search.body.js" \
  "assets/js/split/search/search.suggestions_box.js" \
  "assets/js/split/search/search.suggestions_interactions.js" \
  "assets/js/split/search/search.results_render.js" \
  "assets/js/split/search/search.results_clicks.js" \
  "assets/js/split/search/search.results_load_and_page.js" \
  "assets/js/split/search/search.export.js"

build_js_bundle \
  "assets/js/ia-discuss.ui.composer.js" \
  "assets/js/split/composer/_composer.body.js" \
  "assets/js/split/composer/composer.bind_state_and_files.js" \
  "assets/js/split/composer/composer.bind_attachments.js" \
  "assets/js/split/composer/composer.export.js"

build_js_bundle \
  "assets/js/ia-discuss.router.js" \
  "assets/js/split/router/_router.body.js" \
  "assets/js/split/router/router.render.entry.js" \
  "assets/js/split/router/router.render.agoras.js" \
  "assets/js/split/router/router.render.default_views.js" \
  "assets/js/split/router/router.feed_scroll_state.js" \
  "assets/js/split/router/router.open_pages.js" \
  "assets/js/split/router/router.event_bus.js" \
  "assets/js/split/router/router.route_from_url.js" \
  "assets/js/split/router/router.export.js"

build_js_bundle \
  "assets/js/topic/ia-discuss.topic.actions.js" \
  "assets/js/split/topic/_ta.body.js" \
  "assets/js/split/topic/topic_actions.bind_actions.js" \
  "assets/js/split/topic/topic_actions.export.js"

echo "[IA Discuss] Done"
