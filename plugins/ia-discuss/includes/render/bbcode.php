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

    $html = $this->nl2p($html);

    return wp_kses($html, $this->allowed_tags());
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
    return (string)$s;
  }

  private function bbcode_to_html(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

    return $s;
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
      ],
      'img' => [
        'src' => true,
        'alt' => true,
        'loading' => true,
      ],
    ];
  }
}
