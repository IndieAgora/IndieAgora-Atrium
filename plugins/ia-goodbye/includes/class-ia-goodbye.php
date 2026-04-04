<?php
if (!defined('ABSPATH')) exit;

final class IA_Goodbye {
  private static $instance = null;

  public static function instance(): self {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
  }

  private function __construct() {
    // Ensure resurrection paths are neutralised after all plugins have registered hooks.
    
  }

  public function activate(): void {
    $this->install_tables();
  }

  private function install_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $t = $wpdb->prefix . 'ia_goodbye_tombstones';

    $sql = "CREATE TABLE {$t} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      identifier_email VARCHAR(190) NOT NULL DEFAULT '',
      identifier_username_clean VARCHAR(190) NOT NULL DEFAULT '',
      phpbb_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      peertube_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      deleted_at DATETIME NOT NULL,
      reason VARCHAR(190) NOT NULL DEFAULT '',
      PRIMARY KEY (id),
      KEY ident_email (identifier_email),
      KEY ident_uclean (identifier_username_clean),
      KEY phpbb_user_id (phpbb_user_id),
      KEY peertube_user_id (peertube_user_id)
    ) {$charset};";

    dbDelta($sql);
  }

  public static function normalize_identifier_email(string $id): string {
    $id = trim($id);
    $id = strtolower($id);
    return is_email($id) ? $id : '';
  }

  public static function normalize_identifier_uclean(string $id): string {
    $id = trim($id);
    $id = sanitize_user($id, true);
    $id = strtolower($id);
    return $id;
  }

  public static function identifier_is_tombstoned(string $identifier): bool {
    global $wpdb;
    $t = $wpdb->prefix . 'ia_goodbye_tombstones';

    $email = self::normalize_identifier_email($identifier);
    $uclean = self::normalize_identifier_uclean($identifier);

    if ($email === '' && $uclean === '') return false;

    if ($email !== '') {
      $hit = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE identifier_email=%s LIMIT 1", $email));
      if ($hit > 0) return true;
    }

    if ($uclean !== '') {
      $hit = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE identifier_username_clean=%s LIMIT 1", $uclean));
      if ($hit > 0) return true;
    }

    return false;
  }

  public static function clear_tombstone_for(string $email, string $username_clean): void {
    global $wpdb;
    $t = $wpdb->prefix . 'ia_goodbye_tombstones';
    $email = self::normalize_identifier_email($email);
    $username_clean = self::normalize_identifier_uclean($username_clean);

    if ($email !== '') {
      $wpdb->delete($t, ['identifier_email' => $email]);
    }
    if ($username_clean !== '') {
      $wpdb->delete($t, ['identifier_username_clean' => $username_clean]);
    }
  }

  public static function tombstone_identity(string $email, string $username_clean, int $phpbb_user_id = 0, int $peertube_user_id = 0, string $reason = ''): void {
    global $wpdb;
    $t = $wpdb->prefix . 'ia_goodbye_tombstones';

    $email = self::normalize_identifier_email($email);
    $username_clean = self::normalize_identifier_uclean($username_clean);
    $phpbb_user_id = (int)$phpbb_user_id;
    $peertube_user_id = (int)$peertube_user_id;
    $reason = sanitize_text_field($reason);

    // Remove any existing tombstones for these identifiers so we don't accumulate duplicates.
    self::clear_tombstone_for($email, $username_clean);

    $wpdb->insert($t, [
      'identifier_email' => $email,
      'identifier_username_clean' => $username_clean,
      'phpbb_user_id' => $phpbb_user_id,
      'peertube_user_id' => $peertube_user_id,
      'deleted_at' => current_time('mysql', 1),
      'reason' => $reason,
    ]);
  }

  /**
   * Deactivate: reversible. User can log in again (reactivation handled by IA Auth).
   * While deactivated, other plugins should hide/deny interactions based on ia_deactivated meta.
   */
  public function deactivate_account(int $wp_user_id): array {
    $wp_user_id = (int)$wp_user_id;
    if ($wp_user_id <= 0) return ['ok' => false, 'message' => 'Invalid user.'];

    if (!class_exists('IA_Auth')) return ['ok' => false, 'message' => 'IA Auth not available.'];
    $ia = IA_Auth::instance();
    $ident = is_object($ia->db) ? $ia->db->get_identity_by_wp_user_id($wp_user_id) : null;
    $phpbb_user_id = (int)($ident['phpbb_user_id'] ?? 0);

    if ($phpbb_user_id <= 0) {
      // Fallback to usermeta mapping.
      $phpbb_user_id = (int)get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
    }
    if ($phpbb_user_id <= 0) return ['ok' => false, 'message' => 'No phpBB mapping for this user.'];

    $r = $ia->phpbb->deactivate_user($phpbb_user_id);
    if (empty($r['ok'])) return ['ok' => false, 'message' => $r['message'] ?? 'Deactivation failed.'];

    update_user_meta($wp_user_id, 'ia_deactivated', 1);
    wp_update_user(['ID' => $wp_user_id, 'user_status' => 1]);

    // Purge PeerTube tokens for safety.
    $this->delete_peertube_tokens_for_phpbb_user($phpbb_user_id);

    return ['ok' => true];
  }

  /**
   * Delete: irreversible. Hard-block login by tombstoning identifiers. Keep PeerTube account intact.
   */
  public function delete_account(int $wp_user_id, string $reason = 'deleted'): array {
    $wp_user_id = (int)$wp_user_id;
    if ($wp_user_id <= 0) return ['ok' => false, 'message' => 'Invalid user.'];

    if (!class_exists('IA_Auth')) return ['ok' => false, 'message' => 'IA Auth not available.'];
    $ia = IA_Auth::instance();
    $ident = is_object($ia->db) ? $ia->db->get_identity_by_wp_user_id($wp_user_id) : null;
    $phpbb_user_id = (int)($ident['phpbb_user_id'] ?? 0);
    $pt_user_id = (int)($ident['peertube_user_id'] ?? 0);
    $email = (string)($ident['email'] ?? '');
    $uclean = (string)($ident['phpbb_username_clean'] ?? '');

    if ($phpbb_user_id <= 0) {
      $phpbb_user_id = (int)get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
    }
    if ($email === '') {
      $u = get_user_by('id', $wp_user_id);
      if ($u && !empty($u->user_email)) $email = (string)$u->user_email;
    }
    if ($uclean === '') {
      $u = get_user_by('id', $wp_user_id);
      if ($u && !empty($u->user_login)) $uclean = (string)$u->user_login;
    }

    if ($phpbb_user_id <= 0) return ['ok' => false, 'message' => 'No phpBB mapping for this user.'];

    // Hard-block future resurrection before destructive work begins.
    self::tombstone_identity($email, $uclean, $phpbb_user_id, $pt_user_id, $reason);

    // Remove first-party Atrium content owned by this account.
    $this->delete_connect_content($wp_user_id, $phpbb_user_id);
    $this->delete_message_content($phpbb_user_id);
    $this->scrub_phpbb_content($phpbb_user_id);

    // Tombstone the phpBB account record itself so it cannot authenticate again.
    $r = $ia->phpbb->delete_user_preserve_posts($phpbb_user_id, 'deleted user');
    if (empty($r['ok'])) return ['ok' => false, 'message' => $r['message'] ?? 'phpBB delete failed.'];

    // Keep the identity row as a deleted tombstone marker instead of removing it.
    global $wpdb;
    $map_t = $wpdb->prefix . 'ia_identity_map';
    $wpdb->update($map_t, [
      'wp_user_id' => null,
      'status' => 'deleted',
      'last_error' => 'deleted:' . gmdate('c'),
      'updated_at' => current_time('mysql'),
    ], ['phpbb_user_id' => $phpbb_user_id]);

    $this->delete_peertube_tokens_for_phpbb_user($phpbb_user_id);

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($wp_user_id);

    return ['ok' => true];
  }

  private function delete_connect_content(int $wp_user_id, int $phpbb_user_id): void {
    global $wpdb;

    $posts_t = $wpdb->prefix . 'ia_connect_posts';
    $atts_t = $wpdb->prefix . 'ia_connect_attachments';
    $comms_t = $wpdb->prefix . 'ia_connect_comments';
    $folls_t = $wpdb->prefix . 'ia_connect_follows';
    $rels_t = $wpdb->prefix . 'ia_user_relations';

    $post_ids = [];
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $posts_t)) === $posts_t) {
      $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$posts_t} WHERE author_phpbb_id=%d OR author_wp_id=%d",
        $phpbb_user_id,
        $wp_user_id
      )) ?: [];
      $post_ids = array_values(array_filter(array_map('intval', $post_ids)));
    }

    if (!empty($post_ids)) {
      $ph = implode(',', array_fill(0, count($post_ids), '%d'));
      if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $atts_t)) === $atts_t) {
        $sql = $wpdb->prepare("DELETE FROM {$atts_t} WHERE post_id IN ({$ph})", ...$post_ids);
        $wpdb->query($sql);
      }
      if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $comms_t)) === $comms_t) {
        $sql = $wpdb->prepare("DELETE FROM {$comms_t} WHERE post_id IN ({$ph})", ...$post_ids);
        $wpdb->query($sql);
      }
      $sql = $wpdb->prepare("DELETE FROM {$posts_t} WHERE id IN ({$ph})", ...$post_ids);
      $wpdb->query($sql);
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $comms_t)) === $comms_t) {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM {$comms_t} WHERE author_phpbb_id=%d OR author_wp_id=%d",
        $phpbb_user_id,
        $wp_user_id
      ));
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $folls_t)) === $folls_t) {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM {$folls_t} WHERE follower_phpbb_id=%d OR follower_wp_id=%d",
        $phpbb_user_id,
        $wp_user_id
      ));
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rels_t)) === $rels_t) {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM {$rels_t} WHERE src_phpbb_id=%d OR dst_phpbb_id=%d",
        $phpbb_user_id,
        $phpbb_user_id
      ));
    }
  }

  private function delete_message_content(int $phpbb_user_id): void {
    global $wpdb;

    $threads_t = $wpdb->prefix . 'ia_msg_threads';
    $members_t = $wpdb->prefix . 'ia_msg_thread_members';
    $messages_t = $wpdb->prefix . 'ia_msg_messages';
    $invites_t = $wpdb->prefix . 'ia_msg_thread_invites';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $messages_t)) === $messages_t) {
      $wpdb->delete($messages_t, ['author_phpbb_user_id' => $phpbb_user_id], ['%d']);
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $invites_t)) === $invites_t) {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM {$invites_t} WHERE inviter_phpbb_user_id=%d OR invitee_phpbb_user_id=%d",
        $phpbb_user_id,
        $phpbb_user_id
      ));
    }

    $orphan_thread_ids = [];
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $members_t)) === $members_t) {
      $orphan_thread_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT thread_id FROM {$members_t} WHERE phpbb_user_id=%d",
        $phpbb_user_id
      )) ?: [];
      $wpdb->delete($members_t, ['phpbb_user_id' => $phpbb_user_id], ['%d']);
      $orphan_thread_ids = array_values(array_filter(array_map('intval', $orphan_thread_ids)));
    }

    if (!empty($orphan_thread_ids) && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $threads_t)) === $threads_t) {
      foreach ($orphan_thread_ids as $thread_id) {
        $remaining = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(1) FROM {$members_t} WHERE thread_id=%d",
          $thread_id
        ));
        if ($remaining === 0) {
          if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $messages_t)) === $messages_t) {
            $wpdb->delete($messages_t, ['thread_id' => $thread_id], ['%d']);
          }
          if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $invites_t)) === $invites_t) {
            $wpdb->delete($invites_t, ['thread_id' => $thread_id], ['%d']);
          }
          $wpdb->delete($threads_t, ['id' => $thread_id], ['%d']);
        }
      }
    }
  }

  private function scrub_phpbb_content(int $phpbb_user_id): void {
    if ($phpbb_user_id <= 0) return;
    if (!class_exists('IA_Auth')) return;

    $cfg = (class_exists('IA_Engine') && method_exists('IA_Engine', 'phpbb_db'))
      ? IA_Engine::phpbb_db()
      : [];
    if (empty($cfg) || empty($cfg['host']) || empty($cfg['name']) || empty($cfg['user'])) return;

    $host   = (string)($cfg['host'] ?? '');
    $port   = (int)($cfg['port'] ?? 3306);
    $db     = (string)($cfg['name'] ?? '');
    $user   = (string)($cfg['user'] ?? '');
    $pass   = (string)($cfg['pass'] ?? '');
    $prefix = (string)($cfg['prefix'] ?? 'phpbb_');

    try {
      $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
      );

      $posts = $prefix . 'posts';
      $topics = $prefix . 'topics';
      $forums = $prefix . 'forums';

      $pdo->beginTransaction();

      $st = $pdo->prepare("UPDATE {$posts} SET post_subject=:subj, post_text='', post_username='deleted user' WHERE poster_id=:uid");
      $st->execute([':subj' => 'Deleted post', ':uid' => $phpbb_user_id]);

      $st = $pdo->prepare("UPDATE {$topics} SET topic_title=:title WHERE topic_poster=:uid OR topic_first_poster_id=:uid");
      $st->execute([':title' => 'Deleted topic', ':uid' => $phpbb_user_id]);

      if ($this->table_has_column($pdo, $posts, 'forum_id') && $this->table_has_column($pdo, $forums, 'forum_posts')) {
        $forum_ids = $pdo->prepare("SELECT DISTINCT forum_id FROM {$posts} WHERE poster_id=1 AND post_username='deleted user'");
        $forum_ids->execute();
      }

      $pdo->commit();
    } catch (Throwable $e) {
      if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
    }
  }

  private function table_has_column(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
    $st->execute([':c' => $column]);
    return (bool)$st->fetch();
  }

  public function delete_peertube_tokens_for_phpbb_user(int $phpbb_user_id): void {
    global $wpdb;
    $phpbb_user_id = (int)$phpbb_user_id;
    if ($phpbb_user_id <= 0) return;

    // IA Auth token store
    $t1 = $wpdb->prefix . 'ia_peertube_tokens';
    // Token mint plugin store
    $t2 = $wpdb->prefix . 'ia_peertube_user_tokens';

    $wpdb->delete($t1, ['phpbb_user_id' => $phpbb_user_id]);
    $wpdb->delete($t2, ['phpbb_user_id' => $phpbb_user_id]);
  }

}

// Public helpers (used by other plugins without requiring class autoloading).
function ia_goodbye_identifier_is_tombstoned(string $identifier): bool {
  return class_exists('IA_Goodbye') ? IA_Goodbye::identifier_is_tombstoned($identifier) : false;
}

function ia_goodbye_clear_tombstone_for(string $email, string $username_clean): void {
  if (class_exists('IA_Goodbye')) {
    IA_Goodbye::clear_tombstone_for($email, $username_clean);
  }
}
