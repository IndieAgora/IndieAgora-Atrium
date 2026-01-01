<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_Auth {

  /** @var IA_Discuss_Service_PhpBB */
  private $phpbb;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb) {
    $this->phpbb = $phpbb;
  }

  public function is_logged_in(): bool {
    return is_user_logged_in();
  }

  public function current_wp_user_id(): int {
    return (int) get_current_user_id();
  }

  /**
   * Resolve the phpBB user_id corresponding to the currently logged-in user.
   * This is the “shadow account → phpBB authority” bridge.
   */
  public function current_phpbb_user_id(): int {
    if (!is_user_logged_in()) return 0;

    // Preferred: ask IA_User/IA_Auth if they expose a mapper
    $uid = $this->try_external_mapper();
    if ($uid > 0) return $uid;

    // Next: allow the wider Atrium stack to provide a canonical phpBB id.
    // This is intentionally a simple scalar filter so it can be wired up by
    // ia-auth / ia-user / a fallback bridge without Discuss needing to know
    // implementation details.
    $wp_uid = (int) get_current_user_id();
    $filtered = (int) apply_filters('ia_current_phpbb_user_id', 0, $wp_uid);
    if ($filtered > 0) return $filtered;

    // Fallback: map WP user_login → phpbb_users.username_clean
    $u = wp_get_current_user();
    $login = $u && $u->user_login ? (string)$u->user_login : '';
    if ($login === '') return 0;

    return $this->lookup_phpbb_user_id_by_username($login);
  }

  private function try_external_mapper(): int {
    $wp_uid = (int) get_current_user_id();
    if ($wp_uid <= 0) return 0;

    // 0) If IA_Auth exposes a current user resolver, prefer it.
    if (class_exists('IA_Auth')) {
      // Static helpers
      foreach (['current_phpbb_user_id', 'current_phpbb_uid', 'phpbb_user_id_current'] as $m) {
        if (method_exists('IA_Auth', $m)) {
          try {
            $out = (int) IA_Auth::{$m}();
            if ($out > 0) return $out;
          } catch (Throwable $e) {
            // ignore
          }
        }
      }
      // Instance helpers
      foreach (['instance', 'get_instance'] as $inst) {
        if (method_exists('IA_Auth', $inst)) {
          try {
            $ia = IA_Auth::{$inst}();
            if (is_object($ia)) {
              foreach (['current_phpbb_user_id', 'current_phpbb_uid'] as $m2) {
                if (method_exists($ia, $m2)) {
                  $out = (int) $ia->{$m2}();
                  if ($out > 0) return $out;
                }
              }
            }
          } catch (Throwable $e) {
            // ignore
          }
        }
      }
    }

    // 1) A common pattern: IA_User::instance()->phpbb_user_id()
    if (class_exists('IA_User') && method_exists('IA_User', 'instance')) {
      $iu = IA_User::instance();
      foreach (['phpbb_user_id', 'get_phpbb_user_id', 'phpbb_uid'] as $m) {
        if (is_object($iu) && method_exists($iu, $m)) {
          $out = (int) $iu->{$m}($wp_uid);
          if ($out > 0) return $out;
        }
      }

      // Also support no-arg current-user resolvers
      foreach (['current_phpbb_user_id', 'current_phpbb_uid'] as $m) {
        if (is_object($iu) && method_exists($iu, $m)) {
          $out = (int) $iu->{$m}();
          if ($out > 0) return $out;
        }
      }
    }

    // 2) A static helper pattern
    if (class_exists('IA_Auth')) {
      foreach (['phpbb_user_id_for_wp', 'wp_to_phpbb_user_id'] as $m) {
        if (method_exists('IA_Auth', $m)) {
          $out = (int) IA_Auth::{$m}($wp_uid);
          if ($out > 0) return $out;
        }
      }
    }

    return 0;
  }

  private function lookup_phpbb_user_id_by_username(string $wp_login): int {
    if (!$this->phpbb->is_ready()) return 0;

    $db = $this->phpbb->db();
    if (!$db) return 0;

    $users = $this->phpbb->prefix() . 'users';

    // phpBB stores a normalized username_clean
    $clean = strtolower(sanitize_user($wp_login, true));
    if ($clean === '') return 0;

    $sql = "SELECT user_id FROM {$users} WHERE username_clean = %s LIMIT 1";
    $row = $db->get_var($db->prepare($sql, $clean));

    return (int) ($row ?: 0);
  }
}
