<?php if (!defined('ABSPATH')) exit; ?>

<div class="ia-msg-shell"
     data-panel="<?php echo esc_attr(IA_MESSAGE_PANEL_KEY); ?>"
     data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>">

  <div class="ia-msg-cols" data-ia-msg-layout="cols">
    <aside class="ia-msg-left" aria-label="Message threads">
      <div class="ia-msg-left-head">
        <div class="ia-msg-title">Messages</div>
        <button type="button" class="ia-msg-btn ia-msg-btn-new" data-ia-msg-action="new">New</button>
      </div>

      <div class="ia-msg-search">
        <input type="search" class="ia-msg-search-input" placeholder="Search…" autocomplete="off">
      </div>

      <div class="ia-msg-threadlist" data-ia-msg-slot="threadlist">
        <div class="ia-msg-empty">No threads yet.</div>
      </div>
    </aside>

    <section class="ia-msg-main" aria-label="Conversation">
      <header class="ia-msg-main-head" data-ia-msg-slot="threadhead">
        <button type="button" class="ia-msg-back" data-ia-msg-action="back" aria-label="Back">←</button>
        <div class="ia-msg-threadname">Select a conversation</div>
      </header>

      <div class="ia-msg-log" data-ia-msg-slot="log">
        <div class="ia-msg-empty">Nothing to show.</div>
      </div>

      <footer class="ia-msg-composer" aria-label="Message composer">
        <textarea class="ia-msg-text" rows="2" placeholder="Message…" data-ia-msg-slot="composer"></textarea>
        <button type="button" class="ia-msg-btn ia-msg-btn-send" data-ia-msg-action="send">Send</button>
      </footer>
    </section>
  </div>
</div>
