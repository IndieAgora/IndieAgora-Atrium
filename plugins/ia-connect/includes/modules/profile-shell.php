<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_profile_shell implements ia_connect_module_interface {

  public function boot(): void {
    add_action('ia_atrium_panel_connect', [$this, 'render_connect_panel']);
  }

  public function ajax_routes(): array {
    return [];
  }

  public function render_connect_panel(): void {
    ?>
    <div class="ia-connect" id="ia-connect-root" aria-label="Connect profile">

      <header class="ia-connect-header">

        <button type="button"
                class="ia-connect-banner"
                data-ia-connect-cover-btn
                aria-label="Change cover photo">
          <img class="ia-connect-cover-img" data-ia-connect-cover-img data-ia-connect-open-viewer="cover" alt="" />
          <span class="ia-connect-cover-overlay">Change cover</span>
        </button>

        <div class="ia-connect-header-row">
          <button type="button"
                  class="ia-connect-avatar"
                  data-ia-connect-avatar-btn
                  aria-label="Change profile picture">
            <img class="ia-connect-avatar-img" data-ia-connect-avatar-img data-ia-connect-open-viewer="avatar" alt="" />

            <span class="ia-connect-avatar-overlay">
              <span class="ia-connect-avatar-overlay-btn" role="button" tabindex="0">Change</span>
            </span>
          </button>

          <div class="ia-connect-identity">
            <div class="ia-connect-name" data-ia-connect-name>Profile</div>
            <div class="ia-connect-handle" data-ia-connect-handle></div>

            <div class="ia-connect-usersearch" data-ia-connect-usersearch>
              <input type="search"
                     class="ia-input ia-connect-usersearch-input"
                     data-ia-connect-usersearch-input
                     placeholder="Search users…"
                     autocomplete="off"
                     aria-label="Search users" />
              <div class="ia-connect-usersearch-results" data-ia-connect-usersearch-results aria-hidden="true"></div>
            </div>


            <div class="ia-connect-actions">
              <button type="button" class="ia-btn ia-btn-primary" data-ia-connect-action="follow" disabled>Follow</button>
              <button type="button" class="ia-btn" data-ia-connect-action="message" disabled>Message</button>
            </div>
          </div>
        </div>

        <div class="ia-connect-bio" aria-label="Bio">
          <div class="ia-connect-bio-text" data-ia-connect-bio-text></div>
          <button type="button" class="ia-pill" data-ia-connect-edit-bio>Edit bio</button>
        </div>
      </header>

      <nav class="ia-connect-subtabs" aria-label="Connect views">
        <button type="button" class="ia-subtab active" data-ia-connect-view-btn="wall">Wall</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="edit">Edit</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="media">Media</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="activity">Activity</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="privacy">Privacy</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="notifications">Notifications</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="blocked">Blocked</button>
        <button type="button" class="ia-subtab" data-ia-connect-view-btn="export">Export</button>
      </nav>

      <section class="ia-connect-view" data-ia-connect-view="wall"></section>
      <section class="ia-connect-view" data-ia-connect-view="edit" hidden></section>
      <section class="ia-connect-view" data-ia-connect-view="media" hidden></section>
      <section class="ia-connect-view" data-ia-connect-view="activity" hidden></section>
      <section class="ia-connect-view" data-ia-connect-view="privacy" hidden></section>
      <section class="ia-connect-view" data-ia-connect-view="notifications" hidden></section>
      <section class="ia-connect-view" data-ia-connect-view="blocked" hidden></section>
      <section class="ia-connect-view" data-ia-connect-view="export" hidden></section>

      <input type="file" accept="image/*" class="ia-connect-hidden-file" data-ia-connect-avatar-file aria-hidden="true" />
      <input type="file" accept="image/*" class="ia-connect-hidden-file" data-ia-connect-cover-file aria-hidden="true" />

      <div class="ia-connect-viewer" data-ia-connect-viewer aria-hidden="true">
        <div class="ia-connect-viewer-backdrop" data-ia-connect-viewer-close></div>

        <div class="ia-connect-viewer-toolbar">
          <div class="ia-connect-viewer-title" data-ia-connect-viewer-title>Image</div>

          <div class="ia-connect-viewer-actions">
            <button type="button" class="ia-btn" data-ia-connect-viewer-zoom-out aria-label="Zoom out">−</button>
            <button type="button" class="ia-btn" data-ia-connect-viewer-zoom-in aria-label="Zoom in">+</button>
            <button type="button" class="ia-btn" data-ia-connect-viewer-reset aria-label="Reset view">Reset</button>
            <a class="ia-btn ia-icon-btn-link" data-ia-connect-viewer-open target="_blank" rel="noopener">Open</a>
            <a class="ia-btn ia-icon-btn-link" data-ia-connect-viewer-download download>Download</a>
            <button type="button" class="ia-btn ia-btn-primary" data-ia-connect-viewer-close-btn aria-label="Close viewer">Close</button>
          </div>
        </div>

        <div class="ia-connect-viewer-stage" data-ia-connect-viewer-stage>
          <img class="ia-connect-viewer-img" data-ia-connect-viewer-img alt="" />
        </div>
      </div>

      <div class="ia-connect-modal" data-ia-connect-modal="deactivate" aria-hidden="true">
        <div class="ia-connect-modal-backdrop" data-ia-connect-close></div>
        <div class="ia-connect-modal-card" role="dialog" aria-modal="true" aria-label="Deactivate account">
          <div class="ia-connect-modal-header">
            <div class="ia-connect-modal-title">Deactivate Account</div>
            <button type="button" class="ia-icon-btn" data-ia-connect-close aria-label="Close">×</button>
          </div>
          <div class="ia-connect-modal-body" data-ia-connect-modal-body="deactivate"></div>
          <div class="ia-connect-modal-footer">
            <button type="button" class="ia-btn" data-ia-connect-close>Cancel</button>
            <button type="button" class="ia-btn ia-btn-primary" disabled>Deactivate (later)</button>
          </div>
        </div>
      </div>

      <div class="ia-connect-modal" data-ia-connect-modal="delete" aria-hidden="true">
        <div class="ia-connect-modal-backdrop" data-ia-connect-close></div>
        <div class="ia-connect-modal-card" role="dialog" aria-modal="true" aria-label="Delete account">
          <div class="ia-connect-modal-header">
            <div class="ia-connect-modal-title">Delete Account</div>
            <button type="button" class="ia-icon-btn" data-ia-connect-close aria-label="Close">×</button>
          </div>
          <div class="ia-connect-modal-body" data-ia-connect-modal-body="delete"></div>
          <div class="ia-connect-modal-footer">
            <button type="button" class="ia-btn" data-ia-connect-close>Cancel</button>
            <button type="button" class="ia-btn ia-btn-primary" disabled>Delete (later)</button>
          </div>
        </div>
      </div>

      <div class="ia-connect-toast" data-ia-connect-toast aria-hidden="true"></div>

      <div class="ia-connect-loading" data-ia-connect-loading aria-hidden="true">
        <div class="ia-connect-loading-card">
          <div class="ia-connect-spinner" aria-hidden="true"></div>
          <div class="ia-connect-loading-text" data-ia-connect-loading-text>Working…</div>
        </div>
      </div>

    </div>
    <?php
  }
}
