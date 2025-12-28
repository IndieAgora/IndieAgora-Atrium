<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_Text {

  public function squash_ws(string $s): string {
    $s = preg_replace("~\s+~u", " ", (string)$s);
    return trim($s ?? '');
  }

  public function limit_chars(string $s, int $max, string $suffix = '…'): string {
    $s = (string) $s;
    if ($max < 0) $max = 0;
    if (mb_strlen($s, 'UTF-8') > $max) {
      return mb_substr($s, 0, $max, 'UTF-8') . $suffix;
    }
    return $s;
  }
}
