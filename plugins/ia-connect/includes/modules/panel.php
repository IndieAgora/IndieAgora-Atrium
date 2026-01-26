<?php
if (!defined('ABSPATH')) exit;

class IA_Connect_Module_Panel {

  public static function render(): void {
    $me = (int) get_current_user_id();
    if ($me <= 0) {
      echo '<div class="iac-wrap"><div class="iac-card">Please log in.</div></div>';
      return;
    }

    $profile_ctx = self::resolve_profile_context($me);
    $target_wp = $profile_ctx['wp_user_id'];
    $target_phpbb = $profile_ctx['phpbb_user_id'];
    $is_me = ($target_wp === $me);

    $is_admin = ia_connect_viewer_is_admin($me);

    // Privacy: block profile view for non-admin viewers when user is not searchable.
    if (!$is_me && !$is_admin && !ia_connect_user_profile_searchable($target_wp)) {
      echo '<div class="iac-wrap"><div class="iac-card">User privacy settings prohibit you to see this profile.</div></div>';
      return;
    }

    // SEO visibility: discourage indexing when disabled.
    if (!ia_connect_user_seo_visible($target_wp)) {
      static $iac_noindex_hooked = false;
      if (!$iac_noindex_hooked) {
        $iac_noindex_hooked = true;
        add_action('wp_head', function () {
          echo "\n<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
        }, 1);
      }
    }


    $user = get_userdata($target_wp);
    $display = $user ? ($user->display_name ?: $user->user_login) : 'User';
    $username = $user ? $user->user_login : 'user';

    $profile = (string) get_user_meta($target_wp, IA_CONNECT_META_PROFILE, true);
    if ($profile === '') $profile = ia_connect_avatar_url($target_wp, 256);

    $cover = (string) get_user_meta($target_wp, IA_CONNECT_META_COVER, true);

    $settings = ia_connect_get_settings();

    ?>
    <div class="iac-top-search">
      <div class="iac-search">
        <input type="text" class="iac-search-input" placeholder="Search users, posts, comments" autocomplete="off" />
        <div class="iac-search-results" hidden></div>
      </div>
    </div>

    <div class="iac-profile" data-iac-profile data-wall-wp="<?php echo (int)$target_wp; ?>" data-wall-phpbb="<?php echo (int)$target_phpbb; ?>">
      <div class="iac-cover" data-iac-view="<?php echo esc_attr($cover ?: $profile); ?>">
        <?php if ($cover): ?>
          <img class="iac-cover-img" src="<?php echo esc_url($cover); ?>" alt="Cover" />
        <?php else: ?>
          <div class="iac-cover-fallback"></div>
        <?php endif; ?>

        <?php if ($is_me): ?>
          <button type="button" class="iac-cover-change" data-iac-change="cover">Change cover</button>
        <?php endif; ?>
      </div>

      <div class="iac-head">
        <div class="iac-avatar" data-iac-view="<?php echo esc_attr($profile); ?>">
          <img class="iac-avatar-img" src="<?php echo esc_url($profile); ?>" alt="Profile" />
          <?php if ($is_me): ?>
            <button type="button" class="iac-avatar-change" data-iac-change="profile">Change</button>
          <?php endif; ?>
        </div>

        <div class="iac-meta">
          <div class="iac-name"><?php echo esc_html($display); ?></div>
          <div class="iac-sub"><?php echo $is_me ? 'Your profile' : '@' . esc_html($username); ?></div>
        </div>

        <div class="iac-actions">
          <?php if ($is_me || $is_admin || ia_connect_user_allows_messages($target_wp)): ?>
          <button type="button" class="iac-msg" data-iac-message>
            <span class="iac-msg-ic">✉</span>
            <span>Message</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="iac-tabs">
        <button type="button" class="iac-tab is-active" data-iac-tab="content">Content</button>
        <button type="button" class="iac-tab" data-iac-tab="discuss">Discuss</button>
        <button type="button" class="iac-tab" data-iac-tab="stream">Stream</button>
        <button type="button" class="iac-tab" data-iac-tab="settings">Settings</button>
        <?php if ($is_me || $is_admin): ?>
        <button type="button" class="iac-tab" data-iac-tab="privacy">Privacy</button>
        <?php endif; ?>
      </div>

      <div class="iac-tabpanels">
        <section class="iac-panel is-active" data-iac-panel="content">

          <div class="iac-composer" data-iac-composer>
            <div class="iac-composer-title">Create post</div>
            <div class="iac-composer-fields">
              <input class="iac-input" type="text" placeholder="Title" data-iac-title />
              <textarea class="iac-textarea" placeholder="What's happening? Use @username to tag." data-iac-body></textarea>

              <div class="iac-attach-row">
                <label class="iac-attach-btn">
                  <input type="file" multiple data-iac-files accept="image/*,video/*,application/pdf,.doc,.docx,.txt,.zip,.rar" />
                  Attach
                </label>
                <div class="iac-attach-meta" data-iac-files-meta></div>
              </div>

              <div class="iac-attach-preview" data-iac-preview hidden></div>

              <div class="iac-composer-actions">
                <button type="button" class="iac-post" data-iac-post>Post</button>
              </div>
            </div>
          </div>

          <div class="iac-feed" data-iac-feed>
            <div class="iac-feed-inner" data-iac-feed-inner></div>
            <div class="iac-loadmore-wrap">
              <button type="button" class="iac-loadmore" data-iac-loadmore>Load more</button>
            </div>
          </div>

        </section>

        
        <section class="iac-panel" data-iac-panel="discuss" data-iac-activity-root data-iac-activity-scope="discuss" data-target-wp="<?php echo (int)$target_wp; ?>">
          <div class="iac-activity">
            <div class="iac-activity-top">
              <div class="iac-activity-subtabs">
                <button type="button" class="iac-subtab is-active" data-iac-activity-type="agoras_created">Agoras created</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="agoras_joined">Agoras joined</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="topics_created">Topics created</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="replies">Replies</button>
              </div>
              <div class="iac-activity-search">
                <input type="text" class="iac-input" placeholder="Search Discuss activity" autocomplete="off" data-iac-activity-q />
                <div class="iac-activity-hint">Results update as you type.</div>
              </div>
            </div>

            <div class="iac-activity-list" data-iac-activity-list></div>
            <div class="iac-activity-more">
              <button type="button" class="iac-loadmore" data-iac-activity-more>Load more</button>
            </div>
          </div>
        </section>

        <section class="iac-panel" data-iac-panel="stream" data-iac-activity-root data-iac-activity-scope="stream" data-target-wp="<?php echo (int)$target_wp; ?>">
          <div class="iac-activity">
            <div class="iac-activity-top">
              <div class="iac-activity-subtabs">
                <button type="button" class="iac-subtab is-active" data-iac-activity-type="videos">Videos</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="comments">Comments</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="likes">Likes</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="subscriptions">Subscriptions</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="playlists">Playlists</button>
                <button type="button" class="iac-subtab" data-iac-activity-type="history">History</button>
              </div>
              <div class="iac-activity-search">
                <input type="text" class="iac-input" placeholder="Search Stream activity" autocomplete="off" data-iac-activity-q />
                <div class="iac-activity-hint">Results update as you type.</div>
              </div>
            </div>

            <div class="iac-activity-list" data-iac-activity-list></div>
            <div class="iac-activity-more">
              <button type="button" class="iac-loadmore" data-iac-activity-more>Load more</button>
            </div>
          </div>
        </section>

        <?php if ($is_me || $is_admin): ?>
        <?php $privacy = ia_connect_get_user_privacy($target_wp); ?>
        <section class="iac-panel" data-iac-panel="privacy" data-iac-privacy-root data-target-wp="<?php echo (int)$target_wp; ?>">
          <div class="iac-settings">
            <div class="iac-setting">
              <div class="iac-setting-title">Discuss content visible to others?</div>
              <label class="iac-switch">
                <input type="checkbox" data-iac-privacy-key="discuss_visible" <?php echo !empty($privacy['discuss_visible']) ? 'checked' : ''; ?> />
                <span>Yes</span>
              </label>
            </div>

            <div class="iac-setting">
              <div class="iac-setting-title">Stream content visible to others?</div>
              <label class="iac-switch">
                <input type="checkbox" data-iac-privacy-key="stream_visible" <?php echo !empty($privacy['stream_visible']) ? 'checked' : ''; ?> />
                <span>Yes</span>
              </label>
            </div>

            <div class="iac-setting">
              <div class="iac-setting-title">Connect profile searchable by other users?</div>
              <div class="iac-setting-sub">If disabled, your profile won't appear in search and direct access is blocked.</div>
              <label class="iac-switch">
                <input type="checkbox" data-iac-privacy-key="searchable" <?php echo !empty($privacy['searchable']) ? 'checked' : ''; ?> />
                <span>Yes</span>
              </label>
            </div>

            <div class="iac-setting">
              <div class="iac-setting-title">Connect profile seen by search engines?</div>
              <div class="iac-setting-sub">If disabled, we add noindex/nofollow.</div>
              <label class="iac-switch">
                <input type="checkbox" data-iac-privacy-key="seo" <?php echo !empty($privacy['seo']) ? 'checked' : ''; ?> />
                <span>Yes</span>
              </label>
            </div>

            <div class="iac-setting">
              <div class="iac-setting-title">Can other users message you?</div>
              <div class="iac-setting-sub">Controls Message button and ia-message search/DM initiation.</div>
              <label class="iac-switch">
                <input type="checkbox" data-iac-privacy-key="allow_messages" <?php echo !empty($privacy['allow_messages']) ? 'checked' : ''; ?> />
                <span>Yes</span>
              </label>
            </div>

            <div class="iac-setting">
              <button type="button" class="iac-post" data-iac-privacy-save>Save privacy settings</button>
              <span class="iac-privacy-status" data-iac-privacy-status></span>
            </div>
          </div>
        </section>
        <?php endif; ?>



<section class="iac-panel" data-iac-panel="settings">
          <div class="iac-settings">

            <div class="iac-setting" data-iac-settings-section="reset">
              <div class="iac-setting-title">Reset password</div>
              <div class="iac-setting-sub">Send yourself a password reset email. This is handled by Atrium login (no PeerTube integration).</div>
              <button type="button" class="iac-post" data-iac-acct-reset>Send reset email</button>
              <div class="iac-setting-hint" data-iac-acct-reset-status></div>
            </div>

            <div class="iac-setting" data-iac-settings-section="export">
              <div class="iac-setting-title">Export data</div>
              <div class="iac-setting-sub">Download a ZIP containing your Connect posts/comments/attachments and your Discuss topics/replies.</div>
              <button type="button" class="iac-post" data-iac-acct-export>Generate export</button>
              <div class="iac-setting-hint" data-iac-acct-export-status></div>
            </div>

            <div class="iac-setting" data-iac-settings-section="deactivate">
              <div class="iac-setting-title">Deactivate account</div>
              <div class="iac-setting-sub">Temporarily disables your Atrium account (phpBB + WordPress). PeerTube is not touched. Log in again to reactivate.</div>
              <label class="iac-switch"><input type="checkbox" data-iac-acct-deactivate-confirm /> <span>I understand</span></label>
              <button type="button" class="iac-post iac-danger" data-iac-acct-deactivate disabled>Deactivate</button>
              <div class="iac-setting-hint" data-iac-acct-deactivate-status></div>
            </div>

            <div class="iac-setting" data-iac-settings-section="delete">
              <div class="iac-setting-title">Delete account</div>
              <div class="iac-setting-sub">Permanently deletes your Atrium account (phpBB + WordPress). This cannot be undone.</div>
              <label class="iac-switch"><input type="checkbox" data-iac-acct-delete-confirm /> <span>I understand this is permanent</span></label>
              <input type="password" class="iac-input" placeholder="Current password" data-iac-acct-delete-pass />
              <button type="button" class="iac-post iac-danger" data-iac-acct-delete disabled>Delete account</button>
              <div class="iac-setting-hint" data-iac-acct-delete-status></div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="iac-viewer" data-iac-viewer hidden>
      <div class="iac-viewer-backdrop" data-iac-viewer-close></div>
      <div class="iac-viewer-sheet">
        <button type="button" class="iac-viewer-x" data-iac-viewer-close aria-label="Close">×</button>
        <div class="iac-viewer-body" data-iac-viewer-body></div>
      </div>
    </div>

    <div class="iac-modal" data-iac-post-modal hidden>
      <div class="iac-modal-backdrop" data-iac-post-close></div>
      <div class="iac-modal-sheet">
        <div class="iac-modal-top">
          <button type="button" class="iac-modal-back" data-iac-post-close aria-label="Back">←</button>
          <div class="iac-modal-title">Post</div>
          <div class="iac-modal-actions">
            <button type="button" class="iac-modal-act" data-iac-post-copy>Copy link</button>
            <button type="button" class="iac-modal-act" data-iac-post-share>Share</button>
          </div>
        </div>
        <div class="iac-modal-body" data-iac-post-body></div>
        <div class="iac-modal-comments" data-iac-post-comments></div>
        <div class="iac-modal-compose">
          <input class="iac-comment-input" type="text" placeholder="Write a comment... Use @username" data-iac-post-comment />
          <button type="button" class="iac-comment-send" data-iac-post-comment-send>Send</button>
        </div>
      </div>
    </div>

    <div class="iac-share" data-iac-share-modal hidden>
      <div class="iac-share-backdrop" data-iac-share-close></div>
      <div class="iac-share-sheet">
        <div class="iac-share-top">
          <div class="iac-share-title">Share</div>
          <button type="button" class="iac-share-x" data-iac-share-close aria-label="Close">×</button>
        </div>
        <div class="iac-share-body">
          <div class="iac-share-row">
            <button type="button" class="iac-share-btn" data-iac-share-self>Share to my wall</button>
          </div>
          <div class="iac-share-sub">Share to other users</div>
          <div class="iac-share-search">
            <input type="text" class="iac-share-input" placeholder="Search users" autocomplete="off" data-iac-share-search />
            <div class="iac-share-results" data-iac-share-results></div>
          </div>
          <div class="iac-share-picked" data-iac-share-picked></div>
          <div class="iac-share-actions">
            <button type="button" class="iac-share-btn" data-iac-share-send disabled>Share to selected</button>
          </div>
        </div>
      </div>
    </div>

    <input type="file" hidden data-iac-filepick-profile accept="image/*" />
    <input type="file" hidden data-iac-filepick-cover accept="image/*" />
    <?php
  }

  private static function resolve_profile_context(int $me): array {
    $phpbb_id = isset($_GET['ia_profile']) ? (int) $_GET['ia_profile'] : 0;
    $username = isset($_GET['ia_profile_name']) ? sanitize_user((string) wp_unslash($_GET['ia_profile_name']), true) : '';

    // Default to self.
    $target_wp = $me;

    // Try map by username first.
    if ($username !== '') {
      $u = get_user_by('login', $username);
      if (!$u) $u = get_user_by('slug', $username);
      if ($u) $target_wp = (int)$u->ID;
    }

    // Try map by phpBB id (canonical table first, then user meta).
    if ($phpbb_id > 0) {
      global $wpdb;
      $map = $wpdb->prefix . 'phpbb_user_map';
      $has_map = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map));
      if ($has_map) {
        $wpid = (int)$wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM $map WHERE phpbb_user_id=%d LIMIT 1", $phpbb_id));
        if ($wpid > 0) {
          // Guard: only accept mapping if the WP user actually exists (avoids blank avatars when map points to a missing shadow user).
          $u = get_userdata($wpid);
          if ($u) $target_wp = $wpid;
        }
}

      // Fallback: meta mapping (legacy)
      if ($target_wp === $me) {
        $q = new WP_User_Query([
          'meta_query' => [
            'relation' => 'OR',
            [ 'key' => 'ia_phpbb_user_id', 'value' => $phpbb_id, 'compare' => '=' ],
            [ 'key' => 'phpbb_user_id', 'value' => $phpbb_id, 'compare' => '=' ],
          ],
          'number' => 1,
          'fields' => ['ID'],
        ]);
        $r = $q->get_results();
        if (!empty($r)) $target_wp = (int)$r[0]->ID;
      }
    }

    $target_phpbb = ia_connect_user_phpbb_id($target_wp);
    return [
      'wp_user_id' => $target_wp,
      'phpbb_user_id' => $target_phpbb,
    ];
  }
}
