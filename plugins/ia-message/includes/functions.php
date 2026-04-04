<?php
if (!defined('ABSPATH')) exit;

/**
 * Safe require (never fatal if missing; returns bool).
 */
function ia_message_require(string $rel): bool {
  $path = IA_MESSAGE_PATH . ltrim($rel, '/');
  if (file_exists($path)) {
    require_once $path;
    return true;
  }
  return false;
}

/**
 * Admin-only error notice helper (non-fatal doctrine).
 */
function ia_message_admin_notice(string $msg): void {
  if (!is_admin()) return;
  add_action('admin_notices', function () use ($msg) {
    echo '<div class="notice notice-error"><p><strong>IA Message:</strong> ' . esc_html($msg) . '</p></div>';
  });
}

/**
 * Simple feature gate: only enqueue when Atrium is present.
 * We avoid guessing URLs; instead we require Atrium to be active (hooks exist).
 */
function ia_message_atrium_present(): bool {
  // If Atrium exposes a known function/hook, we key off that.
  // This keeps ia-message dormant unless Atrium is installed.
  return has_action('ia_atrium_panel_' . IA_MESSAGE_PANEL_KEY)
      || did_action('ia_atrium_boot')
      || function_exists('ia_atrium_boot')
      || defined('IA_ATRIUM_VERSION');
}

/**
 * Undo WordPress magic slashes / accidental addslashes, but only when it
 * looks like quote escaping is present. This avoids stripping intentional
 * backslashes in most normal cases.
 */
function ia_message_maybe_unslash(string $s): string {
  if ($s === '') return $s;
  // Only unslash when we see the typical escaped-quote sequences.
  if (strpos($s, "\\'") !== false || strpos($s, '\\"') !== false) {
    return wp_unslash($s);
  }
  return $s;
}

// Cross-platform user relationships (follow/block). Guarded to avoid redeclare.
if (!function_exists('ia_user_rel_table')) { function ia_user_rel_table(): string { global $wpdb; return $wpdb->prefix . 'ia_user_relations'; } }
if (!function_exists('ia_user_rel_ensure_table')) {
  function ia_user_rel_ensure_table(): void {
    global $wpdb; $t = ia_user_rel_table();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
    if ((string)$exists === (string)$t) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$t} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      rel_type VARCHAR(20) NOT NULL,
      src_phpbb_id BIGINT(20) UNSIGNED NOT NULL,
      dst_phpbb_id BIGINT(20) UNSIGNED NOT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_rel (rel_type, src_phpbb_id, dst_phpbb_id),
      KEY src (src_phpbb_id, rel_type),
      KEY dst (dst_phpbb_id, rel_type)
    ) {$charset};";
    dbDelta($sql);
  }
}
if (!function_exists('ia_user_rel_is_following')) {
  function ia_user_rel_is_following(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb=(int)$src_phpbb; $dst_phpbb=(int)$dst_phpbb;
    if ($src_phpbb<=0||$dst_phpbb<=0||$src_phpbb===$dst_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $v=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE rel_type='follow' AND src_phpbb_id=%d AND dst_phpbb_id=%d LIMIT 1",$src_phpbb,$dst_phpbb));
    return (string)$v==='1';
  }
}
if (!function_exists('ia_user_rel_toggle_follow')) {
  function ia_user_rel_toggle_follow(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb=(int)$src_phpbb; $dst_phpbb=(int)$dst_phpbb;
    if ($src_phpbb<=0||$dst_phpbb<=0||$src_phpbb===$dst_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    if (ia_user_rel_is_following($src_phpbb,$dst_phpbb)) { $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE rel_type='follow' AND src_phpbb_id=%d AND dst_phpbb_id=%d",$src_phpbb,$dst_phpbb)); return false; }
    $now=current_time('mysql');
    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$t}(rel_type,src_phpbb_id,dst_phpbb_id,created_at) VALUES('follow',%d,%d,%s)",$src_phpbb,$dst_phpbb,$now));

    /**
     * Signal: a follow relationship was created.
     * Used by ia-mail-suite now, and ia-notifications later.
     */
    do_action('ia_user_follow_created', $src_phpbb, $dst_phpbb, ['source' => 'message']);

    return true;
  }
}
if (!function_exists('ia_user_rel_is_blocked_any')) {
  function ia_user_rel_is_blocked_any(int $a_phpbb, int $b_phpbb): bool {
    $a_phpbb=(int)$a_phpbb; $b_phpbb=(int)$b_phpbb;
    if ($a_phpbb<=0||$b_phpbb<=0||$a_phpbb===$b_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $v=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE rel_type='block' AND ((src_phpbb_id=%d AND dst_phpbb_id=%d) OR (src_phpbb_id=%d AND dst_phpbb_id=%d)) LIMIT 1",$a_phpbb,$b_phpbb,$b_phpbb,$a_phpbb));
    return (string)$v==='1';
  }
}
if (!function_exists('ia_user_rel_is_blocked_by_me')) {
  function ia_user_rel_is_blocked_by_me(int $me_phpbb, int $other_phpbb): bool {
    $me_phpbb=(int)$me_phpbb; $other_phpbb=(int)$other_phpbb;
    if ($me_phpbb<=0||$other_phpbb<=0||$me_phpbb===$other_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $v=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE rel_type='block' AND src_phpbb_id=%d AND dst_phpbb_id=%d LIMIT 1",$me_phpbb,$other_phpbb));
    return (string)$v==='1';
  }
}
if (!function_exists('ia_user_rel_toggle_block')) {
  function ia_user_rel_toggle_block(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb=(int)$src_phpbb; $dst_phpbb=(int)$dst_phpbb;
    if ($src_phpbb<=0||$dst_phpbb<=0||$src_phpbb===$dst_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    if (ia_user_rel_is_blocked_by_me($src_phpbb,$dst_phpbb)) { $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE rel_type='block' AND src_phpbb_id=%d AND dst_phpbb_id=%d",$src_phpbb,$dst_phpbb)); return false; }
    $now=current_time('mysql'); $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$t}(rel_type,src_phpbb_id,dst_phpbb_id,created_at) VALUES('block',%d,%d,%s)",$src_phpbb,$dst_phpbb,$now)); return true;
  }
}
