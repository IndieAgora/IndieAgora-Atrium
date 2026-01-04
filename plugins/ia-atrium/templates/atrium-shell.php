<?php if (!defined('ABSPATH')) exit; ?>

<div id="ia-atrium-shell"
     class="ia-atrium-shell"
     data-default-tab="<?php echo esc_attr($data['default_tab']); ?>"
     data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>">

  <?php do_action('ia_atrium_shell_before'); ?>

  <header class="ia-atrium-topbar" role="navigation" aria-label="Atrium tabs">
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
  </header>

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
  </main>
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
        <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
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
