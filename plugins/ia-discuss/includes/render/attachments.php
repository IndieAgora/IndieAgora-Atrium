<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Render_Attachments {

  public function extract(string $text): array {
    if (!preg_match('~\[ia_attachments\]([A-Za-z0-9+/=]+)~', (string)$text, $m)) return [];
    $json = base64_decode($m[1], true);
    if (!$json) return [];
    $arr = json_decode($json, true);
    if (!is_array($arr)) return [];

    $out = [];
    foreach ($arr as $a) {
      if (!is_array($a)) continue;
      $url = (string)($a['url'] ?? '');
      if ($url === '') continue;
      $out[] = [
        'url'      => $url,
        'mime'     => (string)($a['mime'] ?? ''),
        'filename' => (string)($a['filename'] ?? ''),
        'size'     => (int)($a['size'] ?? 0),
      ];
    }
    return $out;
  }

  public function strip_payload(string $text): string {
    // Remove ONLY the IA attachment payload marker from visible text.
    return (string) preg_replace('~\[ia_attachments\][A-Za-z0-9+/=]+~', '', (string)$text);
  }
}
