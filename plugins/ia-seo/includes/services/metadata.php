<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ia_seo_metadata_boot')) {
  function ia_seo_metadata_boot(): void {
    add_action('wp_head', 'ia_seo_metadata_output', 1);
  }
}

if (!function_exists('ia_seo_metadata_output')) {
  function ia_seo_metadata_output(): void {
    if (is_admin()) return;
    $s = function_exists('ia_seo_get_settings') ? ia_seo_get_settings() : [];
    if (empty($s['metadata_enabled'])) return;

    $meta = ia_seo_metadata_for_request($s);
    if (!is_array($meta) || empty($meta['title'])) return;

    $description = isset($meta['description']) ? trim((string)$meta['description']) : '';
    if ($description !== '') {
      echo "\n" . '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
      echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
      echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
    }

    echo '<meta property="og:title" content="' . esc_attr((string)$meta['title']) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr((string)$meta['title']) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url((string)($meta['url'] ?? home_url('/'))) . '">' . "\n";
    if (!empty($meta['image'])) {
      echo '<meta property="og:image" content="' . esc_url((string)$meta['image']) . '">' . "\n";
      echo '<meta name="twitter:image" content="' . esc_url((string)$meta['image']) . '">' . "\n";
    }
    if (!empty($meta['type'])) {
      echo '<meta property="og:type" content="' . esc_attr((string)$meta['type']) . '">' . "\n";
    }

    if (!empty($s['metadata_jsonld_enabled']) && !empty($meta['jsonld']) && is_array($meta['jsonld'])) {
      echo '<script type="application/ld+json">' . wp_json_encode($meta['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
  }
}

if (!function_exists('ia_seo_metadata_for_request')) {
  function ia_seo_metadata_for_request(array $s): ?array {
    $tab = isset($_GET['tab']) ? sanitize_key((string)$_GET['tab']) : '';
    $site_name = trim((string)($s['metadata_site_name'] ?? get_bloginfo('name')));
    $url = ia_seo_route_url(home_url('/'), $_GET);
    $max = max(80, (int)($s['metadata_max_description_chars'] ?? 320));
    $include_discuss_replies = !empty($s['metadata_include_discuss_replies']);
    $include_stream_comments = !empty($s['metadata_include_stream_comments']);

    if ($tab === 'connect' && !empty($s['metadata_connect_enabled'])) {
      $db = new IA_SEO_Connect_Metadata_DB();
      $slug = isset($_GET['ia_profile_name']) ? sanitize_user((string)$_GET['ia_profile_name'], true) : '';
      if ($slug !== '') {
        $profile = $db->get_profile_by_slug($slug);
        if (is_array($profile)) {
          $parts = array_merge((array)($profile['recent_titles'] ?? []), (array)($profile['recent_bodies'] ?? []));
          $description = ia_seo_metadata_summarize($parts, $max, 'Profile activity on ' . $site_name . '.');
          return [
            'kind' => 'connect_profile',
            'title' => trim((string)$profile['display_name']) . ' | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'connect', 'ia_profile_name' => $slug]),
            'type' => 'profile',
            'jsonld' => [
              '@context' => 'https://schema.org',
              '@type' => 'ProfilePage',
              'name' => trim((string)$profile['display_name']),
              'url' => ia_seo_route_url(home_url('/'), ['tab' => 'connect', 'ia_profile_name' => $slug]),
              'description' => $description,
              'mainEntity' => [
                '@type' => 'Person',
                'name' => trim((string)$profile['display_name']),
              ],
            ],
          ];
        }
      }
      $post_id = isset($_GET['ia_post']) ? (int)$_GET['ia_post'] : 0;
      if ($post_id > 0) {
        $post = $db->get_post_by_id($post_id);
        if (is_array($post)) {
          $parts = [(string)($post['title'] ?? ''), (string)($post['body'] ?? '')];
          if (!empty($post['comment_bodies']) && is_array($post['comment_bodies'])) {
            $parts = array_merge($parts, $post['comment_bodies']);
          }
          $description = ia_seo_metadata_summarize($parts, $max, 'Connect post on ' . $site_name . '.');
          $title = trim((string)($post['title'] ?? ''));
          if ($title === '') $title = 'Connect post';
          return [
            'kind' => 'connect_post',
            'title' => $title . ' | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'connect', 'ia_post' => $post_id]),
            'type' => 'article',
            'jsonld' => [
              '@context' => 'https://schema.org',
              '@type' => 'SocialMediaPosting',
              'headline' => $title,
              'description' => $description,
              'url' => ia_seo_route_url(home_url('/'), ['tab' => 'connect', 'ia_post' => $post_id]),
              'author' => [
                '@type' => 'Person',
                'name' => trim((string)($post['author_name'] ?? '')),
              ],
              'commentCount' => (int)($post['comment_count'] ?? 0),
            ],
          ];
        }
      }
    }

    if ($tab === 'discuss' && !empty($s['metadata_discuss_enabled'])) {
      $db = new IA_SEO_PHPBB_Metadata_DB();
      $forum_id = isset($_GET['iad_forum']) ? (int)$_GET['iad_forum'] : 0;
      if ($forum_id > 0) {
        $forum = $db->get_forum_by_id($forum_id);
        if (is_array($forum)) {
          $description = ia_seo_metadata_summarize([(string)($forum['forum_desc'] ?? ''), (string)($forum['forum_name'] ?? '')], $max, 'Discuss Agora on ' . $site_name . '.');
          return [
            'kind' => 'discuss_agora',
            'title' => trim((string)$forum['forum_name']) . ' | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'discuss', 'iad_view' => 'agora', 'iad_forum' => $forum_id]),
            'type' => 'website',
            'jsonld' => [
              '@context' => 'https://schema.org',
              '@type' => 'CollectionPage',
              'name' => trim((string)$forum['forum_name']),
              'description' => $description,
              'url' => ia_seo_route_url(home_url('/'), ['tab' => 'discuss', 'iad_view' => 'agora', 'iad_forum' => $forum_id]),
            ],
          ];
        }
      }
      $topic_id = isset($_GET['iad_topic']) ? (int)$_GET['iad_topic'] : 0;
      if ($topic_id > 0) {
        $topic = $db->get_topic_by_id($topic_id);
        if (is_array($topic)) {
          $parts = [(string)($topic['topic_title'] ?? ''), (string)($topic['first_post_text'] ?? '')];
          if ($include_discuss_replies && !empty($topic['reply_bodies']) && is_array($topic['reply_bodies'])) {
            $parts = array_merge($parts, $topic['reply_bodies']);
          }
          $description = ia_seo_metadata_summarize($parts, $max, 'Discuss topic on ' . $site_name . '.');
          return [
            'kind' => 'discuss_topic',
            'title' => trim((string)$topic['topic_title']) . ' | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'discuss', 'iad_topic' => $topic_id]),
            'type' => 'article',
            'jsonld' => [
              '@context' => 'https://schema.org',
              '@type' => 'DiscussionForumPosting',
              'headline' => trim((string)$topic['topic_title']),
              'articleBody' => ia_seo_metadata_trim_text((string)($topic['first_post_text'] ?? ''), min($max, 5000)),
              'description' => $description,
              'url' => ia_seo_route_url(home_url('/'), ['tab' => 'discuss', 'iad_topic' => $topic_id]),
              'commentCount' => max(0, (int)($topic['topic_replies'] ?? 0)),
            ],
          ];
        }
      }
      $post_id = isset($_GET['iad_post']) ? (int)$_GET['iad_post'] : 0;
      if ($post_id > 0) {
        $post = $db->get_post_by_id($post_id);
        if (is_array($post)) {
          $description = ia_seo_metadata_summarize([(string)($post['topic_title'] ?? ''), (string)($post['post_text'] ?? '')], $max, 'Discuss reply on ' . $site_name . '.');
          return [
            'kind' => 'discuss_post',
            'title' => trim((string)$post['topic_title']) . ' | Reply | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'discuss', 'iad_topic' => (int)$post['topic_id'], 'iad_post' => $post_id]),
            'type' => 'article',
            'jsonld' => [
              '@context' => 'https://schema.org',
              '@type' => 'Comment',
              'text' => ia_seo_metadata_trim_text((string)($post['post_text'] ?? ''), min($max, 5000)),
              'about' => trim((string)($post['topic_title'] ?? '')),
              'url' => ia_seo_route_url(home_url('/'), ['tab' => 'discuss', 'iad_topic' => (int)$post['topic_id'], 'iad_post' => $post_id]),
            ],
          ];
        }
      }
    }

    if ($tab === 'stream' && !empty($s['metadata_stream_enabled'])) {
      $stream = new IA_SEO_Stream_Service();
      $channel = isset($_GET['stream_channel']) ? sanitize_text_field((string)$_GET['stream_channel']) : '';
      if ($channel !== '' && $stream->ok()) {
        $item = $stream->get_channel_by_handle($channel);
        if (is_array($item)) {
          $description = ia_seo_metadata_summarize([(string)($item['description'] ?? ''), (string)($item['display_name'] ?? '')], $max, 'Stream channel on ' . $site_name . '.');
          return [
            'kind' => 'stream_channel',
            'title' => trim((string)($item['display_name'] ?: $item['handle'])) . ' | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'stream', 'stream_channel' => $channel]),
            'type' => 'video.other',
            'jsonld' => [
              '@context' => 'https://schema.org',
              '@type' => 'CollectionPage',
              'name' => trim((string)($item['display_name'] ?: $item['handle'])),
              'description' => $description,
              'url' => ia_seo_route_url(home_url('/'), ['tab' => 'stream', 'stream_channel' => $channel]),
            ],
          ];
        }
      }
      $video = isset($_GET['video']) ? sanitize_text_field((string)$_GET['video']) : '';
      if ($video !== '' && $stream->ok()) {
        $item = $stream->get_video_by_id($video);
        if (is_array($item)) {
          $parts = [(string)($item['name'] ?? ''), (string)($item['description'] ?? '')];
          $comments = [];
          if ($include_stream_comments) {
            $comments_raw = $stream->get_video_comments($video, (int)($s['metadata_stream_comment_limit'] ?? 5));
            foreach ($comments_raw as $thread) {
              if (!empty($thread['text'])) $comments[] = (string)$thread['text'];
              if (!empty($thread['replies']) && is_array($thread['replies'])) $comments = array_merge($comments, $thread['replies']);
            }
            $parts = array_merge($parts, $comments);
          }
          $description = ia_seo_metadata_summarize($parts, $max, 'Video on ' . $site_name . '.');
          $jsonld = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => trim((string)($item['name'] ?? 'Video')),
            'description' => $description,
            'thumbnailUrl' => !empty($item['thumbnail_url']) ? [(string)$item['thumbnail_url']] : [],
            'uploadDate' => (string)($item['published_at'] ?? ''),
            'contentUrl' => ia_seo_route_url(home_url('/'), ['tab' => 'stream', 'video' => $video]),
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'stream', 'video' => $video]),
            'keywords' => !empty($item['tags']) && is_array($item['tags']) ? implode(', ', $item['tags']) : '',
            'genre' => (string)($item['category'] ?? ''),
            'inLanguage' => (string)($item['language'] ?? ''),
        ];
          if (!empty($comments)) {
            $jsonld['comment'] = [];
            $limit_comments = min(count($comments), max(0, (int)($s['metadata_stream_comment_limit'] ?? 5)));
            for ($i = 0; $i < $limit_comments; $i++) {
              $jsonld['comment'][] = [
                '@type' => 'Comment',
                'text' => ia_seo_metadata_trim_text((string)$comments[$i], min($max, 2000)),
              ];
            }
          }
          return [
            'kind' => 'stream_video',
            'title' => trim((string)($item['name'] ?? 'Video')) . ' | ' . $site_name,
            'description' => $description,
            'url' => ia_seo_route_url(home_url('/'), ['tab' => 'stream', 'video' => $video]),
            'image' => (string)($item['thumbnail_url'] ?? ''),
            'type' => 'video.other',
            'jsonld' => $jsonld,
          ];
        }
      }
    }

    return null;
  }
}

if (!function_exists('ia_seo_metadata_trim_text')) {
  function ia_seo_metadata_trim_text(string $text, int $max): string {
    $text = wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string)$text);
    if ($max > 0 && mb_strlen($text) > $max) {
      $text = rtrim(mb_substr($text, 0, max(0, $max - 1))) . '…';
    }
    return $text;
  }
}

if (!function_exists('ia_seo_metadata_summarize')) {
  function ia_seo_metadata_summarize(array $parts, int $max, string $fallback = ''): string {
    $clean = [];
    foreach ($parts as $part) {
      $part = ia_seo_metadata_trim_text((string)$part, max(40, $max));
      if ($part === '') continue;
      if (in_array($part, $clean, true)) continue;
      $clean[] = $part;
    }
    $text = implode(' ', array_slice($clean, 0, 6));
    $text = ia_seo_metadata_trim_text($text, $max);
    if ($text === '') $text = ia_seo_metadata_trim_text($fallback, $max);
    return $text;
  }
}
