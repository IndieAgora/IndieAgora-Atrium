<?php
if (!defined('ABSPATH')) exit;

final class IA_Post {

  private static $instance = null;

  public static function instance(): self {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
  }

  private function __construct() {
    add_action('wp_enqueue_scripts', [ 'IA_Post_Assets', 'enqueue' ], 1000);

    // Composer UI mounts inside Atrium composer modal.
    add_action('ia_atrium_composer_body', [ $this, 'render_composer_mount' ], 5);

    add_action('wp_ajax_ia_post_stream_bootstrap', [ $this, 'ajax_stream_bootstrap' ]);
    add_action('wp_ajax_ia_post_stream_upload', [ $this, 'ajax_stream_upload' ]);
  }

  public function render_composer_mount(): void {
    // Keep the shell placeholder, but our JS will hide it once mounted.
    $tpl = IA_POST_PATH . 'templates/composer-mount.php';
    if (file_exists($tpl)) include $tpl;
  }


  public function ajax_stream_bootstrap(): void {
    $this->verify_ajax();
    if (!is_user_logged_in()) wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Login required' ] ]);

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      wp_send_json([ 'success' => false, 'data' => [ 'message' => 'IA Stream API service missing' ] ]);
    }

    $status = $this->current_token_status();
    if (empty($status['ok']) || trim((string) ($status['token'] ?? '')) === '') {
      wp_send_json([
        'success' => false,
        'data' => [
          'message' => (string) ($status['error'] ?? 'PeerTube token unavailable'),
          'code' => (string) ($status['code'] ?? 'missing_user_token'),
        ],
      ]);
    }

    $api = new IA_Stream_Service_PeerTube_API();
    $api->set_token((string) $status['token']);

    $me = $api->get_me();
    if (empty($me['ok']) || !is_array($me['data'] ?? null)) {
      $msg = !empty($me['error']) ? (string) $me['error'] : 'Unable to load PeerTube account';
      wp_send_json([ 'success' => false, 'data' => [ 'message' => $msg ] ]);
    }

    $me_data = (array) $me['data'];
    $account_name = $this->extract_account_name($me_data);

    $channels  = $account_name !== '' ? $api->get_account_channels($account_name, [ 'includeCollaborations' => true, 'count' => 100, 'sort' => 'name' ]) : [ 'ok' => true, 'data' => [] ];
    $playlists = $account_name !== '' ? $api->get_account_playlists($account_name, [ 'includeCollaborations' => true, 'count' => 100, 'sort' => '-createdAt', 'playlistType' => 1 ]) : [ 'ok' => true, 'data' => [] ];
    $categories = $api->get_video_categories();
    $licences   = $api->get_video_licences();
    $languages  = $api->get_video_languages();
    $privacies  = $api->get_video_privacies();

    wp_send_json([
      'success' => true,
      'data' => [
        'accountName' => $account_name,
        'channels' => $this->normalize_channels($channels['data'] ?? []),
        'playlists' => $this->normalize_playlists($playlists['data'] ?? []),
        'categories' => $this->normalize_dict($categories['data'] ?? []),
        'licences' => $this->normalize_dict($licences['data'] ?? []),
        'languages' => $this->normalize_dict($languages['data'] ?? [], true),
        'privacies' => $this->normalize_dict($privacies['data'] ?? []),
        'commentPolicies' => [
          [ 'id' => 1, 'label' => 'Enabled' ],
          [ 'id' => 2, 'label' => 'Disabled' ],
          [ 'id' => 3, 'label' => 'Requires approval' ],
        ],
      ],
    ]);
  }

  public function ajax_stream_upload(): void {
    $this->verify_ajax();
    if (!is_user_logged_in()) wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Login required' ] ]);
    if (!class_exists('IA_Stream_Service_PeerTube_API')) wp_send_json([ 'success' => false, 'data' => [ 'message' => 'IA Stream API service missing' ] ]);

    $status = $this->current_token_status();
    if (empty($status['ok']) || trim((string) ($status['token'] ?? '')) === '') {
      wp_send_json([ 'success' => false, 'data' => [ 'message' => (string) ($status['error'] ?? 'PeerTube token unavailable'), 'code' => (string) ($status['code'] ?? 'missing_user_token') ] ]);
    }

    if (empty($_FILES['videofile']) || !is_array($_FILES['videofile'])) {
      wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Video file required' ] ]);
    }
    $file = $_FILES['videofile'];
    if (!empty($file['error'])) {
      wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Upload error code ' . (int) $file['error'] ] ]);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
      wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Invalid uploaded file' ] ]);
    }

    $channel_id = max(0, (int) ($_POST['channel_id'] ?? 0));
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($channel_id <= 0) wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Choose a channel' ] ]);
    if ($name === '') {
      $name = sanitize_text_field(pathinfo((string) ($file['name'] ?? 'video'), PATHINFO_FILENAME));
    }
    $name = mb_substr($name, 0, 120);
    if (mb_strlen($name) < 3) wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Video title must be at least 3 characters' ] ]);

    $fields = [
      'channelId' => $channel_id,
      'name' => $name,
    ];

    $map_int = [
      'privacy' => 'privacy',
      'category_id' => 'category',
      'licence_id' => 'licence',
      'comments_policy' => 'commentsPolicy',
    ];
    foreach ($map_int as $src => $dst) {
      $v = isset($_POST[$src]) ? (int) $_POST[$src] : 0;
      if ($v > 0) $fields[$dst] = $v;
    }

    $map_text = [
      'description' => 'description',
      'language_id' => 'language',
      'support' => 'support',
      'nsfw_summary' => 'nsfwSummary',
    ];
    foreach ($map_text as $src => $dst) {
      $v = trim((string) ($_POST[$src] ?? ''));
      if ($v !== '') $fields[$dst] = $v;
    }

    $fields['nsfw'] = !empty($_POST['nsfw']) ? 'true' : 'false';
    $fields['downloadEnabled'] = !empty($_POST['download_enabled']) ? 'true' : 'false';
    $fields['waitTranscoding'] = !empty($_POST['wait_transcoding']) ? 'true' : 'false';

    $tags_raw = trim((string) ($_POST['tags'] ?? ''));
    if ($tags_raw !== '') {
      $tags = preg_split('/[
,]+/', $tags_raw);
      $clean = [];
      foreach ((array) $tags as $tag) {
        $t = trim((string) $tag);
        if ($t === '') continue;
        $t = mb_substr($t, 0, 30);
        if (mb_strlen($t) < 2) continue;
        $key = mb_strtolower($t);
        if (isset($clean[$key])) continue;
        $clean[$key] = $t;
        if (count($clean) >= 5) break;
      }
      if (!empty($clean)) $fields['tags'] = array_values($clean);
    }

    $video_password = trim((string) ($_POST['video_password'] ?? ''));
    if ($video_password !== '') $fields['videoPasswords'] = [ $video_password ];

    if (!empty($_FILES['thumbnailfile']) && is_array($_FILES['thumbnailfile']) && empty($_FILES['thumbnailfile']['error']) && is_uploaded_file($_FILES['thumbnailfile']['tmp_name'])) {
      if (class_exists('CURLFile')) {
        $fields['thumbnailfile'] = new CURLFile($_FILES['thumbnailfile']['tmp_name'], (string) ($_FILES['thumbnailfile']['type'] ?? 'image/jpeg'), (string) ($_FILES['thumbnailfile']['name'] ?? 'thumbnail.jpg'));
      }
    }

    $api = new IA_Stream_Service_PeerTube_API();
    $api->set_token((string) $status['token']);

    $upload = $api->upload_video($fields, (string) $file['tmp_name'], (string) ($file['name'] ?? 'video'), (string) ($file['type'] ?? ''));
    if (empty($upload['ok'])) {
      $msg = !empty($upload['error']) ? (string) $upload['error'] : 'Video upload failed';
      if (!empty($upload['body']) && is_array($upload['body'])) {
        $msg .= ': ' . wp_json_encode($upload['body']);
      }
      wp_send_json([ 'success' => false, 'data' => [ 'message' => $msg ] ]);
    }

    $video = (array) (($upload['data']['video'] ?? []));
    $video_id = $video['id'] ?? 0;
    $video_uuid = (string) ($video['uuid'] ?? '');

    $playlist_id = max(0, (int) ($_POST['playlist_id'] ?? 0));
    $playlist_added = false;
    if ($playlist_id > 0 && ($video_id || $video_uuid !== '')) {
      $add = $api->add_video_to_playlist($playlist_id, $video_id ?: $video_uuid);
      $playlist_added = !empty($add['ok']);
    }

    $url = '';
    if ($video_uuid !== '') {
      $base = method_exists($api, 'public_base') ? rtrim((string) $api->public_base(), '/') : '';
      if ($base !== '') $url = $base . '/w/' . rawurlencode($video_uuid);
    }

    wp_send_json([
      'success' => true,
      'data' => [
        'video' => [
          'id' => $video_id,
          'uuid' => $video_uuid,
          'shortUUID' => (string) ($video['shortUUID'] ?? ''),
          'url' => $url,
        ],
        'playlistAdded' => $playlist_added,
      ],
    ]);
  }

  private function verify_ajax(): void {
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'ia_post_nonce')) {
      wp_send_json([ 'success' => false, 'data' => [ 'message' => 'Security check failed' ] ]);
    }
  }

  private function current_token_status(): array {
    if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user')) {
      try {
        return (array) IA_PeerTube_Token_Helper::get_token_status_for_current_user();
      } catch (Throwable $e) {
        return [ 'ok' => false, 'code' => 'token_helper_exception', 'error' => $e->getMessage() ];
      }
    }
    if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
      $tok = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
      return [ 'ok' => $tok !== '', 'code' => $tok !== '' ? 'valid_token' : 'missing_user_token', 'token' => $tok, 'error' => $tok !== '' ? '' : 'PeerTube token unavailable' ];
    }
    return [ 'ok' => false, 'code' => 'token_helper_missing', 'error' => 'Token helper missing' ];
  }

  private function extract_account_name(array $me_data): string {
    $candidates = [
      $me_data['username'] ?? '',
      $me_data['name'] ?? '',
      $me_data['account']['name'] ?? '',
      $me_data['videoChannel']['account']['name'] ?? '',
    ];
    foreach ($candidates as $candidate) {
      $candidate = trim((string) $candidate);
      if ($candidate !== '') return $candidate;
    }
    return '';
  }

  private function normalize_channels($raw): array {
    $list = is_array($raw) && isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : (is_array($raw) ? $raw : []);
    $out = [];
    foreach ($list as $row) {
      if (!is_array($row)) continue;
      $handle = trim((string) ($row['nameWithHost'] ?? $row['name'] ?? ''));
      $out[] = [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['displayName'] ?? $row['name'] ?? $handle),
        'handle' => $handle,
      ];
    }
    return $out;
  }

  private function normalize_playlists($raw): array {
    $list = is_array($raw) && isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : (is_array($raw) ? $raw : []);
    $out = [];
    foreach ($list as $row) {
      if (!is_array($row)) continue;
      $out[] = [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['displayName'] ?? $row['name'] ?? 'Playlist'),
      ];
    }
    return $out;
  }

  private function normalize_dict($raw, bool $allow_long = false): array {
    $src = is_array($raw) ? $raw : [];
    $out = [];
    foreach ($src as $k => $v) {
      $id = (string) $k;
      $label = trim((string) $v);
      if ($label === '') continue;
      if (!$allow_long && mb_strlen($label) > 120) continue;
      $out[] = [ 'id' => $id, 'label' => $label ];
    }
    return $out;
  }

}
