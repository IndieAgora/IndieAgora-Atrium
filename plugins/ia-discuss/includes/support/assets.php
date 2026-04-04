<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss — Asset registration + enqueue
 *
 * CSS is atomised into smaller files under assets/css/split/* and loaded with
 * explicit WordPress dependencies so the cascade stays stable.
 *
 * JS runtime remains on the generated bundle files for the live site:
 *   - assets/js/ia-discuss.ui.feed.js
 *   - assets/js/ia-discuss.ui.search.js
 *   - assets/js/ia-discuss.ui.composer.js
 *   - assets/js/ia-discuss.router.js
 *   - assets/js/topic/ia-discuss.topic.actions.js
 *
 * Source-of-truth maintenance slices now live under assets/js/split/*.
 * Rebuild the generated runtime bundles with tools/build-assets.sh after editing
 * the split source slices.
 */

function ia_discuss_support_assets_boot(): void {
  add_action('wp_enqueue_scripts', 'ia_discuss_enqueue_assets', 20);
}

function ia_discuss_register_assets(): void {
  $ver = defined('IA_DISCUSS_VERSION') ? IA_DISCUSS_VERSION : '0.0.0';
  $in_footer = true;

  // ---------------------------
  // CSS
  // ---------------------------
  wp_register_style('ia-discuss-base', IA_DISCUSS_URL . 'assets/css/ia-discuss.base.css', [], $ver);
  wp_register_style('ia-discuss-layout', IA_DISCUSS_URL . 'assets/css/ia-discuss.layout.css', ['ia-discuss-base'], $ver);

  // Theme overrides
  wp_register_style('ia-discuss-light', IA_DISCUSS_URL . 'assets/css/ia-discuss.light.css', ['ia-discuss-layout'], $ver);
  wp_register_style('ia-discuss-legacy', IA_DISCUSS_URL . 'assets/css/ia-discuss.legacy.css', ['ia-discuss-light'], $ver);

  wp_register_style('ia-discuss-modal', IA_DISCUSS_URL . 'assets/css/ia-discuss.modal.css', ['ia-discuss-layout'], $ver);
  wp_register_style('ia-discuss-agora', IA_DISCUSS_URL . 'assets/css/ia-discuss.agora.css', ['ia-discuss-layout'], $ver);
  wp_register_style('ia-discuss-composer', IA_DISCUSS_URL . 'assets/css/ia-discuss.composer.css', ['ia-discuss-layout'], $ver);
  wp_register_style('ia-discuss-topic', IA_DISCUSS_URL . 'assets/css/ia-discuss.topic.css', ['ia-discuss-layout', 'ia-discuss-modal'], $ver);
  wp_register_style('ia-discuss-agora-create', IA_DISCUSS_URL . 'assets/css/ia-discuss.agora.create.css', ['ia-discuss-layout', 'ia-discuss-modal'], $ver);

  // Moderation / rules
  wp_register_style('ia-discuss-moderation', IA_DISCUSS_URL . 'assets/css/ia-discuss.moderation.css', ['ia-discuss-layout', 'ia-discuss-modal'], $ver);
  wp_register_style('ia-discuss-rules', IA_DISCUSS_URL . 'assets/css/ia-discuss.rules.css', ['ia-discuss-modal'], $ver);

  // Audio player
  wp_register_style('ia-discuss-audio', IA_DISCUSS_URL . 'assets/css/ia-discuss.audio.css', ['ia-discuss-layout'], $ver);

  // Cards (split)
  wp_register_style('ia-discuss-cards-feed-list-layout', IA_DISCUSS_URL . 'assets/css/split/cards/cards.feed_list_layout.css', ['ia-discuss-layout'], $ver);
  wp_register_style('ia-discuss-cards-card-body-actions', IA_DISCUSS_URL . 'assets/css/split/cards/cards.card_body_actions.css', ['ia-discuss-cards-feed-list-layout'], $ver);
  wp_register_style('ia-discuss-cards-media-thumbs', IA_DISCUSS_URL . 'assets/css/split/cards/cards.media_thumbs.css', ['ia-discuss-cards-card-body-actions'], $ver);
  wp_register_style('ia-discuss-cards-pills-links-modal', IA_DISCUSS_URL . 'assets/css/split/cards/cards.pills_and_links_modal.css', ['ia-discuss-cards-media-thumbs'], $ver);
  wp_register_style('ia-discuss-cards-links-modal-items', IA_DISCUSS_URL . 'assets/css/split/cards/cards.links_modal_items.css', ['ia-discuss-cards-pills-links-modal'], $ver);
  wp_register_style('ia-discuss-cards-video-modal', IA_DISCUSS_URL . 'assets/css/split/cards/cards.video_modal.css', ['ia-discuss-cards-links-modal-items'], $ver);
  wp_register_style('ia-discuss-cards-icon-buttons', IA_DISCUSS_URL . 'assets/css/split/cards/cards.icon_buttons.css', ['ia-discuss-cards-video-modal'], $ver);

  // Search CSS (split)
  wp_register_style('ia-discuss-search-suggestions-dropdown', IA_DISCUSS_URL . 'assets/css/split/search/search.suggestions_dropdown.css', ['ia-discuss-cards-icon-buttons'], $ver);
  wp_register_style('ia-discuss-search-results-list', IA_DISCUSS_URL . 'assets/css/split/search/search.results_list.css', ['ia-discuss-search-suggestions-dropdown'], $ver);
  wp_register_style('ia-discuss-search-results-row-details', IA_DISCUSS_URL . 'assets/css/split/search/search.results_row_details.css', ['ia-discuss-search-results-list'], $ver);

  // ---------------------------
  // JS (stable runtime)
  // ---------------------------
  wp_register_script('ia-discuss-core', IA_DISCUSS_URL . 'assets/js/ia-discuss.core.js', [], $ver, $in_footer);
  wp_register_script('ia-discuss-api', IA_DISCUSS_URL . 'assets/js/ia-discuss.api.js', ['ia-discuss-core'], $ver, $in_footer);
  wp_register_script('ia-discuss-state', IA_DISCUSS_URL . 'assets/js/ia-discuss.state.js', ['ia-discuss-core'], $ver, $in_footer);
  wp_register_script('ia-discuss-youtube', IA_DISCUSS_URL . 'assets/js/ia-discuss.youtube.js', ['ia-discuss-core'], $ver, $in_footer);

  // Audio player (must load before UI modules render players)
  wp_register_script('ia-discuss-audio', IA_DISCUSS_URL . 'assets/js/ia-discuss.audio.js', ['ia-discuss-core'], $ver, $in_footer);

  // Topic base
  wp_register_script('ia-discuss-topic-utils', IA_DISCUSS_URL . 'assets/js/topic/ia-discuss.topic.utils.js', ['ia-discuss-core'], $ver, $in_footer);
  wp_register_script('ia-discuss-topic-media', IA_DISCUSS_URL . 'assets/js/topic/ia-discuss.topic.media.js', ['ia-discuss-core', 'ia-discuss-topic-utils', 'ia-discuss-youtube'], $ver, $in_footer);
  wp_register_script('ia-discuss-topic-modal', IA_DISCUSS_URL . 'assets/js/topic/ia-discuss.topic.modal.js', ['ia-discuss-core', 'ia-discuss-topic-utils'], $ver, $in_footer);
  wp_register_script('ia-discuss-topic-render', IA_DISCUSS_URL . 'assets/js/topic/ia-discuss.topic.render.js', ['ia-discuss-core', 'ia-discuss-topic-utils', 'ia-discuss-topic-media'], $ver, $in_footer);
  wp_register_script('ia-discuss-topic-actions', IA_DISCUSS_URL . 'assets/js/topic/ia-discuss.topic.actions.js', ['ia-discuss-core', 'ia-discuss-topic-utils', 'ia-discuss-topic-modal'], $ver, $in_footer);

  // UI shell + modules
  wp_register_script('ia-discuss-ui-shell', IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.shell.js', ['ia-discuss-core'], $ver, $in_footer);
  wp_register_script('ia-discuss-ui-agora', IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.agora.js', ['ia-discuss-core', 'ia-discuss-api'], $ver, $in_footer);
  wp_register_script('ia-discuss-ui-agora-membership', IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.agora.membership.js', ['ia-discuss-core', 'ia-discuss-api'], $ver, $in_footer);
  wp_register_script('ia-discuss-ui-feed', IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.feed.js', ['ia-discuss-core', 'ia-discuss-api', 'ia-discuss-state', 'ia-discuss-youtube'], $ver, $in_footer);
  wp_register_script('ia-discuss-ui-composer', IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.composer.js', ['ia-discuss-core', 'ia-discuss-api'], $ver, $in_footer);
  wp_register_script('ia-discuss-ui-search', IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.search.js', ['ia-discuss-core', 'ia-discuss-api'], $ver, $in_footer);

  // Moderation + rules UI
  wp_register_script(
    'ia-discuss-ui-moderation',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.moderation.js',
    ['ia-discuss-core', 'ia-discuss-api', 'ia-discuss-ui-shell'],
    $ver,
    $in_footer
  );

  // Small behavioural patches that must run before boot.
  wp_register_script(
    'ia-discuss-modtools',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.modtools.js',
    ['ia-discuss-router', 'ia-discuss-ui-moderation', 'ia-discuss-ui-agora-membership'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-ui-rules',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.rules.js',
    ['ia-discuss-core'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-ui-topic',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.topic.js',
    [
      'ia-discuss-core',
      'ia-discuss-api',
      'ia-discuss-state',
      'ia-discuss-topic-utils',
      'ia-discuss-topic-media',
      'ia-discuss-topic-modal',
      'ia-discuss-topic-render',
      'ia-discuss-topic-actions',
    ],
    $ver,
    $in_footer
  );

  // Router
  wp_register_script(
    'ia-discuss-router',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.router.js',
    [
      'ia-discuss-core',
      'ia-discuss-audio',
      'ia-discuss-api',
      'ia-discuss-state',
      'ia-discuss-ui-shell',
      'ia-discuss-ui-feed',
      'ia-discuss-ui-agora',
      'ia-discuss-ui-agora-membership',
      'ia-discuss-ui-topic',
      'ia-discuss-ui-composer',
      'ia-discuss-ui-search',
      'ia-discuss-ui-moderation',
      'ia-discuss-ui-rules',
    ],
    $ver,
    $in_footer
  );

  // Agora create
  wp_register_script(
    'ia-discuss-agora-create',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.agora.create.js',
    ['ia-discuss-core', 'ia-discuss-api', 'ia-discuss-router', 'ia-discuss-ui-shell'],
    $ver,
    $in_footer
  );

  // Boot
  wp_register_script(
    'ia-discuss-boot',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.boot.js',
    [
      'ia-discuss-core',
      'ia-discuss-audio',
      'ia-discuss-api',
      'ia-discuss-state',
      'ia-discuss-ui-shell',
      'ia-discuss-ui-feed',
      'ia-discuss-ui-agora',
      'ia-discuss-ui-agora-membership',
      'ia-discuss-ui-topic',
      'ia-discuss-ui-composer',
      'ia-discuss-ui-search',
      'ia-discuss-ui-moderation',
      'ia-discuss-ui-rules',
      'ia-discuss-router',
      'ia-discuss-modtools',
    ],
    $ver,
    $in_footer
  );

  wp_localize_script('ia-discuss-core', 'IA_DISCUSS', [
    'ajaxUrl'  => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('ia_discuss'),
    'loggedIn' => is_user_logged_in() ? '1' : '0',
    'userId'   => get_current_user_id(),
    'version'  => $ver,
    'siteTitle' => 'IndieAgora',
    // Optional: Connect integration (user search nonce for Share-to-Connect modal).
    // Strictly additive and safe if ia-connect is not active.
    'connect'  => [
      'active' => function_exists('ia_connect_nonce') ? '1' : '0',
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonces' => [
        'user_search' => function_exists('ia_connect_nonce') ? ia_connect_nonce('user_search') : '',
      ],
    ],

    // Asset URLs for UI modules (additive)
    'assets' => [
      'agoraPlayerLogo' => IA_DISCUSS_URL . 'assets/img/agora-player-logo.png',
    ],
  ]);
}

function ia_discuss_enqueue_assets(): void {
  if (is_admin()) return;

  ia_discuss_register_assets();

  // Base + layout
  wp_enqueue_style('ia-discuss-base');
  wp_enqueue_style('ia-discuss-layout');
  wp_enqueue_style('ia-discuss-light');
  wp_enqueue_style('ia-discuss-legacy');

  // Split CSS endcaps (deps pull the rest)
  wp_enqueue_style('ia-discuss-cards-icon-buttons');
  wp_enqueue_style('ia-discuss-search-suggestions-dropdown');
  wp_enqueue_style('ia-discuss-search-results-list');
  wp_enqueue_style('ia-discuss-search-results-row-details');

  // Existing page CSS
  wp_enqueue_style('ia-discuss-modal');
  wp_enqueue_style('ia-discuss-agora');
  wp_enqueue_style('ia-discuss-composer');
  wp_enqueue_style('ia-discuss-topic');
  wp_enqueue_style('ia-discuss-agora-create');
  wp_enqueue_style('ia-discuss-moderation');
  wp_enqueue_style('ia-discuss-rules');
  wp_enqueue_style('ia-discuss-audio');

  // JS
  wp_enqueue_script('ia-discuss-boot');
  wp_enqueue_script('ia-discuss-agora-create');
}
