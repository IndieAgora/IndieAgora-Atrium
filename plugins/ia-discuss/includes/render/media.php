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
      if (strpos($lu, 'youtube.com/shorts/') !== false) return (string)$u;
      if (strpos($lu, 'youtube.com/live/') !== false) return (string)$u;
      if (strpos($lu, 'youtu.be/') !== false) return (string)$u;
      if (strpos($lu, '/videos/watch/') !== false) return (string)$u; // peertube-ish
      if (strpos($lu, '/videos/embed/') !== false) return (string)$u;
      if (preg_match('~https?://[^/]+/w/[^\s\?/#]+~i', (string)$u)) return (string)$u;
      if (preg_match('~\.(mp4|webm|mov)(\?|$)~', $lu)) return (string)$u;
    }
    return null;
  }

  public function extract_media(string $text): array {
    // Normalise newlines so standalone-URL detection works regardless of \r\n vs \n.
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);

    $urls = $this->extract_urls($text);
    // We no longer surface a single "primary" video at the bottom of the post.
    // Video links are rendered inline at their exact position in the post body.
    $video = null;

    // If a URL is on its own line in the post body, the BBCode renderer will
    // render it inline (video embed or link card). In that case we must NOT
    // render it again in the bottom media/URL area.
    $standalone = [];
    if (preg_match_all('~(^|\n)\s*(https?://[^\s<>"\']+)\s*(?=\n|$)~i', (string)$text, $m)) {
      foreach ($m[2] as $u) {
        $standalone[] = rtrim((string)$u, ".,);:]");
      }
    }
    $standalone = array_values(array_unique($standalone));

    // Remove video-like URLs from the general URL list so videos don't render twice.
    // Also remove standalone URLs (rendered inline as cards).
    $urls = array_values(array_filter($urls, function($u) use ($standalone){
      $lu = strtolower((string)$u);
      if (in_array((string)$u, $standalone, true)) return false;
      if (strpos($lu, 'youtu.be/') !== false) return false;
      if (strpos($lu, 'youtube.com/watch') !== false) return false;
      if (strpos($lu, 'youtube.com/shorts/') !== false) return false;
      if (strpos($lu, 'youtube.com/live/') !== false) return false;
      if (strpos($lu, '/videos/watch/') !== false) return false;
      if (strpos($lu, '/videos/embed/') !== false) return false;
      if (preg_match('~https?://[^/]+/w/[^\s\?/#]+~i', (string)$u)) return false;
      if (preg_match('~\.(mp4|webm|mov)(\?|$)~', $lu)) return false;
      return true;
    }));

    // If ANY standalone URL is video-like, it will be rendered inline by the
    // post-body renderer (embed/card). In that case, suppress the bottom video
    // embed entirely to prevent a “first video duplicates at bottom” case.
    if (!empty($standalone)) {
      $has_standalone_video = false;
      foreach ($standalone as $su) {
        if ($this->pick_video_url([(string)$su]) !== null) {
          $has_standalone_video = true;
          break;
        }
      }
      if ($has_standalone_video) {
        $video = null;
      }
    }
    return [
      'video_url' => $video,
      'urls'      => $urls,
    ];
  }
}
