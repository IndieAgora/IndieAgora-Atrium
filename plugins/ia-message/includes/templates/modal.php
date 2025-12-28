<?php if (!defined('ABSPATH')) exit; ?>

<div id="ia-message-modal"
     class="ia-msg-modal"
     role="dialog"
     aria-hidden="true">

  <div class="ia-msg-backdrop" data-ia-msg-close="1"></div>

  <div class="ia-msg-shell">

    <!-- HEADER -->
    <header class="ia-msg-header">
      <div class="ia-msg-title">Messages</div>
      <button type="button"
              class="ia-msg-close"
              data-ia-msg-close="1"
              aria-label="Close">×</button>
    </header>

    <!-- BODY -->
    <div class="ia-msg-body">

      <!-- THREAD LIST -->
      <aside class="ia-msg-list">
        <div class="ia-msg-list-head">
          <div class="ia-msg-list-title">Messages</div>
          <button type="button"
                  class="ia-msg-btn"
                  data-ia-msg-action="new">New</button>
        </div>

        <input type="text"
               class="ia-msg-search"
               placeholder="Search conversations…">

        <div class="ia-msg-threadlist"
             data-ia-msg-slot="threadlist"></div>
      </aside>

      <!-- CHAT -->
      <section class="ia-msg-chat">

        <header class="ia-msg-chat-head"
                data-ia-msg-slot="threadhead">
          <button type="button"
                  class="ia-msg-back"
                  data-ia-msg-action="back">←</button>
          <div class="ia-msg-threadname">Conversation</div>
        </header>

        <div class="ia-msg-log"
             data-ia-msg-slot="log">
          <div class="ia-msg-empty">Select a conversation.</div>
        </div>

        <footer class="ia-msg-composer">
          <textarea data-ia-msg-slot="composer"
                    placeholder="Message…"></textarea>
          <button type="button"
                  data-ia-msg-action="send">Send</button>
        </footer>
      </section>

    </div>

    <!-- NEW CHAT SHEET -->
    <div class="ia-msg-sheet"
         data-ia-msg-sheet="newchat"
         aria-hidden="true">

      <div class="ia-msg-sheet-card">

        <header class="ia-msg-sheet-head">
          <div>New chat</div>
          <button type="button"
                  data-ia-msg-sheet-close="1">×</button>
        </header>

        <label class="ia-msg-sheet-label">User</label>
        <input type="text"
               class="ia-msg-sheet-input"
               data-ia-msg-newchat-q
               placeholder="Search users…"
               autocomplete="off">

        <div class="ia-msg-sheet-actions">
          <button type="button"
                  class="ia-msg-btn"
                  data-ia-msg-newchat-self="1">
            Message yourself
          </button>

          <button type="button"
                  class="ia-msg-btn ia-msg-btn-primary"
                  data-ia-msg-newchat-go="1"
                  disabled>
            Start
          </button>
        </div>

        <div class="ia-msg-sheet-hint">
          Search by username or display name.
          Identity resolves silently via email on the backend.
        </div>

      </div>
    </div>

  </div>
</div>
