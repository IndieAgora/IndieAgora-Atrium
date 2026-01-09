<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Connect — Account editor (username/email/bio)
 *
 * - Username + bio apply immediately.
 * - Email requires verification via link sent to the new address.
 * - Sync targets:
 *   - WordPress users
 *   - phpBB users table (via IA_Engine credentials)
 *   - PeerTube Postgres public."user" (via IA_Engine credentials)
 *   - Identity map table (if present): {$wpdb->prefix}ia_identity_map
 */
final class ia_connect_module_account implements ia_connect_module_interface {

  private const META_PENDING_EMAIL = 'ia_connect_pending_email';
  private const META_EMAIL_TOKEN   = 'ia_connect_pending_email_token';
  private const META_EMAIL_EXPIRES = 'ia_connect_pending_email_expires';

  public function boot(): void {
    add_action('init', [$this, 'maybe_handle_email_verify']);

    // Account editing (self)
    add_action('wp_ajax_ia_connect_update_account', [$this, 'ajax_update_account']);

    // Password reset (send WP reset email)
    add_action('wp_ajax_ia_connect_password_reset', [$this, 'ajax_password_reset']);

    // When WP password changes via reset flow, also sync to phpBB.
    add_action('after_password_reset', [$this, 'after_password_reset_sync_phpbb'], 10, 2);
  }

  public function ajax_routes(): array {
    return [
      'ia_connect_update_account' => ['method' => 'ajax_update_account', 'public' => false],
    ];
  }

  public function ajax_update_account(): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    $wp_user_id = get_current_user_id();
    $u = get_userdata($wp_user_id);
    if (!$u) wp_send_json_error(['message' => 'User not found.'], 404);

    $bio = isset($_POST['bio']) ? sanitize_textarea_field(wp_unslash($_POST['bio'])) : null;
    $username = isset($_POST['username']) ? trim((string)wp_unslash($_POST['username'])) : null;
    $email = isset($_POST['email']) ? trim((string)wp_unslash($_POST['email'])) : null;

    $out = [
      'bio' => null,
      'username' => null,
      'email' => null,
      'email_verification_sent' => false,
      'message' => 'Saved.',
    ];

    // BIO (immediate)
    if ($bio !== null) {
      update_user_meta($wp_user_id, 'ia_connect_bio', $bio);
      // Also mirror into WP description for compatibility with other tools.
      wp_update_user(['ID' => $wp_user_id, 'description' => $bio]);
      $out['bio'] = $bio;
    }

    // USERNAME (immediate)
    if ($username !== null) {
      $res = $this->update_username_everywhere($wp_user_id, $username);
      if (is_wp_error($res)) {
        wp_send_json_error(['message' => $res->get_error_message()], 400);
      }
      $out['username'] = $res;
    }

    // EMAIL (verification)
    if ($email !== null) {
      $email = sanitize_email($email);
      if ($email === '' || !is_email($email)) {
        wp_send_json_error(['message' => 'Please enter a valid email address.'], 400);
      }
      if (strcasecmp($email, (string)$u->user_email) !== 0) {
        $send = $this->start_email_verification($wp_user_id, $email);
        if (is_wp_error($send)) {
          wp_send_json_error(['message' => $send->get_error_message()], 400);
        }
        $out['email'] = $email;
        $out['email_verification_sent'] = true;
        $out['message'] = 'Verification email sent.';
      } else {
        $out['email'] = $email;
      }
    }

    wp_send_json_success($out);
  }

  // ------------------------
  // Email verification flow
  // ------------------------

  private function start_email_verification(int $wp_user_id, string $new_email) {
    // Uniqueness check in WP.
    $existing = get_user_by('email', $new_email);
    if ($existing && (int)$existing->ID !== $wp_user_id) {
      return new WP_Error('email_taken', 'That email is already in use.');
    }

    $token = bin2hex(random_bytes(16));
    $expires = time() + (60 * 60 * 24); // 24h

    update_user_meta($wp_user_id, self::META_PENDING_EMAIL, $new_email);
    update_user_meta($wp_user_id, self::META_EMAIL_TOKEN, wp_hash($token));
    update_user_meta($wp_user_id, self::META_EMAIL_EXPIRES, (string)$expires);

    $url = add_query_arg([
      'ia_connect_verify_email' => '1',
      'uid' => (string)$wp_user_id,
      'token' => $token,
    ], home_url('/'));

    $subject = 'Verify your new email address';
    $body = "Hi,\n\nYou requested to change the email address on your IndieAgora account.\n\nClick this link to confirm:\n{$url}\n\nThis link expires in 24 hours.\n\nIf you did not request this, you can ignore this email.";

    $sent = wp_mail($new_email, $subject, $body);
    if (!$sent) {
      return new WP_Error('mail_failed', 'Could not send verification email.');
    }
    return true;
  }

  public function maybe_handle_email_verify(): void {
    if (empty($_GET['ia_connect_verify_email'])) return;

    $wp_user_id = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
    $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
    if ($wp_user_id <= 0 || $token === '') {
      wp_die('Invalid verification link.');
    }

    $pending = (string) get_user_meta($wp_user_id, self::META_PENDING_EMAIL, true);
    $hash = (string) get_user_meta($wp_user_id, self::META_EMAIL_TOKEN, true);
    $expires = (int) get_user_meta($wp_user_id, self::META_EMAIL_EXPIRES, true);

    if ($pending === '' || $hash === '' || $expires <= 0) {
      wp_die('Verification link is no longer valid.');
    }
    if (time() > $expires) {
      $this->clear_pending_email($wp_user_id);
      wp_die('Verification link has expired.');
    }
    if (!hash_equals($hash, wp_hash($token))) {
      wp_die('Verification link is invalid.');
    }

    $apply = $this->apply_email_everywhere($wp_user_id, $pending);
    $this->clear_pending_email($wp_user_id);
    if (is_wp_error($apply)) {
      wp_die(esc_html($apply->get_error_message()));
    }

    // Redirect back to site (Connect tab). Keep it simple.
    wp_safe_redirect(add_query_arg(['tab' => 'connect', 'email_verified' => '1'], home_url('/')));
    exit;
  }

  private function clear_pending_email(int $wp_user_id): void {
    delete_user_meta($wp_user_id, self::META_PENDING_EMAIL);
    delete_user_meta($wp_user_id, self::META_EMAIL_TOKEN);
    delete_user_meta($wp_user_id, self::META_EMAIL_EXPIRES);
  }

  // ------------------------
  // Cross-system sync
  // ------------------------

  private function update_username_everywhere(int $wp_user_id, string $new_username) {
    $new_username = trim($new_username);
    if ($new_username === '') return new WP_Error('bad_username', 'Username cannot be empty.');

    // Clean login (keeps your existing convention: spaces -> underscore, lowercase)
    $username_clean = strtolower(trim(preg_replace('/\s+/', '_', $new_username)));
    $username_clean = sanitize_user($username_clean, true);
    if ($username_clean === '') return new WP_Error('bad_username', 'Username is not valid.');

    $existing = get_user_by('login', $username_clean);
    if ($existing && (int)$existing->ID !== $wp_user_id) {
      return new WP_Error('username_taken', 'Username is already taken.');
    }

    global $wpdb;
    $wpdb->update(
      $wpdb->users,
      [
        'user_login' => $username_clean,
        'user_nicename' => sanitize_title($username_clean),
        'display_name' => $new_username,
      ],
      ['ID' => $wp_user_id],
      ['%s','%s','%s'],
      ['%d']
    );
    clean_user_cache($wp_user_id);

    // Identity map + cross systems
    $phpbb_id = $this->resolve_phpbb_user_id($wp_user_id);
    if ($phpbb_id > 0) {
      $this->phpbb_update_username($phpbb_id, $new_username, $username_clean);
      $this->identity_map_update(['phpbb_user_id' => $phpbb_id, 'phpbb_username_clean' => $username_clean]);
    }

    $pt_user_id = $this->resolve_peertube_user_id($wp_user_id);
    if ($pt_user_id > 0) {
      $this->peertube_update_username($pt_user_id, $username_clean);
    }

    return $new_username;
  }

  private function apply_email_everywhere(int $wp_user_id, string $new_email) {
    $new_email = sanitize_email($new_email);
    if ($new_email === '' || !is_email($new_email)) return new WP_Error('bad_email', 'Invalid email.');

    $existing = get_user_by('email', $new_email);
    if ($existing && (int)$existing->ID !== $wp_user_id) {
      return new WP_Error('email_taken', 'That email is already in use.');
    }

    $res = wp_update_user(['ID' => $wp_user_id, 'user_email' => $new_email]);
    if (is_wp_error($res)) return $res;

    $phpbb_id = $this->resolve_phpbb_user_id($wp_user_id);
    if ($phpbb_id > 0) {
      $this->phpbb_update_email($phpbb_id, $new_email);
      $this->identity_map_update(['phpbb_user_id' => $phpbb_id, 'email' => $new_email]);
    }

    $pt_user_id = $this->resolve_peertube_user_id($wp_user_id);
    if ($pt_user_id > 0) {
      $this->peertube_update_email($pt_user_id, $new_email);
    }

    return true;
  }

  private function resolve_phpbb_user_id(int $wp_user_id): int {
    $candidates = ['ia_phpbb_user_id','phpbb_user_id','ia_phpbb_uid','phpbb_uid','ia_identity_phpbb'];
    foreach ($candidates as $k) {
      $v = (int) get_user_meta($wp_user_id, $k, true);
      if ($v > 0) return $v;
    }
    global $wpdb;
    $t = $wpdb->prefix . 'ia_identity_map';
    $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    if ($has === $t) {
      $id = (int) $wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM {$t} WHERE wp_user_id=%d LIMIT 1", $wp_user_id));
      if ($id > 0) return $id;
    }
    return 0;
  }

  private function resolve_peertube_user_id(int $wp_user_id): int {
    if (class_exists('IA_Auth') && method_exists(IA_Auth::class, 'instance')) {
      $ia = IA_Auth::instance();
      if (isset($ia->db) && method_exists($ia->db, 'get_identity_by_wp_user_id')) {
        $ident = $ia->db->get_identity_by_wp_user_id($wp_user_id);
        if (is_array($ident) && !empty($ident['peertube_user_id'])) return (int)$ident['peertube_user_id'];
      }
    }

    global $wpdb;
    $t = $wpdb->prefix . 'ia_identity_map';
    $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    if ($has === $t) {
      $id = (int) $wpdb->get_var($wpdb->prepare("SELECT peertube_user_id FROM {$t} WHERE wp_user_id=%d LIMIT 1", $wp_user_id));
      if ($id > 0) return $id;
    }
    return 0;
  }

  private function identity_map_update(array $row): void {
    if (class_exists('IA_Auth') && method_exists(IA_Auth::class, 'instance')) {
      $ia = IA_Auth::instance();
      if (isset($ia->db) && method_exists($ia->db, 'upsert_identity')) {
        try { $ia->db->upsert_identity($row); } catch (Throwable $e) {}
        return;
      }
    }
    // Fallback: update via $wpdb if table exists
    global $wpdb;
    $t = $wpdb->prefix . 'ia_identity_map';
    $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    if ($has !== $t) return;
    if (empty($row['phpbb_user_id'])) return;
    $phpbb_id = (int)$row['phpbb_user_id'];
    unset($row['phpbb_user_id']);
    if (empty($row)) return;
    $wpdb->update($t, $row, ['phpbb_user_id' => $phpbb_id]);
  }

  private function phpbb_update_username(int $phpbb_user_id, string $username, string $username_clean): void {
    if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'phpbb_db')) return;
    $cfg = IA_Engine::phpbb_db();
    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 3306);
    $db   = (string)($cfg['name'] ?? '');
    $user = (string)($cfg['user'] ?? '');
    $pass = (string)($cfg['pass'] ?? '');
    $prefix = (string)($cfg['prefix'] ?? 'phpbb_');
    if ($host === '' || $db === '' || $user === '' || $prefix === '') return;
    $table = $prefix . 'users';

    try {
      $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      // Ensure username_clean uniqueness
      $st = $pdo->prepare("SELECT user_id FROM {$table} WHERE username_clean=:u AND user_id<>:id LIMIT 1");
      $st->execute([':u' => $username_clean, ':id' => $phpbb_user_id]);
      if ($st->fetch()) return;

      $st = $pdo->prepare("UPDATE {$table} SET username=:n, username_clean=:c WHERE user_id=:id");
      $st->execute([':n' => $username, ':c' => $username_clean, ':id' => $phpbb_user_id]);
    } catch (Throwable $e) {
      // swallow
    }
  }

  private function phpbb_update_email(int $phpbb_user_id, string $email): void {
    if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'phpbb_db')) return;
    $cfg = IA_Engine::phpbb_db();
    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 3306);
    $db   = (string)($cfg['name'] ?? '');
    $user = (string)($cfg['user'] ?? '');
    $pass = (string)($cfg['pass'] ?? '');
    $prefix = (string)($cfg['prefix'] ?? 'phpbb_');
    if ($host === '' || $db === '' || $user === '' || $prefix === '') return;
    $table = $prefix . 'users';
    try {
      $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      $st = $pdo->prepare("SELECT user_id FROM {$table} WHERE user_email=:e AND user_id<>:id LIMIT 1");
      $st->execute([':e' => $email, ':id' => $phpbb_user_id]);
      if ($st->fetch()) return;

      $st = $pdo->prepare("UPDATE {$table} SET user_email=:e WHERE user_id=:id");
      $st->execute([':e' => $email, ':id' => $phpbb_user_id]);
    } catch (Throwable $e) {}
  }

  private function peertube_update_username(int $peertube_user_id, string $username_clean): void {
    if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'peertube_db')) return;
    $cfg = IA_Engine::peertube_db();
    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 5432);
    $db   = (string)($cfg['name'] ?? '');
    $user = (string)($cfg['user'] ?? '');
    $pass = (string)($cfg['pass'] ?? '');
    if ($host === '' || $db === '' || $user === '') return;
    try {
      $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      $st = $pdo->prepare('SELECT id FROM public."user" WHERE username=:u AND id<>:id LIMIT 1');
      $st->execute([':u' => $username_clean, ':id' => $peertube_user_id]);
      if ($st->fetch()) return;
      $st = $pdo->prepare('UPDATE public."user" SET username=:u, "updatedAt"=NOW() WHERE id=:id');
      $st->execute([':u' => $username_clean, ':id' => $peertube_user_id]);
    } catch (Throwable $e) {}
  }

  private function peertube_update_email(int $peertube_user_id, string $email): void {
    if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'peertube_db')) return;
    $cfg = IA_Engine::peertube_db();
    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 5432);
    $db   = (string)($cfg['name'] ?? '');
    $user = (string)($cfg['user'] ?? '');
    $pass = (string)($cfg['pass'] ?? '');
    if ($host === '' || $db === '' || $user === '') return;
    try {
      $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
      $st = $pdo->prepare('SELECT id FROM public."user" WHERE email=:e AND id<>:id LIMIT 1');
      $st->execute([':e' => $email, ':id' => $peertube_user_id]);
      if ($st->fetch()) return;
      $st = $pdo->prepare('UPDATE public."user" SET email=:e, "emailVerified"=false, "updatedAt"=NOW() WHERE id=:id');
      $st->execute([':e' => $email, ':id' => $peertube_user_id]);
    } catch (Throwable $e) {}
  }


  public function ajax_password_reset(): void {
    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => 'Not logged in.'], 401);
    }

    $nonce = (string)($_POST['nonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'ia_connect_nonce')) {
      wp_send_json_error(['message' => 'Bad nonce. Refresh and try again.'], 403);
    }

    $u = wp_get_current_user();
    if (!$u || empty($u->ID)) {
      wp_send_json_error(['message' => 'User not available.'], 400);
    }

    // Send WordPress reset email to the currently verified email address.
    // Return generic success to avoid leaking info.
    $login = (string)($u->user_login ?? '');
    if ($login !== '') {
      // retrieve_password() exists in core and sends the email.
      @retrieve_password($login);
    }

    wp_send_json_success(['message' => 'If your account is eligible, a reset email has been sent.']);
  }

  public function after_password_reset_sync_phpbb($user, string $new_pass): void {
    try {
      if (!$user || empty($user->ID) || $new_pass === '') return;

      $wp_user_id = (int)$user->ID;
      $phpbb_id = (int)get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
      if ($phpbb_id <= 0) return;

      $this->phpbb_update_password($phpbb_id, $new_pass);
    } catch (Throwable $e) {
      // Silent: do not block WP password reset flow.
    }
  }

  private function phpbb_update_password(int $phpbb_user_id, string $password): void {
    if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'phpbb_db')) return;
    $cfg = IA_Engine::phpbb_db();
    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 3306);
    $db   = (string)($cfg['name'] ?? '');
    $user = (string)($cfg['user'] ?? '');
    $pass = (string)($cfg['pass'] ?? '');
    $prefix = (string)($cfg['prefix'] ?? 'phpbb_');
    if ($host === '' || $db === '' || $user === '' || $prefix === '') return;

    $table_users = $prefix . 'users';

    // Use PASSWORD_DEFAULT so phpBB can verify via password_verify for modern hashes.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!$hash) return;

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("UPDATE {$table_users} SET user_password = :hash WHERE user_id = :uid");
    $stmt->execute([':hash' => $hash, ':uid' => $phpbb_user_id]);
  }
}
