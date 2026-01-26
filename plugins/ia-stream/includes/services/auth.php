<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Auth Service
 *
 * Stream is read-only for now:
 * - Guests can read feed/channels/video/comments.
 * - Write actions (future) would be gated here.
 */
final class IA_Stream_Service_Auth {

  public function can_read(): bool {
    return true;
  }

  public function can_write(): bool {
    // Future: require login, require mapped PeerTube identity, etc.
    return is_user_logged_in();
  }

  /**
   * Optional: if your platform has a global IA_Auth / IA_User authority,
   * later we can map to PeerTube identity here.
   */
  public function current_identity(): array {
    return [
      'wp_user_id' => get_current_user_id(),
      'logged_in'  => is_user_logged_in(),
    ];
  }

  /**
   * Best-effort: resolve the *current user's* PeerTube bearer token.
   *
   * This integrates with IA Auth when present:
   * - wp_ia_identity_map (wp_user_id -> phpbb_user_id)
   * - wp_ia_peertube_tokens (phpbb_user_id -> encrypted access token)
   *
   * If IA Auth isn't installed, or the user has no token, returns ''.
   */
  public function current_peertube_token(): string {
    if (!is_user_logged_in()) return '';

    $wp_user_id = (int) get_current_user_id();
    if ($wp_user_id <= 0) return '';

    // We only *read* IA Auth tables if present. No hard dependency.
    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) return '';

    $map_table = $wpdb->prefix . 'ia_identity_map';
    $tok_table = $wpdb->prefix . 'ia_peertube_tokens';

    // Find phpBB user id for this WP user (canonical mapping table).
    $phpbb_user_id = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT phpbb_user_id FROM {$map_table} WHERE wp_user_id=%d AND status IN ('linked','partial') ORDER BY phpbb_user_id DESC LIMIT 1",
        $wp_user_id
      )
    );

    // Fallback: some deployments keep the phpBB id on user meta.
    if ($phpbb_user_id <= 0) {
      $phpbb_user_id = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
    }

    if ($phpbb_user_id <= 0) return '';

    $access_enc = (string) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT access_token_enc FROM {$tok_table} WHERE phpbb_user_id=%d LIMIT 1",
        $phpbb_user_id
      )
    );

    if ($access_enc === '') return '';

    // Decrypt using IA Auth crypto if available.
    if (class_exists('IA_Auth_Crypto')) {
      try {
        $c = new IA_Auth_Crypto();
        $tok = (string) $c->decrypt($access_enc);
        return trim($tok);
      } catch (Throwable $e) {
        return '';
      }
    }

    // If IA Auth isn't loaded, we cannot decrypt.
    return '';
  }

  /**
   * Diagnostics for why PeerTube is not activated for the current user.
   * Returns ['ok'=>bool,'phpbb_user_id'=>int,'has_token'=>bool,'status'=>string,'last_error'=>string]
   */
  public function activation_info(): array {
    $info = [
      'ok' => false,
      'phpbb_user_id' => 0,
      'has_token' => false,
      'status' => '',
      'last_error' => '',
    ];

    if (!is_user_logged_in()) return $info;

    $wp_user_id = (int) get_current_user_id();
    if ($wp_user_id <= 0) return $info;

    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) return $info;

    $map_table = $wpdb->prefix . 'ia_identity_map';
    $tok_table = $wpdb->prefix . 'ia_peertube_tokens';

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT phpbb_user_id, status, last_error FROM {$map_table} WHERE wp_user_id=%d ORDER BY phpbb_user_id DESC LIMIT 1",
        $wp_user_id
      ),
      ARRAY_A
    );

    if (is_array($row)) {
      $info['phpbb_user_id'] = (int)($row['phpbb_user_id'] ?? 0);
      $info['status'] = (string)($row['status'] ?? '');
      $info['last_error'] = (string)($row['last_error'] ?? '');
    }

    if ($info['phpbb_user_id'] <= 0) {
      $info['phpbb_user_id'] = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
    }

    if ($info['phpbb_user_id'] > 0) {
      $enc = (string)$wpdb->get_var(
        $wpdb->prepare("SELECT access_token_enc FROM {$tok_table} WHERE phpbb_user_id=%d LIMIT 1", $info['phpbb_user_id'])
      );
      $info['has_token'] = trim($enc) !== '';
      if (!$info['has_token'] && $info['status'] === 'linked' && $info['last_error'] === '') {
        // Common confusing state: identity marked linked but no token row.
        $info['last_error'] = 'Identity is linked but no PeerTube token is stored for this phpBB user id.';
      }
    }

    $info['ok'] = ($info['phpbb_user_id'] > 0) && $info['has_token'];
    return $info;
  }

}
