<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Panel Module
 *
 * Renders the Stream shell inside Atrium.
 * Tabs:
 * - Discover
 * - Browse videos
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

      <header class="ia-stream-header" role="navigation" aria-label="Stream navigation">
        <div class="ia-stream-header-row">
          <div class="ia-stream-tabs" role="tablist">
            <button class="ia-stream-tab is-active"
                    type="button"
                    data-tab="discover"
                    aria-selected="true">
              Discover
            </button>

            <button class="ia-stream-tab"
                    type="button"
                    data-tab="browse"
                    aria-selected="false">
              Browse videos
            </button>

            <button class="ia-stream-tab"
                    type="button"
                    data-tab="subscriptions"
                    aria-selected="false">
              Subscriptions
            </button>

            <button class="ia-stream-tab"
                    type="button"
                    data-tab="search"
                    data-ia-stream-search-tab
                    aria-selected="false"
                    hidden>
              Search results
            </button>
          </div>
        </div>
      </header>

      <main class="ia-stream-main" role="main">
        <section class="ia-stream-panel is-active"
                 data-panel="discover"
                 role="tabpanel">
          <div class="ia-stream-discover">
            <section class="ia-stream-hero">
              <div class="ia-stream-hero-copy">
                <div class="ia-stream-kicker">Stream</div>
                <h2 class="ia-stream-hero-title">Discover videos and creators</h2>
                
              </div>
            </section>

            <section class="ia-stream-section">
              <div class="ia-stream-section-head">
                <h3 class="ia-stream-section-title">Recently added</h3>
                <div class="ia-stream-section-actions">
                  <button type="button" class="ia-stream-more-btn" data-ia-stream-discover-more="recent">Load more</button>
                </div>
              </div>
              <div class="ia-stream-feed ia-stream-feed--grid" data-ia-stream-discover-recent>
                <div class="ia-stream-placeholder">Loading recent videos…</div>
              </div>
            </section>

            <section class="ia-stream-section">
              <div class="ia-stream-section-head">
                <h3 class="ia-stream-section-title">Trending now</h3>
                <div class="ia-stream-section-actions">
                  <button type="button" class="ia-stream-more-btn" data-ia-stream-discover-more="trending">Load more</button>
                </div>
              </div>
              <div class="ia-stream-feed ia-stream-feed--grid" data-ia-stream-discover-trending>
                <div class="ia-stream-placeholder">Loading trending videos…</div>
              </div>
            </section>

            <section class="ia-stream-section">
              <div class="ia-stream-section-head">
                <h3 class="ia-stream-section-title">Channels</h3>
                <div class="ia-stream-section-actions">
                  <button type="button" class="ia-stream-more-btn" data-ia-stream-open-browse>Browse all</button>
                </div>
              </div>
              <div class="ia-stream-channels" data-ia-stream-discover-channels>
                <div class="ia-stream-placeholder">Loading channels…</div>
              </div>
            </section>
          </div>
        </section>

        <section class="ia-stream-panel"
                 data-panel="browse"
                 role="tabpanel"
                 hidden>
          <div class="ia-stream-results-head">
            <div class="ia-stream-results-copy">
              <h3 class="ia-stream-section-title">Browse videos</h3>
              <div class="ia-stream-results-meta" data-ia-stream-browse-meta>Recently added videos</div>
            </div>
          </div>

          <div class="ia-stream-feed ia-stream-feed--grid" data-ia-stream-browse-feed>
            <div class="ia-stream-placeholder">Loading videos…</div>
          </div>
        </section>

        <section class="ia-stream-panel"
                 data-panel="subscriptions"
                 role="tabpanel"
                 hidden>
          <div class="ia-stream-results-head">
            <div class="ia-stream-results-copy">
              <h3 class="ia-stream-section-title">Subscriptions</h3>
              <div class="ia-stream-results-meta" data-ia-stream-subscriptions-meta>Your subscriptions feed</div>
            </div>
          </div>

          <div class="ia-stream-feed ia-stream-feed--grid" data-ia-stream-subscriptions-feed>
            <div class="ia-stream-placeholder">Loading subscriptions…</div>
          </div>
        </section>

        <section class="ia-stream-panel"
                 data-panel="search"
                 role="tabpanel"
                 hidden>
          <div class="ia-stream-results-head">
            <div class="ia-stream-results-copy">
              <h3 class="ia-stream-section-title">Search results</h3>
              <div class="ia-stream-results-meta" data-ia-stream-search-meta>Search results</div>
            </div>
          </div>

          <div class="ia-stream-feed ia-stream-feed--grid" data-ia-stream-search-feed>
            <div class="ia-stream-placeholder">Search videos, channels, users, tags and comments…</div>
          </div>

          <div class="ia-stream-search-extras" data-ia-stream-search-extras hidden>
            <section class="ia-stream-search-extra" data-ia-stream-user-matches-wrap hidden>
              <div class="ia-stream-section-head">
                <h4 class="ia-stream-section-title">Users</h4>
              </div>
              <div class="ia-stream-channels ia-stream-channels--compact" data-ia-stream-user-matches></div>
            </section>

            <section class="ia-stream-search-extra" data-ia-stream-channel-matches-wrap hidden>
              <div class="ia-stream-section-head">
                <h4 class="ia-stream-section-title">Matching channels</h4>
              </div>
              <div class="ia-stream-channels ia-stream-channels--compact" data-ia-stream-channel-matches></div>
            </section>

            <section class="ia-stream-search-extra" data-ia-stream-tag-matches-wrap hidden>
              <div class="ia-stream-section-head">
                <h4 class="ia-stream-section-title">Tags and categories</h4>
              </div>
              <div class="ia-stream-tag-search" data-ia-stream-tag-matches></div>
            </section>

            <section class="ia-stream-search-extra" data-ia-stream-comment-matches-wrap hidden>
              <div class="ia-stream-section-head">
                <h4 class="ia-stream-section-title">Comments</h4>
              </div>
              <div class="ia-stream-comment-search" data-ia-stream-comment-matches></div>
            </section>
          </div>
        </section>
      </main>

    </div>
    <?php
  }
}
