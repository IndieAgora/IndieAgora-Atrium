<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Render_BBCode {

  private function sanitize_youtube_id(string $id): string {
    $id = trim((string)$id);
    if ($id === '') return '';

    // The id is sometimes polluted by malformed share URLs like:
    //   ...watch?v=VIDEOID?feature=shared
    // where the second '?' becomes part of the v= value.
    // It can also arrive URL-encoded (e.g. '%3Ffeature%3Dshared').
    for ($i = 0; $i < 2; $i++) {
      if (strpos($id, '%') !== false) {
        $dec = rawurldecode($id);
        if ($dec !== $id) { $id = $dec; continue; }
      }
      break;
    }

    // Strip common delimiters (real and encoded) that should never be inside the id.
    $id = preg_replace('~(%3f|%26|%23).*~i', '', $id);
    $id = preg_replace('~[\?&#/].*~', '', $id);

    // Prefer the canonical 11-char video id when present.
    if (preg_match('~([A-Za-z0-9_-]{11})~', $id, $m)) {
      return (string)$m[1];
    }

    // Fallback: keep a conservative set of characters.
    $id = preg_replace('~[^A-Za-z0-9_-]~', '', $id);
    return $id;
  }

  private function parse_youtube_embed_meta(string $url): array {
    $raw = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($raw === '') return ['id' => '', 'playlist_id' => '', 'is_short' => false, 'is_playlist' => false, 'index' => '', 'start' => ''];

    $parts = wp_parse_url($raw);
    if (!is_array($parts)) return ['id' => '', 'playlist_id' => '', 'is_short' => false, 'is_playlist' => false, 'index' => '', 'start' => ''];

    $host = isset($parts['host']) ? strtolower((string)$parts['host']) : '';
    $path = isset($parts['path']) ? (string)$parts['path'] : '';
    $query = [];
    if (!empty($parts['query'])) parse_str((string)$parts['query'], $query);

    $id = '';
    $is_short = false;

    if (strpos($host, 'youtu.be') !== false) {
      $seg = trim($path, '/');
      if ($seg !== '') $id = $this->sanitize_youtube_id(strtok($seg, '/'));
    }

    if ($id === '' && strpos($host, 'youtube.com') !== false) {
      if (!empty($query['v'])) $id = $this->sanitize_youtube_id((string)$query['v']);
      if ($id === '' && preg_match('~^/(shorts|live)/([^/?#]+)~i', $path, $m)) {
        $is_short = strtolower((string)$m[1]) === 'shorts';
        $id = $this->sanitize_youtube_id((string)$m[2]);
      }
    }

    $playlist_id = !empty($query['list']) ? trim((string)$query['list']) : '';
    $index = !empty($query['index']) ? trim((string)$query['index']) : '';
    $start = '';

    if (!empty($query['start'])) {
      $start = trim((string)$query['start']);
    } elseif (!empty($query['t'])) {
      $t = trim((string)$query['t']);
      if (preg_match('~^\d+$~', $t)) {
        $start = $t;
      } elseif (preg_match('~^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$~i', $t, $m)) {
        $secs = ((int)($m[1] ?? 0) * 3600) + ((int)($m[2] ?? 0) * 60) + (int)($m[3] ?? 0);
        if ($secs > 0) $start = (string)$secs;
      }
    }

    $is_playlist = $playlist_id !== '';
    if ($id === '' && !$is_playlist) {
      return ['id' => '', 'playlist_id' => '', 'is_short' => false, 'is_playlist' => false, 'index' => '', 'start' => ''];
    }

    return [
      'id' => $id,
      'playlist_id' => $playlist_id,
      'is_short' => $is_short,
      'is_playlist' => $is_playlist,
      'index' => $index,
      'start' => $start,
    ];
  }

  private function build_youtube_embed_url(array $yt): string {
    if (!empty($yt['is_playlist']) && !empty($yt['playlist_id'])) {
      $query = [
        'autoplay' => '0',
        'playsinline' => '1',
        'rel' => '0',
        'list' => (string)$yt['playlist_id'],
      ];
      if (!empty($yt['index'])) $query['index'] = (string)$yt['index'];
      if (!empty($yt['start'])) $query['start'] = (string)$yt['start'];
      return 'https://www.youtube.com/embed/videoseries?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    if (empty($yt['id'])) return '';
    $query = [
      'autoplay' => '0',
      'playsinline' => '1',
      'rel' => '0',
    ];
    if (!empty($yt['start'])) $query['start'] = (string)$yt['start'];
    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode((string)$yt['id']) . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
  }

  private function build_youtube_open_url(array $yt): string {
    if (!empty($yt['is_playlist']) && !empty($yt['playlist_id'])) {
      $query = ['list' => (string)$yt['playlist_id']];
      if (!empty($yt['id'])) $query['v'] = (string)$yt['id'];
      if (!empty($yt['index'])) $query['index'] = (string)$yt['index'];
      if (!empty($yt['start'])) $query['start'] = (string)$yt['start'];
      return 'https://www.youtube.com/watch?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    if (empty($yt['id'])) return '';
    $query = ['v' => (string)$yt['id']];
    if (!empty($yt['start'])) $query['start'] = (string)$yt['start'];
    return 'https://www.youtube.com/watch?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
  }

  private function youtube_thumb_url(array $yt): string {
    if (empty($yt['id'])) return '';
    return 'https://img.youtube.com/vi/' . rawurlencode((string)$yt['id']) . '/hqdefault.jpg';
  }

  private function youtube_playlist_card_html(array $yt, string $fallback_url): string {
    $href = esc_url($this->build_youtube_open_url($yt));
    if (!$href) $href = esc_url($fallback_url);
    if (!$href) return '';
    $thumb = esc_url($this->youtube_thumb_url($yt));
    $thumb_html = $thumb
      ? '<span class="iad-playlist-thumb"><img src="' . $thumb . '" alt="" loading="lazy" decoding="async" /></span>'
      : '<span class="iad-playlist-thumb iad-playlist-thumb-empty"></span>';
    return '<div class="iad-attwrap"><div class="iad-att-media iad-att-playlist">'
      . '<a class="iad-playlist-card" href="' . $href . '" target="_blank" rel="noopener noreferrer" aria-label="Open playlist on YouTube">'
      . $thumb_html
      . '<span class="iad-playlist-body"><span class="iad-playlist-kicker">YouTube playlist</span><span class="iad-playlist-title">Open playlist on YouTube</span></span>'
      . '</a></div></div>';
  }

  private function sanitize_linkmeta_image(string $img): string {
    $img = trim((string)$img);
    if ($img === '') return '';

    $tmp = html_entity_decode($img, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Some cached/legacy values contain an HTML-ish snippet like: <img src="https://..." ...>
    // Extract the first URL and discard the rest.
    if (stripos($tmp, '<') !== false) {
      if (preg_match('~https?://[A-Za-z0-9\-._~%/:?#\[\]@!$&\'()*+,;=]+~', $tmp, $mm)) {
        $img = (string)$mm[0];
      }
    }

    $img = trim((string)$img);
    if (!preg_match('~^https?://~i', $img)) return '';
    return $img;
  }

  public function format_post_html(string $text): string {
    $raw = (string)$text;
    if ($raw === '') return '';

    $raw = preg_replace("/\r\n|\r/", "\n", $raw);

    // Compatibility: strip legacy pasted link-preview HTML that was stored inside [code] blocks.
    // These snippets usually contain an <img src="..."> preview image and then the real URL on the next line.
    // Keeping them renders a huge code box above the actual link card.
    $raw = $this->strip_legacy_preview_codeblocks($raw);

    // Protect [code] blocks from any HTML-ish stripping/normalisation passes.
    // Some phpBB stored content uses tag-like wrappers (eg <i>, <b>, <t>) which we
    // strip elsewhere; that must never run on code samples.
    $code_stash = [];
    $raw = preg_replace_callback('~\[code\](.*?)\[/code\]~is', function($m) use (&$code_stash) {
      $key = '%%IAD_CODE_STASH_' . count($code_stash) . '%%';
      $code_stash[$key] = (string)$m[0];
      return $key;
    }, $raw);

    // Remove IA attachment payload from visible body
    $raw = preg_replace('~\[ia_attachments\][A-Za-z0-9+/=]+~', '', $raw);

    // phpBB sometimes stores internal “tag-like” wrappers that look like HTML.
    // Strip these BEFORE deciding if it’s real HTML.
    $raw = $this->strip_phpbb_internal_tags($raw);

    // Restore protected [code] blocks.
    if ($code_stash) {
      $raw = strtr($raw, $code_stash);
    }

    // Decide if it contains real HTML (not just remnants).
    $has_real_html = (bool) preg_match('~<(a|img|br|p|blockquote|pre|code)\b[^>]*>~i', $raw);

    $html = $has_real_html ? $raw : $this->bbcode_to_html($raw);

    // Normalise paragraph structure before running HTML-level transforms.
    $html = $this->nl2p($html);

    // Compatibility: legacy link-preview attempts sometimes persisted an <img src="...">
    // preview snippet inside a PRE/CODE block. This renders as a giant code box above
    // the real link card, even when the user only pasted URLs.
    $html = $this->strip_legacy_preview_preblocks($html);

    // Compatibility: older “rich card” attempts sometimes stored an <img ...> preview
    // snippet inside a code block. In topic view this renders as a huge code box.
    // If we can safely recover a URL from that snippet, collapse the entire code
    // block down to the URL so the normal link-card pipeline can render.
    $html = $this->collapse_legacy_richcard_codeblocks_in_html($html);

    // Some legacy snippets also leave stray fragments like: " alt=\"\" loading=\"lazy\" />
    // adjacent to a URL. Strip those tails so the URL can be detected cleanly.
    $html = preg_replace('~(https?://[^\s<>"]+)\s+"?\s*alt\s*=\s*"[^"]*"\s*loading\s*=\s*"lazy"\s*/?>~i', '$1', $html);
    $html = preg_replace('~(https?://[^\s<>"]+)\s+"?\s*loading\s*=\s*"lazy"\s*/?>~i', '$1', $html);

    // If the post already contains HTML (eg. phpBB stored <a> links), we still want
    // video links to render inline (where they appear) rather than being duplicated
    // into the bottom media area.
    $html = $this->embed_video_links_in_html($html);

    // Link @mentions to Connect profiles (skips code/pre blocks).
    $html = $this->link_mentions_in_html($html);

    return wp_kses($html, $this->allowed_tags());
  }

  /**
   * Convert @username occurrences in already-rendered HTML to links to Connect profiles.
   * Keep it conservative and never touch code/pre blocks.
   */
  private function link_mentions_in_html(string $html): string {
    $html = (string)$html;
    if ($html === '' || stripos($html, '@') === false) return $html;

    // Stash <pre> and <code> blocks so we don't link inside code.
    $stash = [];
    $html = preg_replace_callback('~<(pre|code)(\b[^>]*)>.*?</\1>~is', function($m) use (&$stash) {
      $k = '%%IAD_MENTION_STASH_' . count($stash) . '%%';
      $stash[$k] = (string)$m[0];
      return $k;
    }, $html);

    // Replace mentions in the remaining HTML.
    $html = preg_replace_callback('/(^|[^a-zA-Z0-9_])@([a-zA-Z0-9_\-\.]{2,40})/u', function($m){
      $prefix = (string)$m[1];
      $u = (string)$m[2];
      if ($u === '') return (string)$m[0];
      $url = add_query_arg([
        'tab' => 'connect',
        'ia_profile_name' => $u,
      ], home_url('/'));
      return $prefix . '<a class="iad-mention" href="' . esc_url($url) . '">@' . esc_html($u) . '</a>';
    }, $html);

    // Restore stashed blocks.
    if ($stash) $html = strtr($html, $stash);
    return $html;
  }

  
  private function strip_legacy_preview_codeblocks(string $text): string {
    $s = (string)$text;
    // Only operate when we see a [code] block and an <img marker.
    if (stripos($s, '[code]') === false) return $s;
    if (stripos($s, '<img') === false && stripos($s, '&lt;img') === false) return $s;

    return preg_replace_callback('~\[code\](.*?)\[/code\](\s*\n\s*)(https?://[^\s<>"\']+)~is', function($m){
      $inner = (string)$m[1];
      $gap   = (string)$m[2];
      $next  = rtrim((string)$m[3], ".,);:]");

      $decoded = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $maybe = ltrim($decoded);

      // Only target code blocks that start with an <img preview snippet.
      if ($maybe === '' || stripos($maybe, '<img') !== 0) return (string)$m[0];
      if (stripos($maybe, 'src=') === false) return (string)$m[0];

      // Extract URLs present inside the code block.
      $compact = preg_replace('/\s+/', '', $maybe);
      $urls = [];
      if (preg_match_all("~https?://[A-Za-z0-9\-._~%/:?#\[\]@!$&'()*+,;=]+~", $compact, $mm)) {
        foreach ($mm[0] as $u) {
          $u = rtrim((string)$u, ".,);:]");
          if ($u !== '') $urls[] = $u;
        }
      }
      if (empty($urls)) return (string)$m[0];

      // If the code block only contains image-ish URLs (common preview-image sources),
      // drop the entire [code] block and keep the real URL that follows.
      $has_non_image = false;
      foreach ($urls as $u) {
        if (!preg_match('~\.(png|jpe?g|gif|webp)(\?|$)~i', $u)) { $has_non_image = true; break; }
      }
      if ($has_non_image) return (string)$m[0];

      $eu = esc_url_raw($next);
      if (!$eu) return (string)$m[0];

      // Return just the following URL on its own line; it will be card-rendered later.
      return "\n" . $eu . "\n";
    }, $s);
  }

  // Strip PRE/CODE blocks that are clearly not user-authored code, but a stale
  // link-preview snippet (typically starting with <img src="https://s.yimg.com/..."
  // or similar). We keep this narrow to avoid touching legitimate code samples.
  private function strip_legacy_preview_preblocks(string $html): string {
    $s = (string)$html;

    // Fast check to avoid work on most posts.
    if (stripos($s, '<pre') === false) return $s;
    if (stripos($s, 's.yimg.com') === false && stripos($s, 'media.zenfs.com') === false && stripos($s, 'zenfs.com') === false) {
      // Still allow stripping when the code block clearly contains a preview-img tag.
      if (stripos($s, '&lt;img') === false && stripos($s, '<img') === false) return $s;
    }

    return preg_replace_callback('~<pre\b[^>]*>\s*(?:<code\b[^>]*>)?(.*?)(?:</code>\s*)?</pre>~is', function($m){
      $inner = (string)$m[1];
      $decoded = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $maybe = ltrim($decoded);

      // Only if it starts with an <img preview snippet.
      if ($maybe === '' || stripos($maybe, '<img') !== 0) return (string)$m[0];
      if (stripos($maybe, 'src=') === false) return (string)$m[0];

      // Heuristics: common preview sources and attributes.
      $low = strtolower($maybe);
      $looks_preview = (strpos($low, 's.yimg.com') !== false)
        || (strpos($low, 'media.zenfs.com') !== false)
        || (strpos($low, 'zenfs.com') !== false)
        || (strpos($low, 'loading="lazy"') !== false)
        || (strpos($low, 'loading=\'lazy\'') !== false);
      if (!$looks_preview) return (string)$m[0];

      // Drop the whole PRE block.
      return '';
    }, $s);
  }


  private function collapse_legacy_richcard_codeblocks_in_html(string $html): string {
    $s = (string)$html;

    return preg_replace_callback('~<pre\b[^>]*>\s*<code\b[^>]*>(.*?)</code>\s*</pre>~is', function($m){
      $inner = (string)$m[1];

      // Decode entities so &lt;img becomes <img for detection.
      $decoded = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $maybe = ltrim($decoded);

      // Only collapse blocks that look like the legacy preview snippet.
      // Keep this narrow to avoid touching genuine code samples.
      if ($maybe === '' || stripos($maybe, '<img') !== 0) return (string)$m[0];
      if (stripos($maybe, 'src=') === false) return (string)$m[0];
      if (stripos($maybe, 'http') === false) return (string)$m[0];

      // Recover URLs even if they were line-broken (compact whitespace).
      $compact = preg_replace('/\s+/', '', $maybe);
      $urls = [];
      if (preg_match_all("~https?://[A-Za-z0-9\-._~%/:?#\[\]@!$&'()*+,;=]+~", $compact, $mm)) {
        foreach ($mm[0] as $u) {
          $u = rtrim((string)$u, ".,);:]");
          if ($u !== '') $urls[] = $u;
        }
      }
      if (empty($urls)) return (string)$m[0];

      // Prefer a non-image URL if present.
      $pick = '';
      foreach ($urls as $u) {
        if (!preg_match('~\.(png|jpe?g|gif|webp)(\?|$)~i', $u)) { $pick = $u; break; }
      }
      if ($pick === '') $pick = (string)$urls[0];

      $eu = $pick ? esc_url($pick) : '';
      if (!$eu) return (string)$m[0];

      // Output as a normal link line; client-side link-card scanning can pick it up.
      $label = esc_html($pick);
      return '<p><a href="' . $eu . '" target="_blank" rel="noopener noreferrer">' . $label . '</a></p>';
    }, $s);
  }

  private function embed_video_links_in_html(string $html): string {
    $s = (string)$html;

    // Never touch code/pre blocks. URLs inside [code]...[/code] must remain literal.
    $stash = [];
    $s = preg_replace_callback('~<(pre|code)(\b[^>]*)>.*?</\1>~is', function($m) use (&$stash) {
      $k = '%%IAD_VIDEO_HTML_STASH_' . count($stash) . '%%';
      $stash[$k] = (string)$m[0];
      return $k;
    }, $s);

    $seen = [];

    // Replace <a href="...">...</a> where the href is a video-like URL.
    $s = preg_replace_callback("~<a\b[^>]*href=([\"'])(https?://[^\"']+)\1[^>]*>.*?</a>~is", function($m) use (&$seen){
      $u = rtrim((string)$m[2], ".,);:]");
      if (in_array($u, $seen, true)) return (string)$m[0];
      $embed = $this->video_embed_html($u);
      if ($embed !== '') { $seen[] = $u; return $embed; }
      return (string)$m[0];
    }, $s);

    // Replace any remaining standalone URLs that survive as plain text inside HTML.
    // (Keep it conservative: only when surrounded by whitespace or paragraph boundaries.)
    $s = preg_replace_callback("~(^|[\s>])((?:https?://)[^\s<>\"']+)~i", function($m) use (&$seen){
      $lead = (string)$m[1];
      $u = rtrim((string)$m[2], ".,);:]");
      if (in_array($u, $seen, true)) return (string)$m[0];
      $embed = $this->video_embed_html($u);
      if ($embed !== '') { $seen[] = $u; return $lead . $embed; }
      return (string)$m[0];
    }, $s);

    if ($stash) $s = strtr($s, $stash);
    return $s;
  }

  private function video_embed_html(string $url): string {
    $raw = trim((string)$url);
    if ($raw === '') return '';
    $lu = strtolower($raw);

    // Direct file videos
    if (preg_match('~\.(mp4|webm|mov)(\?|$)~i', $lu)) {
      $src = esc_url($raw);
      if (!$src) return '';
      return '<div class="iad-attwrap"><div class="iad-att-media iad-att-video">'
        . '<video class="iad-att-video" controls playsinline preload="none">'
        . '<source src="' . $src . '" />'
        . '</video></div></div>';
    }

    // YouTube
    $yt = $this->parse_youtube_embed_meta($raw);
    if (!empty($yt['id']) || !empty($yt['playlist_id'])) {
      $embed = $this->build_youtube_embed_url($yt);
      $eu = esc_url($embed);
      if (!$eu) return '';
      $iframe_class = !empty($yt['is_short']) ? 'iad-att-iframe is-vertical' : 'iad-att-iframe';
      return '<div class="iad-attwrap"><div class="iad-att-media iad-att-video">'
        . '<iframe class="' . esc_attr($iframe_class) . '" src="' . $eu . '" title="Video" frameborder="0" loading="lazy" referrerpolicy="origin-when-cross-origin"'
        . ' allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>'
        . '</div></div>';
    }

    // PeerTube-ish: /videos/watch/{uuid} or /w/{id} -> /videos/embed/{id}
    if (preg_match('~^(https?://[^/]+)/(?:w|videos/watch)/([^\?/#]+)~i', $raw, $mm)) {
      $base = rtrim((string)$mm[1], '/');
      $vid  = (string)$mm[2];
      $embed = $base . '/videos/embed/' . rawurlencode($vid) . '?autoplay=0';
      $eu = esc_url($embed);
      if (!$eu) return '';
      return '<div class="iad-attwrap"><div class="iad-att-media iad-att-video">'
        . '<iframe class="iad-att-iframe" src="' . $eu . '" title="Video" frameborder="0" loading="lazy" referrerpolicy="origin-when-cross-origin"'
        . ' allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>'
        . '</div></div>';
    }

    return '';
  }

  public function excerpt_html(string $text, int $maxChars): string {
    $plain = $this->to_plaintext($text);
    $plain = trim(preg_replace("~\s+~u", " ", $plain));

    if (mb_strlen($plain, 'UTF-8') > $maxChars) {
      $plain = mb_substr($plain, 0, $maxChars, 'UTF-8') . '…';
    }
    return '<p>' . esc_html($plain) . '</p>';
  }

  public function to_plaintext(string $text): string {
    $s = (string)$text;

    // Remove attachment payload
    $s = preg_replace('~\[ia_attachments\][A-Za-z0-9+/=]+~', '', $s);

    // Strip phpBB internal tag wrappers
    $s = $this->strip_phpbb_internal_tags($s);

    // Strip HTML
    $s = wp_strip_all_tags($s);

    // Strip BBCode-ish
    $s = preg_replace('~\[(\/)?[a-z0-9\*\=\#\s"\':;\/\.\-\_\?\&]+]~i', '', $s);

    return (string)$s;
  }

  private function strip_phpbb_internal_tags(string $s): string {
    // These often appear in phpBB stored post_text depending on config/plugins.
    // We remove ONLY the tags, keeping inner text.
    $s = preg_replace('~</?(r|t|s|e|l|i|b|u|quote|code|img|url)[^>]*>~i', '', $s);
    // Also strip any leftover <br /> variants that came through as text-y wrappers
    $s = str_replace(["\xC2\xA0"], ' ', $s);

    // Some stored content contains brace-style remnants like {size} or {colour}.
    // We strip the tags only, keeping the inner text.
    $s = preg_replace('~\{\/?\s*(size|colour|color)\b[^}]*\}~i', '', $s);
    return (string)$s;
  }

  private function bbcode_to_html(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Strip style-only tags we don't render (avoid raw BBCode showing).
    // We intentionally drop styling in Discuss while preserving content.
    // [size=...][/size] / [color=...][/color] / [colour=...][/colour]
    $s = preg_replace('~\[(size|color|colour)(?:=[^\]]+)?\](.*?)\[/\1\]~is', '$2', $s);

    // [url=]
    $s = preg_replace_callback('~\[url=(.+?)\](.*?)\[/url\]~is', function($m){
      $u = esc_url_raw(trim($m[1]));
      $t = trim($m[2]);
      if (!$u) return esc_html($t);
      return '<a href="' . esc_url($u) . '" target="_blank" rel="noopener noreferrer">' . esc_html($t) . '</a>';
    }, $s);

    // [url]
    $s = preg_replace_callback('~\[url\](.+?)\[/url\]~is', function($m){
      $u = esc_url_raw(trim($m[1]));
      if (!$u) return '';
      return '<a href="' . esc_url($u) . '" target="_blank" rel="noopener noreferrer">' . esc_html($u) . '</a>';
    }, $s);

    // Basic formatting tags
    $map = [
      'b' => 'strong',
      'i' => 'em',
      'u' => 'u',
      's' => 's',
    ];
    foreach ($map as $bb => $tag) {
      $s = preg_replace('~\['.$bb.'\](.*?)\[/'.$bb.'\]~is', '<'.$tag.'>$1</'.$tag.'>', $s);
    }

    // [quote] (nested quotes will naturally nest as blockquotes)
    $s = preg_replace('~\[quote\](.*?)\[/quote\]~is', '<blockquote>$1</blockquote>', $s);

    // [code]
    $s = preg_replace_callback('~\[code\](.*?)\[/code\]~is', function($m){
      // Render as a real block with a dedicated class so styling is contained.
      // Important: keep literal newlines; never insert <br> into code.
      $code = (string)$m[1];
      $code = preg_replace('/\r\n|\r/', "\n", $code);
      // In case some upstream formatting already inserted <br> tags, normalise back.
      $code = preg_replace('~<br\s*/?>~i', "\n", $code);

      return '<pre class="iad-code-block"><code>' . esc_html($code) . '</code></pre>';
    }, $s);

    // [img]
    $s = preg_replace_callback('~\[img\](.+?)\[/img\]~is', function($m){
      $u = esc_url_raw(trim($m[1]));
      if (!$u) return '';
      return '<a href="'.esc_url($u).'" target="_blank" rel="noopener noreferrer"><img src="'.esc_url($u).'" alt="" loading="lazy" /></a>';
    }, $s);

    // Clean up stray remnants from pasted <img ... loading="lazy" /> snippets that sometimes
    // end up as plain text after URL extraction (e.g. trailing \" alt=\"...\" loading=\"lazy\" />).
    $s = preg_replace('~^\s*"?\s*alt\s*=\s*"[^"]*"\s*loading\s*=\s*"lazy"\s*/?>\s*$~im', '', $s);
    $s = preg_replace('~^\s*"?\s*loading\s*=\s*"lazy"\s*/?>\s*$~im', '', $s);



    // Strip [color] tags (keep inner text)
    $s = preg_replace('~\[color(?:=[^\]]+)?\](.*?)\[/color\]~is', '$1', $s);

    // [list] / [list=1] with [*] items (support common typo [/list})
    $s = preg_replace_callback('~\[list(?:=([^\]]+))?\](.*?)\[\/list\]?\}?~is', function($m){
      $type = isset($m[1]) ? trim((string)$m[1]) : '';
      $body = (string)($m[2] ?? '');
      $items = preg_split('~\[\*\]~', $body);
      $items = array_values(array_filter(array_map('trim', $items), function($x){ return $x !== ''; }));
      if (!$items) return '';
      $tag = ($type !== '' && $type !== 'disc') ? 'ol' : 'ul';
      $lis = '';
      foreach ($items as $it) { $lis .= '<li>' . $it . '</li>'; }
      return '<' . $tag . '>' . $lis . '</' . $tag . '>';
    }, $s);

    // [quote=...] and phpBB style [quote="user" post_id=.. time=.. user_id=..]
    // Render a small header inside the blockquote when username is present.
    $s = preg_replace_callback('~\[quote=([^\]]+)\]~i', function($m){
      $meta = trim((string)$m[1]);
      // If quoted, strip surrounding quotes
      if (preg_match('~^"(.+)"~', $meta, $mm)) $meta = $mm[1];
      // If contains key=value pairs, prefer the first token before space
      $user = $meta;
      if (strpos($meta, ' ') !== false) {
        $user = trim(strtok($meta, ' '));
      }
      $user = esc_html($user);
      if ($user === '') return '<blockquote>';
      return '<blockquote><div class="iad-quote-meta">' . $user . ' wrote:</div>';
    }, $s);
    $s = preg_replace('~\[/quote\]~i', '</blockquote>', $s);
    // Plain [quote]...[/quote] already handled above; this enhances only quote= forms.

    // [url] and [url=...] tags
    $s = preg_replace_callback('~\[url\](.+?)\[/url\]~is', function($m){
      $u = esc_url_raw(trim($m[1]));
      if (!$u) return '';
      return '<a class="iad-link" href="' . esc_url($u) . '" target="_blank" rel="noopener noreferrer">' . esc_html($u) . '</a>';
    }, $s);
    $s = preg_replace_callback('~\[url=([^\]]+)\](.*?)\[/url\]~is', function($m){
      $u = esc_url_raw(trim($m[1]));
      if (!$u) return esc_html($m[2]);
      $label = trim((string)$m[2]);
      if ($label === '') $label = $u;
      return '<a class="iad-link" href="' . esc_url($u) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
    }, $s);

    // Protect rendered code blocks from URL auto-embed and auto-link passes.
    $code_html_stash = [];
    $s = preg_replace_callback("~<pre\b[^>]*class=(?:\"|')?[^>]*\biad-code-block\b[^>]*>.*?</pre>~is", function($m) use (&$code_html_stash){
      $key = '%%IAD_HTML_CODE_STASH_' . count($code_html_stash) . '%%';
      $code_html_stash[$key] = (string)$m[0];
      return $key;
    }, $s);

    // Replace standalone video URLs with an inline embed (and do NOT show the raw URL).
    $s = preg_replace_callback('~(^|\n)\s*(https?://[^\s<>"\']+)\s*(?=\n|$)~i', function($m){
      $lead = $m[1];
      $u = rtrim((string)$m[2], ".,);:]");
      $embed = $this->video_embed_html($u);
      return $embed !== '' ? $lead . $embed : $m[0];
    }, $s);

    // Auto-link remaining standalone URLs as rich link cards (favicon + title/desc/image).
    $s = preg_replace_callback('~(^|\n)\s*(https?://[^\s<>"\']+)\s*(?=\n|$)~i', function($m){
      $lead = $m[1];
      $u = rtrim((string)$m[2], ".,);:]");
      $eu = esc_url($u);
      if (!$eu) return $m[0];
      $host = parse_url($u, PHP_URL_HOST);
      $host = is_string($host) ? $host : '';
      $hostLabel = $host ? esc_html($host) : esc_html($u);
      $fav = '';
      if ($host) {
        $fav = 'https://www.google.com/s2/favicons?sz=64&domain_url=' . rawurlencode('https://' . $host);
      }
      $favImg = $fav ? '<img class="iad-linkcard-fav" src="' . esc_url($fav) . '" alt="" loading="lazy" />' : '';
      $meta = $this->link_meta($u);
      $title = trim((string)($meta['title'] ?? ''));
      $desc  = trim((string)($meta['description'] ?? ''));
      $img   = $this->sanitize_linkmeta_image((string)($meta['image'] ?? ''));

      // Fallbacks
      if ($title === '') $title = $host ? $host : $u;

      $imgHtml = '';
      if ($img !== '') {
        $imgHtml = '<span class="iad-linkcard-thumb"><img src="' . esc_url($img) . '" alt="" loading="lazy" /></span>';
      }

      $descHtml = '';
      if ($desc !== '') {
        $descHtml = '<span class="iad-linkcard-desc">' . esc_html($desc) . '</span>';
      }

      // Note: we intentionally do NOT print the raw URL inside the card.
      return $lead . '<a class="iad-linkcard" href="' . $eu . '" target="_blank" rel="noopener noreferrer">'
        . $imgHtml
        . '<span class="iad-linkcard-main">'
        . '<span class="iad-linkcard-top">' . $favImg . '<span class="iad-linkcard-host">' . $hostLabel . '</span></span>'
        . '<span class="iad-linkcard-title">' . esc_html($title) . '</span>'
        . $descHtml
        . '</span></a>';
    }, $s);

    // Auto-link inline URLs (non-standalone) as simple anchors.
    $s = preg_replace_callback('~(?<![="\w])(https?://[^\s<>"\']+)~i', function($m) use (&$seen){
      $u = rtrim((string)$m[1], ".,);:]");
      $eu = esc_url($u);
      if (!$eu) return $m[0];
      return '<a class="iad-link" href="' . $eu . '" target="_blank" rel="noopener noreferrer">' . esc_html($u) . '</a>';
    }, $s);

    if ($code_html_stash) {
      $s = strtr($s, $code_html_stash);
    }

    return $s;
  }


  private function abs_url(string $base, string $maybe): string {
    $maybe = trim($maybe);
    if ($maybe === '') return '';
    if (preg_match('~^https?://~i', $maybe)) return $maybe;
    if (strpos($maybe, '//') === 0) {
      $scheme = parse_url($base, PHP_URL_SCHEME);
      if (!is_string($scheme) || $scheme === '') $scheme = 'https';
      return $scheme . ':' . $maybe;
    }
    if ($maybe[0] !== '/') $maybe = '/' . $maybe;
    return rtrim($base, '/') . $maybe;
  }

  private function link_meta(string $url): array {
		// IMPORTANT: Topic rendering must never stall while trying to fetch previews.
		// Limit uncached remote fetches per PHP request (topics can contain many links).
		static $fetches = 0;
		$max_fetches = 2;

    $key = 'iad_linkmeta_' . md5($url);
    $cached = get_transient($key);
    if (is_array($cached)) {
      $c = [
        'title' => (string)($cached['title'] ?? ''),
        'description' => (string)($cached['description'] ?? ''),
        'image' => (string)($cached['image'] ?? ''),
      ];
      // Guard against older cached values that stored HTML-ish snippets in the image field.
      $clean_img = $this->sanitize_linkmeta_image($c['image']);
      if ($clean_img !== $c['image']) {
        $c['image'] = $clean_img;
        set_transient($key, $c, 12 * HOUR_IN_SECONDS);
      }
      return $c;
    }

    $out = ['title' => '', 'description' => '', 'image' => ''];

    $eu = esc_url_raw($url);
    if (!$eu || !preg_match('~^https?://~i', $eu)) {
      return $out;
    }

		// If we don't have a cached preview, only attempt a couple of fetches per request.
		if ($fetches >= $max_fetches) {
		  return $out;
		}
		$fetches++;

		$args = [
		  'timeout' => 2,
      'redirection' => 3,
      'user-agent' => 'IA-Discuss/1.0 (+link-preview)',
      'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
    ];

    $res = wp_safe_remote_get($eu, $args);
    if (is_wp_error($res)) {
      return $out;
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 400) {
      return $out;
    }

    $body = (string) wp_remote_retrieve_body($res);
    if ($body === '') return $out;

    if (strlen($body) > 200000) $body = substr($body, 0, 200000);

    if (preg_match('~<head[^>]*>(.*?)</head>~is', $body, $m)) {
      $head = $m[1];
    } else {
      $head = $body;
    }

    $p = wp_parse_url($eu);
    $base = '';
    if (is_array($p) && !empty($p['scheme']) && !empty($p['host'])) {
      $base = $p['scheme'] . '://' . $p['host'];
      if (!empty($p['port'])) $base .= ':' . $p['port'];
    }

    $pick = function(array $patterns) use ($head) {
      foreach ($patterns as $pat) {
        if (preg_match($pat, $head, $mm)) {
          return trim(html_entity_decode($mm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
      }
      return '';
    };

    $out['title'] = $pick([
      '~<meta\s+property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
      '~<meta\s+name=["\']twitter:title["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
      '~<title[^>]*>(.*?)</title>~is',
    ]);

    $out['description'] = $pick([
      '~<meta\s+property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
      '~<meta\s+name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
      '~<meta\s+name=["\']twitter:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
    ]);

    $img = $pick([
      '~<meta\s+property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
      '~<meta\s+name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\'][^>]*>~i',
    ]);
    if ($img !== '' && $base !== '') $img = $this->abs_url($base, $img);

    $out['image'] = $this->sanitize_linkmeta_image($img);

    $out['title'] = mb_substr($out['title'], 0, 140);
    $out['description'] = mb_substr($out['description'], 0, 220);

    set_transient($key, $out, 12 * HOUR_IN_SECONDS);
    return $out;
  }

  private function nl2p(string $html): string {
    $html = trim($html);
    if ($html === '') return '';

    // Do not run newline->paragraph conversion inside <pre> blocks (code samples).
    // If we replace \n with <br /> inside a <pre>, the browser renders double-spaced.
    $pre_stash = [];
    $html = preg_replace_callback('~<pre\b[^>]*>.*?</pre>~is', function($m) use (&$pre_stash) {
      $key = '%%IAD_PRE_STASH_' . count($pre_stash) . '%%';
      $pre_stash[$key] = (string)$m[0];
      return $key;
    }, $html);

    $html = preg_replace("/\n{3,}/", "\n\n", $html);

    $parts = preg_split("/\n{2,}/", $html);
    $out = [];
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p === '') continue;
      // If this paragraph is a stashed <pre>, output it as-is (no <p> wrapper).
      if (isset($pre_stash[$p])) {
        $out[] = $p;
        continue;
      }

      $p = preg_replace("/\n/", "<br />\n", $p);
      $out[] = '<p>' . $p . '</p>';
    }

    $joined = implode("\n", $out);
    if ($pre_stash) {
      $joined = strtr($joined, $pre_stash);
    }
    return $joined;
  }

  private function allowed_tags(): array {
    return [
      'p' => [],
      'br' => [],
      'strong' => [],
      'em' => [],
      'u' => [],
      's' => [],
      'blockquote' => [],
      'pre' => [
        'class' => true,
      ],
      'code' => [
        'class' => true,
      ],
      'a' => [
        'href' => true,
        'target' => true,
        'rel' => true,
        'class' => true,
      ],
      'img' => [
        'src' => true,
        'alt' => true,
        'loading' => true,
        'class' => true,
      ],
      'div' => [
        'class' => true,
      ],
      'span' => [
        'class' => true,
      ],
      'ul' => [
        'class' => true,
      ],
      'ol' => [
        'class' => true,
      ],
      'li' => [
        'class' => true,
      ],
      'iframe' => [
        'src' => true,
        'title' => true,
        'frameborder' => true,
        'loading' => true,
        'referrerpolicy' => true,
        'allow' => true,
        'allowfullscreen' => true,
        'class' => true,
      ],
      'video' => [
        'controls' => true,
        'playsinline' => true,
        'preload' => true,
        'class' => true,
      ],
      'source' => [
        'src' => true,
        'type' => true,
      ],
    ];
  }
}
