<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Render: Media
 *
 * Stateless transformers that convert PeerTube API payloads
 * into stable “card” objects for JS.
 *
 * We deliberately normalize to a consistent shape:
 * - Videos: { id, title, url, embed_url, thumbnail, preview, channel:{...}, counts:{...}, published_ago, excerpt }
 * - Channels: { id, name, display_name, url, avatar, cover, followers }
 * - Comments: { id, text, created_ago, author:{...}, replies_count }
 */

function ia_stream_abs_url(?string $base, ?string $path): string {
  $base = rtrim(trim((string)$base), '/');
  $path = trim((string)$path);

  if ($path === '') return '';
  if (preg_match('#^https?://#i', $path)) return $path;

  if ($base === '') return $path;
  if ($path[0] !== '/') $path = '/' . $path;

  return $base . $path;
}

/**
 * PeerTube often returns a "thumbnailPath" or "previewPath".
 * Sometimes it returns arrays of thumbnails. We try common fields.
 */
function ia_stream_pick_image(array $o, array $keys): string {
  foreach ($keys as $k) {
    if (!isset($o[$k])) continue;

    $v = $o[$k];

    // common: string path
    if (is_string($v) && trim($v) !== '') return trim($v);

    // sometimes: array of sizes
    if (is_array($v)) {
      // try "path"
      if (isset($v['path']) && is_string($v['path']) && trim($v['path']) !== '') return trim($v['path']);

      // try list: pick first with path
      foreach ($v as $row) {
        if (is_array($row) && isset($row['path']) && is_string($row['path']) && trim($row['path']) !== '') {
          return trim($row['path']);
        }
      }
    }
  }
  return '';
}

/**
 * Normalize PeerTube "channel/actor" object into a consistent channel shape.
 */
function ia_stream_norm_channel(array $ch, string $base = ''): array {
  // PeerTube channel object often has: id, name, displayName, url, avatars[], banners[]
  $id = isset($ch['id']) ? (string)$ch['id'] : '';
  $name = isset($ch['name']) ? (string)$ch['name'] : '';
  $display = ia_stream_first_str(
    isset($ch['displayName']) ? (string)$ch['displayName'] : '',
    isset($ch['display_name']) ? (string)$ch['display_name'] : '',
    $name
  );

  $url = ia_stream_first_str(
    isset($ch['url']) ? (string)$ch['url'] : '',
    isset($ch['uri']) ? (string)$ch['uri'] : ''
  );

  // avatars / banners can be arrays of sizes with path
  $avatarPath = '';
  if (isset($ch['avatars']) && is_array($ch['avatars'])) {
    foreach ($ch['avatars'] as $a) {
      if (is_array($a) && isset($a['path']) && is_string($a['path']) && trim($a['path']) !== '') {
        $avatarPath = trim($a['path']);
        break;
      }
    }
  }
  if ($avatarPath === '') $avatarPath = ia_stream_pick_image($ch, ['avatarPath', 'avatar_path', 'avatar']);

  $coverPath = '';
  if (isset($ch['banners']) && is_array($ch['banners'])) {
    foreach ($ch['banners'] as $b) {
      if (is_array($b) && isset($b['path']) && is_string($b['path']) && trim($b['path']) !== '') {
        $coverPath = trim($b['path']);
        break;
      }
    }
  }
  if ($coverPath === '') $coverPath = ia_stream_pick_image($ch, ['bannerPath', 'banner_path', 'coverPath', 'cover_path', 'banner', 'cover']);

  $followers = 0;
  if (isset($ch['followersCount'])) $followers = (int)$ch['followersCount'];
  if (isset($ch['followers_count'])) $followers = (int)$ch['followers_count'];

  return [
    'id'           => $id,
    'name'         => $name,
    'display_name' => $display,
    'url'          => $url,
    'avatar'       => ia_stream_abs_url($base, $avatarPath),
    'cover'        => ia_stream_abs_url($base, $coverPath),
    'followers'    => $followers,
  ];
}

/**
 * Normalize a PeerTube video object into a “video card” shape.
 * Works with /api/v1/videos and /api/v1/videos/{id}.
 */
function ia_stream_norm_video(array $v, string $base = ''): array {
  $id = ia_stream_first_str(
    isset($v['uuid']) ? (string)$v['uuid'] : '',
    isset($v['id']) ? (string)$v['id'] : ''
  );

  $title = ia_stream_first_str(
    isset($v['name']) ? (string)$v['name'] : '',
    isset($v['title']) ? (string)$v['title'] : ''
  );

  $desc = ia_stream_first_str(
    isset($v['description']) ? (string)$v['description'] : '',
    isset($v['descriptionHTML']) ? (string)$v['descriptionHTML'] : ''
  );

  $url = ia_stream_first_str(
    isset($v['url']) ? (string)$v['url'] : '',
    isset($v['originalUrl']) ? (string)$v['originalUrl'] : ''
  );

  // thumbnails / preview
  $thumbPath = ia_stream_pick_image($v, ['thumbnailPath', 'thumbnail_path', 'thumbnail']);
  $prevPath  = ia_stream_pick_image($v, ['previewPath', 'preview_path', 'preview']);

  // channel embedded in video
  $channel = [];
  if (isset($v['channel']) && is_array($v['channel'])) {
    $channel = ia_stream_norm_channel($v['channel'], $base);
  } elseif (isset($v['videoChannel']) && is_array($v['videoChannel'])) {
    $channel = ia_stream_norm_channel($v['videoChannel'], $base);
  }

  // counts
  $views = isset($v['views']) ? (int)$v['views'] : (isset($v['viewsCount']) ? (int)$v['viewsCount'] : 0);
  $likes = isset($v['likes']) ? (int)$v['likes'] : (isset($v['likesCount']) ? (int)$v['likesCount'] : 0);
  $dislikes = isset($v['dislikes']) ? (int)$v['dislikes'] : 0;
  // PeerTube list endpoints are not always consistent about comment count field names.
  // Prefer commentsCount, but fall back to other common variants.
  $comments = 0;
  if (isset($v['commentsEnabled']) && !$v['commentsEnabled']) {
    $comments = 0;
  } else {
    if (isset($v['commentsCount'])) $comments = (int)$v['commentsCount'];
    elseif (isset($v['commentCount'])) $comments = (int)$v['commentCount'];
    elseif (isset($v['comments'])) $comments = (int)$v['comments'];
    elseif (isset($v['totalComments'])) $comments = (int)$v['totalComments'];
  }

  // publish
  $publishedAt = ia_stream_first_str(
    isset($v['publishedAt']) ? (string)$v['publishedAt'] : '',
    isset($v['createdAt']) ? (string)$v['createdAt'] : ''
  );

  // embed URL: PeerTube supports embed at /videos/embed/{uuid}
  $embed = '';
  if ($id !== '' && $base !== '') {
    $embed = rtrim($base, '/') . '/videos/embed/' . rawurlencode($id);
  }

  // Optional support text (PeerTube "support" field)
  $support = '';
  if (array_key_exists('support', $v)) {
    $support = is_string($v['support']) ? (string) $v['support'] : '';
  }

  return [
    'id'          => $id,
    'title'       => $title,
    'url'         => $url,
    'embed_url'   => $embed,
    'thumbnail'   => ia_stream_abs_url($base, $thumbPath),
    'preview'     => ia_stream_abs_url($base, $prevPath),
    'excerpt'     => ia_stream_excerpt($desc, 240),
    'support'     => $support,
    'published_at'=> $publishedAt,
    'published_ago'=> ia_stream_time_ago($publishedAt),
    'channel'     => $channel,
    'counts'      => [
      'views'    => $views,
      'likes'    => $likes,
      'dislikes' => $dislikes,
      'comments' => $comments,
    ],
  ];
}

/**
 * Normalize PeerTube comment thread item into a consistent comment shape.
 * PeerTube comment threads include a "comment" and possibly "children"/"totalReplies".
 */
function ia_stream_norm_comment_thread(array $t, string $base = ''): array {
  // Thread objects vary; try common layouts:
  // - { id, comment: { text, account/author, createdAt }, totalReplies, children }
  // - sometimes: { id, text, account, createdAt }
  $id = ia_stream_first_str(
    isset($t['id']) ? (string)$t['id'] : '',
    isset($t['uuid']) ? (string)$t['uuid'] : ''
  );

  $c = [];
  if (isset($t['comment']) && is_array($t['comment'])) $c = $t['comment'];
  else $c = $t;

  // Root comment id (used for replies endpoint).
  $comment_id = '';
  if (isset($c['id'])) $comment_id = (string)$c['id'];

  $text = ia_stream_first_str(
    isset($c['text']) ? (string)$c['text'] : '',
    isset($c['textHtml']) ? (string)$c['textHtml'] : '',
    isset($c['textHTML']) ? (string)$c['textHTML'] : ''
  );

  $created = ia_stream_first_str(
    isset($c['createdAt']) ? (string)$c['createdAt'] : '',
    isset($c['publishedAt']) ? (string)$c['publishedAt'] : ''
  );

  // author/account
  $acc = [];
  if (isset($c['account']) && is_array($c['account'])) $acc = $c['account'];

  $authorId = 0;
  if (isset($acc['id'])) $authorId = (int)$acc['id'];

  $authorName = ia_stream_first_str(
    isset($acc['displayName']) ? (string)$acc['displayName'] : '',
    isset($acc['name']) ? (string)$acc['name'] : ''
  );

  $authorUrl = ia_stream_first_str(
    isset($acc['url']) ? (string)$acc['url'] : '',
    isset($acc['uri']) ? (string)$acc['uri'] : ''
  );

  $authorAvatar = '';
  if (isset($acc['avatars']) && is_array($acc['avatars'])) {
    foreach ($acc['avatars'] as $a) {
      if (is_array($a) && isset($a['path']) && is_string($a['path']) && trim($a['path']) !== '') {
        $authorAvatar = trim($a['path']);
        break;
      }
    }
  }

  // Tombstone detection: deleted comments should vanish in Atrium UI.
  $is_deleted = false;
  if (isset($comment['deletedAt']) && is_string($comment['deletedAt']) && trim($comment['deletedAt']) !== '') $is_deleted = true;
  if (isset($comment['isDeleted']) && $comment['isDeleted'] === true) $is_deleted = true;
  if (!$is_deleted) {
    $hasText = trim(strip_tags((string)$text)) !== '';
    $hasAcc = !empty($acc);
    if (!$hasText && !$hasAcc) $is_deleted = true;
  }

  $replies = 0;
  if (isset($t['totalReplies'])) $replies = (int)$t['totalReplies'];
  if (isset($t['totalRepliesCount'])) $replies = (int)$t['totalRepliesCount'];

  return [
    'id'          => $id,
    'comment_id'  => $comment_id,
    'text'        => ia_stream_excerpt($text, 800),
    'created_at'  => $created,
    'created_ago' => ia_stream_time_ago($created),
    'is_deleted'  => $is_deleted,
    'author'      => [
      'id'     => $authorId,
      'name'   => $authorName,
      'url'    => $authorUrl,
      'avatar' => ia_stream_abs_url($base, $authorAvatar),
    ],
    'replies_count' => $replies,
  ];
}

/**
 * Normalize a comment node (used by comment thread tree).
 * Returns a compact structure with nested children.
 */
function ia_stream_norm_comment_node(array $c, string $base = ''): array {
  $comment_id = isset($c['id']) ? (string)$c['id'] : '';

  // PeerTube will sometimes return a "tombstone" comment after deletion.
  // We mark these so the UI can omit them entirely (Atrium wants deleted comments to vanish).
  $is_deleted = false;
  if (isset($c['deletedAt']) && is_string($c['deletedAt']) && trim($c['deletedAt']) !== '') $is_deleted = true;
  if (isset($c['isDeleted']) && $c['isDeleted'] === true) $is_deleted = true;

  $text = ia_stream_first_str(
    isset($c['text']) ? (string)$c['text'] : '',
    isset($c['textHtml']) ? (string)$c['textHtml'] : '',
    isset($c['textHTML']) ? (string)$c['textHTML'] : ''
  );

  $created = ia_stream_first_str(
    isset($c['createdAt']) ? (string)$c['createdAt'] : '',
    isset($c['publishedAt']) ? (string)$c['publishedAt'] : ''
  );

  $acc = [];
  if (isset($c['account']) && is_array($c['account'])) $acc = $c['account'];

  $authorId = 0;
  if (isset($acc['id'])) $authorId = (int)$acc['id'];
  elseif (isset($acc['accountId'])) $authorId = (int)$acc['accountId'];
  elseif (isset($acc['userId'])) $authorId = (int)$acc['userId'];

  $authorName = ia_stream_first_str(
    isset($acc['displayName']) ? (string)$acc['displayName'] : '',
    isset($acc['name']) ? (string)$acc['name'] : ''
  );

  $authorUrl = ia_stream_first_str(
    isset($acc['url']) ? (string)$acc['url'] : '',
    isset($acc['uri']) ? (string)$acc['uri'] : ''
  );

  $authorAvatar = '';
  if (isset($acc['avatars']) && is_array($acc['avatars'])) {
    foreach ($acc['avatars'] as $a) {
      if (is_array($a) && isset($a['path']) && is_string($a['path']) && trim($a['path']) !== '') {
        $authorAvatar = trim($a['path']);
        break;
      }
    }
  }

  $children = [];
  if (isset($c['children']) && is_array($c['children'])) {
    foreach ($c['children'] as $ch) {
      if (is_array($ch) && isset($ch['comment']) && is_array($ch['comment'])) {
        $children[] = ia_stream_norm_comment_node($ch['comment'], $base);
      } elseif (is_array($ch)) {
        $children[] = ia_stream_norm_comment_node($ch, $base);
      }
    }
  }

  // If it's a tombstone (no text + no account), treat as deleted.
  if (!$is_deleted) {
    $hasText = trim(strip_tags((string)$text)) !== '';
    $hasAcc = !empty($acc);
    if (!$hasText && !$hasAcc) $is_deleted = true;
  }

  return [
    'id' => $comment_id,
    'text' => ia_stream_excerpt($text, 2000),
    'created_at' => $created,
    'created_ago' => ia_stream_time_ago($created),
    'is_deleted' => $is_deleted,
    'author' => [
      'id' => $authorId,
      'name' => $authorName,
      'url' => $authorUrl,
      'avatar' => ia_stream_abs_url($base, $authorAvatar),
    ],
    'children' => $children,
  ];
}

/**
 * Normalize PeerTube thread tree response.
 */
function ia_stream_norm_comment_thread_tree(array $tree, string $base = ''): array {
  $thread_id = isset($tree['id']) ? (string)$tree['id'] : '';
  $root = [];
  if (isset($tree['comment']) && is_array($tree['comment'])) $root = $tree['comment'];
  elseif (is_array($tree)) $root = $tree;

  $node = ia_stream_norm_comment_node($root, $base);
  // If thread response wraps children at the top level, merge them.
  if (isset($tree['children']) && is_array($tree['children']) && empty($node['children'])) {
    foreach ($tree['children'] as $ch) {
      if (is_array($ch) && isset($ch['comment']) && is_array($ch['comment'])) {
        $node['children'][] = ia_stream_norm_comment_node($ch['comment'], $base);
      } elseif (is_array($ch)) {
        $node['children'][] = ia_stream_norm_comment_node($ch, $base);
      }
    }
  }

  return [
    'thread_id' => $thread_id,
    'root' => $node,
  ];
}
