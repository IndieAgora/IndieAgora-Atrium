<?php
if (!defined('ABSPATH')) exit;

/**
 * Minimal phpBB user provider.
 * - authenticate() verifies hashes used by phpBB (bcrypt/argon2, phpass, legacy md5)
 * - create_user() inserts into phpbb_users (+ phpbb_user_group) with safe defaults
 */
final class IA_User_PHPBB {

    private function pdo(array $cfg): PDO {
        $host = (string)($cfg['host'] ?? '');
        $port = (int)($cfg['port'] ?? 3306);
        $name = (string)($cfg['name'] ?? '');
        $user = (string)($cfg['user'] ?? '');
        $pass = (string)($cfg['pass'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }

    private function prefix(array $cfg): string {
        $p = (string)($cfg['prefix'] ?? 'phpbb_');
        // Normalize: allow "phpbb" and "phpbb_" inputs.
        $p = rtrim($p, '_') . '_';
        return $p;
    }

    private function find_user(string $username_or_email, array $cfg): ?array {
        $pdo = $this->pdo($cfg);
        $prefix = $this->prefix($cfg);

        $id = trim($username_or_email);
        $id_clean = strtolower($id);

        $table = $prefix . 'users';

        // Match by username_clean OR user_email.
        $sql = "SELECT * FROM {$table} WHERE username_clean = :u OR user_email = :e LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':u' => $id_clean,
            ':e' => $id,
        ]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Authenticate against phpBB.
     * Returns: ['ok'=>true,'user'=>$row] OR ['ok'=>false,'message'=>...]
     */
    public function authenticate(string $username_or_email, string $password, array $cfg): array {
        $username_or_email = trim($username_or_email);
        if ($username_or_email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Missing username/password.'];
        }

        try {
            $row = $this->find_user($username_or_email, $cfg);
            if (!$row) return ['ok' => false, 'message' => 'Invalid username/email or password.'];

            $hash = (string)($row['user_password'] ?? '');
            if ($hash === '') return ['ok' => false, 'message' => 'Invalid username/email or password.'];

            if (!$this->verify_phpbb_password($password, $hash)) {
                return ['ok' => false, 'message' => 'Invalid username/email or password.'];
            }

            return ['ok' => true, 'user' => $row];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'phpBB auth error.'];
        }
    }

    public function create_user(string $username, string $email, string $password, array $cfg): array {
        $username = trim($username);
        $email = trim($email);
        if ($username === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Missing fields.'];
        }

        $username_clean = strtolower($username);

        try {
            $pdo = $this->pdo($cfg);
            $prefix = $this->prefix($cfg);

            $table_users = $prefix . 'users';
            $table_ug = $prefix . 'user_group';

            // Guard against existing username/email
            $st = $pdo->prepare("SELECT user_id FROM {$table_users} WHERE username_clean = :u OR user_email = :e LIMIT 1");
            $st->execute([':u' => $username_clean, ':e' => $email]);
            if ($st->fetch()) {
                return ['ok' => false, 'message' => 'Username or email already exists.'];
            }

            $now = time();

            // Use modern hash by default (bcrypt/argon2 depending on server).
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Minimal required NOT NULL fields from schema are included.
            // Keep group_id default (3) to match schema; also insert into user_group.
            $group_id = 3;

            $st = $pdo->prepare("
                INSERT INTO {$table_users} (
                    user_type, group_id, user_permissions, user_perm_from, user_ip, user_regdate,
                    username, username_clean, user_password, user_passchg, user_email, user_birthday,
                    user_lastvisit, user_last_active, user_lastmark, user_lastpost_time, user_lastpage,
                    user_last_confirm_key, user_last_search, user_warnings, user_last_warning, user_login_attempts,
                    user_inactive_reason, user_inactive_time, user_posts, user_lang, user_timezone, user_dateformat,
                    user_style, user_rank, user_colour, user_new_privmsg, user_unread_privmsg, user_last_privmsg,
                    user_message_rules, user_full_folder, user_emailtime, user_topic_show_days, user_topic_sortby_type,
                    user_topic_sortby_dir, user_post_show_days, user_post_sortby_type, user_post_sortby_dir,
                    user_notify, user_notify_pm, user_notify_type, user_allow_pm, user_allow_viewonline,
                    user_allow_viewemail, user_allow_massemail, user_options, user_avatar, user_avatar_type,
                    user_avatar_width, user_avatar_height, user_sig, user_sig_bbcode_uid, user_sig_bbcode_bitfield,
                    user_jabber, user_actkey, user_actkey_expiration, reset_token, reset_token_expiration,
                    user_newpasswd, user_form_salt, user_new, user_reminded, user_reminded_time
                ) VALUES (
                    0, :group_id, '', 0, '', :regdate,
                    :username, :username_clean, :user_password, 0, :user_email, '',
                    0, 0, 0, 0, '',
                    '', 0, 0, 0, 0,
                    0, 0, 0, 'en', 'UTC', 'd M Y H:i',
                    0, 0, '', 0, 0, 0,
                    0, -3, 0, 0, 't',
                    'd', 0, 't', 'a',
                    0, 1, 0, 1, 1,
                    1, 1, 230271, '', '',
                    0, 0, '', '', '',
                    '', '', 0, '', 0,
                    '', '', 1, 0, 0
                )
            ");

            $st->execute([
                ':group_id'       => $group_id,
                ':regdate'        => $now,
                ':username'       => $username,
                ':username_clean' => $username_clean,
                ':user_password'  => $hash,
                ':user_email'     => $email,
            ]);

            $new_id = (int)$pdo->lastInsertId();
            if ($new_id <= 0) {
                return ['ok' => false, 'message' => 'Registration failed (no user id).'];
            }

            // Ensure group membership row (non-pending).
            $st = $pdo->prepare("INSERT INTO {$table_ug} (group_id, user_id, group_leader, user_pending) VALUES (:gid, :uid, 0, 0)");
            $st->execute([':gid' => $group_id, ':uid' => $new_id]);

            $st = $pdo->prepare("SELECT * FROM {$table_users} WHERE user_id = :id LIMIT 1");
            $st->execute([':id' => $new_id]);
            $row = $st->fetch();

            if (!$row) return ['ok' => false, 'message' => 'User created but could not reload phpBB user row.'];

            return ['ok' => true, 'user' => $row];

        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Registration insert failed.'];
        }
    }

    // ---------------- Hash verification ----------------

    private function verify_phpbb_password(string $password, string $hash): bool {
        if (strpos($hash, '$argon2') === 0 || strpos($hash, '$2') === 0) {
            return password_verify($password, $hash);
        }

        if (strpos($hash, '$H$') === 0 || strpos($hash, '$P$') === 0) {
            return hash_equals($this->phpbb_hash($password, $hash), $hash);
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
            return hash_equals(md5($password), strtolower($hash));
        }

        return false;
    }

    private function phpbb_hash($password, $setting) {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        if (substr($setting, 0, 3) != '$H$' && substr($setting, 0, 3) != '$P$') {
            return '';
        }

        $count_log2 = strpos($itoa64, $setting[3]);
        if ($count_log2 < 7 || $count_log2 > 30) {
            return '';
        }

        $count = 1 << $count_log2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) != 8) {
            return '';
        }

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($setting, 0, 12);
        $output .= $this->encode64($hash, 16, $itoa64);
        return $output;
    }

    private function encode64($input, $count, $itoa64) {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= $itoa64[$value & 0x3f];

            if ($i < $count) $value |= ord($input[$i]) << 8;
            $output .= $itoa64[($value >> 6) & 0x3f];

            if ($i++ >= $count) break;
            if ($i < $count) $value |= ord($input[$i]) << 16;
            $output .= $itoa64[($value >> 12) & 0x3f];

            if ($i++ >= $count) break;
            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
