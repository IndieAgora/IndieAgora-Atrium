<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Render_BBCode {

  public function format_post_html(string $text): string {
    $raw = (string)$text;
    if ($raw === '') return '';

    $raw = preg_replace("/\r\n|\r/", "\n", $raw);

    // Remove IA attachment payload from visible body
    $raw = preg_replace('~\[ia_attachments\][A-Za-z0-9+/=]+~', '', $raw);

    // phpBB sometimes stores internal “tag-like” wrappers that look like HTML.
    // Strip these BEFORE deciding if it’s real HTML.
    $raw = $this->strip_phpbb_internal_tags($raw);

    // Decide if it contains real HTML (not just remnants).
    $has_real_html = (bool) preg_match('~<(a|img|br|p|blockquote|pre|code)\b[^>]*>~i', $raw);

    $html = $has_real_html ? $raw : $this->bbcode_to_html($raw);

    // Normalise paragraph structure before running HTML-level transforms.
    $html = $this->nl2p($html);

    // If the post already contains HTML (eg. phpBB stored <a> links), we still want
    // video links to render inline (where they appear) rather than being duplicated
    // into the bottom media area.
    $html = $this->embed_video_links_in_html($html);

    return wp_kses($html, $this->allowed_tags());
  }

  private function embed_video_links_in_html(string $html): string {
    $s = (string)$html;

    // Replace <a href="...">...</a> where the href is a video-like URL.
    $s = preg_replace_callback('~<a\b[^>]*href=(["\'])(https?://[^"\']+)\1[^>]*>.*?</a>~is', function($m){
      $u = rtrim((string)$m[2], ".,);:]");
      $embed = $this->video_embed_html($u);
      return $embed !== '' ? $embed : (string)$m[0];
    }, $s);

    // Replace any remaining standalone URLs that survive as plain text inside HTML.
    // (Keep it conservative: only when surrounded by whitespace or paragraph boundaries.)
    $s = preg_replace_callback('~(^|[\s>])((?:https?://)[^\s<>"\']+)~i', function($m){
      $lead = (string)$m[1];
      $u = rtrim((string)$m[2], ".,);:]");
      $embed = $this->video_embed_html($u);
      return $embed !== '' ? $lead . $embed : (string)$m[0];
    }, $s);

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
    $yid = '';
    if (preg_match('~youtu\.be/([^\?/#]+)~i', $raw, $mm)) $yid = (string)$mm[1];
    if ($yid === '' && preg_match('~[\?&]v=([^&]+)~i', $raw, $mm)) $yid = (string)$mm[1];
    if ($yid === '' && preg_match('~youtube\.com/shorts/([^\?/#]+)~i', $raw, $mm)) $yid = (string)$mm[1];
    if ($yid === '' && preg_match('~youtube\.com/live/([^\?/#]+)~i', $raw, $mm)) $yid = (string)$mm[1];
    if ($yid !== '') {
      $embed = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($yid) . '?autoplay=0&playsinline=1&rel=0';
      $eu = esc_url($embed);
      if (!$eu) return '';
      return '<div class="iad-attwrap"><div class="iad-att-media iad-att-video">'
        . '<iframe class="iad-att-iframe" src="' . $eu . '" title="Video" frameborder="0" loading="lazy" referrerpolicy="origin-when-cross-origin"'
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
      return '<pre><code>' . esc_html($m[1]) . '</code></pre>';
    }, $s);

    // [img]
    $s = preg_replace_callback('~\[img\](.+?)\[/img\]~is', function($m){
      $u = esc_url_raw(trim($m[1]));
      if (!$u) return '';
      return '<a href="'.esc_url($u).'" target="_blank" rel="noopener noreferrer"><img src="'.esc_url($u).'" alt="" loading="lazy" /></a>';
    }, $s);



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
      $img   = trim((string)($meta['image'] ?? ''));

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
    $s = preg_replace_callback('~(?<![="\w])(https?://[^\s<>"\']+)~i', function($m){
      $u = rtrim((string)$m[1], ".,);:]");
      $eu = esc_url($u);
      if (!$eu) return $m[0];
      return '<a class="iad-link" href="' . $eu . '" target="_blank" rel="noopener noreferrer">' . esc_html($u) . '</a>';
    }, $s);

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
      return [
        'title' => (string)($cached['title'] ?? ''),
        'description' => (string)($cached['description'] ?? ''),
        'image' => (string)($cached['image'] ?? ''),
      ];
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
    $out['image'] = $img;

    $out['title'] = mb_substr($out['title'], 0, 140);
    $out['description'] = mb_substr($out['description'], 0, 220);

    set_transient($key, $out, 12 * HOUR_IN_SECONDS);
    return $out;
  }

  private function nl2p(string $html): string {
    $html = trim($html);
    if ($html === '') return '';

    $html = preg_replace("/\n{3,}/", "\n\n", $html);

    $parts = preg_split("/\n{2,}/", $html);
    $out = [];
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p === '') continue;
      $p = preg_replace("/\n/", "<br />\n", $p);
      $out[] = '<p>' . $p . '</p>';
    }
    return implode("\n", $out);
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
      'pre' => [],
      'code' => [],
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
