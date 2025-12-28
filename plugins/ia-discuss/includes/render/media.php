<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Render_Media {

  public function extract_urls(string $text): array {
    $urls = [];
    if (preg_match_all('~https?://[^\s<>"\'\]]+~i', (string)$text, $m)) {
      foreach ($m[0] as $u) {
        $u = rtrim($u, ".,);:]");
        $urls[] = $u;
      }
    }
    return array_values(array_unique($urls));
  }

  public function pick_video_url(array $urls): ?string {
    foreach ($urls as $u) {
      $lu = strtolower((string)$u);
      if (strpos($lu, 'youtube.com/watch') !== false) return (string)$u;
      if (strpos($lu, 'youtu.be/') !== false) return (string)$u;
      if (strpos($lu, '/videos/watch/') !== false) return (string)$u; // peertube-ish
      if (preg_match('~\.(mp4|webm|mov)(\?|$)~', $lu)) return (string)$u;
    }
    return null;
  }

  public function extract_media(string $text): array {
    $urls = $this->extract_urls($text);
    return [
      'video_url' => $this->pick_video_url($urls),
      'urls'      => $urls,
    ];
  }
}
