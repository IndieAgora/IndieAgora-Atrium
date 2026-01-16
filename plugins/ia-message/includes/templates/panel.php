<?php if (!defined('ABSPATH')) exit; ?>

<?php
  // Canonical phpBB user id for the currently logged-in WP session.
  // This is crucial for client-side "mine vs theirs" rendering.
  $me_phpbb = function_exists('ia_message_current_phpbb_user_id') ? (int) ia_message_current_phpbb_user_id() : 0;
?>

<div class="ia-msg-shell"
     data-panel="<?php echo esc_attr(IA_MESSAGE_PANEL_KEY); ?>"
     data-mobile-view="list"
     data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
     data-phpbb-me="<?php echo esc_attr($me_phpbb); ?>">

  <div class="ia-msg-cols">
    <!-- LEFT: THREADS -->
    <aside class="ia-msg-left" aria-label="Message threads">
      <div class="ia-msg-left-head">
        <div class="ia-msg-title">Messages</div>
        <button type="button" class="ia-msg-btn" data-ia-msg-action="new">New</button>
      </div>

      <div class="ia-msg-search">
        <input type="search"
               class="ia-msg-search-input"
               placeholder="Search users…"
               autocomplete="off"
               data-ia-msg-user-q>
        <div class="ia-msg-suggest" data-ia-msg-suggest></div>
      </div>

      <div class="ia-msg-threadlist" data-ia-msg-threads>
        <div class="ia-msg-empty">Loading…</div>
      </div>
    </aside>

    <!-- RIGHT: CHAT -->
    <section class="ia-msg-main" aria-label="Conversation">
      <header class="ia-msg-main-head">
        <button type="button" class="ia-msg-back" data-ia-msg-action="back" aria-label="Back">←</button>
        <div class="ia-msg-threadname" data-ia-msg-chat-title>Select a conversation</div>
      </header>

      <div class="ia-msg-log" data-ia-msg-chat-messages>
        <div class="ia-msg-empty">Select a conversation.</div>
      </div>

      <form class="ia-msg-composer" data-ia-msg-send-form>
        <textarea class="ia-msg-text"
                  rows="2"
                  placeholder="Message…"
                  data-ia-msg-send-input></textarea>
        <button type="submit" class="ia-msg-btn">Send</button>
      </form>
    </section>
  </div>

  <!-- NEW CHAT SHEET -->
  <div class="ia-msg-sheet"
       data-ia-msg-sheet="newchat"
       aria-hidden="true">

    <div class="ia-msg-sheet-card">
      <header class="ia-msg-sheet-head">
        <div>New chat</div>
        <button type="button" class="ia-msg-btn" data-ia-msg-sheet-close="1">×</button>
      </header>

      <label class="ia-msg-sheet-label">User</label>
      <input type="text"
             class="ia-msg-sheet-input"
             placeholder="Type a username…"
             autocomplete="off"
             data-ia-msg-new-q>

      <div class="ia-msg-suggest" data-ia-msg-new-suggest></div>

      <label class="ia-msg-sheet-label" style="margin-top:10px;">Message</label>
      <textarea class="ia-msg-text"
                rows="3"
                placeholder="Write your first message…"
                data-ia-msg-new-body></textarea>

      <input type="hidden" data-ia-msg-new-to-phpbb value="">

      <div class="ia-msg-sheet-actions">
        <button type="button" class="ia-msg-btn" data-ia-msg-new-self="1">Message yourself</button>
        <button type="button" class="ia-msg-btn" data-ia-msg-new-start="1" disabled>Start</button>
      </div>

      <div class="ia-msg-sheet-hint">
        Search by username. Atrium identity remains authoritative; ia-message only consumes canonical phpBB user IDs.
      </div>
    </div>
  </div>

</div>
