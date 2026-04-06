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
      <div class="ia-msg-left-head" aria-label="Messages header">
        <div class="ia-msg-title" aria-hidden="true">Messages</div>
        <!--
          IMPORTANT (mobile): some theme/Atrium global scripts/CSS hide <button> elements
          in narrow portrait layouts. Use anchor tags with role="button" to avoid
          being targeted, while still using the same data-ia-msg-action contract.
        -->
        <a href="#" role="button" class="ia-msg-btn ia-msg-headicon ia-msg-headicon-new" title="New chat" aria-label="New chat" data-ia-msg-action="new"></a>
        <a href="#" role="button" class="ia-msg-btn ia-msg-btn-icon ia-msg-headicon ia-msg-headicon-prefs" title="Notification settings" aria-label="Notification settings" data-ia-msg-action="prefs">⚙</a>
        <a href="#" role="button" class="ia-msg-btn ia-msg-btn-icon ia-msg-headicon ia-msg-headicon-close" title="Close" aria-label="Close" data-ia-msg-action="close">×</a>
      </div>

      <div class="ia-msg-search">
        <input type="search"
               class="ia-msg-search-input"
               placeholder="Search chats…"
               autocomplete="off"
               data-ia-msg-chat-q>
</div>

      <div class="ia-msg-threadlist" data-ia-msg-threads>
        <div class="ia-msg-invites" data-ia-msg-invites hidden></div>
        <div class="ia-msg-empty">Loading…</div>
      </div>
    </aside>

    <!-- RIGHT: CHAT -->
    <section class="ia-msg-main" aria-label="Conversation">
      <header class="ia-msg-main-head">
        <button type="button" class="ia-msg-back" data-ia-msg-action="back" aria-label="Back">←</button>
        <div class="ia-msg-threadname" data-ia-msg-chat-title>Select a conversation</div>
        <button type="button" class="ia-msg-relbtn" data-ia-msg-action="members" aria-label="Members" title="Members" hidden>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </button>
        <div class="ia-msg-rel" data-ia-msg-rel hidden>
          <button type="button" class="ia-msg-relbtn" data-ia-msg-follow-user aria-label="Follow">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
              <path class="ia-msg-plus" d="M19 8v6M16 11h6"/>
            </svg>
          </button>
          <button type="button" class="ia-msg-relbtn" data-ia-msg-block-user aria-label="Block">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9"/>
              <path d="M5.5 5.5l13 13" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
        <button type="button" class="ia-msg-close" data-ia-msg-action="close" aria-label="Close">×</button>
      </header>

      <div class="ia-msg-log" data-ia-msg-chat-messages>
        <div class="ia-msg-empty">Select a conversation.</div>
      </div>

      <form class="ia-msg-composer" data-ia-msg-send-form>
        <div class="ia-msg-replybar" data-ia-msg-replybar hidden>
          <div class="ia-msg-replybar__meta">
            <div class="ia-msg-replybar__label">Replying to</div>
            <div class="ia-msg-replybar__who" data-ia-msg-reply-who></div>
          </div>
          <button type="button" class="ia-msg-replybar__quote" data-ia-msg-reply-quote></button>
          <button type="button" class="ia-msg-replybar__close" data-ia-msg-reply-clear aria-label="Cancel reply">×</button>
        </div>

        <div class="ia-msg-composer-row">
          <label class="ia-msg-upload" title="Upload" aria-label="Upload" for="ia-msg-upload-input" data-ia-msg-upload-btn>
            <span aria-hidden="true">📎</span>
          </label>
          <input type="file" class="ia-msg-upload-input" id="ia-msg-upload-input" data-ia-msg-upload-input multiple />
          <div class="ia-msg-upload-progress" data-ia-msg-upload-progress hidden>
            <span class="ia-msg-upload-spinner" aria-hidden="true"></span>
            <span class="ia-msg-upload-progress__pct" data-ia-msg-upload-pct>0%</span>
            <span class="ia-msg-sr" data-ia-msg-upload-label>Uploading…</span>
          </div>
          <textarea class="ia-msg-text"
                    rows="2"
                    placeholder="Message…"
                    data-ia-msg-send-input></textarea>
          <button type="submit" class="ia-msg-btn">Send</button>
        </div>
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

      <div class="ia-msg-sheet-tabs" role="tablist" aria-label="Chat type">
        <button type="button" class="ia-msg-tab is-on" data-ia-msg-new-mode="dm" role="tab" aria-selected="true">Direct</button>
        <button type="button" class="ia-msg-tab" data-ia-msg-new-mode="group" role="tab" aria-selected="false">Group</button>
      </div>

      <!-- DM MODE -->
      <div class="ia-msg-newmode" data-ia-msg-newmode="dm">
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
      </div>

      <!-- GROUP MODE -->
      <div class="ia-msg-newmode" data-ia-msg-newmode="group" hidden>
        <label class="ia-msg-sheet-label">Group name</label>
        <input type="text" class="ia-msg-sheet-input" placeholder="e.g. Atrium Build" autocomplete="off" data-ia-msg-group-title>

        <div class="ia-msg-group-avatarrow">
          <div class="ia-msg-group-avatar" data-ia-msg-group-avatar-preview aria-hidden="true"></div>
          <div class="ia-msg-group-avatar-actions">
            <label class="ia-msg-btn ia-msg-btn-small" for="ia-msg-group-avatar-input">Upload avatar</label>
            <input type="file" id="ia-msg-group-avatar-input" class="ia-msg-hiddenfile" accept="image/*" data-ia-msg-group-avatar-input>
            <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-group-avatar-clear>Remove</button>
          </div>
          <input type="hidden" data-ia-msg-group-avatar-url value="">
        </div>

        <label class="ia-msg-sheet-label" style="margin-top:10px;">Members</label>
        <input type="text" class="ia-msg-sheet-input" placeholder="Search users…" autocomplete="off" data-ia-msg-group-q>
        <div class="ia-msg-suggest" data-ia-msg-group-suggest></div>

        <div class="ia-msg-group-browse" data-ia-msg-group-browse aria-label="Browse users"></div>

        <div class="ia-msg-group-actions">
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-group-selectall>Select all</button>
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-group-clear>Clear</button>
        </div>

        <div class="ia-msg-group-selected" data-ia-msg-group-selected></div>
        <input type="hidden" data-ia-msg-group-members value="">

        <label class="ia-msg-sheet-label" style="margin-top:10px;">Message</label>
        <textarea class="ia-msg-text" rows="3" placeholder="Write your first message…" data-ia-msg-group-body></textarea>

        <div class="ia-msg-sheet-actions">
          <button type="button" class="ia-msg-btn" data-ia-msg-group-start disabled>Start group chat</button>
        </div>
      </div>

      <div class="ia-msg-sheet-hint">
        Search by username. Atrium identity remains authoritative.
      </div>
    </div>
  </div>

  <!-- GROUP MEMBERS / INVITES SHEET -->
  <div class="ia-msg-sheet"
       data-ia-msg-sheet="members"
       aria-hidden="true">

    <div class="ia-msg-sheet-card">
      <header class="ia-msg-sheet-head">
        <div>Members</div>
        <button type="button" class="ia-msg-btn" data-ia-msg-sheet-close="1">×</button>
      </header>

      <div class="ia-msg-members-meta" data-ia-msg-members-meta></div>

      <div class="ia-msg-members-list" data-ia-msg-members-list></div>

      <div class="ia-msg-members-invite" data-ia-msg-members-invite hidden>
        <label class="ia-msg-sheet-label" style="margin-top:10px;">Invite</label>

        <input type="text" class="ia-msg-sheet-input" placeholder="Search users…" autocomplete="off" data-ia-msg-invite-q>

        <div class="ia-msg-suggest" data-ia-msg-invite-suggest></div>

        <div class="ia-msg-group-selected" data-ia-msg-invite-selected></div>

        <div class="ia-msg-row" style="justify-content:flex-end; gap:8px; margin-top:6px;">
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-invite-selectall>Select all</button>
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-invite-clear>Clear</button>
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-invite-send disabled>Send invites</button>
        </div>

        <div class="ia-msg-group-browse" data-ia-msg-invite-browse style="margin-top:10px;"></div>
      </div>
    </div>
  </div>



  <!-- FORWARD SHEET -->
  <div class="ia-msg-sheet"
       data-ia-msg-sheet="forward"
       aria-hidden="true">

    <div class="ia-msg-sheet-card">
      <header class="ia-msg-sheet-head">
        <div>Forward message</div>
        <button type="button" class="ia-msg-btn" data-ia-msg-sheet-close="1">×</button>
      </header>

      <div class="ia-msg-fwd-preview" data-ia-msg-fwd-preview></div>

      <label class="ia-msg-sheet-label" style="margin-top:10px;">To</label>
      <input type="text"
             class="ia-msg-sheet-input"
             placeholder="Type a username…"
             autocomplete="off"
             data-ia-msg-fwd-q>

      <div class="ia-msg-suggest" data-ia-msg-fwd-suggest></div>

      <div class="ia-msg-fwd-selected" data-ia-msg-fwd-selected></div>

      <input type="hidden" data-ia-msg-fwd-to value="">
      <input type="hidden" data-ia-msg-fwd-mid value="">

      <div class="ia-msg-sheet-actions">
        <button type="button" class="ia-msg-btn" data-ia-msg-fwd-send="1" disabled>Forward</button>
      </div>

      <div class="ia-msg-sheet-hint">
        Select one or more recipients. This forwards the message into a DM thread with each recipient.
      </div>
    </div>
  </div>


  <!-- PREFS SHEET -->
  <div class="ia-msg-sheet"
       data-ia-msg-sheet="prefs"
       aria-hidden="true">

    <div class="ia-msg-sheet-card">
      <header class="ia-msg-sheet-head">
        <div>Notifications</div>
        <button type="button" class="ia-msg-btn" data-ia-msg-sheet-close="1">×</button>
      </header>

      <div class="ia-msg-prefs">
        <label class="ia-msg-pref-row">
          <input type="checkbox" data-ia-msg-pref="email">
          <span>Email notifications</span>
        </label>

        <label class="ia-msg-pref-row">
          <input type="checkbox" data-ia-msg-pref="popup">
          <span>Popup notifications</span>
        </label>

        <div class="ia-msg-pref-help">
          The unread badge always remains visible.
        </div>
      </div>
    </div>
  </div>

</div>
