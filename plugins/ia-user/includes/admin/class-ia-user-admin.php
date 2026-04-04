<?php
if (!defined('ABSPATH')) exit;

final class IA_User_Admin {
    private $user_runtime;

    public function __construct($user_runtime) {
        $this->user_runtime = $user_runtime;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_ia_user_admin_search_suggest', [$this, 'ajax_search_suggest']);

        add_action('admin_post_ia_user_admin_save_display_name', [$this, 'save_display_name']);
        add_action('admin_post_ia_user_admin_save_signature', [$this, 'save_signature']);
        add_action('admin_post_ia_user_admin_save_account_name', [$this, 'save_account_name']);
        add_action('admin_post_ia_user_admin_save_email', [$this, 'save_email']);
        add_action('admin_post_ia_user_admin_save_password', [$this, 'save_password']);
        add_action('admin_post_ia_user_admin_deactivate', [$this, 'deactivate']);
        add_action('admin_post_ia_user_admin_reactivate', [$this, 'reactivate']);
        add_action('admin_post_ia_user_admin_delete', [$this, 'delete']);
    }

    public function menu(): void {
        add_menu_page(
            'IA User',
            'IA User',
            'manage_options',
            'ia-user',
            [$this, 'render'],
            'dashicons-admin-users',
            59
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_ia-user') return;
        wp_enqueue_style('ia-user-admin', IA_USER_URL . 'assets/css/ia-user-admin.css', [], IA_USER_VERSION);
        wp_enqueue_script('ia-user-admin', IA_USER_URL . 'assets/js/ia-user-admin.js', [], IA_USER_VERSION, true);
    }

    private function guard(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
    }

    private function redirect_back(int $wp_user_id = 0, string $msg = '', string $err = ''): void {
        $args = ['page' => 'ia-user'];
        if ($wp_user_id > 0) $args['wp_user_id'] = $wp_user_id;
        if ($msg !== '') $args['ia_user_msg'] = rawurlencode($msg);
        if ($err !== '') $args['ia_user_err'] = rawurlencode($err);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function require_target_wp_user_id(): int {
        $wp_user_id = (int)($_POST['wp_user_id'] ?? 0);
        if ($wp_user_id <= 0) wp_die('Missing user.');
        return $wp_user_id;
    }

    private function auth_instance() {
        if (!class_exists('IA_Auth') || !method_exists('IA_Auth', 'instance')) {
            return null;
        }
        return IA_Auth::instance();
    }

    private function current_context(int $wp_user_id): array {
        $ctx = [
            'wp_user' => get_user_by('id', $wp_user_id),
            'identity' => null,
            'phpbb_user_id' => 0,
            'peertube_user_id' => 0,
            'phpbb_user' => null,
            'tombstone' => null,
        ];

        $ia = $this->auth_instance();
        if ($ia && isset($ia->db) && is_object($ia->db) && method_exists($ia->db, 'get_identity_by_wp_user_id')) {
            $ctx['identity'] = $ia->db->get_identity_by_wp_user_id($wp_user_id);
        }

        $ctx['phpbb_user_id'] = (int)($ctx['identity']['phpbb_user_id'] ?? 0);
        if ($ctx['phpbb_user_id'] <= 0) {
            $ctx['phpbb_user_id'] = (int)get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
        }
        $ctx['peertube_user_id'] = (int)($ctx['identity']['peertube_user_id'] ?? 0);

        $ctx['phpbb_user'] = $this->load_phpbb_user($ctx['phpbb_user_id']);
        $ctx['tombstone'] = $this->load_tombstone($ctx['phpbb_user_id'], (string)($ctx['identity']['email'] ?? ''), (string)($ctx['identity']['phpbb_username_clean'] ?? ''));

        return $ctx;
    }

    private function load_tombstone(int $phpbb_user_id, string $email = '', string $username_clean = ''): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'ia_goodbye_tombstones';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t)));
        if ($exists !== $t) return null;

        $parts = [];
        $params = [];
        if ($phpbb_user_id > 0) {
            $parts[] = 'phpbb_user_id=%d';
            $params[] = $phpbb_user_id;
        }
        if ($email !== '') {
            $parts[] = 'identifier_email=%s';
            $params[] = IA_Goodbye::normalize_identifier_email($email);
        }
        if ($username_clean !== '') {
            $parts[] = 'identifier_username_clean=%s';
            $params[] = IA_Goodbye::normalize_identifier_uclean($username_clean);
        }
        if (!$parts) return null;

        $sql = 'SELECT * FROM ' . $t . ' WHERE ' . implode(' OR ', $parts) . ' ORDER BY id DESC LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function phpbb_cfg(): array {
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'phpbb_db')) {
            return (array)IA_Engine::phpbb_db();
        }
        return [];
    }

    private function phpbb_pdo(): ?PDO {
        $cfg = $this->phpbb_cfg();
        $host = (string)($cfg['host'] ?? '');
        $name = (string)($cfg['name'] ?? '');
        $user = (string)($cfg['user'] ?? '');
        $pass = (string)($cfg['pass'] ?? '');
        $port = (int)($cfg['port'] ?? 3306);
        if ($host === '' || $name === '' || $user === '') return null;

        try {
            return new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    private function phpbb_table(string $name): string {
        $cfg = $this->phpbb_cfg();
        $prefix = (string)($cfg['prefix'] ?? 'phpbb_');
        $prefix = rtrim($prefix, '_') . '_';
        return $prefix . $name;
    }

    private function load_phpbb_user(int $phpbb_user_id): ?array {
        if ($phpbb_user_id <= 0) return null;
        $pdo = $this->phpbb_pdo();
        if (!$pdo) return null;

        try {
            $users = $this->phpbb_table('users');
            $st = $pdo->prepare("SELECT * FROM {$users} WHERE user_id=:uid LIMIT 1");
            $st->execute([':uid' => $phpbb_user_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function search_users(string $search, int $paged = 1, int $per_page = 25): array {
        $paged = max(1, $paged);
        $per_page = max(1, min(200, $per_page));
        $args = [
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'orderby' => 'ID',
            'order' => 'DESC',
            'count_total' => true,
            'meta_query' => [
                [
                    'key' => 'ia_phpbb_user_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $search = trim($search);
        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['ID', 'user_login', 'user_email', 'display_name'];
        }

        $q = new WP_User_Query($args);
        $users = is_array($q->get_results()) ? $q->get_results() : [];
        $total = (int)$q->get_total();

        return [
            'users' => $users,
            'total' => $total,
            'per_page' => $per_page,
            'paged' => $paged,
            'total_pages' => max(1, (int)ceil($total / max(1, $per_page))),
        ];
    }

    public function ajax_search_suggest(): void {
        $this->guard();
        $q = isset($_POST['q']) ? sanitize_text_field((string)wp_unslash($_POST['q'])) : '';
        $q = trim($q);
        if ($q === '' || strlen($q) < 2) {
            wp_send_json_success(['items' => []]);
        }

        $result = $this->search_users($q, 1, 8);
        $items = [];
        foreach (($result['users'] ?? []) as $u) {
            $label = sprintf('%s — %s — %s', (string)$u->display_name, (string)$u->user_login, (string)$u->user_email);
            $items[] = [
                'value' => (string)$u->user_login,
                'label' => $label,
                'wp_user_id' => (int)$u->ID,
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    private function maybe_update_identity(array $ctx, array $fields): void {
        $ia = $this->auth_instance();
        if (!$ia || !isset($ia->db) || !is_object($ia->db) || !method_exists($ia->db, 'upsert_identity')) return;
        $identity = is_array($ctx['identity']) ? $ctx['identity'] : [];
        $row = array_merge([
            'phpbb_user_id' => (int)$ctx['phpbb_user_id'],
            'phpbb_username_clean' => (string)($identity['phpbb_username_clean'] ?? ''),
            'email' => (string)($identity['email'] ?? ''),
            'wp_user_id' => (int)($ctx['wp_user'] ? $ctx['wp_user']->ID : 0),
            'peertube_user_id' => isset($identity['peertube_user_id']) ? (int)$identity['peertube_user_id'] : null,
            'peertube_account_id' => isset($identity['peertube_account_id']) ? (int)$identity['peertube_account_id'] : null,
            'peertube_actor_id' => isset($identity['peertube_actor_id']) ? (int)$identity['peertube_actor_id'] : null,
            'status' => (string)($identity['status'] ?? 'linked'),
            'last_error' => (string)($identity['last_error'] ?? ''),
        ], $fields);
        $ia->db->upsert_identity($row);
    }

    private function peertube_cfg(): array {
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api')) {
            return (array)IA_Engine::peertube_api();
        }
        return [];
    }

    private function maybe_update_peertube_display_name(int $phpbb_user_id, string $new_name): void {
        $ia = $this->auth_instance();
        if (!$ia || !isset($ia->db, $ia->crypto, $ia->peertube)) return;
        if (!method_exists($ia->db, 'get_tokens_by_phpbb_user_id') || !method_exists($ia->crypto, 'decrypt') || !method_exists($ia->peertube, 'user_update_me_display_name')) return;
        $tok = $ia->db->get_tokens_by_phpbb_user_id($phpbb_user_id);
        if (!is_array($tok) || empty($tok['access_token_enc'])) return;
        $access = $ia->crypto->decrypt((string)$tok['access_token_enc']);
        if (!$access) return;
        $ia->peertube->user_update_me_display_name($access, $new_name, $this->peertube_cfg());
    }

    public function render(): void {
        $this->guard();

        $search = isset($_GET['s']) ? sanitize_text_field((string)$_GET['s']) : '';
        $selected_id = isset($_GET['wp_user_id']) ? (int)$_GET['wp_user_id'] : 0;
        $paged = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
        if (!in_array($per_page, [10, 25, 50, 100], true)) $per_page = 25;
        $search_result = $this->search_users($search, $paged, $per_page);
        $users = $search_result['users'];
        $ctx = $selected_id > 0 ? $this->current_context($selected_id) : null;

        echo '<div class="wrap ia-user-admin">';
        echo '<h1>IA User</h1>';
        echo '<p>Admin control for Atrium-linked users. This page edits the same cross-system account surface that users can affect from the frontend: display name, signature, account name, email, password, deactivate/reactivate, and delete.</p>';

        $msg = isset($_GET['ia_user_msg']) ? rawurldecode((string)$_GET['ia_user_msg']) : '';
        $err = isset($_GET['ia_user_err']) ? rawurldecode((string)$_GET['ia_user_err']) : '';
        if ($msg !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
        }
        if ($err !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($err) . '</p></div>';
        }

        echo '<div class="ia-user-admin-grid">';
        echo '<section class="ia-user-admin-card">';
        echo '<h2>Find user</h2>';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="ia-user">';
        echo '<div class="ia-user-admin-row ia-user-admin-search-row">';
        echo '<input type="search" class="regular-text ia-user-admin-search-input" name="s" value="' . esc_attr($search) . '" placeholder="Search login, email, display name" list="ia-user-admin-search-suggestions" autocomplete="off">';
        echo '<datalist id="ia-user-admin-search-suggestions"></datalist>';
        echo '<label><span class="screen-reader-text">Users per page</span><select name="per_page">';
        foreach ([10,25,50,100] as $pp) { echo '<option value="' . (int)$pp . '"' . selected($per_page, $pp, false) . '>' . (int)$pp . ' / page</option>'; }
        echo '</select></label>';
        submit_button('Search', 'secondary', '', false);
        echo '</div>';
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr><th>WP</th><th>Display</th><th>Email</th><th>phpBB</th><th>Status</th></tr></thead><tbody>';
        if (!$users) {
            echo '<tr><td colspan="5">No Atrium-linked users found.</td></tr>';
        } else {
            foreach ($users as $u) {
                $phpbb_id = (int)get_user_meta((int)$u->ID, 'ia_phpbb_user_id', true);
                $status = ((int)get_user_meta((int)$u->ID, 'ia_deactivated', true) === 1) ? 'Deactivated' : 'Active';
                $link = add_query_arg([
                    'page' => 'ia-user',
                    's' => $search,
                    'per_page' => $per_page,
                    'paged' => $paged,
                    'wp_user_id' => (int)$u->ID,
                ], admin_url('admin.php'));
                echo '<tr>';
                echo '<td><a href="' . esc_url($link) . '">#' . (int)$u->ID . ' ' . esc_html($u->user_login) . '</a></td>';
                echo '<td>' . esc_html((string)$u->display_name) . '</td>';
                echo '<td>' . esc_html((string)$u->user_email) . '</td>';
                echo '<td>' . (int)$phpbb_id . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        $total = (int)($search_result['total'] ?? 0);
        $total_pages = (int)($search_result['total_pages'] ?? 1);
        $current_page = (int)($search_result['paged'] ?? 1);
        echo '<div class="tablenav bottom"><div class="displaying-num">' . esc_html(sprintf('%d users', $total)) . '</div>';
        if ($total_pages > 1) {
            echo '<div class="tablenav-pages">';
            $base = ['page' => 'ia-user', 's' => $search, 'per_page' => $per_page];
            if ($current_page > 1) {
                $prev = add_query_arg(array_merge($base, ['paged' => $current_page - 1]), admin_url('admin.php'));
                echo '<a class="button" href="' . esc_url($prev) . '">Previous</a> ';
            }
            echo '<span class="paging-input">Page ' . (int)$current_page . ' of ' . (int)$total_pages . '</span>';
            if ($current_page < $total_pages) {
                $next = add_query_arg(array_merge($base, ['paged' => $current_page + 1]), admin_url('admin.php'));
                echo ' <a class="button" href="' . esc_url($next) . '">Next</a>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</section>';

        echo '<section class="ia-user-admin-card">';
        if (!$ctx || !$ctx['wp_user']) {
            echo '<h2>User details</h2><p>Select a user from the list to edit their account.</p>';
        } else {
            $wp_user = $ctx['wp_user'];
            $identity = is_array($ctx['identity']) ? $ctx['identity'] : [];
            $phpbb_user = is_array($ctx['phpbb_user']) ? $ctx['phpbb_user'] : [];
            $tombstone = is_array($ctx['tombstone']) ? $ctx['tombstone'] : [];
            $sig = defined('IA_CONNECT_META_SIGNATURE') ? (string)get_user_meta((int)$wp_user->ID, IA_CONNECT_META_SIGNATURE, true) : '';
            $show_sig = defined('IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS') ? (int)get_user_meta((int)$wp_user->ID, IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS, true) : 0;

            echo '<h2>User details</h2>';
            echo '<table class="widefat striped ia-user-admin-summary">';
            echo '<tbody>';
            echo '<tr><th>WP user</th><td>#' . (int)$wp_user->ID . ' / ' . esc_html($wp_user->user_login) . '</td></tr>';
            echo '<tr><th>Display name</th><td>' . esc_html((string)$wp_user->display_name) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . esc_html((string)$wp_user->user_email) . '</td></tr>';
            echo '<tr><th>phpBB user id</th><td>' . (int)$ctx['phpbb_user_id'] . '</td></tr>';
            echo '<tr><th>phpBB username</th><td>' . esc_html((string)($phpbb_user['username'] ?? '')) . '</td></tr>';
            echo '<tr><th>phpBB email</th><td>' . esc_html((string)($phpbb_user['user_email'] ?? '')) . '</td></tr>';
            echo '<tr><th>PeerTube user id</th><td>' . (int)$ctx['peertube_user_id'] . '</td></tr>';
            echo '<tr><th>Identity status</th><td>' . esc_html((string)($identity['status'] ?? '')) . '</td></tr>';
            echo '<tr><th>Deactivated</th><td>' . (((int)get_user_meta((int)$wp_user->ID, 'ia_deactivated', true) === 1) ? 'Yes' : 'No') . '</td></tr>';
            echo '<tr><th>Tombstoned</th><td>' . ($tombstone ? 'Yes' : 'No') . '</td></tr>';
            if ($tombstone) {
                echo '<tr><th>Tombstone reason</th><td>' . esc_html((string)($tombstone['reason'] ?? '')) . '</td></tr>';
                echo '<tr><th>Tombstone date</th><td>' . esc_html((string)($tombstone['deleted_at'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table>';

            $this->render_form_display_name($wp_user, $search);
            $this->render_form_signature($wp_user, $sig, $show_sig, $search);
            $this->render_form_account_name($ctx, $search);
            $this->render_form_email($ctx, $search);
            $this->render_form_password($wp_user, $search);
            $this->render_form_lifecycle($wp_user, $search);
        }
        echo '</section>';
        echo '</div></div>';
    }

    private function hidden_state_fields(int $wp_user_id, string $search): void {
        echo '<input type="hidden" name="wp_user_id" value="' . (int)$wp_user_id . '">';
        echo '<input type="hidden" name="return_search" value="' . esc_attr($search) . '">';
    }

    private function render_form_display_name(WP_User $wp_user, string $search): void {
        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Display name</h3><p>Matches the simple frontend display-name setting. This changes WordPress display_name only.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_save_display_name">';
        wp_nonce_field('ia_user_admin_save_display_name');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        echo '<label><span>Display name</span><input type="text" name="display_name" class="regular-text" value="' . esc_attr((string)$wp_user->display_name) . '"></label>';
        submit_button('Save display name', 'secondary', '', false);
        echo '</form>';
    }

    private function render_form_signature(WP_User $wp_user, string $sig, int $show_sig, string $search): void {
        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Signature</h3><p>Matches the frontend Discuss signature settings.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_save_signature">';
        wp_nonce_field('ia_user_admin_save_signature');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        echo '<label><span>Signature</span><textarea name="signature" rows="5" class="large-text">' . esc_textarea($sig) . '</textarea></label>';
        echo '<label class="ia-user-admin-check"><input type="checkbox" name="show_discuss" value="1"' . checked($show_sig, 1, false) . '> Show in Discuss</label>';
        submit_button('Save signature', 'secondary', '', false);
        echo '</form>';
    }

    private function render_form_account_name(array $ctx, string $search): void {
        $wp_user = $ctx['wp_user'];
        $phpbb_name = (string)($ctx['phpbb_user']['username'] ?? $wp_user->display_name);
        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Account name</h3><p>Matches the frontend account-name change. This updates phpBB username + username_clean, WordPress login/nicename/display_name/nickname, identity-map username_clean, and best-effort PeerTube display name.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_save_account_name">';
        wp_nonce_field('ia_user_admin_save_account_name');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        echo '<label><span>Account name</span><input type="text" name="account_name" class="regular-text" value="' . esc_attr($phpbb_name) . '"></label>';
        submit_button('Save account name', 'primary', '', false);
        echo '</form>';
    }

    private function render_form_email(array $ctx, string $search): void {
        $wp_user = $ctx['wp_user'];
        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Email</h3><p>Matches the frontend email change. This updates phpBB, WordPress, identity-map email, and PeerTube email where a PeerTube user id exists.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_save_email">';
        wp_nonce_field('ia_user_admin_save_email');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        echo '<label><span>Email</span><input type="email" name="email" class="regular-text" value="' . esc_attr((string)$wp_user->user_email) . '"></label>';
        submit_button('Save email', 'secondary', '', false);
        echo '</form>';
    }

    private function render_form_password(WP_User $wp_user, string $search): void {
        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Password</h3><p>Matches the frontend password change. This updates phpBB, WordPress, and PeerTube where linked.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_save_password">';
        wp_nonce_field('ia_user_admin_save_password');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        echo '<label><span>New password</span><input type="password" name="new_password" class="regular-text" value=""></label>';
        submit_button('Save password', 'secondary', '', false);
        echo '</form>';
    }

    private function render_form_lifecycle(WP_User $wp_user, string $search): void {
        echo '<div class="ia-user-admin-actions">';

        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Deactivate</h3><p>Uses IA Goodbye deactivation logic.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_deactivate">';
        wp_nonce_field('ia_user_admin_deactivate');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        submit_button('Deactivate account', 'secondary', '', false);
        echo '</form>';

        echo '<form class="ia-user-admin-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Reactivate</h3><p>Clears the deactivated state in phpBB and WordPress.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_reactivate">';
        wp_nonce_field('ia_user_admin_reactivate');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        submit_button('Reactivate account', 'secondary', '', false);
        echo '</form>';

        echo '<form class="ia-user-admin-form ia-user-admin-danger" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>Delete account</h3><p>Uses the new tombstone-first delete logic. This is irreversible and removes the WordPress shadow user after phpBB/identity cleanup. Type DELETE to confirm.</p>';
        echo '<input type="hidden" name="action" value="ia_user_admin_delete">';
        wp_nonce_field('ia_user_admin_delete');
        $this->hidden_state_fields((int)$wp_user->ID, $search);
        echo '<label><span>Confirm</span><input type="text" name="confirm_delete" class="regular-text" value="" placeholder="DELETE"></label>';
        submit_button('Delete account', 'delete', '', false);
        echo '</form>';

        echo '</div>';
    }

    public function save_display_name(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_save_display_name');
        $wp_user_id = $this->require_target_wp_user_id();
        $dn = trim((string)($_POST['display_name'] ?? ''));

        if ($dn === '') {
            $u = get_userdata($wp_user_id);
            $dn = $u ? (string)($u->user_login ?: '') : '';
        }
        if ($dn === '') {
            $this->redirect_back($wp_user_id, '', 'Unable to resolve username.');
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\. ]{2,40}$/', $dn)) {
            $this->redirect_back($wp_user_id, '', 'Invalid display name. Use 2–40 chars: letters, numbers, spaces, underscore, dash, dot.');
        }
        $res = wp_update_user(['ID' => $wp_user_id, 'display_name' => $dn]);
        if (is_wp_error($res)) {
            $this->redirect_back($wp_user_id, '', $res->get_error_message());
        }
        $this->redirect_back($wp_user_id, 'Display name updated.');
    }

    public function save_signature(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_save_signature');
        $wp_user_id = $this->require_target_wp_user_id();
        $sig = isset($_POST['signature']) ? (string)wp_unslash($_POST['signature']) : '';
        $sig = sanitize_textarea_field(trim($sig));
        if (strlen($sig) > 500) $sig = substr($sig, 0, 500);
        $show = isset($_POST['show_discuss']) ? 1 : 0;

        if (defined('IA_CONNECT_META_SIGNATURE')) {
            update_user_meta($wp_user_id, IA_CONNECT_META_SIGNATURE, $sig);
        }
        if (defined('IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS')) {
            update_user_meta($wp_user_id, IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS, $show);
        }
        $this->redirect_back($wp_user_id, 'Signature updated.');
    }

    public function save_account_name(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_save_account_name');
        $wp_user_id = $this->require_target_wp_user_id();
        $ctx = $this->current_context($wp_user_id);
        if (!$ctx['wp_user']) $this->redirect_back($wp_user_id, '', 'User not found.');
        if ($ctx['phpbb_user_id'] <= 0) $this->redirect_back($wp_user_id, '', 'No phpBB mapping for this user.');
        $ia = $this->auth_instance();
        if (!$ia || !isset($ia->phpbb)) $this->redirect_back($wp_user_id, '', 'IA Auth not available.');

        $new_name = trim((string)($_POST['account_name'] ?? ''));
        if (strlen($new_name) < 3) {
            $this->redirect_back($wp_user_id, '', 'Name must be at least 3 characters.');
        }
        $clean = strtolower(preg_replace('/[^a-z0-9_\-]+/i', '', $new_name));
        if ($clean === '') {
            $this->redirect_back($wp_user_id, '', 'Unable to derive a valid username slug.');
        }

        $current_clean = (string)($ctx['identity']['phpbb_username_clean'] ?? '');
        if (function_exists('ia_goodbye_identifier_is_tombstoned') && $clean !== $current_clean && ia_goodbye_identifier_is_tombstoned($clean)) {
            $this->redirect_back($wp_user_id, '', 'That username belongs to a deleted account and cannot be reused.');
        }

        global $wpdb;
        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE user_login=%s AND ID<>%d LIMIT 1", $clean, $wp_user_id));
        if ($exists > 0) {
            $this->redirect_back($wp_user_id, '', 'That username is already taken.');
        }

        $r = $ia->phpbb->update_user_fields((int)$ctx['phpbb_user_id'], [
            'username' => $new_name,
            'username_clean' => $clean,
        ]);
        if (empty($r['ok'])) {
            $this->redirect_back($wp_user_id, '', (string)($r['message'] ?? 'phpBB update failed.'));
        }

        wp_update_user([
            'ID' => $wp_user_id,
            'display_name' => $new_name,
            'user_nicename' => sanitize_title($new_name),
        ]);
        update_user_meta($wp_user_id, 'nickname', $new_name);
        $wpdb->update($wpdb->users, ['user_login' => $clean, 'user_nicename' => $clean], ['ID' => $wp_user_id]);
        clean_user_cache($wp_user_id);

        $this->maybe_update_identity($ctx, ['phpbb_username_clean' => $clean]);
        $this->maybe_update_peertube_display_name((int)$ctx['phpbb_user_id'], $new_name);
        $this->redirect_back($wp_user_id, 'Account name updated.');
    }

    public function save_email(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_save_email');
        $wp_user_id = $this->require_target_wp_user_id();
        $ctx = $this->current_context($wp_user_id);
        if (!$ctx['wp_user']) $this->redirect_back($wp_user_id, '', 'User not found.');
        if ($ctx['phpbb_user_id'] <= 0) $this->redirect_back($wp_user_id, '', 'No phpBB mapping for this user.');
        $ia = $this->auth_instance();
        if (!$ia || !isset($ia->phpbb)) $this->redirect_back($wp_user_id, '', 'IA Auth not available.');

        $new_email = trim((string)($_POST['email'] ?? ''));
        if (!is_email($new_email)) {
            $this->redirect_back($wp_user_id, '', 'Enter a valid email address.');
        }

        $current_email = (string)($ctx['identity']['email'] ?? $ctx['wp_user']->user_email);
        if (function_exists('ia_goodbye_identifier_is_tombstoned') && strtolower($new_email) !== strtolower($current_email) && ia_goodbye_identifier_is_tombstoned($new_email)) {
            $this->redirect_back($wp_user_id, '', 'That email belongs to a deleted account and cannot be reused.');
        }
        $other = get_user_by('email', $new_email);
        if ($other && (int)$other->ID !== $wp_user_id) {
            $this->redirect_back($wp_user_id, '', 'That email is already in use.');
        }

        $r = $ia->phpbb->update_user_fields((int)$ctx['phpbb_user_id'], ['user_email' => $new_email]);
        if (empty($r['ok'])) {
            $this->redirect_back($wp_user_id, '', (string)($r['message'] ?? 'phpBB update failed.'));
        }

        $wp_res = wp_update_user(['ID' => $wp_user_id, 'user_email' => $new_email]);
        if (is_wp_error($wp_res)) {
            $this->redirect_back($wp_user_id, '', $wp_res->get_error_message());
        }
        update_user_meta($wp_user_id, 'ia_email', $new_email);
        $this->maybe_update_identity($ctx, ['email' => $new_email]);

        if ((int)$ctx['peertube_user_id'] > 0 && isset($ia->peertube) && method_exists($ia->peertube, 'admin_update_user_email')) {
            $ia->peertube->admin_update_user_email((int)$ctx['peertube_user_id'], $new_email, $this->peertube_cfg());
        }
        $this->redirect_back($wp_user_id, 'Email updated.');
    }

    public function save_password(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_save_password');
        $wp_user_id = $this->require_target_wp_user_id();
        $ctx = $this->current_context($wp_user_id);
        if (!$ctx['wp_user']) $this->redirect_back($wp_user_id, '', 'User not found.');
        if ($ctx['phpbb_user_id'] <= 0) $this->redirect_back($wp_user_id, '', 'No phpBB mapping for this user.');
        $ia = $this->auth_instance();
        if (!$ia || !isset($ia->phpbb)) $this->redirect_back($wp_user_id, '', 'IA Auth not available.');

        $new = (string)($_POST['new_password'] ?? '');
        if (strlen($new) < 8) {
            $this->redirect_back($wp_user_id, '', 'New password must be at least 8 characters.');
        }
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $r = $ia->phpbb->update_user_fields((int)$ctx['phpbb_user_id'], ['user_password' => $hash]);
        if (empty($r['ok'])) {
            $this->redirect_back($wp_user_id, '', (string)($r['message'] ?? 'phpBB update failed.'));
        }
        wp_set_password($new, $wp_user_id);
        if ((int)$ctx['peertube_user_id'] > 0 && isset($ia->peertube) && method_exists($ia->peertube, 'admin_update_user_password')) {
            $ia->peertube->admin_update_user_password((int)$ctx['peertube_user_id'], $new, $this->peertube_cfg());
        }
        $this->redirect_back($wp_user_id, 'Password updated.');
    }

    public function deactivate(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_deactivate');
        $wp_user_id = $this->require_target_wp_user_id();
        if (!class_exists('IA_Goodbye')) {
            $this->redirect_back($wp_user_id, '', 'IA Goodbye not available.');
        }
        $res = IA_Goodbye::instance()->deactivate_account($wp_user_id);
        if (empty($res['ok'])) {
            $this->redirect_back($wp_user_id, '', (string)($res['message'] ?? 'Deactivation failed.'));
        }
        $this->redirect_back($wp_user_id, 'Account deactivated.');
    }

    public function reactivate(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_reactivate');
        $wp_user_id = $this->require_target_wp_user_id();
        $ctx = $this->current_context($wp_user_id);
        if (!$ctx['wp_user']) $this->redirect_back($wp_user_id, '', 'User not found.');
        if ($ctx['phpbb_user_id'] <= 0) $this->redirect_back($wp_user_id, '', 'No phpBB mapping for this user.');
        $ia = $this->auth_instance();
        if (!$ia || !isset($ia->phpbb) || !method_exists($ia->phpbb, 'reactivate_if_deactivated')) {
            $this->redirect_back($wp_user_id, '', 'IA Auth reactivate path not available.');
        }
        $ia->phpbb->reactivate_if_deactivated((int)$ctx['phpbb_user_id']);
        delete_user_meta($wp_user_id, 'ia_deactivated');
        wp_update_user(['ID' => $wp_user_id, 'user_status' => 0]);
        $this->redirect_back($wp_user_id, 'Account reactivated.');
    }

    public function delete(): void {
        $this->guard();
        check_admin_referer('ia_user_admin_delete');
        $wp_user_id = $this->require_target_wp_user_id();
        $confirm = trim((string)($_POST['confirm_delete'] ?? ''));
        if ($confirm !== 'DELETE') {
            $this->redirect_back($wp_user_id, '', 'Type DELETE to confirm account deletion.');
        }
        if (!class_exists('IA_Goodbye')) {
            $this->redirect_back($wp_user_id, '', 'IA Goodbye not available.');
        }
        $res = IA_Goodbye::instance()->delete_account($wp_user_id, 'admin_delete');
        if (empty($res['ok'])) {
            $this->redirect_back($wp_user_id, '', (string)($res['message'] ?? 'Account delete failed.'));
        }
        $this->redirect_back(0, 'Account deleted.');
    }
}
