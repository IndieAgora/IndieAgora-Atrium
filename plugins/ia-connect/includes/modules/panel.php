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

    $signature = (string) get_user_meta($target_wp, IA_CONNECT_META_SIGNATURE, true);
    $signature = trim($signature);

    $settings = ia_connect_get_settings();

    ?>
    <div class="iac-top-search" data-iac-style="<?php echo esc_attr(ia_connect_get_user_style($me)); ?>">
      <div class="iac-search">
        <input type="text" class="iac-search-input" placeholder="Search users, posts, comments" autocomplete="off" />
        <div class="iac-search-results" hidden></div>
      </div>
    </div>

    <div class="iac-profile" data-iac-profile data-iac-style="<?php echo esc_attr(ia_connect_get_user_style($me)); ?>" data-wall-wp="<?php echo (int)$target_wp; ?>" data-wall-phpbb="<?php echo (int)$target_phpbb; ?>">
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

      <div class="iac-head<?php echo ($signature !== '') ? ' has-signature' : ''; ?>">
        <div class="iac-avatar" data-iac-view="<?php echo esc_attr($profile); ?>">
          <img class="iac-avatar-img" src="<?php echo esc_url($profile); ?>" alt="Profile" />
          <?php if ($is_me): ?>
            <button type="button" class="iac-avatar-change" data-iac-change="profile">Change</button>
          <?php endif; ?>

          <?php if (!$is_me): ?>
          <button type="button" class="iac-rel iac-rel-follow" data-iac-follow-user data-target-phpbb="<?php echo (int)$target_phpbb; ?>" aria-label="Follow">
            <span class="iac-ic" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
                <path class="iac-plus" d="M19 8v6M16 11h6"/>
              </svg>
            </span>
          </button>
          <button type="button" class="iac-rel iac-rel-block" data-iac-block-user data-target-phpbb="<?php echo (int)$target_phpbb; ?>" aria-label="Block">
            <span class="iac-ic" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M5.5 5.5l13 13"/>
              </svg>
            </span>
          </button>
          <?php endif; ?>
        </div>

        <div class="iac-meta">
          <div class="iac-name"><?php echo esc_html($display); ?></div>
          <div class="iac-sub"><?php echo $is_me ? 'Your profile' : '@' . esc_html($username); ?></div>

          <?php if ($signature !== ''): ?>
            <div class="iac-signature" data-iac-signature>
              <div class="iac-signature-divider"></div>
              <div class="iac-signature-body"><?php echo nl2br(esc_html($signature)); ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="iac-actions">
          <?php if ($is_me || $is_admin || ia_connect_user_allows_messages($target_wp)): ?>
          <button type="button" class="iac-msg" data-iac-message>
            <span class="iac-msg-ic">✉</span>
            <span>Message</span>
          </button>
          <?php endif; ?>

          <?php if (!$is_me): ?>
          <button type="button" class="iac-rel iac-rel-follow" data-iac-follow-user data-target-phpbb="<?php echo (int)$target_phpbb; ?>" aria-label="Follow">
            <span class="iac-ic" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
                <path class="iac-plus" d="M19 8v6M16 11h6"/>
              </svg>
            </span>
          </button>
          <button type="button" class="iac-rel iac-rel-block" data-iac-block-user data-target-phpbb="<?php echo (int)$target_phpbb; ?>" aria-label="Block">
            <span class="iac-ic" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M5.5 5.5l13 13"/>
              </svg>
            </span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="iac-tabs">
        <button type="button" class="iac-tab is-active" data-iac-tab="content">Content</button>
        <button type="button" class="iac-tab" data-iac-tab="discuss">Discuss</button>
        <button type="button" class="iac-tab" data-iac-tab="stream">Stream</button>
        <?php if ($is_me || $is_admin): ?>
        <button type="button" class="iac-tab" data-iac-tab="followers">Followers</button>
        <?php endif; ?>
        <?php if ($is_me): ?>
        <button type="button" class="iac-tab" data-iac-tab="settings">Settings</button>
        <?php endif; ?>
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

        
        <section class="iac-panel ia-discuss-root" data-iac-panel="discuss" data-iac-activity-root data-iac-activity-scope="discuss" data-target-wp="<?php echo (int)$target_wp; ?>">
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
        <section class="iac-panel" data-iac-panel="followers" data-iac-followers>
          <div class="iac-card">
            <div class="iac-followers-head">
              <div class="iac-followers-title">Followers <span class="iac-followers-count" data-iac-followers-count>0</span></div>
              <div class="iac-followers-search">
                <input type="text" class="iac-input" placeholder="Search followers" autocomplete="off" data-iac-followers-q />
                <div class="iac-followers-hint">Results update as you type.</div>
              </div>
            </div>
            <div class="iac-followers-list" data-iac-followers-list></div>
          </div>
        </section>
        <?php endif; ?>

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



<?php if ($is_me): ?>
<section class="iac-panel" data-iac-panel="settings">
          <div class="iac-settings">

            <div class="iac-setting" data-iac-settings-section="displayname">
              <div class="iac-setting-title">Display name</div>
              <div class="iac-setting-sub">Shown across Atrium (posts, comments, messages). Your username stays the same for login and emails. Leave blank to use your username.</div>
              <input type="text" class="iac-input" data-iac-displayname-input maxlength="40" placeholder="Display name" />
              <div class="iac-setting-hint">Allowed: letters, numbers, spaces, underscore, dash, dot (2–40 chars). Supports @mentions and search.</div>
              <button type="button" class="iac-post" data-iac-displayname-save>Save display name</button>
              <div class="iac-setting-hint" data-iac-displayname-status></div>
            </div>


            <div class="iac-setting" data-iac-settings-section="signature">
              <div class="iac-setting-title">Bio signature</div>
              <div class="iac-setting-sub">Shown on your Connect profile. Optionally display it below your Discuss posts.</div>
              <textarea class="iac-textarea" rows="3" maxlength="500" placeholder="Write a short bio…" data-iac-signature-input></textarea>
              <label class="iac-switch"><input type="checkbox" data-iac-signature-show-discuss /> <span>Show below my Discuss posts</span></label>
              <button type="button" class="iac-post" data-iac-signature-save>Save signature</button>
              <div class="iac-setting-hint" data-iac-signature-status></div>
            </div>

            <div class="iac-setting" data-iac-settings-section="homepage">
              <div class="iac-setting-title">Homepage</div>
              <div class="iac-setting-sub">Choose which Atrium surface opens first when you enter IndieAgora without a deep link.</div>
              <select class="iac-input" data-iac-home-tab-input>
                <option value="connect">Connect</option>
                <option value="discuss">Discuss</option>
                <option value="stream">Stream</option>
              </select>
              <div class="iac-setting-hint">This only affects plain homepage entry. Direct links to profiles, topics, videos, or other deep routes still open where they point.</div>
              <button type="button" class="iac-post" data-iac-home-tab-save>Save homepage</button>
              <div class="iac-setting-hint" data-iac-home-tab-status></div>
            </div>

            <div class="iac-setting" data-iac-settings-section="style">
              <div class="iac-setting-title">Style</div>
              <div class="iac-setting-sub">Choose the colour style for Connect. Default and Black stay unchanged. The imported styles reuse the Black layout baseline with their own colour families.</div>
              <select class="iac-input" data-iac-style-input>
                <option value="default">Default</option>
                <option value="black">Black</option>
                <option value="calm">Calm</option>
                <option value="dawn">Dawn</option>
                <option value="earth">Earth</option>
                <option value="flame">Flame</option>
                <option value="leaf">Leaf</option>
                <option value="night">Night</option>
                <option value="sun">Sun</option>
                <option value="twilight">Twilight</option>
                <option value="water">Water</option>
              </select>
              <div class="iac-setting-hint">Default and Black are left as-is. The imported styles only recolour the Black-style surfaces.</div>
              <button type="button" class="iac-post" data-iac-style-save>Save style</button>
              <div class="iac-setting-hint" data-iac-style-status></div>
            </div>

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
              <div class="iac-setting-sub">Temporarily disables your Atrium account. Stream data is not touched. Log in again to reactivate.</div>
              <label class="iac-switch"><input type="checkbox" data-iac-acct-deactivate-confirm /> <span>I understand</span></label>
              <button type="button" class="iac-post iac-danger" data-iac-acct-deactivate disabled>Deactivate</button>
              <div class="iac-setting-hint" data-iac-acct-deactivate-status></div>
            </div>

            <div class="iac-setting" data-iac-settings-section="delete">
              <div class="iac-setting-title">Delete account</div>
              <div class="iac-setting-sub">Permanently deletes your Atrium account. This cannot be undone.</div>
              <label class="iac-switch"><input type="checkbox" data-iac-acct-delete-confirm /> <span>I understand this is permanent</span></label>
              <input type="password" class="iac-input" placeholder="Current password" data-iac-acct-delete-pass />
              <button type="button" class="iac-post iac-danger" data-iac-acct-delete disabled>Delete account</button>
              <div class="iac-setting-hint" data-iac-acct-delete-status></div>
            </div>
          </div>
        </section>
<?php endif; ?>
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
            <button type="button" class="iac-modal-ico" data-iac-post-jump-comments aria-label="Comments" title="Comments">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
              <span class="iac-modal-badge" data-iac-post-comment-count>0</span>
            </button>
            <button type="button" class="iac-modal-ico" data-iac-post-copy aria-label="Copy link" title="Copy link">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 1 0-7l1-1a5 5 0 0 1 7 7l-1 1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M14 11a5 5 0 0 1 0 7l-1 1a5 5 0 0 1-7-7l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <button type="button" class="iac-modal-ico" data-iac-post-share aria-label="Share" title="Share">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M16 6l-4-4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M12 2v13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <button type="button" class="iac-modal-ico" data-iac-post-follow aria-label="Follow" title="Follow">
              <svg viewBox="0 0 24 24" aria-hidden="true" data-iac-post-follow-ico>
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
                <path class="iac-follow-plus" d="M19 8v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path class="iac-follow-plus" d="M16 11h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path class="iac-follow-minus" d="M16 11h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
	          <button type="button" class="iac-modal-ico" data-iac-post-edit aria-label="Edit" title="Edit" hidden>
	            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75Z" fill="currentColor"/></svg>
	          </button>
	          <button type="button" class="iac-modal-ico" data-iac-post-delete aria-label="Delete" title="Delete" hidden>
	            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 14H7L6 7Zm3-3h6l1 2H8l1-2Zm-4 2h14v2H5V6Z" fill="currentColor"/></svg>
	          </button>
          </div>
        </div>
        <div class="iac-modal-body" data-iac-post-body></div>
        <div class="iac-modal-compose">
          <textarea class="iac-comment-input" rows="1" placeholder="Write a comment... Use @username" data-iac-post-comment></textarea>
          <button type="button" class="iac-comment-send" data-iac-post-comment-send>Send</button>
        </div>
        <div class="iac-modal-comments" data-iac-post-comments></div>
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

    <div class="iac-confirm" data-iac-confirm hidden>
      <div class="iac-confirm-backdrop" data-iac-confirm-cancel></div>
      <div class="iac-confirm-sheet" role="dialog" aria-modal="true" aria-labelledby="iac-confirm-title">
        <div class="iac-confirm-title" id="iac-confirm-title">Confirm</div>
        <div class="iac-confirm-msg" data-iac-confirm-msg>Are you sure?</div>
        <div class="iac-confirm-actions">
          <button type="button" class="iac-confirm-btn iac-confirm-cancel" data-iac-confirm-cancel>Cancel</button>
          <button type="button" class="iac-confirm-btn iac-confirm-ok" data-iac-confirm-ok>OK</button>
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

    // If not found, also accept display_name (for @mentions/search using display name).
    if ($username !== '' && $target_wp === $me) {
      try {
        global $wpdb;
        $tbl = $wpdb->users;
        $wpid = (int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$tbl} WHERE display_name=%s LIMIT 1", $username));
        if ($wpid > 0) {
          $u = get_userdata($wpid);
          if ($u) $target_wp = $wpid;
        }
      } catch (Throwable $e) {}
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
