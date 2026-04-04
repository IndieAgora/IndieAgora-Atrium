<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Diag implements IA_Discuss_Module_Interface {

  private $phpbb;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb) {
    $this->phpbb = $phpbb;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_diag'  => ['method' => 'ajax_diag', 'public' => false],
      'ia_discuss_probe' => ['method' => 'ajax_probe', 'public' => false],
      'ia_discuss_repair_agora_mods' => ['method' => 'ajax_repair_agora_mods', 'public' => false],
    ];
  }

  public function ajax_diag(): void {
    if (!current_user_can('manage_options')) {
      ia_discuss_json_err('Admin only', 403);
    }
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    ia_discuss_json_ok([
      'resolved' => $this->phpbb->diagnostics(),
    ]);
  }

  public function ajax_probe(): void {
    if (!current_user_can('manage_options')) {
      ia_discuss_json_err('Admin only', 403);
    }
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    ia_discuss_json_ok($this->phpbb->probe());
  }

  public function ajax_repair_agora_mods(): void {
    if (!current_user_can('manage_options')) {
      ia_discuss_json_err('Admin only', 403);
    }
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    if (!function_exists('ia_discuss_repair_agora_moderator_cache')) {
      ia_discuss_json_err('Repair routine not available', 500);
    }

    $max = isset($_POST['max']) ? (int)$_POST['max'] : 500;
    $max = max(1, min(2000, $max));

    $r = ia_discuss_repair_agora_moderator_cache($this->phpbb, $max);
    ia_discuss_json_ok($r);
  }
}
