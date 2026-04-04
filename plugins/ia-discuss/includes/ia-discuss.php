<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss Orchestrator (Safe Boot)
 */

if (!function_exists('ia_discuss_boot')) {

  function ia_discuss_require_if_exists(string $path): bool {
    if (file_exists($path)) {
      require_once $path;
      return true;
    }
    return false;
  }

  function ia_discuss_admin_notice(string $msg): void {
    if (!is_admin()) return;
    add_action('admin_notices', function () use ($msg) {
      echo '<div class="notice notice-error"><p><strong>IA Discuss:</strong> ' . esc_html($msg) . '</p></div>';
    });
  }

  function ia_discuss_boot(): void {

    if (!defined('IA_DISCUSS_PATH')) {
      ia_discuss_admin_notice('IA_DISCUSS_PATH is not defined. Root loader may be corrupted.');
      return;
    }

    $core = IA_DISCUSS_PATH . 'includes/functions.php';
    if (!file_exists($core)) {
      ia_discuss_admin_notice('Missing core file: includes/functions.php');
      return;
    }
    require_once $core;

    if (function_exists('ia_discuss_meta_boot')) ia_discuss_meta_boot();

    // Support
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/support/security.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/support/assets.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/support/ajax.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/support/user-rel-ajax.php');

    // Render helpers
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/render/text.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/render/bbcode.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/render/attachments.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/render/media.php');

    // Services
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/auth.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/membership.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/agora-privacy.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/reports.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/notify.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/phpbb.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/text.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/upload.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/services/phpbb-write.php');

    // Modules
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/module-interface.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/panel.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/feed.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/topic.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/agoras.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/membership.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/forum-meta.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/moderation.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/upload.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/write.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/diag.php');
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/agora-create.php');

    // ✅ NEW: Search module
    ia_discuss_require_if_exists(IA_DISCUSS_PATH . 'includes/modules/search.php');

    $boots = [
      'ia_discuss_support_security_boot',
      'ia_discuss_support_assets_boot',
      'ia_discuss_support_ajax_boot',

      'ia_discuss_module_panel_boot',
      'ia_discuss_module_feed_boot',
      'ia_discuss_module_topic_boot',
      'ia_discuss_module_agoras_boot',
      'ia_discuss_module_upload_boot',
      'ia_discuss_module_write_boot',
      'ia_discuss_module_diag_boot',
    ];

    foreach ($boots as $fn) {
      if (function_exists($fn)) {
        try { $fn(); }
        catch (\Throwable $e) { ia_discuss_admin_notice($fn . ' failed: ' . $e->getMessage()); }
      }
    }

    try {
      $phpbb  = new IA_Discuss_Service_PhpBB();
      $bbcode = new IA_Discuss_Render_BBCode();
      $media  = new IA_Discuss_Render_Media();
      $atts   = new IA_Discuss_Render_Attachments();

      $auth   = new IA_Discuss_Service_Auth($phpbb);
      $membership = new IA_Discuss_Service_Membership($phpbb, $auth);
      $membership->boot();
      $privacy = new IA_Discuss_Service_Agora_Privacy($phpbb);
      $privacy->boot();
      $reports = new IA_Discuss_Service_Reports($phpbb);
      $reports->boot();
      $notify = new IA_Discuss_Service_Notify($phpbb, $auth);
      $notify->set_membership_service($membership);
      $notify->boot();

      // Expose topic participant IDs to other Atrium plugins (e.g. IA Notify).
      // Returns an array of canonical phpBB user IDs who have posted in the topic.
      add_filter('ia_discuss_topic_participants', function ($ids, $topic_id) use ($notify) {
        try {
          return $notify->list_topic_participants((int)$topic_id);
        } catch (Throwable $e) {
          return is_array($ids) ? $ids : [];
        }
      }, 10, 2);
      $upload = new IA_Discuss_Service_Upload();
      $write  = new IA_Discuss_Service_PhpBB_Write($phpbb, $auth);
      $write->boot();

      // Wire cron handler.
      add_action('ia_discuss_agora_inactivity_tick', function () use ($membership, $notify) {
        try { $membership->cron_inactivity_tick($notify); } catch (Throwable $e) {}
      });

      // --- Self-heal: backfill missing moderator_cache markers for agoras ---
      // In this project, "Agora created" is represented by a row in phpBB's
      // {prefix}moderator_cache. If forums exist without the marker row, profile
      // views and moderation badges become inconsistent. We:
      // 1) Run a one-time repair after update/first boot.
      // 2) Schedule a daily lightweight repair via wp-cron.
      add_action('ia_discuss_repair_agora_modcache_tick', function () use ($phpbb) {
        try {
          if (!function_exists('ia_discuss_repair_agora_moderator_cache')) return;
          $r = ia_discuss_repair_agora_moderator_cache($phpbb, 200);
          if (!empty($r['ok']) && !empty($r['inserted'])) {
            ia_discuss_log('AutoRepair: inserted=' . (int)$r['inserted'] . ' skipped=' . (int)($r['skipped'] ?? 0));
          }
        } catch (Throwable $e) {
          ia_discuss_log('AutoRepair: exception ' . $e->getMessage());
        }
      });

      if (!wp_next_scheduled('ia_discuss_repair_agora_modcache_tick')) {
        // Run daily at a random-ish minute to reduce collision.
        wp_schedule_event(time() + 300, 'daily', 'ia_discuss_repair_agora_modcache_tick');
      }

      // One-time repair after plugin update/first boot.
      $repair_flag = 'ia_discuss_repair_agora_modcache_v1_done';
      if (!get_option($repair_flag)) {
        try {
          if (function_exists('ia_discuss_repair_agora_moderator_cache')) {
            $r = ia_discuss_repair_agora_moderator_cache($phpbb, 500);
            if (!empty($r['ok'])) {
              update_option($repair_flag, 1, false);
              if (!empty($r['inserted'])) {
                ia_discuss_log('OneTimeRepair: inserted=' . (int)$r['inserted'] . ' skipped=' . (int)($r['skipped'] ?? 0));
              }
            }
          }
        } catch (Throwable $e) {
          ia_discuss_log('OneTimeRepair: exception ' . $e->getMessage());
        }
      }

      $modules = [
        new IA_Discuss_Module_Feed($phpbb, $bbcode, $media, $atts, $auth, $privacy),
        new IA_Discuss_Module_Topic($phpbb, $bbcode, $media, $auth, $notify, $membership, $privacy, $reports),
        new IA_Discuss_Module_Agoras($phpbb, $bbcode, $auth, $membership, $write, $privacy),
        new IA_Discuss_Module_Forum_Meta($phpbb, $bbcode, $auth, $membership, $write, $privacy),
        new IA_Discuss_Module_Moderation($phpbb, $bbcode, $auth, $membership, $write, $privacy),
        new IA_Discuss_Module_Membership($auth, $phpbb, $write, $membership, $privacy),
        new IA_Discuss_Module_Agora_Create($phpbb, $bbcode, $auth),
        new IA_Discuss_Module_Upload($upload),
        new IA_Discuss_Module_Write($phpbb, $bbcode, $auth, $write, $notify, $membership, $privacy, $reports),
        new IA_Discuss_Module_Diag($phpbb),

        // ✅ NEW: Search
        new IA_Discuss_Module_Search($phpbb),
      ];

      foreach ($modules as $m) {
        if ($m instanceof IA_Discuss_Module_Interface) $m->boot();
      }

      if (function_exists('ia_discuss_ajax') && function_exists('ia_discuss_security')) {
        ia_discuss_ajax()->boot($modules, ia_discuss_security());
      }
    } catch (\Throwable $e) {
      ia_discuss_admin_notice('Bootstrap wiring failed: ' . $e->getMessage());
    }

    if (!has_action('ia_atrium_panel_discuss')) {
      ia_discuss_admin_notice('Discuss panel hook not registered (ia_atrium_panel_discuss). Check includes/modules/panel.php');
    }
  }
}
