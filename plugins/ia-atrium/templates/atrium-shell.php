<?php if (!defined('ABSPATH')) exit; ?>

<?php
  $me = (int) get_current_user_id();
  $connect_style = ($me > 0 && function_exists('ia_connect_get_user_style')) ? (string) ia_connect_get_user_style($me) : 'default';
?>
<script>
(function(){
  try{ document.documentElement.setAttribute('data-iac-style', <?php echo wp_json_encode($connect_style); ?>); }catch(e){}
  try{ if (document.body) document.body.setAttribute('data-iac-style', <?php echo wp_json_encode($connect_style); ?>); }catch(e){}
})();
</script>

<div id="ia-atrium-shell"
     class="ia-atrium-shell"
     data-default-tab="<?php echo esc_attr($data['default_tab']); ?>"
     data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
     data-iac-style="<?php echo esc_attr($connect_style); ?>">

  <?php do_action('ia_atrium_shell_before'); ?>

  <?php
    $site_icon = function_exists('get_site_icon_url') ? (string) get_site_icon_url(64) : '';
    $site_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
    // Prefer the IA Connect profile photo if available; fall back to the WP avatar (e.g. Gravatar).
    if ($me > 0 && function_exists('ia_connect_avatar_url')) {
      $me_avatar = (string) ia_connect_avatar_url($me, 48);
    } else {
      $me_avatar = $me > 0 ? (string) get_avatar_url($me, ['size' => 48]) : '';
    }
  ?>

  <header class="ia-atrium-topbar" role="navigation" aria-label="Atrium navigation">
    <div class="ia-atrium-topbar-inner">
      <div class="ia-atrium-brand">
        <?php if ($site_icon): ?>
          <img class="ia-atrium-logo" src="<?php echo esc_url($site_icon); ?>" alt="Site" />
        <?php else: ?>
          <span class="ia-atrium-logo-fallback" aria-hidden="true">
            <svg viewBox="0 0 24 24" role="img" aria-label="Site">
              <path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2Zm0 1.7c4.6 0 8.3 3.7 8.3 8.3S16.6 20.3 12 20.3 3.7 16.6 3.7 12 7.4 3.7 12 3.7Zm-.8 3.2c-1.1 0-2 .9-2 2 0 .7.4 1.3.9 1.7l-2.1 6.1h1.6l1.6-4.8 1.6 4.8h1.6l-1.1-3.3 1-2.9c.5-.4.9-1 .9-1.7 0-1.1-.9-2-2-2-.6 0-1.1.3-1.5.6-.4-.4-.9-.6-1.5-.6Zm5.4 1.1-1.8 5.3-1.1-3.4.6-1.8c.1-.4.2-.7.2-1 0-.3-.1-.6-.2-.9.6.2 1 .8 1 1.5 0 .2 0 .4-.1.6l1.1 3.2 1.1-3.2c-.1-.2-.1-.4-.1-.6 0-.7.4-1.3 1-1.5-.1.3-.2.6-.2.9 0 .3.1.6.2 1l.3.9Zm-8.6 0c-.1.3-.2.6-.2.9 0 .3.1.6.2 1l1.3 3.9-1.5 4.5H6.4l2.1-6.1c-.5-.4-.9-1-.9-1.7 0-.7.4-1.3 1-1.5Z"/>
            </svg>
          </span>
        <?php endif; ?>

        <?php if (!empty($site_name)): ?>
          <span class="ia-atrium-brand-text" aria-label="<?php echo esc_attr($site_name); ?>">
            <?php echo esc_html($site_name); ?>
          </span>
        <?php endif; ?>

        <button class="ia-atrium-tabmenu-toggle" type="button" data-ia-tabmenu-toggle aria-haspopup="true" aria-expanded="false">
          <span class="ia-atrium-tabmenu-label" data-ia-current-tab-label>Connect</span>
          <span class="ia-atrium-tabmenu-caret" aria-hidden="true">▾</span>
        </button>

        <div class="ia-atrium-tabmenu" data-ia-tabmenu aria-hidden="true">
          <?php foreach ($data['tabs'] as $key => $tab): ?>
            <button class="ia-atrium-tabmenu-item" type="button" data-ia-tabmenu-item data-target="<?php echo esc_attr($key); ?>">
              <?php echo esc_html($tab['label']); ?>
            </button>
          <?php endforeach; ?>
        </div>

        <!-- Keep the original tab buttons for accessibility + legacy JS (hidden by CSS) -->
        <div class="ia-atrium-tabs" role="tablist" aria-label="Atrium sections">
          <?php foreach ($data['tabs'] as $key => $tab): ?>
            <button class="ia-tab"
                    type="button"
                    role="tab"
                    aria-selected="false"
                    data-target="<?php echo esc_attr($key); ?>">
              <?php echo esc_html($tab['label']); ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="button" class="ia-atrium-search-btn" data-ia-search-open aria-label="Search">
        <span class="ia-atrium-search-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
            <path d="M16.6 16.6 21 21" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
          </svg>
        </span>
      </button>
    </div>
  </header>

  <!-- Fullscreen Search Overlay -->
  <div class="ia-atrium-search" data-ia-search aria-hidden="true">
    <div class="ia-atrium-search-backdrop" data-ia-search-close></div>
    <div class="ia-atrium-search-panel" role="dialog" aria-modal="true" aria-label="Search">
      <div class="ia-atrium-search-top">
        <input class="ia-atrium-search-input" type="search" inputmode="search" placeholder="Search users, posts, replies" autocomplete="off" data-ia-search-input />
        <button type="button" class="ia-atrium-search-close" data-ia-search-close aria-label="Close">×</button>
      </div>
      <div class="ia-atrium-search-results" data-ia-search-results></div>
    </div>
  </div>

  <main class="ia-atrium-main" role="main">
    <section class="ia-panel" data-panel="connect" role="tabpanel" aria-label="Connect panel">
      <div class="ia-panel-inner">
        <div class="ia-slot ia-slot-connect" data-slot="connect">
          <?php do_action('ia_atrium_panel_connect'); ?>
        </div>
      </div>
    </section>

    <section class="ia-panel" data-panel="discuss" role="tabpanel" aria-label="Discuss panel">
      <div class="ia-panel-inner">
        <div class="ia-slot ia-slot-discuss" data-slot="discuss">
          <?php do_action('ia_atrium_panel_discuss'); ?>
        </div>
      </div>
    </section>

    <section class="ia-panel" data-panel="stream" role="tabpanel" aria-label="Stream panel">
      <div class="ia-panel-inner">
        <div class="ia-slot ia-slot-stream" data-slot="stream">
          <?php do_action('ia_atrium_panel_stream'); ?>
        </div>
      </div>
    </section>
 
<!-- Messages panel (opened from bottom chat icon; NOT a top tab) -->
    <section class="ia-panel" data-panel="messages" role="tabpanel" aria-label="Messages panel">
      <div class="ia-panel-inner">
        <div class="ia-slot ia-slot-messages" data-slot="messages">
          <?php do_action('ia_atrium_panel_messages'); ?>
        </div>
      </div>
    </section>
  </main>
  <!-- Composer Modal Shell (micro-plugins will replace content) -->
  <div class="ia-modal" id="ia-atrium-composer" aria-hidden="true">
    <div class="ia-modal-backdrop" data-ia-modal-close></div>

    <div class="ia-modal-card" role="dialog" aria-modal="true" aria-label="Create post">
      <div class="ia-modal-header">
        <div class="ia-modal-title">Create</div>
        <button type="button" class="ia-icon-btn" data-ia-modal-close aria-label="Close">×</button>
      </div>

      <div class="ia-modal-body">
        <div class="ia-slot ia-slot-composer" data-slot="composer">
          <?php do_action('ia_atrium_composer_body'); ?>

          <div class="ia-composer-placeholder">
            <p><strong>Composer is a shell.</strong></p>
            <p>A micro-plugin will provide: destination selector (Connect/Discuss/Stream), title/body fields, uploads, submit handlers.</p>
          </div>
        </div>
      </div>

      <div class="ia-modal-footer">
        <?php do_action('ia_atrium_composer_footer'); ?>
        <button type="button" class="ia-btn" data-ia-modal-close>Close</button>
      </div>
    </div>
  </div>

  <!-- Auth Modal (Login/Register) -->
  
  <?php do_action('ia_atrium_auth_modal'); ?>

  <!-- Bottom Navigation -->
  <nav class="ia-bottom-nav" role="navigation" aria-label="Atrium bottom navigation">

    <?php
      $logged_in = is_user_logged_in();
      $logout_url = wp_logout_url( (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/') );
    ?>

    <!-- Profile button + dropdown when logged in -->
    <div class="ia-bottom-item-wrap">
      <button type="button"
              class="ia-bottom-item"
              data-bottom="profile"
              aria-label="Profile">
        <?php if ($me_avatar): ?>
          <img class="ia-bottom-avatar" src="<?php echo esc_url($me_avatar); ?>" alt="" />
        <?php else: ?>
          <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="ia-bottom-label">Profile</span>
      </button>

      <?php if ($logged_in): ?>
        <div class="ia-profile-menu" data-profile-menu aria-hidden="true">
          <button type="button" class="ia-menu-item" data-profile-action="go_profile">
            Go to Connect Profile
          </button>
          <a class="ia-menu-item" href="<?php echo esc_url($logout_url); ?>">
            Log Out
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Post / Chat / Notifications -->
    <button type="button"
            class="ia-bottom-item"
            data-bottom="post"
            aria-label="Post">
      <span class="dashicons dashicons-edit" aria-hidden="true"></span>
      <span class="ia-bottom-label">Post</span>
    </button>

    <button type="button"
            class="ia-bottom-item"
            data-bottom="chat"
            aria-label="Chat">
      <span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
      <span class="ia-bottom-label">Chat</span>
    </button>

    <button type="button"
            class="ia-bottom-item"
            data-bottom="notify"
            aria-label="Notifications">
      <span class="dashicons dashicons-bell" aria-hidden="true"></span>
      <span class="ia-bottom-label">Notifications</span>
    </button>

  </nav>

  <?php do_action('ia_atrium_shell_after'); ?>

</div>
