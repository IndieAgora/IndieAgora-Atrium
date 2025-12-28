<?php
if (!defined('ABSPATH')) { exit; }

/**
 * phpBB authentication helper.
 * Supports:
 * - Argon2 ($argon2i$, $argon2id$) via password_verify
 * - bcrypt ($2y$, $2a$, $2b$) via password_verify
 * - phpBB portable hashes ($H$ / $P$) (phpass)
 * - legacy md5
 *
 * IMPORTANT:
 * Config keys expected (normalized by IA_Auth::engine_normalized()):
 * - host, port, name, user, pass, prefix
 */
final class IA_Auth_PHPBB {

    private $log;

    public function __construct($logger) {
        $this->log = $logger;
    }

    /**
     * Find a phpBB user row without verifying password.
     * Returns associative array row or null.
     */
    public function find_user(string $identity, array $cfg): ?array {
        $identity = trim($identity);
        if ($identity === '') return null;

        $host   = (string)($cfg['host'] ?? '');
        $port   = (int)($cfg['port'] ?? 3306);
        $db     = (string)($cfg['name'] ?? '');
        $user   = (string)($cfg['user'] ?? '');
        $pass   = (string)($cfg['pass'] ?? '');
        $prefix = (string)($cfg['prefix'] ?? 'phpbb_');

        if ($host === '' || $db === '' || $user === '' || $prefix === '') {
            return null;
        }

        $table_users = $prefix . 'users';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $this->log->error('phpbb_db_connect_failed', ['error' => $e->getMessage()]);
            return null;
        }

        // Match by email OR username_clean OR username
        $sql = "SELECT * FROM {$table_users}
                WHERE user_email = :id
                   OR username_clean = :id_clean
                   OR username = :id
                LIMIT 1";

        $id_clean = strtolower($identity);

        try {
            $st = $pdo->prepare($sql);
            $st->execute([
                ':id' => $identity,
                ':id_clean' => $id_clean,
            ]);
            $row = $st->fetch();
        } catch (Throwable $e) {
            $this->log->error('phpbb_user_lookup_failed', ['error' => $e->getMessage()]);
            return null;
        }

        return $row ?: null;
    }

    /**
     * Create a phpBB user row (canonical identity).
     * Returns: ['ok'=>true,'user'=>$row] OR ['ok'=>false,'message'=>...]
     *
     * This is tailored to your phpBB schema dump where phpbb_users includes:
     * - user_permissions MEDIUMTEXT NOT NULL (no default)
     * - user_sig        MEDIUMTEXT NOT NULL (no default)
     * - group_id default 3
     */
    public function create_user(string $username, string $email, string $password, array $cfg): array {
        $username = trim($username);
        $email    = trim($email);

        if ($username === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Missing fields.'];
        }

        $host   = (string)($cfg['host'] ?? '');
        $port   = (int)($cfg['port'] ?? 3306);
        $db     = (string)($cfg['name'] ?? '');
        $user   = (string)($cfg['user'] ?? '');
        $pass   = (string)($cfg['pass'] ?? '');
        $prefix = (string)($cfg['prefix'] ?? 'phpbb_');

        if ($host === '' || $db === '' || $user === '' || $prefix === '') {
            return ['ok' => false, 'message' => 'phpBB DB config missing.'];
        }

        $table_users = $prefix . 'users';

        // Basic clean (phpBB proper has utf/normalization; this is good enough for your current flow)
        $username_clean = strtolower(trim(preg_replace('/\s+/', '_', $username)));

        // Hash: bcrypt/argon via password_hash (phpBB can verify with password_verify for modern hashes)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$hash) {
            return ['ok' => false, 'message' => 'Could not hash password.'];
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $this->log->error('phpbb_db_connect_failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'phpBB DB connect failed.'];
        }

        // Uniqueness checks (schema has UNIQUE(username_clean), and KEY(user_email))
        try {
            $st = $pdo->prepare("SELECT user_id FROM {$table_users} WHERE username_clean = :u LIMIT 1");
            $st->execute([':u' => $username_clean]);
            if ($st->fetch()) {
                return ['ok' => false, 'message' => 'Username is already taken.'];
            }

            $st = $pdo->prepare("SELECT user_id FROM {$table_users} WHERE user_email = :e LIMIT 1");
            $st->execute([':e' => $email]);
            if ($st->fetch()) {
                return ['ok' => false, 'message' => 'Email is already registered.'];
            }
        } catch (Throwable $e) {
            $this->log->error('phpbb_register_uniqueness_check_failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Registration check failed.'];
        }

        $now = time();
        $ip  = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        // From your schema: group_id default is 3, user_type default 0
        $user_type = 0;
        $group_id  = 3;

        // Required NOT NULL (no defaults in your schema)
        $user_permissions = '';
        $user_sig         = '';

        // Reasonable defaults (schema defaults are '', 0 etc., but setting these is harmless)
        $user_lang     = 'en';
        $user_timezone = '';
        $user_dateformat = 'd M Y H:i';

        try {
            $sql = "INSERT INTO {$table_users}
                (user_type, group_id, user_permissions, user_ip, user_regdate,
                 username, username_clean, user_password, user_email,
                 user_lang, user_timezone, user_dateformat, user_sig)
                VALUES
                (:user_type, :group_id, :user_permissions, :user_ip, :user_regdate,
                 :username, :username_clean, :user_password, :user_email,
                 :user_lang, :user_timezone, :user_dateformat, :user_sig)";

            $st = $pdo->prepare($sql);
            $st->execute([
                ':user_type'        => $user_type,
                ':group_id'         => $group_id,
                ':user_permissions' => $user_permissions,
                ':user_ip'          => $ip,
                ':user_regdate'     => $now,
                ':username'         => $username,
                ':username_clean'   => $username_clean,
                ':user_password'    => $hash,
                ':user_email'       => $email,
                ':user_lang'        => $user_lang,
                ':user_timezone'    => $user_timezone,
                ':user_dateformat'  => $user_dateformat,
                ':user_sig'         => $user_sig,
            ]);

            $new_id = (int)$pdo->lastInsertId();
            if ($new_id <= 0) {
                return ['ok' => false, 'message' => 'Registration failed (no user id).'];
            }

            // Load full row so downstream uses the same shape as login()
            $st = $pdo->prepare("SELECT * FROM {$table_users} WHERE user_id = :id LIMIT 1");
            $st->execute([':id' => $new_id]);
            $row = $st->fetch();

            if (!$row) {
                return ['ok' => false, 'message' => 'User created but could not reload phpBB user row.'];
            }

            return ['ok' => true, 'user' => $row];

        } catch (Throwable $e) {
            $this->log->error('phpbb_register_insert_failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Registration insert failed.'];
        }
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

        $row = $this->find_user($username_or_email, $cfg);
        if (!$row) {
            return ['ok' => false, 'message' => 'Invalid username/email or password.'];
        }

        $hash = (string)($row['user_password'] ?? '');
        if ($hash === '') {
            return ['ok' => false, 'message' => 'Invalid username/email or password.'];
        }

        if (!$this->verify_phpbb_password($password, $hash)) {
            return ['ok' => false, 'message' => 'Invalid username/email or password.'];
        }

        return ['ok' => true, 'user' => $row];
    }

    private function verify_phpbb_password(string $password, string $hash): bool {
        // Modern hashes handled by password_verify (argon2/bcrypt).
        if (strpos($hash, '$argon2') === 0 || strpos($hash, '$2') === 0) {
            return password_verify($password, $hash);
        }

        // phpBB portable hashes ($H$ / $P$) (phpass)
        if (strpos($hash, '$H$') === 0 || strpos($hash, '$P$') === 0) {
            return hash_equals($this->phpbb_hash($password, $hash), $hash);
        }

        // legacy md5
        if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
            return hash_equals(md5($password), strtolower($hash));
        }

        // unknown
        return false;
    }

    // ---------------- phpBB legacy portable hashing implementation ($H$/$P$) ----------------

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
