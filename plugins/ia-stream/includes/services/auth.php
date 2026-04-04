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
   * Canonical current-user bearer resolution for Stream.
   *
   * Stream reads only through the per-user helper contract. Legacy login-era
   * token tables are no longer authoritative for Stream writes.
   */
  public function current_peertube_token(): string {
    if (!(class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user'))) {
      return '';
    }

    try {
      $status = IA_PeerTube_Token_Helper::get_token_status_for_current_user();
      if (is_array($status) && !empty($status['ok'])) {
        return trim((string) ($status['token'] ?? ''));
      }
    } catch (Throwable $e) {
      return '';
    }

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
      'token_source' => '',
      'reason_code' => '',
    ];

    if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user')) {
      try {
        $status = IA_PeerTube_Token_Helper::get_token_status_for_current_user();
        if (is_array($status)) {
          $info['ok'] = !empty($status['ok']);
          $info['phpbb_user_id'] = (int)($status['phpbb_user_id'] ?? 0);
          $info['has_token'] = !empty($status['ok']) && trim((string)($status['token'] ?? '')) !== '';
          $info['last_error'] = (string)($status['error'] ?? '');
          $info['token_source'] = (string)($status['token_source'] ?? '');
          $info['reason_code'] = (string)($status['code'] ?? '');
          if ($info['phpbb_user_id'] > 0 || $info['reason_code'] !== '') {
            return $info;
          }
        }
      } catch (Throwable $e) {
        // fall through to legacy diagnostics
      }
    }

    return $info;
  }

}
