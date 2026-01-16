<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Auth Service
 *
 * Responsibilities:
 * - Gate write actions behind login
 * - Resolve a user-scoped PeerTube Bearer token (just-in-time)
 * - Provision PeerTube account on-demand when missing (capability activation)
 *
 * This is deliberately "best effort" and additive:
 * - If IA Auth/Engine are missing, Stream remains read-only.
 * - If the user has no PeerTube identity, we create one silently on first write attempt.
 */
final class IA_Stream_Service_Auth {

  public function can_read(): bool {
    return true;
  }

  public function can_write(): bool {
    return is_user_logged_in();
  }

  public function current_identity(): array {
    return [
      'wp_user_id' => get_current_user_id(),
      'logged_in'  => is_user_logged_in(),
    ];
  }

  /**
   * Ensure we have a valid PeerTube bearer token for the current logged-in user.
   *
   * Returns:
   *  - ['ok'=>true,'bearer'=>string,'phpbb_user_id'=>int,'identity'=>array]
   *  - ['ok'=>false,'error'=>string,'message'=>string]
   */
  public function ensure_user_bearer(): array {
    if (!is_user_logged_in()) {
      return ['ok' => false, 'error' => 'login_required', 'message' => 'Login required.'];
    }

    if (!class_exists('IA_Auth')) {
      return ['ok' => false, 'error' => 'ia_auth_missing', 'message' => 'IA Auth not available.'];
    }

    if (!class_exists('IA_Engine')) {
      return ['ok' => false, 'error' => 'ia_engine_missing', 'message' => 'IA Engine not available.'];
    }

    $ia = IA_Auth::instance();
    if (!is_object($ia) || !isset($ia->db) || !isset($ia->crypto) || !isset($ia->peertube)) {
      return ['ok' => false, 'error' => 'ia_auth_incomplete', 'message' => 'IA Auth is not fully initialised.'];
    }

    $wp_user_id = (int) get_current_user_id();
    $phpbb_user_id = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);

    // Resolve identity row (may be null if not created yet)
    $identity = null;
    if (method_exists($ia->db, 'get_identity_by_wp_user_id')) {
      $identity = $ia->db->get_identity_by_wp_user_id($wp_user_id);
    }
    if (is_array($identity) && isset($identity['phpbb_user_id'])) {
      $phpbb_user_id = (int)$identity['phpbb_user_id'];
    }

    if ($phpbb_user_id <= 0) {
      return ['ok' => false, 'error' => 'no_identity', 'message' => 'No linked identity for this user.'];
    }

    // If PeerTube ids are missing, provision now (activation on demand)
    $needs_pt = (!is_array($identity) || empty($identity['peertube_user_id']));
    if ($needs_pt) {
      $prov = $this->provision_peertube_for_user($ia, $wp_user_id, $phpbb_user_id);
      if (!$prov['ok']) return $prov;
      $identity = $prov['identity'];
    }

    // Fetch token row
    $row = null;
    if (method_exists($ia->db, 'get_peertube_token_row')) {
      $row = $ia->db->get_peertube_token_row($phpbb_user_id);
    }

    $access_enc  = is_array($row) ? (string)($row['access_token_enc'] ?? '') : '';
    $refresh_enc = is_array($row) ? (string)($row['refresh_token_enc'] ?? '') : '';
    $expires_at  = is_array($row) ? (string)($row['expires_at_utc'] ?? '') : '';

    $access  = $access_enc !== '' ? (string)$ia->crypto->decrypt($access_enc) : '';
    $refresh = $refresh_enc !== '' ? (string)$ia->crypto->decrypt($refresh_enc) : '';

    $expires_ts = 0;
    if ($expires_at !== '') {
      $t = strtotime($expires_at . ' UTC');
      if ($t !== false) $expires_ts = (int)$t;
    }

    $pt_cfg = IA_Engine::peertube_api();

    // If access token is missing or expiring soon, refresh or mint
    $needs_new = ($access === '' || $expires_ts === 0 || $expires_ts <= (time() + 60));

    if ($needs_new) {
      // Try refresh grant first
      if ($refresh !== '' && method_exists($ia->peertube, 'refresh_grant')) {
        $r = $ia->peertube->refresh_grant($refresh, $pt_cfg);
        if (!empty($r['ok']) && !empty($r['token']) && is_array($r['token'])) {
          $tok = $r['token'];
          $access  = (string)($tok['access_token'] ?? '');
          $refresh = (string)($tok['refresh_token'] ?? $refresh);

          $exp = (int)($tok['expires_in'] ?? 0);
          $exp_at = $exp > 0 ? gmdate('Y-m-d H:i:s', time() + $exp) : null;

          $ia->db->store_peertube_token($phpbb_user_id, [
            'access_token_enc'  => $ia->crypto->encrypt($access),
            'refresh_token_enc' => $ia->crypto->encrypt($refresh),
            'expires_at_utc'    => $exp_at,
            'scope'             => '',
            'token_source'      => 'refresh_grant',
          ]);

          return ['ok' => true, 'bearer' => $access, 'phpbb_user_id' => $phpbb_user_id, 'identity' => $identity];
        }
      }

      // Fall back to password grant using stored PeerTube password (if available)
      $pw_enc = (string) get_user_meta($wp_user_id, 'ia_peertube_pw_enc', true);
      if ($pw_enc === '') {
        // If user was provisioned via email verification, they may have a pending pw stored earlier
        $pw_enc = (string) get_user_meta($wp_user_id, 'ia_pw_enc_pending', true);
      }
      $pw = $pw_enc !== '' ? (string)$ia->crypto->decrypt($pw_enc) : '';

      if ($pw === '') {
        return ['ok' => false, 'error' => 'no_peertube_password', 'message' => 'Stream needs activation for your account (missing PeerTube credentials).'];
      }

      // Use email as username for PeerTube token endpoint (PeerTube accepts username or email)
      $u = get_userdata($wp_user_id);
      $email = $u ? (string)$u->user_email : '';
      $username = $u ? (string)$u->user_login : '';

      $login = $email !== '' ? $email : $username;
      $mint = $ia->peertube->password_grant($login, $pw, $pt_cfg);
      if (empty($mint['ok']) || empty($mint['token']) || !is_array($mint['token'])) {
        return ['ok' => false, 'error' => 'token_mint_failed', 'message' => $mint['message'] ?? 'Could not mint PeerTube token.'];
      }

      $tok = $mint['token'];
      $access  = (string)($tok['access_token'] ?? '');
      $refresh = (string)($tok['refresh_token'] ?? '');
      $exp = (int)($tok['expires_in'] ?? 0);
      $exp_at = $exp > 0 ? gmdate('Y-m-d H:i:s', time() + $exp) : null;

      $ia->db->store_peertube_token($phpbb_user_id, [
        'access_token_enc'  => $ia->crypto->encrypt($access),
        'refresh_token_enc' => $ia->crypto->encrypt($refresh),
        'expires_at_utc'    => $exp_at,
        'scope'             => '',
        'token_source'      => 'password_grant',
      ]);
    }

    if ($access === '') {
      return ['ok' => false, 'error' => 'no_token', 'message' => 'PeerTube token unavailable.'];
    }

    return ['ok' => true, 'bearer' => $access, 'phpbb_user_id' => $phpbb_user_id, 'identity' => $identity];
  }

  /**
   * Provision a PeerTube account for this Atrium identity.
   * Uses the admin token configured in IA Engine/IA Auth.
   */
  private function provision_peertube_for_user($ia, int $wp_user_id, int $phpbb_user_id): array {
    $u = get_userdata($wp_user_id);
    if (!$u) return ['ok' => false, 'error' => 'user_missing', 'message' => 'User not found.'];

    $username = (string) $u->user_login;
    $email    = (string) $u->user_email;

    if ($username === '' || $email === '') {
      return ['ok' => false, 'error' => 'bad_user', 'message' => 'User record missing username/email.'];
    }

    $pt_cfg = IA_Engine::peertube_api();

    // Deterministic channel name that is not equal to username
    $chan_base = strtolower((string)$username);
    $chan_base = preg_replace('/[^a-z0-9_]+/', '_', $chan_base);
    $chan_base = trim($chan_base, '_');
    if ($chan_base === '') $chan_base = 'user';
    $channel_name = substr($chan_base . '_channel', 0, 50);

    // Generate and persist a PeerTube password for token minting.
    $pw = wp_generate_password(24, true, true);
    update_user_meta($wp_user_id, 'ia_peertube_pw_enc', $ia->crypto->encrypt($pw));

    $created = $ia->peertube->admin_create_user($username, $email, $pw, $channel_name, $pt_cfg);
    if (empty($created['ok'])) {
      return ['ok' => false, 'error' => 'provision_failed', 'message' => $created['message'] ?? 'Could not provision PeerTube user.'];
    }

    // Upsert identity map with PeerTube ids
    $ia->db->upsert_identity([
      'phpbb_user_id'        => $phpbb_user_id,
      'phpbb_username_clean' => '',
      'email'                => $email,
      'wp_user_id'           => $wp_user_id,
      'peertube_user_id'     => (int)($created['peertube_user_id'] ?? 0),
      'peertube_account_id'  => (int)($created['peertube_account_id'] ?? 0),
      'peertube_actor_id'    => (int)($created['peertube_actor_id'] ?? 0),
      'status'               => 'linked',
      'last_error'           => '',
    ]);

    $identity = null;
    if (method_exists($ia->db, 'get_identity_by_wp_user_id')) {
      $identity = $ia->db->get_identity_by_wp_user_id($wp_user_id);
    }

    return ['ok' => true, 'identity' => $identity];
  }
}
