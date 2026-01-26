<?php
if (!defined('ABSPATH')) { exit; }

class IA_Auth_DB {

    private $log;
    private $wpdb;

    public function __construct($logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->log = $logger;
    }

    private function table_exists(string $table_name): bool {
        // $table_name should be the fully-prefixed table e.g. 8LkWnv_ia_identity_map
        $like = $this->wpdb->esc_like($table_name);
        $found = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $like));
        return !empty($found);
    }

    private function maybe_install(): void {
        // If the identity map table is missing, we consider the whole schema missing/partial.
        $identity = $this->wpdb->prefix . 'ia_identity_map';
        if (!$this->table_exists($identity)) {
            $this->install();
        }
    }

    public function install() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset  = $this->wpdb->get_charset_collate();
        $identity = $this->wpdb->prefix . 'ia_identity_map';
        $tokens   = $this->wpdb->prefix . 'ia_peertube_tokens';
        $queue    = $this->wpdb->prefix . 'ia_auth_queue';
        $audit    = $this->wpdb->prefix . 'ia_auth_audit';

        $sql1 = "CREATE TABLE $identity (
            phpbb_user_id INT(10) UNSIGNED NOT NULL,
            phpbb_username_clean VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(100) NOT NULL DEFAULT '',
            wp_user_id BIGINT(20) UNSIGNED NULL,
            peertube_user_id INT NULL,
            peertube_account_id INT NULL,
            peertube_actor_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'partial',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_error VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (phpbb_user_id),
            KEY email (email),
            KEY wp_user_id (wp_user_id),
            KEY status (status)
        ) $charset;";

        $sql2 = "CREATE TABLE $tokens (
            phpbb_user_id INT(10) UNSIGNED NOT NULL,
            access_token_enc LONGTEXT NULL,
            refresh_token_enc LONGTEXT NULL,
            expires_at_utc DATETIME NULL,
            scope VARCHAR(255) NULL,
            token_source VARCHAR(30) NOT NULL DEFAULT 'password_grant',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (phpbb_user_id)
        ) $charset;";

        $sql3 = "CREATE TABLE $queue (
            job_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phpbb_user_id INT(10) UNSIGNED NOT NULL,
            type VARCHAR(40) NOT NULL,
            payload_json LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            next_run_at DATETIME NULL,
            last_error VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (job_id),
            KEY status (status),
            KEY phpbb_user_id (phpbb_user_id),
            KEY next_run_at (next_run_at)
        ) $charset;";

        $sql4 = "CREATE TABLE $audit (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(80) NOT NULL,
            phpbb_user_id INT(10) UNSIGNED NULL,
            actor_wp_user_id BIGINT(20) UNSIGNED NULL,
            details_json LONGTEXT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY action (action),
            KEY phpbb_user_id (phpbb_user_id),
            KEY actor_wp_user_id (actor_wp_user_id)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }

    /**
     * Create or reuse a WP shadow user for a phpBB user row.
     *
     * IMPORTANT (production semantics):
     * - Match-first: if a WP user already exists for this person (email/username), LINK it.
     * - Only create a new WP user if no match exists.
     * - Do NOT introduce phpBB IDs/prefixes into user_login for collision handling.
     *
     * @param array $phpbb_user phpBB row.
     * @param array $opt plugin options.
     * @param ?bool $created_out set to true if we created a new WP user, false if reused.
     *
     * @return int WP user ID or 0.
     */
    public function ensure_wp_shadow_user(array $phpbb_user, array $opt, ?bool &$created_out = null): int {
        $email          = (string)($phpbb_user['user_email'] ?? '');
        $username_clean = (string)($phpbb_user['username_clean'] ?? '');
        $username       = (string)($phpbb_user['username'] ?? '');

        $phpbb_user_id = (int)($phpbb_user['user_id'] ?? 0);
        if (!$phpbb_user_id) return 0;

        $created = false;

        // Try to find an existing user by meta mapping.
        $existing = $this->find_wp_user_by_phpbb_id($phpbb_user_id);
        if ($existing) {
            if ($created_out !== null) $created_out = false;
            return (int)$existing;
        }

        // Otherwise try by email or username (depending on policy).
        $match_policy = (string)($opt['match_policy'] ?? 'email_then_username');

        $wp_user_id = 0;
        if ($match_policy === 'email_then_username') {
            if ($email !== '') {
                $u = get_user_by('email', $email);
                if ($u) $wp_user_id = (int)$u->ID;
            }
            if (!$wp_user_id && $username_clean !== '') {
                $u = get_user_by('login', $username_clean);
                if ($u) $wp_user_id = (int)$u->ID;
            }
            if (!$wp_user_id && $username !== '') {
                $u = get_user_by('login', $username);
                if ($u) $wp_user_id = (int)$u->ID;
            }
        } else {
            if ($username_clean !== '') {
                $u = get_user_by('login', $username_clean);
                if ($u) $wp_user_id = (int)$u->ID;
            }
            if (!$wp_user_id && $username !== '') {
                $u = get_user_by('login', $username);
                if ($u) $wp_user_id = (int)$u->ID;
            }
            if (!$wp_user_id && $email !== '') {
                $u = get_user_by('email', $email);
                if ($u) $wp_user_id = (int)$u->ID;
            }
        }

        // If none found, create a new WP user (shadow user).
        if (!$wp_user_id) {
            $login = $username_clean !== '' ? $username_clean : ($username !== '' ? $username : 'phpbb_' . $phpbb_user_id);

            // Ensure uniqueness
            $base = sanitize_user($login, true);
            if ($base === '') $base = 'phpbb_' . $phpbb_user_id;

            $candidate = $base;
            $i = 1;
            while (username_exists($candidate)) {
                $candidate = $base . '_' . $i;
                $i++;
                if ($i > 2000) {
                    $this->log->error('shadow_user_username_collision', ['base' => $base, 'phpbb_user_id' => $phpbb_user_id]);
                    return 0;
                }
            }

            // Email: only use if unique; otherwise use a deterministic placeholder.
            $create_email = $email !== '' ? $email : '';
            if ($create_email === '' || email_exists($create_email)) {
                $create_email = 'phpbb+' . $phpbb_user_id . '@example.invalid';
            }

            $pass = wp_generate_password(32, true, true); // never used for login; phpBB is authoritative
            $new_id = wp_create_user($candidate, $pass, $create_email);
            if (is_wp_error($new_id)) {
                $this->log->error('shadow_user_create_failed', ['phpbb_user_id' => $phpbb_user_id, 'err' => $new_id->get_error_message()]);
                return 0;
            }
            $wp_user_id = (int)$new_id;
            $created = true;

            // Set role (default subscriber)
            $role = (string)($opt['wp_shadow_role'] ?? 'subscriber');
            $u = new WP_User($wp_user_id);
            if ($role !== '') $u->set_role($role);
        }

        // Map phpbb user id. Only mark ia_shadow_user if we created the WP user here.
        if ($created) {
            update_user_meta($wp_user_id, 'ia_shadow_user', '1');
        }
        update_user_meta($wp_user_id, 'ia_phpbb_user_id', (string)$phpbb_user_id);

        if ($created_out !== null) $created_out = $created;

        return (int)$wp_user_id;
    }

	/**
	 * Find the linked WP user ID for a phpBB user ID (via usermeta ia_phpbb_user_id).
	 *
	 * Public because IA_Auth uses it during verification/approval flows.
	 */
	public function find_wp_user_by_phpbb_id(int $phpbb_user_id): int {
        $meta_key = 'ia_phpbb_user_id';
        $found = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT user_id FROM {$this->wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s LIMIT 1",
            $meta_key,
            (string)$phpbb_user_id
        ));
        return $found ? (int)$found : 0;
    }

    /**
     * Lookup the identity map row for a given WordPress user ID.
     * Returns associative array or null if not found.
     */
    public function get_identity_by_wp_user_id(int $wp_user_id): ?array {
        $wp_user_id = (int) $wp_user_id;
        if ($wp_user_id <= 0) return null;

        $this->maybe_install();
        $table = $this->wpdb->prefix . 'ia_identity_map';

        // Primary lookup by wp_user_id if present.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE wp_user_id=%d LIMIT 1", $wp_user_id),
            ARRAY_A
        );
        if (is_array($row) && !empty($row)) return $row;

        // Fallback: resolve phpbb_user_id from usermeta then look up by primary key.
        $phpbb_user_id = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
        if ($phpbb_user_id <= 0) return null;

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id),
            ARRAY_A
        );
        return is_array($row) && !empty($row) ? $row : null;
    }

    public function upsert_identity(array $row) {
        $this->maybe_install();

        $table = $this->wpdb->prefix . 'ia_identity_map';

        $phpbb_user_id = (int)($row['phpbb_user_id'] ?? 0);
        if (!$phpbb_user_id) return false;

        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT phpbb_user_id FROM $table WHERE phpbb_user_id=%d", $phpbb_user_id),
            ARRAY_A
        );

        $data = [
            'phpbb_username_clean' => (string)($row['phpbb_username_clean'] ?? ''),
            'email'                => (string)($row['email'] ?? ''),
            'wp_user_id'           => isset($row['wp_user_id']) ? (int)$row['wp_user_id'] : null,
            'peertube_user_id'     => isset($row['peertube_user_id']) ? (int)$row['peertube_user_id'] : null,
            'peertube_account_id'  => isset($row['peertube_account_id']) ? (int)$row['peertube_account_id'] : null,
            'peertube_actor_id'    => isset($row['peertube_actor_id']) ? (int)$row['peertube_actor_id'] : null,
            'status'               => (string)($row['status'] ?? 'linked'),
            'updated_at'           => gmdate('Y-m-d H:i:s'),
            'last_error'           => (string)($row['last_error'] ?? ''),
        ];

        if ($existing) {
            $this->wpdb->update($table, $data, ['phpbb_user_id' => $phpbb_user_id]);
        } else {
            $data['phpbb_user_id'] = $phpbb_user_id;
            $data['created_at']    = gmdate('Y-m-d H:i:s');
            $this->wpdb->insert($table, $data);
        }

        return true;
    }

    public function list_identities($limit=200, $offset=0, $search='') {
        $this->maybe_install();

        $table = $this->wpdb->prefix . 'ia_identity_map';

        $sql = "SELECT * FROM $table";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE phpbb_username_clean LIKE %s OR email LIKE %s";
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY phpbb_user_id ASC LIMIT %d OFFSET %d";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);
    }

    public function count_identities(): array {
        $this->maybe_install();

        $table = $this->wpdb->prefix . 'ia_identity_map';

        $linked   = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='linked'");
        $partial  = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='partial'");
        $disabled = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='disabled'");

        return [
            'linked'   => $linked,
            'partial'  => $partial,
            'disabled' => $disabled,
        ];
    }

    public function store_peertube_token(int $phpbb_user_id, array $row): bool {
        $this->maybe_install();

        if ($phpbb_user_id <= 0) return false;

        $table = $this->wpdb->prefix . 'ia_peertube_tokens';

        $data = [
            'access_token_enc'  => (string)($row['access_token_enc'] ?? ''),
            'refresh_token_enc' => (string)($row['refresh_token_enc'] ?? ''),
            'expires_at_utc'    => !empty($row['expires_at_utc']) ? (string)$row['expires_at_utc'] : null,
            'scope'             => (string)($row['scope'] ?? ''),
            'token_source'      => (string)($row['token_source'] ?? 'password_grant'),
            'updated_at'        => gmdate('Y-m-d H:i:s'),
        ];

        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT phpbb_user_id FROM $table WHERE phpbb_user_id=%d", $phpbb_user_id)
        );

        $ok = false;
        if ($exists) {
            $r = $this->wpdb->update($table, $data, ['phpbb_user_id' => $phpbb_user_id]);
            // update() returns false on error, 0 if no change, >0 if updated.
            $ok = ($r !== false);
        } else {
            $data['phpbb_user_id'] = $phpbb_user_id;
            $r = $this->wpdb->insert($table, $data);
            $ok = (bool)$r;
        }

        if (!$ok) {
            $this->log->error('store_peertube_token_failed', [
                'phpbb_user_id' => $phpbb_user_id,
                'table' => $table,
                'last_error' => (string)$this->wpdb->last_error,
            ]);
            return false;
        }

        return true;
    }

    public function insert_audit(string $action, ?int $phpbb_user_id, ?int $actor_wp_user_id, array $details = []): bool {
        $this->maybe_install();

        $table = $this->wpdb->prefix . 'ia_auth_audit';

        $ok = $this->wpdb->insert($table, [
            'action'         => $action,
            'phpbb_user_id'  => $phpbb_user_id,
            'actor_wp_user_id' => $actor_wp_user_id,
            'details_json'   => wp_json_encode($details),
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);

        return (bool)$ok;
    }

    public function list_audit(int $limit = 50): array {
        $this->maybe_install();

        $table = $this->wpdb->prefix . 'ia_auth_audit';
        $limit = max(1, min(200, $limit));

        return $this->wpdb->get_results(
            "SELECT * FROM $table ORDER BY id DESC LIMIT " . (int)$limit,
            ARRAY_A
        );
    }

    // ===========================
    // Email verification helpers
    // ===========================

    public function create_email_verification_job(int $phpbb_user_id, string $token, string $payload_json): bool {
        $queue = $this->wpdb->prefix . 'ia_auth_queue';
        $now = current_time('mysql');
        $ok = $this->wpdb->insert($queue, [
            'phpbb_user_id' => $phpbb_user_id,
            'type'          => 'email_verify',
            'payload_json'  => $payload_json,
            'status'        => 'pending',
            'attempts'      => 0,
            'next_run_at'   => null,
            'last_error'    => '',
            'created_at'    => $now,
            'updated_at'    => $now,
        ], ['%d','%s','%s','%s','%d','%s','%s','%s','%s']);

        if (!$ok) return false;

        // Also store token in audit for quick lookup? We embed token into payload_json,
        // but we additionally add a lightweight index table by using wp_options style lookup.
        // Here we use a deterministic option key.
        update_option('ia_auth_verify_' . $token, (string)$this->wpdb->insert_id, false);
        return true;
    }

    public function find_email_verification_job(string $token): ?array {
        $job_id = (int) get_option('ia_auth_verify_' . $token, 0);
        if ($job_id <= 0) return null;

        $queue = $this->wpdb->prefix . 'ia_auth_queue';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $queue WHERE job_id=%d", $job_id), ARRAY_A);
        if (!$row) return null;
        if (($row['type'] ?? '') !== 'email_verify') return null;
        return $row;
    }

    public function mark_email_verification_job_done(string $token, string $status, string $err = ''): void {
        $job_id = (int) get_option('ia_auth_verify_' . $token, 0);
        if ($job_id > 0) {
            $queue = $this->wpdb->prefix . 'ia_auth_queue';
            $this->wpdb->update($queue, [
                'status'     => $status,
                'last_error' => (string)$err,
                'updated_at' => current_time('mysql'),
            ], ['job_id' => $job_id], ['%s','%s','%s'], ['%d']);
        }
        delete_option('ia_auth_verify_' . $token);
    }


    public function get_tokens_by_phpbb_user_id(int $phpbb_user_id): ?array {
        $phpbb_user_id = (int)$phpbb_user_id;
        if ($phpbb_user_id <= 0) return null;

        $this->maybe_install();
        $table = $this->wpdb->prefix . 'ia_peertube_user_tokens';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id),
            ARRAY_A
        );
        return is_array($row) && !empty($row) ? $row : null;
    }
}
