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
}
