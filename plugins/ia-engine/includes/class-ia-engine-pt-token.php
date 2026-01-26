<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Engine — PeerTube admin token refresher
 *
 * Goal: keep a valid Bearer token available for server-to-server API calls
 * (e.g. creating PeerTube users from Atrium).
 *
 * How it works:
 * - Stores OAuth client id/secret (from /api/v1/oauth-clients/local)
 * - Stores admin username/password (PeerTube account, not Linux user)
 * - Stores access + refresh tokens + expiry timestamps
 * - Cron refreshes using refresh_token grant when possible; falls back to
 *   password grant when needed.
 */

final class IA_Engine_PeerTube_Token {

  public const CRON_HOOK = 'ia_engine_pt_refresh_token';

  public static function boot(): void {
    add_action('init', [__CLASS__, 'ensure_scheduled']);
    add_action(self::CRON_HOOK, [__CLASS__, 'cron']);

    add_action('wp_ajax_ia_engine_pt_refresh_now', [__CLASS__, 'ajax_refresh_now']);
  }

  public static function ensure_scheduled(): void {
    // Don't schedule in CLI installs where WP may not be fully ready.
    if (!function_exists('wp_next_scheduled')) return;
    if (wp_next_scheduled(self::CRON_HOOK)) return;

    // Hourly is plenty; we refresh early based on expiry anyway.
    wp_schedule_event(time() + 60, 'hourly', self::CRON_HOOK);
  }

  public static function cron(): void {
    self::refresh_if_needed();
  }

  public static function ajax_refresh_now(): void {
    if (!current_user_can('manage_options')) {
      wp_send_json(['ok' => false, 'message' => 'Forbidden.']);
    }
    // Use the same nonce action as the IA Engine admin page/script.
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'ia_engine_nonce')) {
      wp_send_json(['ok' => false, 'message' => 'Bad nonce.']);
    }

    $res = self::refresh(true);
    wp_send_json($res);
  }

  /** Refresh only if the access token is missing or close to expiry. */
  public static function refresh_if_needed(): void {
    $c = IA_Engine::peertube_api();
    $access = (string)($c['admin_access_token'] ?? '');
    $expiresAt = (int)($c['admin_access_expires_at'] ?? 0);

    // Refresh if missing or expiring within 30 minutes.
    if ($access === '' || $expiresAt === 0 || $expiresAt <= (time() + 1800)) {
      self::refresh(false);
    }
  }

  /**
   * Force or attempt a refresh.
   * Returns: ['ok'=>bool,'message'=>string]
   */
  public static function refresh(bool $force = false): array {
    $c = IA_Engine::peertube_api();
    // Prevent stampedes: multiple concurrent requests can trigger refresh at the same time.
    // This is a best-effort lock and must never break normal page loads.
    $lockKey = 'ia_engine_pt_refresh_lock';
    $locked = false;
    if (!$force && function_exists('get_transient') && function_exists('set_transient')) {
      if (get_transient($lockKey)) {
        return ['ok' => true, 'message' => 'Token refresh already in progress.'];
      }
      set_transient($lockKey, 1, 60);
      $locked = true;
    }

    try {

    $publicUrl = trim((string)($c['public_url'] ?? ''));
    if ($publicUrl === '') {
      return ['ok' => false, 'message' => 'PeerTube public URL not set in IA Engine.'];
    }

    $apiBase = rtrim($publicUrl, '/') . '/api/v1';

    // 1) Ensure OAuth client id/secret
    $clientId = trim((string)($c['oauth_client_id'] ?? ''));
    $clientSecret = trim((string)($c['oauth_client_secret'] ?? ''));

    if ($clientId === '' || $clientSecret === '') {
      $oauth = self::fetch_oauth_client($apiBase);
      if (!$oauth['ok']) return $oauth;
      $clientId = $oauth['client_id'];
      $clientSecret = $oauth['client_secret'];

      IA_Engine::set('peertube_api', [
        'oauth_client_id' => $clientId,
        'oauth_client_secret' => $clientSecret
      ]);
    }

    // 2) Try refresh_token grant first whenever we have one.
    // IMPORTANT: even when the admin clicks "Refresh now" ($force=true), we should still
    // prefer refresh_token grant if available. Password grant is only needed when the
    // refresh token is missing/expired/revoked.
    $refreshToken = trim((string)($c['admin_refresh_token'] ?? ''));
    $refreshExpiresAt = (int)($c['admin_refresh_expires_at'] ?? 0);

    if ($refreshToken !== '') {
      // If we know it is expired, we can still attempt a refresh (some instances may not
      // provide refresh_token_expires_in). If it fails, we fall through to password grant.
      if ($refreshExpiresAt === 0 || $refreshExpiresAt > time() + 300 || $force) {
        $r = self::grant_refresh_token($apiBase, $clientId, $clientSecret, $refreshToken);
        if (!empty($r['ok'])) return $r;
        // fall through to password grant
      }
    }

    // 3) Password grant (requires admin username/password)
    $adminUser = trim((string)($c['admin_username'] ?? ''));
    $adminPass = (string)($c['admin_password'] ?? '');
    if ($adminUser === '' || $adminPass === '') {
      return ['ok' => false, 'message' => 'Admin username/password not set (needed to mint a new token).'];
    }

    return self::grant_password_token($apiBase, $clientId, $clientSecret, $adminUser, $adminPass);
  
    } finally {
      if (!empty($locked) && $locked && function_exists('delete_transient')) {
        delete_transient($lockKey);
      }
    }
}

  private static function fetch_oauth_client(string $apiBase): array {
    $url = rtrim($apiBase, '/') . '/oauth-clients/local';
    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) {
      return ['ok' => false, 'message' => 'Could not fetch oauth client: ' . $resp->get_error_message()];
    }
    $code = (int)wp_remote_retrieve_response_code($resp);
    $body = (string)wp_remote_retrieve_body($resp);
    if ($code < 200 || $code >= 300) {
      return ['ok' => false, 'message' => 'Could not fetch oauth client (HTTP ' . $code . ').'];
    }
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['client_id']) || empty($json['client_secret'])) {
      return ['ok' => false, 'message' => 'OAuth client response was not valid JSON.'];
    }
    return ['ok' => true, 'client_id' => (string)$json['client_id'], 'client_secret' => (string)$json['client_secret']];
  }

  private static function grant_refresh_token(string $apiBase, string $clientId, string $clientSecret, string $refreshToken): array {
    $url = rtrim($apiBase, '/') . '/users/token';
    $resp = wp_remote_post($url, [
      'timeout' => 15,
      'body' => [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
      ]
    ]);
    if (is_wp_error($resp)) {
      return ['ok' => false, 'message' => 'Token refresh failed: ' . $resp->get_error_message()];
    }

    $code = (int)wp_remote_retrieve_response_code($resp);
    $body = (string)wp_remote_retrieve_body($resp);
    if ($code < 200 || $code >= 300) {
      return ['ok' => false, 'message' => 'Token refresh failed (HTTP ' . $code . ').'];
    }

    return self::persist_token_payload($body, 'Refreshed PeerTube admin token.');
  }

  private static function grant_password_token(string $apiBase, string $clientId, string $clientSecret, string $username, string $password): array {
    $url = rtrim($apiBase, '/') . '/users/token';
    $resp = wp_remote_post($url, [
      'timeout' => 15,
      'body' => [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'password',
        'username' => $username,
        'password' => $password
      ]
    ]);
    if (is_wp_error($resp)) {
      return ['ok' => false, 'message' => 'Token mint failed: ' . $resp->get_error_message()];
    }
    $code = (int)wp_remote_retrieve_response_code($resp);
    $body = (string)wp_remote_retrieve_body($resp);
    if ($code < 200 || $code >= 300) {
      return ['ok' => false, 'message' => 'Token mint failed (HTTP ' . $code . ').'];
    }

    return self::persist_token_payload($body, 'Minted PeerTube admin token.');
  }

  private static function persist_token_payload(string $jsonBody, string $successMessage): array {
    $json = json_decode($jsonBody, true);
    if (!is_array($json) || empty($json['access_token'])) {
      return ['ok' => false, 'message' => 'PeerTube token response was not valid JSON.'];
    }

    $now = time();
    $accessToken = (string)($json['access_token'] ?? '');
    $refreshToken = (string)($json['refresh_token'] ?? '');
    $expiresIn = (int)($json['expires_in'] ?? 0);
    $refreshExpiresIn = (int)($json['refresh_token_expires_in'] ?? 0);

    $accessExpiresAt = $expiresIn > 0 ? ($now + $expiresIn) : 0;
    $refreshExpiresAt = $refreshExpiresIn > 0 ? ($now + $refreshExpiresIn) : 0;

    // Persist into ia-engine peertube_api and also mirror into ia-auth option for compatibility.
    IA_Engine::set('peertube_api', [
      'token' => $accessToken,
      'admin_access_token' => $accessToken,
      'admin_refresh_token' => $refreshToken,
      'admin_access_expires_at' => $accessExpiresAt,
      'admin_refresh_expires_at' => $refreshExpiresAt
    ]);

    // Mirror into ia-auth (some plugins may read it there first).
    $iaAuth = get_option('ia_auth_options', []);
    if (!is_array($iaAuth)) $iaAuth = [];
    $iaAuth['peertube_api_token'] = $accessToken;
    update_option('ia_auth_options', $iaAuth);

    return ['ok' => true, 'message' => $successMessage];
  }
}
