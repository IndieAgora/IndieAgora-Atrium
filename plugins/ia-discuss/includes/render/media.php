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

    // Capture a single "primary" video URL for contexts that don't render the full
    // post body (eg. feed cards). Topic view embeds video links inline in the body,
    // and the UI intentionally avoids duplicating this value.
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

    // Prefer the first standalone video-like URL, otherwise fall back to the first
    // video-like URL anywhere in the post.
    $video = $this->pick_video_url($standalone);
    if ($video === null) {
      $video = $this->pick_video_url($urls);
    }

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

    return [
      'video_url' => $video,
      'urls'      => $urls,
    ];
  }
}
