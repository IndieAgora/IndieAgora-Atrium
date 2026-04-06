<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ia_stream_request_context')) {
  function ia_stream_request_context(): array {
    return [
      'tab' => isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '',
      'video' => isset($_GET['video']) ? sanitize_text_field((string) wp_unslash($_GET['video'])) : '',
      'search' => isset($_GET['stream_q']) ? sanitize_text_field((string) wp_unslash($_GET['stream_q'])) : '',
      'view' => isset($_GET['stream_view']) ? sanitize_key((string) wp_unslash($_GET['stream_view'])) : '',
      'channel' => isset($_GET['stream_channel']) ? sanitize_text_field((string) wp_unslash($_GET['stream_channel'])) : '',
      'channel_name' => isset($_GET['stream_channel_name']) ? sanitize_text_field((string) wp_unslash($_GET['stream_channel_name'])) : '',
      'subscriptions' => isset($_GET['stream_subscriptions']) ? sanitize_text_field((string) wp_unslash($_GET['stream_subscriptions'])) : '',
    ];
  }
}

if (!function_exists('ia_stream_resolve_video_title')) {
  function ia_stream_resolve_video_title(string $video_id): string {
    $video_id = trim($video_id);
    if ($video_id === '' || !class_exists('IA_Stream_Module_Video')) return '';
    try {
      $res = IA_Stream_Module_Video::get_video($video_id);
      if (!is_array($res) || empty($res['ok']) || empty($res['item']) || !is_array($res['item'])) return '';
      return trim(wp_strip_all_tags((string) ($res['item']['title'] ?? '')));
    } catch (Throwable $e) {
      return '';
    }
  }
}

if (!function_exists('ia_stream_resolve_page_title')) {
  function ia_stream_resolve_page_title(): string {
    $ctx = ia_stream_request_context();
    if (($ctx['tab'] ?? '') !== 'stream') return '';

    $site = trim(wp_strip_all_tags((string) get_bloginfo('name')));
    $title = 'Discover';

    $video_id = trim((string) ($ctx['video'] ?? ''));
    $search = trim((string) ($ctx['search'] ?? ''));
    $channel_name = trim((string) ($ctx['channel_name'] ?? ''));
    $channel = trim((string) ($ctx['channel'] ?? ''));
    $subs = trim((string) ($ctx['subscriptions'] ?? ''));
    $view = trim((string) ($ctx['view'] ?? ''));

    if ($video_id !== '') {
      $video_title = ia_stream_resolve_video_title($video_id);
      $title = ($video_title !== '') ? $video_title : 'Video';
    } elseif ($search !== '' || $view === 'search') {
      $title = ($search !== '') ? ('Search: ' . $search) : 'Search';
    } elseif ($subs === '1') {
      $title = 'Subscriptions';
    } elseif ($channel_name !== '' || $channel !== '') {
      $title = ($channel_name !== '') ? $channel_name : $channel;
    }

    $title = trim(wp_strip_all_tags((string) $title));
    if ($title === '') return $site;
    if ($site === '') return $title;
    return $title . ' | ' . $site;
  }
}

if (!function_exists('ia_stream_filter_document_title')) {
  function ia_stream_filter_document_title(string $title): string {
    $resolved = ia_stream_resolve_page_title();
    return ($resolved !== '') ? $resolved : $title;
  }
}

if (!function_exists('ia_stream_print_meta_tags')) {
  function ia_stream_print_meta_tags(): void {
    $resolved = ia_stream_resolve_page_title();
    if ($resolved === '') return;
    echo "
" . '<meta property="og:title" content="' . esc_attr($resolved) . '" />' . "
";
    echo '<meta name="twitter:title" content="' . esc_attr($resolved) . '" />' . "
";
  }
}

if (!function_exists('ia_stream_meta_boot')) {
  function ia_stream_meta_boot(): void {
    add_filter('pre_get_document_title', 'ia_stream_filter_document_title', 99);
    add_action('wp_head', 'ia_stream_print_meta_tags', 1);
  }
}
