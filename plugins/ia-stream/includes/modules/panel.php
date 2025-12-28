<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Panel Module
 *
 * Renders the Stream shell inside Atrium.
 * Tabs:
 * - Feed (videos)
 * - Channels
 */
final class IA_Stream_Module_Panel implements IA_Stream_Module_Interface {

  public static function boot(): void {
    // Nothing needed yet — kept for symmetry and future extension
  }

  public static function render(): void {
    ?>
    <div id="ia-stream-shell"
         class="ia-stream-shell"
         data-surface="stream">

      <header class="ia-stream-header" role="navigation" aria-label="Stream tabs">
        <div class="ia-stream-tabs" role="tablist">
          <button class="ia-stream-tab is-active"
                  type="button"
                  data-tab="feed"
                  aria-selected="true">
            Feed
          </button>

          <button class="ia-stream-tab"
                  type="button"
                  data-tab="channels"
                  aria-selected="false">
            Channels
          </button>
        </div>
      </header>

      <main class="ia-stream-main" role="main">
        <section class="ia-stream-panel is-active"
                 data-panel="feed"
                 role="tabpanel">
          <div class="ia-stream-feed">
            <div class="ia-stream-placeholder">
              Loading video feed…
            </div>
          </div>
        </section>

        <section class="ia-stream-panel"
                 data-panel="channels"
                 role="tabpanel"
                 hidden>
          <div class="ia-stream-channels">
            <div class="ia-stream-placeholder">
              Loading channels…
            </div>
          </div>
        </section>
      </main>

    </div>
    <?php
  }
}
