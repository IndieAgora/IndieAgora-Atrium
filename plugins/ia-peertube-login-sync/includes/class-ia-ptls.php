<?php
if (!defined('ABSPATH')) exit;

final class IA_PTLS {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_nopriv_ia_ptls_login', [$this, 'ajax_login']);
        add_action('wp_ajax_ia_ptls_login', [$this, 'ajax_login']);

        // Cron schedule support + runner
        add_filter('cron_schedules', [$this, 'cron_schedules']);
        add_action('ia_ptls_cron_scan', [$this, 'cron_scan']);

        // Keep cron scheduling aligned with settings (only reschedules when settings change).
        add_action('init', [$this, 'maybe_reschedule_cron'], 20);
    }

    /**
     * Dynamic schedule: every N minutes (set in admin).
     */
    public function cron_schedules(array $schedules): array {
        $minutes = (int)get_option('ia_ptls_cron_minutes', 60);
        if ($minutes < 5) $minutes = 5;
        if ($minutes > 1440) $minutes = 1440;

        $schedules['ia_ptls_every_n_minutes'] = [
            'interval' => $minutes * 60,
            'display'  => 'IA PTLS every ' . $minutes . ' minutes',
        ];

        return $schedules;
    }

    /**
     * Only reschedule when relevant options changed (cheap hash check).
     */
    public function maybe_reschedule_cron(): void {
        $enabled = (get_option('ia_ptls_enable_cron', '0') === '1');

        $minutes = (int)get_option('ia_ptls_cron_minutes', 60);
        if ($minutes < 5) $minutes = 5;
        if ($minutes > 1440) $minutes = 1440;

        $hash = $enabled ? ('on:' . $minutes) : 'off';
        $prev = (string)get_option('ia_ptls_cron_hash', '');

        if ($hash === $prev) {
            // If enabled but hook is missing (e.g. after cache flush), re-add.
            if ($enabled && !wp_next_scheduled('ia_ptls_cron_scan')) {
                wp_schedule_event(time() + 120, 'ia_ptls_every_n_minutes', 'ia_ptls_cron_scan');
            }
            return;
        }

        update_option('ia_ptls_cron_hash', $hash, false);
        $this->reschedule_cron();
    }

    /**
     * Clear + schedule according to current settings.
     */
    public function reschedule_cron(): void {
        // Clear existing
        $ts = wp_next_scheduled('ia_ptls_cron_scan');
        while ($ts) {
            wp_unschedule_event($ts, 'ia_ptls_cron_scan');
            $ts = wp_next_scheduled('ia_ptls_cron_scan');
        }

        if (get_option('ia_ptls_enable_cron', '0') !== '1') return;

        if (!wp_next_scheduled('ia_ptls_cron_scan')) {
            wp_schedule_event(time() + 120, 'ia_ptls_every_n_minutes', 'ia_ptls_cron_scan');
        }
    }

    /**
     * PeerTube login endpoint.
     * NOTE: This plugin only supports LOCAL PeerTube users (on your instance DB/API).
     *
     * Request: {identifier: username|email, password: string}
     * Response: {ok: true, redirect: url}
     */
    public function ajax_login() {
        check_ajax_referer('ia_ptls_login_nonce', 'nonce');

        if (!class_exists('IA_Engine')) {
            wp_send_json_error(['message' => 'IA Engine is not active.'], 500);
        }
        if (!class_exists('IA_Auth')) {
            wp_send_json_error(['message' => 'IA Auth is not active.'], 500);
        }

        $id = sanitize_text_field($_POST['identifier'] ?? '');
        $pw = (string)($_POST['password'] ?? '');
        if ($id === '' || $pw === '') {
            wp_send_json_error(['message' => 'Missing username/email or password.'], 400);
        }

        // 1) Authenticate against PeerTube API (preferred).
        $pt = $this->peertube_password_grant($id, $pw);
        if (empty($pt['ok'])) {
            wp_send_json_error(['message' => $pt['message'] ?? 'PeerTube login failed.'], 403);
        }

        $me = $this->peertube_me($pt['access_token']);
        if (empty($me['ok'])) {
            wp_send_json_error(['message' => $me['message'] ?? 'Could not fetch PeerTube user profile.'], 500);
        }

        $pt_user = $me['user'];
        $pt_user_id = (int)($pt_user['id'] ?? 0);
        $pt_username = (string)($pt_user['username'] ?? '');
        $pt_email = (string)($pt_user['email'] ?? '');

        if (!$pt_user_id || $pt_username === '' || $pt_email === '') {
            wp_send_json_error(['message' => 'PeerTube user data incomplete.'], 500);
        }

        // 2) Ensure canonical phpBB user exists (by email match, else create).
        $phpbb = $this->phpbb_find_or_create_user($pt_username, $pt_email);
        if (empty($phpbb['ok'])) {
            wp_send_json_error(['message' => $phpbb['message'] ?? 'Could not create/link phpBB user.'], 500);
        }
        $phpbb_user = $phpbb['user'];

        // 3) Ensure WP shadow user exists + link mapping (write into ia-auth table).
        $wp_user_id = $this->ensure_wp_shadow_user($phpbb_user, $pt_user_id);
        if (!$wp_user_id) {
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
        }

        // 4) Log the WP user in.
        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        /**
         * Atrium shadow-session logins bypass wp_signon/wp_authenticate.
         * The per-user PeerTube token mint plugin relies on receiving the
         * plaintext password at login time via this action.
         */
        do_action('ia_pt_user_password', (int)$wp_user_id, (string)$pw, (string)$id);
        // Hard-call capture to avoid reliance on action wiring in shadow-session stacks.
        if (class_exists('IA_PT_Password_Capture') && method_exists('IA_PT_Password_Capture', 'capture_for_user')) {
            try { IA_PT_Password_Capture::capture_for_user((int)$wp_user_id, (string)$pw, (string)$id); } catch (Throwable $e) { /* ignore */ }
        }


        // Opportunistically mint/store per-user token now if the helper is available.
        if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
            try { IA_PeerTube_Token_Helper::get_token_for_current_user(); } catch (Throwable $e) { /* ignore */ }
        }

        wp_send_json_success([
            'ok' => true,
            'redirect' => home_url('/'),
        ]);
    }

    /**
     * Admin/cron scan:
     * - Always stores counts
     * - If enabled, auto-applies mapping in a batch so users can log in
     */
    public function cron_scan() {
        // Scan first
        $scan = $this->scan_local_peertube_users();

        update_option('ia_ptls_last_scan', [
            'ts' => time(),
            'ok' => !empty($scan['ok']),
            'counts' => $scan['counts'] ?? [],
            'message' => $scan['message'] ?? '',
        ], false);

        // Auto-apply mapping if enabled
        if (get_option('ia_ptls_auto_apply', '0') !== '1') {
            return;
        }
        if (empty($scan['ok']) || empty($scan['rows']) || !is_array($scan['rows'])) {
            return;
        }

        $batch = (int)get_option('ia_ptls_batch_size', 50);
        if ($batch < 1) $batch = 1;
        if ($batch > 500) $batch = 500;

        $result = $this->apply_sync_from_scan_rows($scan['rows'], $batch);

        // Re-scan for updated counts (so Status reflects the new mapping)
        $scan2 = $this->scan_local_peertube_users();

        update_option('ia_ptls_last_scan', [
            'ts' => time(),
            'ok' => !empty($scan2['ok']),
            'counts' => $scan2['counts'] ?? [],
            'message' => sprintf(
                'Auto-sync ran. Processed %d (linked %d, skipped %d).',
                (int)($result['processed'] ?? 0),
                (int)($result['linked'] ?? 0),
                (int)($result['skipped'] ?? 0)
            ),
        ], false);
    }

    /**
     * Apply mapping for unmapped users from a scan rows array.
     * Returns: ['processed'=>int,'linked'=>int,'skipped'=>int]
     */
    private function apply_sync_from_scan_rows(array $rows, int $batch): array {
        $processed = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            if ($processed >= $batch) break;

            // Already mapped? skip
            if (!empty($r['mapped_phpbb_user_id'])) {
                $skipped++;
                continue;
            }

            $pt_username = (string)($r['username'] ?? '');
            $pt_email = (string)($r['email'] ?? '');
            $pt_user_id = (int)($r['id'] ?? 0);

            if ($pt_email === '' || $pt_user_id <= 0) {
                $skipped++;
                continue;
            }

            // Optional safety: do not auto-map blocked accounts
            if (!empty($r['blocked'])) {
                $skipped++;
                continue;
            }

            // Optional safety: only map email-verified accounts.
            // Controlled by admin setting "Require email verified".
            $require_verified = (get_option('ia_ptls_require_verified', '0') === '1');
            if ($require_verified && isset($r['emailVerified']) && (int)$r['emailVerified'] !== 1) {
                $skipped++;
                continue;
            }

            $phpbb = $this->phpbb_find_or_create_user($pt_username, $pt_email);
            if (!empty($phpbb['ok']) && !empty($phpbb['user'])) {
                $wp = (int)$this->ensure_wp_shadow_user($phpbb['user'], $pt_user_id);
                if ($wp > 0) $linked++;
                else $skipped++;
            } else {
                $skipped++;
            }

            $processed++;
        }

        return ['processed' => $processed, 'linked' => $linked, 'skipped' => $skipped];
    }

    // -------------------------
    // PeerTube API helpers
    // -------------------------

    private function peertube_api_base(): array {
        $cfg = IA_Engine::get('peertube_api');
        $base = rtrim((string)($cfg['internal_base'] ?? ''), '/');
        $client_id = (string)($cfg['oauth_client_id'] ?? '');
        $client_secret = (string)($cfg['oauth_client_secret'] ?? '');
        return [$base, $client_id, $client_secret];
    }

    private function peertube_password_grant(string $identifier, string $password): array {
        [$base, $client_id, $client_secret] = $this->peertube_api_base();
        if ($base === '' || $client_id === '' || $client_secret === '') {
            return ['ok' => false, 'message' => 'PeerTube API credentials are not configured in IA Engine.'];
        }

        $url = $base . '/api/v1/users/token';
        $body = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'password',
            'username' => $identifier,
            'password' => $password,
        ];

        $res = wp_remote_post($url, [
            'timeout' => 12,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => $body,
        ]);

        if (is_wp_error($res)) {
            return ['ok' => false, 'message' => $res->get_error_message()];
        }

        $code = (int)wp_remote_retrieve_response_code($res);
        $json = json_decode((string)wp_remote_retrieve_body($res), true);

        if ($code >= 400 || empty($json['access_token'])) {
            $msg = $json['error_description'] ?? $json['error'] ?? 'Invalid credentials.';
            return ['ok' => false, 'message' => $msg];
        }

        return ['ok' => true, 'access_token' => (string)$json['access_token'], 'raw' => $json];
    }

    private function peertube_me(string $access_token): array {
        [$base] = $this->peertube_api_base();
        if ($base === '') return ['ok' => false, 'message' => 'PeerTube API base URL not configured.'];

        $url = $base . '/api/v1/users/me';
        $res = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);
        if (is_wp_error($res)) {
            return ['ok' => false, 'message' => $res->get_error_message()];
        }
        $code = (int)wp_remote_retrieve_response_code($res);
        $json = json_decode((string)wp_remote_retrieve_body($res), true);
        if ($code >= 400 || !is_array($json)) {
            return ['ok' => false, 'message' => 'PeerTube /users/me failed.'];
        }

        // PeerTube returns a "user" object or the fields directly depending on version.
        $user = $json['user'] ?? $json;
        return ['ok' => true, 'user' => $user, 'raw' => $json];
    }

    // -------------------------
    // PeerTube DB scan
    // -------------------------

    public function scan_local_peertube_users(): array {
        if (!class_exists('IA_Engine')) return ['ok' => false, 'message' => 'IA Engine not active'];

        $pt = IA_Engine::get('peertube');
        if (empty($pt['host']) || empty($pt['name']) || empty($pt['user'])) {
            return ['ok' => false, 'message' => 'PeerTube DB credentials not configured in IA Engine.'];
        }

        $conn = @pg_connect(sprintf(
            "host=%s port=%d dbname=%s user=%s password=%s",
            $pt['host'],
            (int)($pt['port'] ?? 5432),
            $pt['name'],
            $pt['user'],
            $pt['password'] ?? ''
        ));

        if (!$conn) {
            return ['ok' => false, 'message' => 'Could not connect to PeerTube Postgres.'];
        }

        // PeerTube table name is quoted ("user").
        // pg_fetch_assoc typically returns booleans as 't'/'f' strings.
        $sql = 'SELECT id, username, email, blocked, "emailVerified" FROM public."user" ORDER BY id ASC';
        $result = @pg_query($conn, $sql);
        if (!$result) {
            @pg_close($conn);
            return ['ok' => false, 'message' => 'Query failed for PeerTube users table.'];
        }

        global $wpdb;
        $map = $wpdb->prefix . 'ia_identity_map';

        $total = 0;
        $mapped = 0;
        $unmapped = 0;
        $missing_email = 0;
        $blocked_count = 0;
        $unverified = 0;

        $rows = [];
        while ($r = pg_fetch_assoc($result)) {
            $total++;
            $pt_user_id = (int)($r['id'] ?? 0);
            $email = (string)($r['email'] ?? '');
            $username = (string)($r['username'] ?? '');
            // booleans can come back as 't'/'f' or 1/0 depending on postgres settings.
            $blocked_raw = $r['blocked'] ?? null;
            $blocked = ($blocked_raw === true || $blocked_raw === 1 || $blocked_raw === '1' || $blocked_raw === 't' || $blocked_raw === 'true');

            $ev_raw = $r['emailVerified'] ?? null;
            $email_verified = ($ev_raw === true || $ev_raw === 1 || $ev_raw === '1' || $ev_raw === 't' || $ev_raw === 'true');

            if ($email === '') $missing_email++;
            if ($blocked) $blocked_count++;
            if (!$email_verified) $unverified++;

            $exists = $wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM $map WHERE peertube_user_id=%d LIMIT 1", $pt_user_id));
            if ($exists) {
                $mapped++;
            } else {
                $unmapped++;
            }

            $rows[] = [
                'id' => $pt_user_id,
                'username' => $username,
                'email' => $email,
                'blocked' => $blocked ? 1 : 0,
                'emailVerified' => $email_verified ? 1 : 0,
                'mapped_phpbb_user_id' => $exists ? (int)$exists : 0,
            ];
        }

        @pg_free_result($result);
        @pg_close($conn);

        return [
            'ok' => true,
            'counts' => [
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => $unmapped,
                'missing_email' => $missing_email,
                'blocked' => $blocked_count,
                'unverified' => $unverified,
            ],
            'rows' => $rows,
        ];
    }

    // -------------------------
    // phpBB DB helpers
    // -------------------------

    private function phpbb_conn() {
        $cfg = IA_Engine::get('phpbb');
        if (empty($cfg['host']) || empty($cfg['name']) || empty($cfg['user'])) return null;

        $mysqli = @new mysqli(
            $cfg['host'],
            $cfg['user'],
            $cfg['password'] ?? '',
            $cfg['name'],
            (int)($cfg['port'] ?? 3306)
        );

        if ($mysqli->connect_errno) return null;
        $mysqli->set_charset('utf8mb4');
        return [$mysqli, $cfg];
    }

    private function phpbb_find_user_by_email($mysqli, string $prefix, string $email): ?array {
        $sql = "SELECT user_id, username, username_clean, user_email FROM {$prefix}users WHERE user_email=? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    private function phpbb_registered_group_id($mysqli, string $prefix): int {
        // Try canonical group name first.
        $sql = "SELECT group_id FROM {$prefix}groups WHERE group_name='REGISTERED' LIMIT 1";
        $res = $mysqli->query($sql);
        if ($res && ($row = $res->fetch_assoc()) && !empty($row['group_id'])) return (int)$row['group_id'];

        // Fallback to the default from schema (commonly 3).
        return 3;
    }

    private function phpbb_create_user($mysqli, string $prefix, string $username, string $email): ?array {
        $username = trim($username);
        if ($username === '') $username = 'peertubeuser';

        // phpBB username_clean is typically lowercase with spaces replaced; keep simple.
        $username_clean = strtolower(preg_replace('/\s+/', '', $username));
        $username_clean = preg_replace('/[^a-z0-9_\-\.]/', '', $username_clean);
        if ($username_clean === '') $username_clean = 'peertubeuser';

        $group_id = $this->phpbb_registered_group_id($mysqli, $prefix);
        $regdate = time();

        // phpBB user_password must be non-empty; use a random string.
        $rand = wp_generate_password(32, true, true);

        $sql = "INSERT INTO {$prefix}users
            (user_type, group_id, user_permissions, user_ip, user_regdate, username, username_clean, user_password, user_email, user_sig)
            VALUES (0, ?, '', '', ?, ?, ?, ?, ?, '')";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('iissss', $group_id, $regdate, $username, $username_clean, $rand, $email);
        $ok = $stmt->execute();
        $new_id = $ok ? (int)$stmt->insert_id : 0;
        $stmt->close();

        if (!$new_id) return null;

        return [
            'user_id' => $new_id,
            'username' => $username,
            'username_clean' => $username_clean,
            'user_email' => $email,
        ];
    }

    public function phpbb_find_or_create_user(string $pt_username, string $pt_email): array {
        $conn = $this->phpbb_conn();
        if (!$conn) return ['ok' => false, 'message' => 'Could not connect to phpBB DB (check IA Engine config).'];

        [$mysqli, $cfg] = $conn;
        $prefix = (string)($cfg['prefix'] ?? 'phpbb_');

        $existing = $this->phpbb_find_user_by_email($mysqli, $prefix, $pt_email);
        if ($existing) {
            $mysqli->close();
            return ['ok' => true, 'user' => [
                'user_id' => (int)$existing['user_id'],
                'username' => (string)$existing['username'],
                'username_clean' => (string)$existing['username_clean'],
                'user_email' => (string)$existing['user_email'],
            ], 'created' => false];
        }

        $created = $this->phpbb_create_user($mysqli, $prefix, $pt_username, $pt_email);
        $mysqli->close();

        if (!$created) return ['ok' => false, 'message' => 'Failed to create phpBB user from PeerTube account.'];

        return ['ok' => true, 'user' => $created, 'created' => true];
    }

    // -------------------------
    // WP shadow + mapping
    // -------------------------

    public function ensure_wp_shadow_user(array $phpbb_user, int $peertube_user_id): int {
        global $wpdb;

        $map = $wpdb->prefix . 'ia_identity_map';

        $phpbb_user_id = (int)($phpbb_user['user_id'] ?? 0);
        if (!$phpbb_user_id) return 0;

        // Ensure mapping table exists (created by IA Auth). If not, bail.
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map));
        if (!$exists) return 0;

        // If mapping already has wp_user_id, reuse it.
        $row = $wpdb->get_row($wpdb->prepare("SELECT wp_user_id FROM $map WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id), ARRAY_A);
        $wp_user_id = isset($row['wp_user_id']) ? (int)$row['wp_user_id'] : 0;
        if ($wp_user_id && get_user_by('id', $wp_user_id)) {
            // Update peertube link
            $wpdb->update($map, [
                'peertube_user_id' => $peertube_user_id,
                'status' => 'linked',
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => '',
            ], ['phpbb_user_id' => $phpbb_user_id]);
            return $wp_user_id;
        }

        // Create (or find) WP user by email.
        $email = (string)($phpbb_user['user_email'] ?? '');
        $username_clean = (string)($phpbb_user['username_clean'] ?? '');
        $login = $username_clean !== '' ? $username_clean : ('phpbb_' . $phpbb_user_id);

        $user = $email ? get_user_by('email', $email) : false;
        if ($user) {
            $wp_user_id = (int)$user->ID;
        } else {
            // Ensure unique user_login
            $base = sanitize_user($login, true);
            if ($base === '') $base = 'user' . $phpbb_user_id;
            $candidate = $base;
            $i = 0;
            while (username_exists($candidate)) {
                $i++;
                $candidate = $base . $i;
                if ($i > 50) break;
            }

            $wp_user_id = (int)wp_insert_user([
                'user_login' => $candidate,
                'user_pass'  => wp_generate_password(32, true, true),
                'user_email' => $email ?: ('ptls-' . $phpbb_user_id . '@invalid.local'),
                'display_name' => (string)($phpbb_user['username'] ?? $candidate),
                'role' => $this->wp_shadow_role(),
            ]);
            if (is_wp_error($wp_user_id) || !$wp_user_id) {
                return 0;
            }
        }

        // Upsert identity row
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'phpbb_user_id' => $phpbb_user_id,
            'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
            'email' => (string)($phpbb_user['user_email'] ?? ''),
            'wp_user_id' => $wp_user_id,
            'peertube_user_id' => $peertube_user_id,
            'status' => 'linked',
            'updated_at' => $now,
            'last_error' => '',
        ];

        $existing = $wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM $map WHERE phpbb_user_id=%d", $phpbb_user_id));
        if ($existing) {
            $wpdb->update($map, $data, ['phpbb_user_id' => $phpbb_user_id]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($map, $data);
        }

        return (int)$wp_user_id;
    }

    private function wp_shadow_role(): string {
        $opt = get_option('ia_auth_options_v1', []);
        $role = is_array($opt) ? (string)($opt['wp_shadow_role'] ?? 'subscriber') : 'subscriber';
        return $role ?: 'subscriber';
    }
}