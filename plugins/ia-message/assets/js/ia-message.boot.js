

(() => {
  function ready(fn){ if(document.readyState!=="loading") fn(); else document.addEventListener("DOMContentLoaded", fn); }
  function q(root, sel){ try { return (root||document).querySelector(sel); } catch(e){ return null; } }
  function qa(root, sel){ try { return Array.from((root||document).querySelectorAll(sel)); } catch(e){ return []; } }

  function scrollToBottom(log, smooth){
    if (!log) return;
    try {
      if (smooth && log.scrollTo) log.scrollTo({ top: log.scrollHeight, behavior: "smooth" });
      else log.scrollTop = log.scrollHeight;
    } catch(e){
      try { log.scrollTop = log.scrollHeight; } catch(e2){}
    }
  }

  function ensureChatBottom(shell, force){
    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (!log) return;

    const st = S(shell);
    const should = !!force || !!st.atBottom || !!st.justOpened;
    if (!should) return;

    // immediate
    scrollToBottom(log, false);
    // after paint/layout
    try {
      requestAnimationFrame(() => {
        scrollToBottom(log, false);
        setTimeout(() => scrollToBottom(log, false), 80);
      });
    } catch(e){}

    // when media finishes loading (height changes)
    try {
      const imgs = Array.from(log.querySelectorAll("img"));
      imgs.forEach(img => {
        if (!img.complete) {
          img.addEventListener("load", () => { if (S(shell).atBottom) scrollToBottom(log, false); }, { once: true });
          img.addEventListener("error", () => { if (S(shell).atBottom) scrollToBottom(log, false); }, { once: true });
        }
      });
      const vids = Array.from(log.querySelectorAll("video"));
      vids.forEach(v => {
        v.addEventListener("loadedmetadata", () => { if (S(shell).atBottom) scrollToBottom(log, false); }, { once: true });
      });
    } catch(e){}

    st.justOpened = false;
  }



  function esc(s){
    return String(s ?? "").replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // Compact, Reddit-ish outline icons (stroke, round caps)
  function replyIconSvg(){
    return `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
      <path d="M9 17l-5-5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M4 12h9a6 6 0 0 1 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;
  }

  function forwardIconSvg(){
    return `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
      <path d="M15 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M4 12h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M14 12a7 7 0 0 0-7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;
  }

  function clampText(s, n){
    const t = String(s ?? "");
    if (t.length <= n) return t;
    return t.slice(0, n).trimEnd() + "…";
  }

  function normUrl(u){
    let s = String(u||"").trim();
    if (!s) return "";
    if (/^www\./i.test(s)) s = "https://" + s;
    return s;
  }

  function extractUrls(text){
    const t = String(text||"");
    const reUrl = /\b((?:https?:\/\/|www\.)[^\s<>()]+)\b/gi;
    const out = [];
    let m;
    while ((m = reUrl.exec(t)) !== null){
      const u = normUrl(m[1]);
      if (u) out.push({ url: u, index: m.index, raw: m[1] });
    }
    return out;
  }

  function urlHost(u){
    try { return (new URL(u)).host; } catch(e){ return ""; }
  }

  function fileNameFromUrl(u){
    try {
      const p = new URL(u).pathname.split("/").pop() || "";
      return decodeURIComponent(p) || u;
    } catch(e){ return u; }
  }

  function isImageUrl(u){ return /\.(png|jpe?g|gif|webp|svg)(\?|#|$)/i.test(u); }
  function isVideoUrl(u){ return /\.(mp4|webm|ogg|mov|m4v)(\?|#|$)/i.test(u); }
  function isAudioUrl(u){ return /\.(mp3|wav|ogg|m4a|flac)(\?|#|$)/i.test(u); }
  function isDocUrl(u){ return /\.(pdf|docx?|xlsx?|pptx?|txt|rtf|csv|zip|7z|rar)(\?|#|$)/i.test(u); }

  function ytEmbed(u){
    try {
      const url = new URL(u);
      if (!/^(?:www\.)?(youtube\.com|m\.youtube\.com)$/.test(url.host) && url.host !== "youtu.be") return "";
      let id = "";
      if (url.host === "youtu.be") id = url.pathname.replace(/^\//,"");
      else id = url.searchParams.get("v") || "";
      if (!id && url.pathname.includes("/shorts/")) id = url.pathname.split("/shorts/")[1]?.split("/")[0] || "";
      if (!id) return "";
      return "https://www.youtube-nocookie.com/embed/" + encodeURIComponent(id);
    } catch(e){ return ""; }
  }

  function vimeoEmbed(u){
    try {
      const url = new URL(u);
      if (!/vimeo\.com$/.test(url.host)) return "";
      const m = url.pathname.match(/\/(\d+)/);
      if (!m) return "";
      return "https://player.vimeo.com/video/" + encodeURIComponent(m[1]);
    } catch(e){ return ""; }
  }

  function peertubeEmbed(u){
    // Best-effort: supports common PeerTube watch patterns.
    try {
      const url = new URL(u);
      const path = url.pathname || "";
      // /w/<shortId> or /videos/watch/<uuid>
      let id = "";
      const mw = path.match(/\/w\/([^\/]+)/);
      if (mw) id = mw[1];
      const mv = path.match(/\/videos\/watch\/([^\/]+)/);
      if (mv) id = mv[1];
      if (!id) return "";
      // PeerTube embed: /videos/embed/<id> (works for uuid; short ids may redirect)
      return url.origin.replace(/\/$/,"") + "/videos/embed/" + encodeURIComponent(id);
    } catch(e){ return ""; }
  }

  function renderLinkCard(u){
    const host = esc(urlHost(u) || u);
    const urlText = esc(u);
    return `
      <a class="ia-msg-linkcard" href="${urlText}" target="_blank" rel="noopener noreferrer">
        <span class="ia-msg-link-ico" aria-hidden="true">🔗</span>
        <span class="ia-msg-link-meta">
          <div class="ia-msg-link-url">${urlText}</div>
          <div class="ia-msg-link-host">${host}</div>
        </span>
      </a>`;
  }

  function renderRichBody(raw){
    const text = String(raw||"");
    const urls = extractUrls(text);
    if (!urls.length) return esc(text);

    // Build text with clickable links, then add embeds for each unique URL.
    const seen = new Set();
    let html = "";
    let last = 0;

    for (const u of urls){
      const before = text.slice(last, u.index);
      html += esc(before);

      const link = normUrl(u.raw);
      const linkEsc = esc(link);
      html += `<a href="${linkEsc}" target="_blank" rel="noopener noreferrer">${linkEsc}</a>`;
      last = u.index + u.raw.length;

      if (!seen.has(link)){
        seen.add(link);

        const y = ytEmbed(link);
        const v = vimeoEmbed(link);
        const p = peertubeEmbed(link);

        if (isImageUrl(link)) {
          html += `<div class="ia-msg-embed"><button type="button" class="ia-msg-media ia-msg-media-img" data-ia-msg-media-url="${linkEsc}" data-ia-msg-media-type="image" aria-label="Open image"><img src="${linkEsc}" alt="Image" loading="lazy"></button></div>`;
        } else if (isVideoUrl(link) || y || v || p) {
          if (y || v || p) {
            const src = esc(y || v || p);
            html += `<div class="ia-msg-embed"><iframe src="${src}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0;border-radius:10px;"></iframe></div>`;
          } else {
            html += `<div class="ia-msg-embed"><video controls preload="metadata" src="${linkEsc}"></video></div>`;
          }
        } else if (isAudioUrl(link)) {
          html += `<div class="ia-msg-embed"><audio controls preload="metadata" src="${linkEsc}"></audio></div>`;
        } else if (isDocUrl(link)) {
          const fn = esc(fileNameFromUrl(link));
          html += `<div class="ia-msg-embed"><a class="ia-msg-pill" href="${linkEsc}" target="_blank" rel="noopener noreferrer">📄 ${fn}</a></div>`;
        } else {
          html += `<div class="ia-msg-embed">${renderLinkCard(link)}</div>`;
        }
      }
    }
    html += esc(text.slice(last));
    return html;
  }



  const post = (window.IAMessageApi && window.IAMessageApi.post) ? window.IAMessageApi.post : null;
  const postFile = (window.IAMessageApi && window.IAMessageApi.postFile) ? window.IAMessageApi.postFile : null;
  const postFileProgress = (window.IAMessageApi && window.IAMessageApi.postFileProgress) ? window.IAMessageApi.postFileProgress : null;

  // Find message shells (Atrium keeps panels in DOM even if not active)
  function findShells(){
    return qa(document, '.ia-msg-shell[data-panel="' + IA_MESSAGE.panelKey + '"]');
  }

  const S = (window.IAMessageState && window.IAMessageState.S) ? window.IAMessageState.S : function(){ return {}; };

  function el(shell, sel){ return q(shell, sel); }

  function setMobile(shell, view){
    // view: list | chat
    shell.setAttribute("data-mobile-view", view);
  }

  function syncMeFromShell(shell){
    const st = S(shell);
    const attr = shell.getAttribute("data-phpbb-me");
    const v = attr ? Number(attr) : 0;
    if (v > 0) st.me = v;
  }

  function renderThreads(shell){
    const st = S(shell);
    const list = el(shell, "[data-ia-msg-threads]");
    if (!list) return;

    const threads = Array.isArray(st.threads) ? st.threads : [];
    if (!threads.length) {
      list.innerHTML = `<div class="ia-msg-empty">No conversations yet.</div>`;
      return;
    }

    list.innerHTML = threads.map(t => {
      const id = Number(t.id || 0);
      const title = esc(t.title || "Conversation");
      const prev = esc(t.last_preview || "");
      const avatarUrl = String(t.avatarUrl || "").trim();
      const on = (id && id === st.activeId) ? " active" : "";
      return `
        <button type="button" class="ia-msg-thread${on}" data-ia-msg-thread="${id}">
          <div class="ia-msg-thread-row">
            <div class="ia-msg-thread-avatar" aria-hidden="true">${(() => {
              if (avatarUrl) {
                const safe = esc(avatarUrl);
                return `<img class="ia-msg-thread-avatar-img" alt="" src="${safe}">`;
              }
              const ch = (title || "C").trim().charAt(0).toUpperCase() || "C";
              return `<span class="ia-msg-thread-initial">${ch}</span>`;
            })()}</div>
            <div class="ia-msg-thread-text">
              <div class="ia-msg-thread-name">${title}</div>
              <div class="ia-msg-thread-last">${prev}</div>
            </div>
          </div>
        </button>
      `;
    }).join("");
  }

  function renderMessages(shell, thread, forceBottom){
    const st = S(shell);
    syncMeFromShell(shell);

    const titleEl = el(shell, "[data-ia-msg-chat-title]");
    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (!log) return;

    const title = thread && thread.title ? String(thread.title) : "Conversation";
    const dmUser = thread && thread.dm_other_username ? String(thread.dm_other_username) : "";
    const dmUserId = thread && thread.dm_other_phpbb_user_id ? Number(thread.dm_other_phpbb_user_id) : 0;
    if (titleEl) {
      titleEl.textContent = title;
      if (dmUser) {
        titleEl.classList.add('ia-msg-titlelink');
        titleEl.setAttribute('role', 'button');
        titleEl.setAttribute('tabindex', '0');
        titleEl.setAttribute('data-ia-msg-open-profile', dmUser);
        titleEl.setAttribute('data-ia-msg-open-profile-id', String(dmUserId || '0'));
      } else {
        titleEl.classList.remove('ia-msg-titlelink');
        titleEl.removeAttribute('role');
        titleEl.removeAttribute('tabindex');
        titleEl.removeAttribute('data-ia-msg-open-profile');
        titleEl.removeAttribute('data-ia-msg-open-profile-id');
      }
    }

    // Follow/Block buttons (DM only)
    const relBox = el(shell, "[data-ia-msg-rel]");
    const fbtn = el(shell, "[data-ia-msg-follow-user]");
    const bbtn = el(shell, "[data-ia-msg-block-user]");
    if (relBox && dmUserId > 0) {
      relBox.hidden = false;
      relBox.setAttribute('data-target-phpbb', String(dmUserId));
      post('ia_message_user_rel_status', { nonce: IA_MESSAGE.nonceBoot, target_phpbb: String(dmUserId) }).then((st)=>{
        if (st && st.ok && st.data){
          if (fbtn) { if (st.data.following) fbtn.classList.add('is-on'); else fbtn.classList.remove('is-on'); }
          if (bbtn) { if (st.data.blocked_by_me) bbtn.classList.add('is-on'); else bbtn.classList.remove('is-on'); }
          const form = el(shell, "[data-ia-msg-send-form]");
          if (form) {
            if (st.data.blocked_any) form.classList.add('ia-msg-is-blocked');
            else form.classList.remove('ia-msg-is-blocked');
          }
        }
      }).catch(()=>{});
    } else {
      if (relBox) { relBox.hidden = true; relBox.removeAttribute('data-target-phpbb'); }
      const form = el(shell, "[data-ia-msg-send-form]");
      if (form) form.classList.remove('ia-msg-is-blocked');
    }


    const msgs = thread && Array.isArray(thread.messages) ? thread.messages : [];
    st.hasMore = !!(thread && thread.has_more);
    st.nextOffset = (thread && typeof thread.next_offset === 'number') ? Number(thread.next_offset) : (st.hasMore ? (msgs.length || st.msgLimit) : 0);
    if (!msgs.length) {
      log.innerHTML = `<div class="ia-msg-empty">No messages in this conversation.</div>`;
      return;
    }

    const loadMoreHtml = st.hasMore ? `<div class="ia-msg-loadmore"><button type="button" class="ia-msg-btn" data-ia-msg-loadmore>Load older</button></div>` : ``;

    log.innerHTML = loadMoreHtml + msgs.map(m => {
      // ✅ Backend sends: author_phpbb_user_id + is_mine (see includes/render/messages.php)
      const author = Number(m.author_phpbb_user_id || 0);
      const mine = (m && m.is_mine === true) || (st.me > 0 && author === st.me);

      const side = mine ? "out" : "in";
      const cls = mine ? " mine" : "";

      const body = renderRichBody(m.body || "");
      const when = esc(m.created_at || "");
      const ava = esc(String(m.author_avatarUrl || ""));
      const who = esc(String(m.author_label || (mine ? "You" : "")));

      return `
        <div class="ia-msg-row" data-ia-msg-side="${side}" data-ia-msg-mid="${Number(m.id||0)}">
          <button type="button" class="ia-msg-replybtn" data-ia-msg-reply-btn="${Number(m.id||0)}" aria-label="Reply">
            ${replyIconSvg()}
          </button>

          <button type="button" class="ia-msg-fwdbtn" data-ia-msg-forward-btn="${Number(m.id||0)}" aria-label="Forward">
            ${forwardIconSvg()}
          </button>

          <div class="ia-msg-avawrap" data-ia-msg-side="${side}">
            ${ava ? `<img class="ia-msg-ava" alt="" src="${ava}">` : `<span class="ia-msg-ava" aria-hidden="true"></span>`}
          </div>

          <div class="ia-msg-bubble${cls}" data-ia-msg-side="${side}" data-ia-msg-mid="${Number(m.id||0)}">
            ${(m.reply && m.reply.id) ? `
              <button type="button" class="ia-msg-replyquote" data-ia-msg-jump="${Number(m.reply.id||0)}" title="Jump to replied message">
                <div class="ia-msg-replyquote__who">${esc(m.reply.author_label || "User")}</div>
                <div class="ia-msg-replyquote__txt">${esc(clampText(m.reply.excerpt || "", 140))}</div>
              </button>
            ` : ``}
            ${(String(m.body_format||"") === "forward") ? `<div class="ia-msg-meta ia-msg-forwarded">Forwarded message</div>` : ``}
            <div class="ia-msg-body">${body}</div>
            <div class="ia-msg-when">${when}</div>
          </div>
        </div>
      `;
    }).join("");

    ensureChatBottom(shell, !!forceBottom);
  }

  function getMessageById(shell, mid){
    const st = S(shell);
    const thread = st.activeThread;
    if (!thread || !Array.isArray(thread.messages)) return null;
    const id = Number(mid||0);
    if (!id) return null;
    return thread.messages.find(m => Number(m.id||0) === id) || null;
  }

  function setReply(shell, mid){
    const st = S(shell);
    const msg = getMessageById(shell, mid);
    const id = msg ? Number(msg.id||0) : Number(mid||0);
    if (!id) return;

    st.replyToId = id;
    const author = msg ? (msg.is_mine ? "You" : (msg.author_label || "")) : "";
    const bodyTxt = msg ? String(msg.body||"") : "";
    const plain = bodyTxt.replace(/<[^>]+>/g, " ").replace(/\s+/g," ").trim();
    st.replyToMeta = {
      id,
      who: author || "Message",
      excerpt: clampText(plain, 160)
    };
    updateReplybar(shell, true);
  }

  function clearReply(shell){
    const st = S(shell);
    st.replyToId = 0;
    st.replyToMeta = null;
    updateReplybar(shell, false);
  }

  function updateReplybar(shell, focus){
    const st = S(shell);
    const bar = el(shell, "[data-ia-msg-replybar]");
    const whoEl = el(shell, "[data-ia-msg-reply-who]");
    const quoteEl = el(shell, "[data-ia-msg-reply-quote]");
    if (!bar) return;

    if (st.replyToId && st.replyToMeta) {
      bar.hidden = false;
      if (whoEl) whoEl.textContent = String(st.replyToMeta.who || "Message");
      if (quoteEl) quoteEl.textContent = String(st.replyToMeta.excerpt || "");
      if (focus) {
        const ta = el(shell, "[data-ia-msg-send-input]");
        if (ta) { try { ta.focus(); } catch(e){} }
      }
    } else {
      bar.hidden = true;
      if (whoEl) whoEl.textContent = "";
      if (quoteEl) quoteEl.textContent = "";
    }
  }

  function jumpToMessage(shell, mid){
    const id = Number(mid||0);
    if (!id) return;
    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (!log) return;

    const target = q(log, `[data-ia-msg-mid="${id}"]`);
    if (!target) return;

    try {
      target.scrollIntoView({ behavior: "smooth", block: "center" });
    } catch(e){
      try { log.scrollTop = Math.max(0, target.offsetTop - 120); } catch(e2){}
    }

    // flash highlight
    const bubble = target.classList && target.classList.contains("ia-msg-bubble") ? target : q(target, ".ia-msg-bubble");
    if (bubble) {
      bubble.classList.add("ia-msg-jumpflash");
      setTimeout(() => bubble.classList.remove("ia-msg-jumpflash"), 900);
    }
  }

  async function loadThreads(shell){
    const list = el(shell, "[data-ia-msg-threads]");
    if (list) list.innerHTML = `<div class="ia-msg-empty">Loading…</div>`;

    try {
      const res = await post("ia_message_threads", { nonce: IA_MESSAGE.nonceBoot });
      if (!res || !res.success) {
        if (list) list.innerHTML = `<div class="ia-msg-empty">Failed to load threads.</div>`;
        return;
      }

      const st = S(shell);
      st.threadsAll = (res.data && res.data.threads) ? res.data.threads : [];
        st.threads = st.threadsAll;

      // If server returns me, store it; also stamp onto shell for CSS/diagnostics
      if (res.data && res.data.me) {
        st.me = Number(res.data.me) || 0;
        if (st.me > 0) shell.setAttribute("data-phpbb-me", String(st.me));
      } else {
        syncMeFromShell(shell);
      }

      renderThreads(shell);
      loadInvites(shell);
    } catch(e){
      if (list) list.innerHTML = `<div class="ia-msg-empty">Failed to load threads.</div>`;
    }
  }

  async function loadInvites(shell){
    const box = el(shell, '[data-ia-msg-invites]');
    if (!box) return;
    try{
      const res = await post('ia_message_group_invites', { nonce: IA_MESSAGE.nonceBoot });
      const arr = (res && res.success && res.data && Array.isArray(res.data.invites)) ? res.data.invites : [];
      renderInvites(shell, arr);
    } catch(e){
      renderInvites(shell, []);
    }
  }

  function renderInvites(shell, invites){
    const box = el(shell, '[data-ia-msg-invites]');
    if (!box) return;
    const arr = Array.isArray(invites) ? invites : [];
    if (!arr.length) { box.innerHTML = ''; box.hidden = true; return; }
    box.hidden = false;
    box.innerHTML = arr.map(i => {
      const iid = Number(i.invite_id||0);
      const tid = Number(i.thread_id||0);
      const title = esc(String(i.title||'Group chat'));
      const who = esc(String(i.inviter_name||''));
      return `<div class="ia-msg-invite-card" data-ia-msg-invite-id="${iid}" data-ia-msg-invite-thread="${tid}">
        <div><strong>${title}</strong></div>
        <div style="opacity:.85; margin-top:4px;">Invite from ${who}</div>
        <div class="ia-msg-invite-actions">
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-invite-accept="${iid}">Accept</button>
          <button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-invite-ignore="${iid}">Ignore</button>
        </div>
      </div>`;
    }).join('');
  }

  async function loadThread(shell, id){
    const st = S(shell);
    st.activeId = Number(id || 0);
    clearReply(shell);
    renderThreads(shell);

    st.msgLimit = 15;
    st.nextOffset = 0;
    st.hasMore = false;
    st.loadingMore = false;
    st.justOpened = true;

    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (log) log.innerHTML = `<div class="ia-msg-empty">Loading…</div>`;

    try {
      const res = await post("ia_message_thread", { nonce: IA_MESSAGE.nonceBoot, thread_id: st.activeId, limit: st.msgLimit, offset: 0 });
      if (!res || !res.success) {
        if (log) log.innerHTML = `<div class="ia-msg-empty">Failed to load messages.</div>`;
        return;
      }

      // If server includes me/thread meta, accept it
      if (res.data && res.data.me) {
        st.me = Number(res.data.me) || 0;
        if (st.me > 0) shell.setAttribute("data-phpbb-me", String(st.me));
      } else {
        syncMeFromShell(shell);
      }

      const thread = res.data && res.data.thread ? res.data.thread : null;
      st.activeThread = thread;
      renderMessages(shell, thread, true);

      // Show/hide Members button (group only)
      try {
        const mb = el(shell, '[data-ia-msg-action="members"]');
        if (mb) mb.hidden = !(thread && String(thread.type||'') === 'group');
      } catch(e) {}
      setMobile(shell, "chat");
      ensureChatBottom(shell, true);
    } catch(e){
      if (log) log.innerHTML = `<div class="ia-msg-empty">Failed to load messages.</div>`;
    }
  }

  async function loadMore(shell){
    const st = S(shell);
    if (st.loadingMore) return;
    if (!st.activeId || !st.activeThread || !st.hasMore) return;

    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (!log) return;

    const beforeH = log.scrollHeight;
    const beforeTop = log.scrollTop;

    st.loadingMore = true;
    try {
      const res = await post("ia_message_thread", {
        nonce: IA_MESSAGE.nonceBoot,
        thread_id: Number(st.activeId||0),
        limit: st.msgLimit,
        offset: Number(st.nextOffset||0)
      });
      if (!res || !res.success || !res.data || !res.data.thread) return;

      const chunk = res.data.thread;
      const older = (chunk && Array.isArray(chunk.messages)) ? chunk.messages : [];

      const cur = st.activeThread && Array.isArray(st.activeThread.messages) ? st.activeThread.messages : [];
      st.activeThread.messages = older.concat(cur);
      st.activeThread.has_more = !!chunk.has_more;
      st.activeThread.next_offset = chunk.next_offset;
      st.hasMore = !!chunk.has_more;
      st.nextOffset = (typeof chunk.next_offset === 'number') ? Number(chunk.next_offset) : 0;

      // Render without forcing bottom.
      const keep = st.atBottom;
      st.atBottom = false;
      const titleEl = el(shell, "[data-ia-msg-chat-title]");
      if (titleEl && chunk.title) titleEl.textContent = String(chunk.title);
      renderMessages(shell, st.activeThread, false);

      // Restore scroll position so user doesn't jump to bottom.
      const afterH = log.scrollHeight;
      const delta = afterH - beforeH;
      log.scrollTop = beforeTop + delta;
      st.atBottom = keep;
    } catch(e) {
      // no-op
    } finally {
      st.loadingMore = false;
    }
  }

  async function sendMessage(shell){
    const st = S(shell);
    if (st.sendBusy) return;
    const tid = Number(st.activeId || 0);
    if (!tid) return;

    const ta = el(shell, "[data-ia-msg-send-input]");
    const body = ta ? String(ta.value || "").trim() : "";
    if (!body) return;

    st.sendBusy = true;
    try {
      const payload = { nonce: IA_MESSAGE.nonceBoot, thread_id: tid, body };
      if (st.replyToId && Number(st.replyToId) > 0) payload.reply_to_message_id = Number(st.replyToId);
      const res = await post("ia_message_send", payload);
      if (res && res.success) {
        if (ta) ta.value = "";
        clearReply(shell);
        await loadThread(shell, tid);
        await loadThreads(shell);
      }
    } finally {
      st.sendBusy = false;
    }
  }

  async function userSearch(query){
    try {
      const res = await post("ia_message_user_search", { nonce: IA_MESSAGE.nonceBoot, q: query });
      if (!res || !res.success) return [];
      return (res.data && res.data.results) ? res.data.results : [];
    } catch(e){ return []; }
  }

  async function openMembersSheet(shell){
    const st = S(shell);
    if (!st.activeThread || String(st.activeThread.type||'') !== 'group') return;
    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    if (!sheet) return;

    // Open first (so user sees loading state)
    sheet.setAttribute('aria-hidden', 'false');
    sheet.classList.add('open');

    const meta = el(sheet, '[data-ia-msg-members-meta]');
    const list = el(sheet, '[data-ia-msg-members-list]');
    const inviteBox = el(sheet, '[data-ia-msg-members-invite]');
    if (meta) meta.innerHTML = '';
    if (list) list.innerHTML = '<div class="ia-msg-empty">Loading…</div>';

    try{
      const res = await post('ia_message_group_members', { nonce: IA_MESSAGE.nonceBoot, thread_id: Number(st.activeId||0) });
      const members = (res && res.success && res.data && Array.isArray(res.data.members)) ? res.data.members : [];
      const meIsMod = !!(res && res.success && res.data && res.data.me_is_mod);
      if (meta) meta.innerHTML = `<div style="opacity:.85;">${members.length} member${members.length===1?'':'s'}</div>`;
      if (inviteBox) inviteBox.hidden = !meIsMod;
      if (list) {
        const myId = Number(st.me||0);
        list.innerHTML = members.map(m => {
          const pid = Number(m.phpbb_user_id||0);
          const name = esc(String(m.display||('User #'+pid)));
          const ava = esc(String(m.avatarUrl||''));
          const isMod = !!m.is_mod;
          const canKick = meIsMod && pid && pid !== myId && !isMod;
          return `<div class="ia-msg-member-row">
            ${ava ? `<img class="ia-msg-member-ava" alt="" src="${ava}">` : `<span class="ia-msg-member-ava" aria-hidden="true"></span>`}
            <div class="ia-msg-member-name">${name}${isMod ? `<span class="ia-msg-member-badge">(mod)</span>` : ``}</div>
            ${canKick ? `<button type="button" class="ia-msg-btn ia-msg-btn-small" data-ia-msg-kick="${pid}">Kick</button>` : ``}
          </div>`;
        }).join('');
      }
    } catch(e){
      if (list) list.innerHTML = '<div class="ia-msg-empty">Failed to load members.</div>';
      if (inviteBox) inviteBox.hidden = true;
    }

    // Bind invite picker UI (mods only)
    if (inviteBox && !inviteBox.hidden) {
      const st = S(shell);
      st.inviteSelected = [];
      st.inviteLastResults = [];
      st.inviteBrowseResults = [];
      renderInviteSelected(shell);

      const q = el(inviteBox, '[data-ia-msg-invite-q]');
      const sug = el(inviteBox, '[data-ia-msg-invite-suggest]');
      if (q && sug) {
        q.value = '';
        sug.innerHTML = '';
        sug.classList.remove('open');
      }
      loadInviteBrowse(shell);
    }
  }

  function renderSuggest(box, results, onPick){
    if (!box) return;
    const arr = Array.isArray(results) ? results : [];

    if (!arr.length) {
      box.innerHTML = ``;
      box.classList.remove("open");
      return;
    }

    box.innerHTML = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ("User #" + id));
      const avatar = String(r.avatarUrl || r.avatar || '').trim();
      const safeDisplay = esc(display);
      const safeUser = esc(username);
      const safeAvatar = esc(avatar);

      return `
        <button type="button" class="ia-msg-suggest-item" data-pick="${id}" data-username="${safeUser}" data-display="${safeDisplay}">
          ${safeAvatar ? `<img class="ia-msg-suggest-avatar" alt="" src="${safeAvatar}">` : `<span class="ia-msg-suggest-avatar" aria-hidden="true"></span>`}
          <div>
            <div class="ia-msg-suggest-name">${safeDisplay}</div>
            ${safeUser ? `<div class="ia-msg-suggest-username">@${safeUser}</div>` : ``}
          </div>
        </button>
      `;
    }).join("");

    box.classList.add("open");

    box.onclick = (e) => {
      const b = e.target.closest("[data-pick]");
      if (!b) return;
      onPick({
        id: Number(b.getAttribute("data-pick")||0),
        username: String(b.getAttribute("data-username")||""),
        display: String(b.getAttribute("data-display")||"")
      });
    };
  }
  // ---------------------------
  // Members sheet: invite picker (multi-select like group creation)
  // ---------------------------
  function renderInviteSelected(shell){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-invite-selected]');
    const send = el(sheet, '[data-ia-msg-invite-send]');
    if (!box || !send) return;

    const sel = Array.isArray(st.inviteSelected) ? st.inviteSelected : [];
    send.disabled = (sel.length < 1);

    if (sel.length < 1) { box.innerHTML = ''; return; }

    box.innerHTML = sel.map(u => {
      const id = Number(u.id||0);
      const name = esc(u.display || u.username || ('User #' + id));
      return `<span class="ia-msg-chip">
        <span class="ia-msg-chip-name">${name}</span>
        <button type="button" class="ia-msg-chip-x" data-ia-msg-invite-remove="${id}" aria-label="Remove">×</button>
      </span>`;
    }).join('');
  }

  function addInviteRecipient(shell, picked){
    const st = S(shell);
    const id = Number(picked && picked.id || 0);
    if (!id) return;

    st.inviteSelected = Array.isArray(st.inviteSelected) ? st.inviteSelected : [];
    if (!st.inviteSelected.some(u => Number(u.id||0) === id)) {
      st.inviteSelected.push({
        id,
        username: String(picked.username||''),
        display: String(picked.display||picked.username||''),
      });
    }
    renderInviteSelected(shell);
  }

  function removeInviteRecipient(shell, id){
    const st = S(shell);
    const pid = Number(id||0);
    if (!pid) return;
    st.inviteSelected = Array.isArray(st.inviteSelected) ? st.inviteSelected : [];
    st.inviteSelected = st.inviteSelected.filter(u => Number(u.id||0) !== pid);
    renderInviteSelected(shell);
  }

  function renderInviteSuggest(shell, results){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-invite-suggest]');
    if (!box) return;

    const arr = Array.isArray(results) ? results : [];
    st.inviteLastResults = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      return { id, username, display };
    }).filter(o => Number(o.id||0) > 0);

    if (!arr.length) {
      box.innerHTML = '';
      box.classList.remove('open');
      return;
    }

    const selectedIds = new Set((Array.isArray(st.inviteSelected) ? st.inviteSelected : []).map(u => Number(u.id||0)));

    box.innerHTML = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      const avatar = String(r.avatarUrl || r.avatar || '').trim();
      const safeDisplay = esc(display);
      const safeUser = esc(username);
      const safeAvatar = esc(avatar);
      const on = selectedIds.has(id) ? ' checked' : '';

      return `
        <button type="button" class="ia-msg-suggest-item" data-ia-msg-invite-pick="${id}" data-username="${safeUser}" data-display="${safeDisplay}">
          <input type="checkbox" tabindex="-1" aria-hidden="true"${on}>
          ${safeAvatar ? `<img class="ia-msg-suggest-avatar" alt="" src="${safeAvatar}">` : `<span class="ia-msg-suggest-avatar" aria-hidden="true"></span>`}
          <div>
            <div class="ia-msg-suggest-name">${safeDisplay}</div>
            ${safeUser ? `<div class="ia-msg-suggest-user">@${safeUser}</div>` : ``}
          </div>
        </button>
      `;
    }).join('');

    box.classList.add('open');
  }

  function renderInviteBrowse(shell, results){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-invite-browse]');
    if (!box) return;

    const arr = Array.isArray(results) ? results : [];
    st.inviteBrowseResults = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      return { id, username, display };
    }).filter(o => Number(o.id||0) > 0);

    const selectedIds = new Set((Array.isArray(st.inviteSelected) ? st.inviteSelected : []).map(u => Number(u.id||0)));

    if (!arr.length) { box.innerHTML = '<div class="ia-msg-empty">No users found.</div>'; return; }

    box.innerHTML = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      const avatar = String(r.avatarUrl || r.avatar || '').trim();
      const safeDisplay = esc(display);
      const safeUser = esc(username);
      const safeAvatar = esc(avatar);
      const on = selectedIds.has(id) ? ' checked' : '';
      return `
        <button type="button" class="ia-msg-suggest-item" data-ia-msg-invite-pick="${id}" data-username="${safeUser}" data-display="${safeDisplay}">
          <input type="checkbox" tabindex="-1" aria-hidden="true"${on}>
          ${safeAvatar ? `<img class="ia-msg-suggest-avatar" alt="" src="${safeAvatar}">` : `<span class="ia-msg-suggest-avatar" aria-hidden="true"></span>`}
          <div>
            <div class="ia-msg-suggest-name">${safeDisplay}</div>
            ${safeUser ? `<div class="ia-msg-suggest-user">@${safeUser}</div>` : ``}
          </div>
        </button>
      `;
    }).join('');
  }

  async function loadInviteBrowse(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-invite-browse]');
    if (!box) return;
    box.innerHTML = '<div class="ia-msg-empty">Loading…</div>';
    try{
      const res = await post('ia_message_user_search', { nonce: IA_MESSAGE.nonceBoot, q:'', limit:25, offset:0 });
      const arr = res && res.success && res.data && Array.isArray(res.data.results) ? res.data.results : [];
      renderInviteBrowse(shell, arr);
    } catch(e){
      box.innerHTML = '<div class="ia-msg-empty">Failed to load users.</div>';
    }
  }

  function toggleInvitePick(shell, picked){
    const st = S(shell);
    const id = Number(picked && picked.id || 0);
    if (!id) return;

    st.inviteSelected = Array.isArray(st.inviteSelected) ? st.inviteSelected : [];
    const exists = st.inviteSelected.some(u => Number(u.id||0) === id);
    if (exists) {
      st.inviteSelected = st.inviteSelected.filter(u => Number(u.id||0) !== id);
    } else {
      st.inviteSelected.push({
        id,
        username: String(picked.username||''),
        display: String(picked.display||picked.username||''),
      });
    }
    renderInviteSelected(shell);
    // Refresh checkboxes in both lists without refetching.
    try{
      const sug = el(shell, '[data-ia-msg-invite-suggest]');
      if (sug && sug.classList.contains('open')) {
        const v = String(el(shell, '[data-ia-msg-invite-q]')?.value || '').trim();
        if (v.length > 0 && Array.isArray(st.inviteLastResults)) {
          // Re-render current suggest list state (best-effort)
          // We don't have avatars here, so just toggle checkbox state in DOM.
          qa(sug, '[data-ia-msg-invite-pick] input[type="checkbox"]').forEach(cb => cb.checked = false);
          qa(sug, '[data-ia-msg-invite-pick]').forEach(btn => {
            const pid = Number(btn.getAttribute('data-ia-msg-invite-pick')||0);
            const on = st.inviteSelected.some(u => Number(u.id||0) === pid);
            const cb = btn.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = on;
          });
        }
      }
      const browse = el(shell, '[data-ia-msg-invite-browse]');
      if (browse) {
        qa(browse, '[data-ia-msg-invite-pick]').forEach(btn => {
          const pid = Number(btn.getAttribute('data-ia-msg-invite-pick')||0);
          const on = st.inviteSelected.some(u => Number(u.id||0) === pid);
          const cb = btn.querySelector('input[type="checkbox"]');
          if (cb) cb.checked = on;
        });
      }
    }catch(e){}
  }

  function inviteSelectAll(shell){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    if (!sheet) return;

    const v = String(el(sheet, '[data-ia-msg-invite-q]')?.value || '').trim();
    const src = (v.length > 0 && Array.isArray(st.inviteLastResults)) ? st.inviteLastResults : (Array.isArray(st.inviteBrowseResults) ? st.inviteBrowseResults : []);
    if (!src.length) return;

    src.forEach(u => addInviteRecipient(shell, u));
    renderInviteSelected(shell);
    // Update checkboxes
    try{
      qa(sheet, '[data-ia-msg-invite-pick]').forEach(btn => {
        const pid = Number(btn.getAttribute('data-ia-msg-invite-pick')||0);
        const on = st.inviteSelected.some(x => Number(x.id||0) === pid);
        const cb = btn.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = on;
      });
    }catch(e){}
  }

  function inviteClear(shell){
    const st = S(shell);
    st.inviteSelected = [];
    renderInviteSelected(shell);
    try{
      const sheet = el(shell, '[data-ia-msg-sheet="members"]');
      if (!sheet) return;
      qa(sheet, '[data-ia-msg-invite-pick] input[type="checkbox"]').forEach(cb => cb.checked = false);
    }catch(e){}
  }

  async function sendInvites(shell){
    const st = S(shell);
    if (!st.activeId) return;

    const sheet = el(shell, '[data-ia-msg-sheet="members"]');
    const send = sheet ? el(sheet, '[data-ia-msg-invite-send]') : null;
    if (send) send.disabled = true;

    const sel = Array.isArray(st.inviteSelected) ? st.inviteSelected : [];
    for (const u of sel) {
      const pid = Number(u.id||0);
      if (!pid) continue;
      try{
        await post('ia_message_group_invite_send', { nonce: IA_MESSAGE.nonceBoot, thread_id: Number(st.activeId||0), to_phpbb: String(pid) });
      }catch(e){}
    }

    st.inviteSelected = [];
    renderInviteSelected(shell);
    if (sheet) {
      const iq = el(sheet, '[data-ia-msg-invite-q]');
      const isug = el(sheet, '[data-ia-msg-invite-suggest]');
      if (iq) iq.value = '';
      if (isug) { isug.classList.remove('open'); isug.innerHTML = ''; }
    }
    await loadInviteBrowse(shell);
  }



  function openSheet(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    sheet.setAttribute("aria-hidden", "false");
    sheet.classList.add("open");


    iaMsgSetBottomNavHeightVar();

    // Default to DM mode each time.
    setNewMode(shell, 'dm');
  }

  
  async function getPrefs(){
    try{
      const res = await post("ia_message_prefs_get", { nonce: IA_MESSAGE.nonceBoot });
      if (res && res.success && res.data) return res.data;
    }catch(e){}
    return { email: true, popup: true };
  }

  async function setPrefs(p){
    try{
      const res = await post("ia_message_prefs_set", { nonce: IA_MESSAGE.nonceBoot, prefs: JSON.stringify(p||{}) });
      return !!(res && res.success);
    }catch(e){}
    return false;
  }

  async function openPrefs(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="prefs"]');
    if (!sheet) return;
    sheet.setAttribute("aria-hidden", "false");
    sheet.classList.add("open");

    const prefs = await getPrefs();
    qa(sheet, "[data-ia-msg-pref]").forEach(cb => {
      const k = cb.getAttribute("data-ia-msg-pref");
      cb.checked = (prefs && prefs[k] !== undefined) ? !!prefs[k] : true;
      cb.onchange = async () => {
        const cur = {
          email: !!el(sheet, '[data-ia-msg-pref="email"]')?.checked,
          popup: !!el(sheet, '[data-ia-msg-pref="popup"]')?.checked
        };
        await setPrefs(cur);
      };
    });
  }

  function renderFwdSelected(shell){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="forward"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-fwd-selected]');
    const hid = el(sheet, '[data-ia-msg-fwd-to]');
    const send = el(sheet, '[data-ia-msg-fwd-send]');
    if (!box || !hid || !send) return;

    const sel = Array.isArray(st.fwdSelected) ? st.fwdSelected : [];
    hid.value = sel.map(u => String(u.id||"")).filter(Boolean).join(',');
    send.disabled = sel.length < 1;

    if (sel.length < 1) {
      box.innerHTML = '';
      return;
    }

    box.innerHTML = sel.map(u => {
      const id = Number(u.id||0);
      const name = esc(u.display || u.username || ("User #" + id));
      return `<span class="ia-msg-chip">
        <span class="ia-msg-chip-name">${name}</span>
        <button type="button" class="ia-msg-chip-x" data-ia-msg-fwd-remove="${id}" aria-label="Remove">×</button>
      </span>`;
    }).join('');
  }

  function addFwdRecipient(shell, picked){
    const st = S(shell);
    const id = Number(picked && picked.id || 0);
    if (!id) return;

    st.fwdSelected = Array.isArray(st.fwdSelected) ? st.fwdSelected : [];
    if (!st.fwdSelected.some(u => Number(u.id||0) === id)) {
      st.fwdSelected.push({
        id,
        username: String(picked.username||""),
        display: String(picked.display||picked.username||"")
      });
    }

    const sheet = el(shell, '[data-ia-msg-sheet="forward"]');
    const qF = sheet ? el(sheet, '[data-ia-msg-fwd-q]') : null;
    const sug = sheet ? el(sheet, '[data-ia-msg-fwd-suggest]') : null;
    if (qF) qF.value = '';
    if (sug) { sug.classList.remove('open'); sug.innerHTML = ''; }

    renderFwdSelected(shell);
  }

  // ---------------------------
  // Group chat new thread (multi-select)
  // ---------------------------
  function renderGroupSelected(shell){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-group-selected]');
    const hid = el(sheet, '[data-ia-msg-group-members]');
    const start = el(sheet, '[data-ia-msg-group-start]');
    if (!box || !hid || !start) return;

    const sel = Array.isArray(st.groupSelected) ? st.groupSelected : [];
    hid.value = sel.map(u => String(u.id||'')).filter(Boolean).join(',');
    start.disabled = (sel.length < 1);

    if (sel.length < 1) { box.innerHTML = ''; return; }

    box.innerHTML = sel.map(u => {
      const id = Number(u.id||0);
      const name = esc(u.display || u.username || ('User #' + id));
      return `<span class="ia-msg-chip">
        <span class="ia-msg-chip-name">${name}</span>
        <button type="button" class="ia-msg-chip-x" data-ia-msg-group-remove="${id}" aria-label="Remove">×</button>
      </span>`;
    }).join('');
  }

  function addGroupRecipient(shell, picked){
    const st = S(shell);
    const id = Number(picked && picked.id || 0);
    if (!id) return;

    st.groupSelected = Array.isArray(st.groupSelected) ? st.groupSelected : [];
    if (!st.groupSelected.some(u => Number(u.id||0) === id)) {
      st.groupSelected.push({
        id,
        username: String(picked.username||""),
        display: String(picked.display||picked.username||""),
      });
    }
    renderGroupSelected(shell);
  }

  function removeGroupRecipient(shell, id){
    const st = S(shell);
    const pid = Number(id||0);
    if (!pid) return;
    st.groupSelected = Array.isArray(st.groupSelected) ? st.groupSelected : [];
    st.groupSelected = st.groupSelected.filter(u => Number(u.id||0) !== pid);
    renderGroupSelected(shell);
  }

  function renderGroupSuggest(shell, results){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-group-suggest]');
    if (!box) return;

    const arr = Array.isArray(results) ? results : [];
    st.groupLastResults = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      return { id, username, display };
    }).filter(o => Number(o.id||0) > 0);

    if (!arr.length) {
      box.innerHTML = '';
      box.classList.remove('open');
      return;
    }

    const selectedIds = new Set((Array.isArray(st.groupSelected) ? st.groupSelected : []).map(u => Number(u.id||0)));

    box.innerHTML = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      const avatar = String(r.avatarUrl || r.avatar || '').trim();
      const safeDisplay = esc(display);
      const safeUser = esc(username);
      const safeAvatar = esc(avatar);
      const on = selectedIds.has(id) ? ' checked' : '';

      return `
        <button type="button" class="ia-msg-suggest-item" data-ia-msg-group-pick="${id}" data-username="${safeUser}" data-display="${safeDisplay}">
          <input type="checkbox" tabindex="-1" aria-hidden="true"${on}>
          ${safeAvatar ? `<img class="ia-msg-suggest-avatar" alt="" src="${safeAvatar}">` : `<span class="ia-msg-suggest-avatar" aria-hidden="true"></span>`}
          <div>
            <div class="ia-msg-suggest-name">${safeDisplay}</div>
            ${safeUser ? `<div class="ia-msg-suggest-user">@${safeUser}</div>` : ``}
          </div>
        </button>
      `;
    }).join('');

    box.classList.add('open');
  }

  function renderGroupBrowse(shell, results){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-group-browse]');
    if (!box) return;

    const arr = Array.isArray(results) ? results : [];
    st.groupBrowseResults = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      return { id, username, display };
    }).filter(o => Number(o.id||0) > 0);

    const selectedIds = new Set((Array.isArray(st.groupSelected) ? st.groupSelected : []).map(u => Number(u.id||0)));

    if (!arr.length) { box.innerHTML = '<div class="ia-msg-empty">No users found.</div>'; return; }

    box.innerHTML = arr.map(r => {
      const id = Number(r.phpbb_user_id || r.id || 0);
      const username = String(r.username || '').trim();
      const display = String(r.display || r.label || username || ('User #' + id));
      const avatar = String(r.avatarUrl || r.avatar || '').trim();
      const safeDisplay = esc(display);
      const safeUser = esc(username);
      const safeAvatar = esc(avatar);
      const on = selectedIds.has(id) ? ' checked' : '';
      return `
        <button type="button" class="ia-msg-suggest-item" data-ia-msg-group-pick="${id}" data-username="${safeUser}" data-display="${safeDisplay}">
          <input type="checkbox" tabindex="-1" aria-hidden="true"${on}>
          ${safeAvatar ? `<img class="ia-msg-suggest-avatar" alt="" src="${safeAvatar}">` : `<span class="ia-msg-suggest-avatar" aria-hidden="true"></span>`}
          <div>
            <div class="ia-msg-suggest-name">${safeDisplay}</div>
            ${safeUser ? `<div class="ia-msg-suggest-user">@${safeUser}</div>` : ``}
          </div>
        </button>
      `;
    }).join('');
  }

  async function loadGroupBrowse(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    const box = el(sheet, '[data-ia-msg-group-browse]');
    if (!box) return;
    box.innerHTML = '<div class="ia-msg-empty">Loading…</div>';
    try{
      const res = await post('ia_message_user_search', { nonce: IA_MESSAGE.nonceBoot, q:'', limit:25, offset:0 });
      const arr = res && res.success && res.data && Array.isArray(res.data.results) ? res.data.results : [];
      renderGroupBrowse(shell, arr);
    } catch(e){
      box.innerHTML = '<div class="ia-msg-empty">Failed to load users.</div>';
    }
  }

  function setNewMode(shell, mode){
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    const m = (mode === 'group') ? 'group' : 'dm';

    qa(sheet, '[data-ia-msg-new-mode]').forEach(btn => {
      const on = (btn.getAttribute('data-ia-msg-new-mode') === m);
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });

    qa(sheet, '[data-ia-msg-newmode]').forEach(box => {
      const k = box.getAttribute('data-ia-msg-newmode');
      box.hidden = (k !== m);
    });

    // Reset per-mode state when switching.
    if (m === 'group') {
      const st = S(shell);
      st.groupSelected = [];
      st.groupLastResults = [];
      st.groupBrowseResults = [];
      renderGroupSelected(shell);
      const gq = el(sheet, '[data-ia-msg-group-q]');
      const sug = el(sheet, '[data-ia-msg-group-suggest]');
      if (gq) gq.value = '';
      if (sug) { sug.innerHTML = ''; sug.classList.remove('open'); }
      loadGroupBrowse(shell);
    }
  }

  async function openForward(shell, mid){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="forward"]');
    if (!sheet) return;

    st.fwdSelected = [];
    renderFwdSelected(shell);

    const hidMid = el(sheet, '[data-ia-msg-fwd-mid]');
    if (hidMid) hidMid.value = String(Number(mid||0));

    const msg = getMessageById(shell, mid);
    const prev = el(sheet, '[data-ia-msg-fwd-preview]');
    if (prev) {
      const bodyTxt = msg ? String(msg.body||"") : "";
      const plain = bodyTxt.replace(/<[^>]+>/g, " ").replace(/\s+/g," ").trim();
      prev.textContent = plain ? clampText(plain, 220) : "Forward this message?";
    }

    sheet.setAttribute('aria-hidden', 'false');
    sheet.classList.add('open');

    const qF = el(sheet, '[data-ia-msg-fwd-q]');
    if (qF) { try { qF.focus(); } catch(e){} }
  }

  async function sendForward(shell){
    const st = S(shell);
    const sheet = el(shell, '[data-ia-msg-sheet="forward"]');
    if (!sheet) return;

    const hidTo = el(sheet, '[data-ia-msg-fwd-to]');
    const hidMid = el(sheet, '[data-ia-msg-fwd-mid]');
    const to = hidTo ? String(hidTo.value||'').trim() : '';
    const mid = hidMid ? Number(hidMid.value||0) : 0;

    if (!to || !mid || !st.activeId) return;

    const sendBtn = el(sheet, '[data-ia-msg-fwd-send]');
    if (sendBtn) sendBtn.disabled = true;

    try{
      const res = await post('ia_message_forward', {
        nonce: IA_MESSAGE.nonceBoot,
        source_thread_id: Number(st.activeId||0),
        message_id: Number(mid||0),
        to: to
      });

      if (res && res.success) {
        closeSheet(shell);
        await loadThreads(shell);
      } else {
        if (sendBtn) sendBtn.disabled = false;
      }
    }catch(e){
      if (sendBtn) sendBtn.disabled = false;
    }
  }



  function applyThreadFilter(shell, qstr){
    const st = S(shell);
    const all = Array.isArray(st.threadsAll) ? st.threadsAll : (Array.isArray(st.threads) ? st.threads : []);
    const q = String(qstr||"").trim().toLowerCase();
    if (!q) {
      st.threads = all;
      renderThreads(shell);
      return;
    }
    st.threads = all.filter(t => {
      const title = String(t.title||"").toLowerCase();
      const prev  = String(t.last_preview||"").toLowerCase();
      return title.includes(q) || prev.includes(q);
    });
    renderThreads(shell);
  }

  // Prevent external/layout scripts from hiding the sidebar actions.
  // We only enforce within the message shell.
  function lockSidebarActions(shell){
    if (shell.getAttribute('data-ia-msg-lock-actions') === '1') return;
    shell.setAttribute('data-ia-msg-lock-actions','1');

    const sel = '.ia-msg-left-head > .ia-msg-btn';
    const fix = () => {
      qa(shell, sel).forEach(btn => {
        // If some script sets inline styles or toggles hidden/aria-hidden, undo.
        btn.style.display = 'inline-flex';
        btn.style.visibility = 'visible';
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
        if (btn.hasAttribute('hidden')) btn.removeAttribute('hidden');
        if (btn.getAttribute('aria-hidden') === 'true') btn.setAttribute('aria-hidden','false');
      });
    };

    // Run immediately and on the next frame (covers "flash then hide").
    fix();
    try { requestAnimationFrame(fix); } catch(_) {}
    setTimeout(fix, 60);
    setTimeout(fix, 250);

    // Observe style/class/hidden changes on the header subtree.
    const head = el(shell, '.ia-msg-left-head');
    if (head && window.MutationObserver) {
      const mo = new MutationObserver(() => fix());
      mo.observe(head, { attributes:true, childList:true, subtree:true, attributeFilter:['style','class','hidden','aria-hidden'] });
    }

    // Re-apply on resize/orientation changes.
    const onR = () => fix();
    window.addEventListener('resize', onR, { passive:true });
    window.addEventListener('orientationchange', onR, { passive:true });
  }
function closeSheet(shell){
    qa(shell, '.ia-msg-sheet').forEach(sheet => {
      sheet.setAttribute("aria-hidden","true");
      sheet.classList.remove("open");
    });
  }

  function bindOnce(shell){
    if (shell.getAttribute("data-ia-msg-ready") === "1") return;
    shell.setAttribute("data-ia-msg-ready", "1");

    shell.addEventListener("click", (e) => {
      const prof = e.target.closest('[data-ia-msg-open-profile]');
      if (prof) {
        try { e.preventDefault(); } catch (_) {}
        const uname = String(prof.getAttribute('data-ia-msg-open-profile') || '').trim();
        if (uname) {
          try { window.dispatchEvent(new CustomEvent('ia_atrium:navigate', {detail:{tab:'connect', ia_profile_name: uname}})); } catch(e2) {}
          try {
            const u = new URL(location.href);
            u.searchParams.set('tab','connect');
            u.searchParams.set('ia_profile_name', uname);
            u.searchParams.delete('ia_msg_to');
            location.href = u.toString();
            return;
          } catch(e3) {}
        }
        return;
      }
      const act = e.target.closest("[data-ia-msg-action]");
      if (act) {
        // If action is an anchor, prevent the default "#" navigation.
        if (act.tagName === 'A') { try { e.preventDefault(); } catch(_){} }
        const a = act.getAttribute("data-ia-msg-action");
        if (a === "new") openSheet(shell);
        if (a === "prefs") { iaMsgSetBottomNavHeightVar(); openPrefs(shell); }
        if (a === "back") setMobile(shell, "list");
        if (a === "members") { iaMsgSetBottomNavHeightVar(); openMembersSheet(shell); }
        if (a === "close") closeMessages();
        return;
      }

      const invAcc = e.target.closest('[data-ia-msg-invite-accept]');
      if (invAcc) {
        e.preventDefault();
        const iid = Number(invAcc.getAttribute('data-ia-msg-invite-accept')||0);
        if (!iid) return;
        (async()=>{
          const res = await post('ia_message_group_invite_respond', { nonce: IA_MESSAGE.nonceBoot, invite_id: iid, response: 'accept' });
          if (res && res.success && res.data && res.data.thread_id) {
            await loadThreads(shell);
            await loadThread(shell, Number(res.data.thread_id||0));
          } else {
            await loadThreads(shell);
          }
        })();
        return;
      }

      const invIgn = e.target.closest('[data-ia-msg-invite-ignore]');
      if (invIgn) {
        e.preventDefault();
        const iid = Number(invIgn.getAttribute('data-ia-msg-invite-ignore')||0);
        if (!iid) return;
        (async()=>{
          await post('ia_message_group_invite_respond', { nonce: IA_MESSAGE.nonceBoot, invite_id: iid, response: 'ignore' });
          await loadThreads(shell);
        })();
        return;
      }

      const kickBtn = e.target.closest('[data-ia-msg-kick]');
      if (kickBtn) {
        e.preventDefault();
        const pid = Number(kickBtn.getAttribute('data-ia-msg-kick')||0);
        const st = S(shell);
        if (!pid || !st.activeId) return;
        (async()=>{
          await post('ia_message_group_kick', { nonce: IA_MESSAGE.nonceBoot, thread_id: Number(st.activeId||0), kick_phpbb: pid });
          iaMsgSetBottomNavHeightVar();
          openMembersSheet(shell);
          await loadThreads(shell);
        })();
        return;
      }

      const close = e.target.closest("[data-ia-msg-sheet-close]");
      if (close) { closeSheet(shell); return; }

      const threadBtn = e.target.closest("[data-ia-msg-thread]");
      if (threadBtn) {
        const tid = Number(threadBtn.getAttribute("data-ia-msg-thread") || 0);
        if (tid) loadThread(shell, tid);
        return;
      }

      const selfBtn = e.target.closest("[data-ia-msg-new-self]");
      if (selfBtn) {
        const hid = el(shell, "[data-ia-msg-new-to-phpbb]");
        const qn  = el(shell, "[data-ia-msg-new-q]");
        if (hid) hid.value = "-1";
        if (qn) qn.value = "Notes (Self)";
        const start = el(shell, "[data-ia-msg-new-start]");
        if (start) start.disabled = false;
        return;
      }

      const startBtn = e.target.closest("[data-ia-msg-new-start]");
      if (startBtn) {
        (async () => {
          const hid = el(shell, "[data-ia-msg-new-to-phpbb]");
          const body = el(shell, "[data-ia-msg-new-body]");
          const to = hid ? Number(hid.value || 0) : 0;
          const msg = body ? String(body.value || "").trim() : "";
          if (!to) return;

          const res = await post("ia_message_new_dm", { nonce: IA_MESSAGE.nonceBoot, to_phpbb: to, body: msg });
          if (res && res.success) {
            closeSheet(shell);
            await loadThreads(shell);
            const tid = res.data && res.data.thread_id ? Number(res.data.thread_id) : 0;
            if (tid) await loadThread(shell, tid);
          }
        })();
        return;
      }

      const modeBtn = e.target.closest('[data-ia-msg-new-mode]');
      if (modeBtn) {
        e.preventDefault();
        const m = String(modeBtn.getAttribute('data-ia-msg-new-mode') || 'dm');
        setNewMode(shell, m);
        return;
      }


      const invPick = e.target.closest('[data-ia-msg-invite-pick]');
      if (invPick) {
        e.preventDefault();
        const id = Number(invPick.getAttribute('data-ia-msg-invite-pick') || 0);
        if (!id) return;
        const username = String(invPick.getAttribute('data-username') || '');
        const display = String(invPick.getAttribute('data-display') || '');
        toggleInvitePick(shell, { id, username, display });
        return;
      }

      const invRm = e.target.closest('[data-ia-msg-invite-remove]');
      if (invRm) {
        e.preventDefault();
        const id = Number(invRm.getAttribute('data-ia-msg-invite-remove') || 0);
        if (id) removeInviteRecipient(shell, id);
        return;
      }

      const grpPick = e.target.closest('[data-ia-msg-group-pick]');
      if (grpPick) {
        e.preventDefault();
        const id = Number(grpPick.getAttribute('data-ia-msg-group-pick') || 0);
        if (!id) return;
        const username = String(grpPick.getAttribute('data-username') || '');
        const display = String(grpPick.getAttribute('data-display') || '');
        // Toggle
        const st = S(shell);
        const has = Array.isArray(st.groupSelected) && st.groupSelected.some(u => Number(u.id||0) === id);
        if (has) removeGroupRecipient(shell, id);
        else addGroupRecipient(shell, { id, username, display });

        // Re-render suggest to reflect checkbox state.
        const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
        const sug = sheet ? el(sheet, '[data-ia-msg-group-suggest]') : null;
        if (sug && sug.classList.contains('open')) {
          // best-effort: just toggle the checkbox in place
          try {
            const cb = grpPick.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = !has;
          } catch(e) {}
        }
        return;
      }

      const grpRm = e.target.closest('[data-ia-msg-group-remove]');
      if (grpRm) {
        e.preventDefault();
        const id = Number(grpRm.getAttribute('data-ia-msg-group-remove') || 0);
        if (id) removeGroupRecipient(shell, id);
        return;
      }

      const grpAll = e.target.closest('[data-ia-msg-group-selectall]');
      if (grpAll) {
        e.preventDefault();
        const st = S(shell);
        const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
        const sug = sheet ? el(sheet, '[data-ia-msg-group-suggest]') : null;
        const browse = sheet ? el(sheet, '[data-ia-msg-group-browse]') : null;

        // Select all from visible browse + current search results (prefer DOM labels).
        const picked = [];
        try {
          const nodes = [];
          if (browse) nodes.push(...qa(browse, '[data-ia-msg-group-pick]'));
          if (sug) nodes.push(...qa(sug, '[data-ia-msg-group-pick]'));
          nodes.forEach(btn => {
            const id = Number(btn.getAttribute('data-ia-msg-group-pick')||0);
            if (!id) return;
            picked.push({
              id,
              username: String(btn.getAttribute('data-username')||''),
              display: String(btn.getAttribute('data-display')||'')
            });
          });
        } catch(e) {}

        // Fallback to stored results if DOM isn't available.
        if (!picked.length) {
          const arr = [];
          if (Array.isArray(st.groupBrowseResults)) arr.push(...st.groupBrowseResults);
          if (Array.isArray(st.groupLastResults)) arr.push(...st.groupLastResults);
          arr.forEach(o => { if (o && Number(o.id||0) > 0) picked.push(o); });
        }

        if (!picked.length) return;
        picked.forEach(o => {
          if (!st.groupSelected.some(u => Number(u.id||0) === Number(o.id||0))) {
            addGroupRecipient(shell, o);
          }
        });

        // Ensure all visible checkboxes are checked
        try {
          if (browse) qa(browse, 'input[type="checkbox"]').forEach(cb => { cb.checked = true; });
          if (sug) qa(sug, 'input[type="checkbox"]').forEach(cb => { cb.checked = true; });
        } catch(e) {}
        return;
      }

      const grpClear = e.target.closest('[data-ia-msg-group-clear]');
      if (grpClear) {
        e.preventDefault();
        const st = S(shell);
        st.groupSelected = [];
        renderGroupSelected(shell);
        const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
        const box = sheet ? el(sheet, '[data-ia-msg-group-suggest]') : null;
        if (box) {
          // uncheck all in view
          qa(box, 'input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        }
        return;
      }

      const grpAvatarClear = e.target.closest('[data-ia-msg-group-avatar-clear]');
      if (grpAvatarClear) {
        e.preventDefault();
        const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
        const hid = sheet ? el(sheet, '[data-ia-msg-group-avatar-url]') : null;
        const prev = sheet ? el(sheet, '[data-ia-msg-group-avatar-preview]') : null;
        const st = S(shell);
        st.groupAvatarUrl = '';
        if (hid) hid.value = '';
        if (prev) prev.innerHTML = '';
        return;
      }

      const grpStart = e.target.closest('[data-ia-msg-group-start]');
      if (grpStart) {
        e.preventDefault();
        (async () => {
          const st = S(shell);
          const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
          if (!sheet) return;

          const titleEl = el(sheet, '[data-ia-msg-group-title]');
          const bodyEl  = el(sheet, '[data-ia-msg-group-body]');
          const hidMem  = el(sheet, '[data-ia-msg-group-members]');
          const hidAv   = el(sheet, '[data-ia-msg-group-avatar-url]');

          const title = titleEl ? String(titleEl.value||'').trim() : '';
          const body  = bodyEl ? String(bodyEl.value||'').trim() : '';
          const members = hidMem ? String(hidMem.value||'').trim() : '';
          const avatar_url = hidAv ? String(hidAv.value||'').trim() : '';

          if (!members) return;
          grpStart.disabled = true;
          try {
            const res = await post('ia_message_new_group', {
              nonce: IA_MESSAGE.nonceBoot,
              title,
              avatar_url,
              members,
              body
            });
            if (res && res.success) {
              closeSheet(shell);
              await loadThreads(shell);
              const tid = res.data && res.data.thread_id ? Number(res.data.thread_id) : 0;
              if (tid) await loadThread(shell, tid);
            } else {
              grpStart.disabled = false;
            }
          } catch(e) {
            grpStart.disabled = false;
          }
        })();
        return;
      }
    });

    const form = el(shell, "[data-ia-msg-send-form]");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        sendMessage(shell);
      });
    }


    // Reply UI: click reply icon beside a message
    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (log) {
      const updBottom = () => {
        try {
          const st = S(shell);
          const gap = log.scrollHeight - (log.scrollTop + log.clientHeight);
          st.atBottom = (gap < 24);
        } catch(e) {}
      };
      log.addEventListener('scroll', updBottom, { passive:true });
      setTimeout(updBottom, 0);

      log.addEventListener("click", (e) => {
        const lm = e.target.closest('[data-ia-msg-loadmore]');
        if (lm) {
          e.preventDefault();
          loadMore(shell);
          return;
        }

        const rb = e.target.closest("[data-ia-msg-reply-btn]");
        if (rb) {
          e.preventDefault();
          const mid = Number(rb.getAttribute("data-ia-msg-reply-btn") || 0);
          if (mid) setReply(shell, mid);
          return;
        }

        const fb = e.target.closest("[data-ia-msg-forward-btn]");
        if (fb) {
          e.preventDefault();
          const mid = Number(fb.getAttribute("data-ia-msg-forward-btn") || 0);
          if (mid) openForward(shell, mid);
          return;
        }



        const jump = e.target.closest("[data-ia-msg-jump]");
        if (jump) {
          e.preventDefault();
          const to = Number(jump.getAttribute("data-ia-msg-jump") || 0);
          if (to) jumpToMessage(shell, to);
          return;
        }
      });
    }

    const replyClear = el(shell, "[data-ia-msg-reply-clear]");
    if (replyClear) {
      replyClear.addEventListener("click", (e) => {
        e.preventDefault();
        clearReply(shell);
      });
    }

    const replyQuote = el(shell, "[data-ia-msg-reply-quote]");
    if (replyQuote) {
      replyQuote.addEventListener("click", (e) => {
        e.preventDefault();
        const st = S(shell);
        if (st.replyToId) jumpToMessage(shell, st.replyToId);
      });
    }



    const upBtn = el(shell, "[data-ia-msg-upload-btn]");
    const upInp = el(shell, "[data-ia-msg-upload-input]");
    if (upBtn && upInp) {
      upBtn.addEventListener("click", (e) => {
        e.preventDefault();
        try { upInp.click(); } catch(_) {}
      });

      upInp.addEventListener("change", async () => {
        const files = upInp.files ? Array.from(upInp.files) : [];
        upInp.value = "";
        if (!files.length) return;

        const ta = el(shell, "[data-ia-msg-send-input]");
        const prog = el(shell, "[data-ia-msg-upload-progress]");
        const progLabel = el(shell, "[data-ia-msg-upload-label]");
        const progPct = el(shell, "[data-ia-msg-upload-pct]");
        const progFill = el(shell, "[data-ia-msg-upload-fill]");

        const showProg = (name) => {
          if (!prog) return;
          prog.hidden = false;
          if (progLabel) progLabel.textContent = "Uploading " + (name || "…");
          if (progPct) progPct.textContent = "0%";
          if (progFill) progFill.style.width = "0%";
        };
        const setProg = (pct) => {
          if (pct == null) return;
          if (progPct) progPct.textContent = String(pct) + "%";
          if (progFill) progFill.style.width = String(pct) + "%";
        };
        const hideProgSoon = () => {
          if (!prog) return;
          try { setTimeout(() => { prog.hidden = true; }, 800); } catch(_) {}
        };

        for (let i = 0; i < files.length; i++) {
          const f = files[i];
          showProg(f && f.name ? f.name : "");

          try {
            const res = await postFileProgress("ia_message_upload", f, {}, (pct) => setProg(pct));
            if (res && res.success && res.data && res.data.url) {
              const url = String(res.data.url);
              if (ta) {
                const cur = String(ta.value || "");
                ta.value = (cur ? (cur.trimEnd() + "\n") : "") + url;
              }
              setProg(100);
            } else {
              alert("Upload failed.");
            }
          } catch (e) {
            alert("Upload failed.");
          }
        }

        hideProgSoon();
        if (ta) { try { ta.focus(); } catch(_){} }
      });
    }


    // Defensive: on some Atrium/mobile builds, global scripts can hide buttons/
    // header actions in narrow portrait after initial paint. The symptom is
    // "visible for a split second, then gone". We lock the visibility of the
    // Messages sidebar actions (New + Settings) by observing attribute changes
    // and restoring a safe display/visibility state.
    lockSidebarActions(shell);

    const qNew = el(shell, "[data-ia-msg-new-q]");
    const sugNew = el(shell, "[data-ia-msg-new-suggest]");
    const hid = el(shell, "[data-ia-msg-new-to-phpbb]");
    const start = el(shell, "[data-ia-msg-new-start]");

    if (qNew && sugNew && hid && start) {
      qNew.addEventListener("input", () => {
        const st = S(shell);
        if (st.userTimer) clearTimeout(st.userTimer);

        const v = String(qNew.value || "").trim();
        hid.value = "";
        start.disabled = true;

        if (v.length < 1) { sugNew.classList.remove("open"); sugNew.innerHTML = ""; return; }

        st.userTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderSuggest(sugNew, results, (picked) => {
            qNew.value = picked.display || picked.username || "";
            hid.value = String(picked.id || "");
            start.disabled = !(Number(hid.value) || 0);
            sugNew.classList.remove("open");
            sugNew.innerHTML = "";
          });
        }, 220);
      });
    }

    // Group sheet: user search (multi-select)
    const gq = el(shell, "[data-ia-msg-group-q]");
    const gsug = el(shell, "[data-ia-msg-group-suggest]");

    if (gq && gsug) {
      gq.addEventListener('input', () => {
        const st = S(shell);
        if (st.groupTimer) clearTimeout(st.groupTimer);

        const v = String(gq.value || '').trim();
        if (v.length < 1) { gsug.classList.remove('open'); gsug.innerHTML = ''; st.groupLastResults = []; return; }

        st.groupTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderGroupSuggest(shell, results);
        }, 220);
      });
    }

    // Members sheet: invite picker search (mods only)
    const iq = el(shell, "[data-ia-msg-invite-q]");
    const isug = el(shell, "[data-ia-msg-invite-suggest]");
    if (iq && isug) {
      iq.addEventListener('input', () => {
        const st = S(shell);
        if (st.inviteTimer) clearTimeout(st.inviteTimer);
        const v = String(iq.value || '').trim();
        if (v.length < 1) { isug.classList.remove('open'); isug.innerHTML = ''; st.inviteLastResults = []; return; }
        st.inviteTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderInviteSuggest(shell, results);
        }, 220);
      });
    }

    // Members sheet: invite action buttons + toggles
    const invSend = el(shell, '[data-ia-msg-invite-send]');
    if (invSend) invSend.addEventListener('click', (e)=>{ e.preventDefault(); sendInvites(shell); });

    const invAll = el(shell, '[data-ia-msg-invite-selectall]');
    if (invAll) invAll.addEventListener('click', (e)=>{ e.preventDefault(); inviteSelectAll(shell); });

    const invClear = el(shell, '[data-ia-msg-invite-clear]');
    if (invClear) invClear.addEventListener('click', (e)=>{ e.preventDefault(); inviteClear(shell); });


    // Group sheet: avatar upload
    const gAvInp = el(shell, '[data-ia-msg-group-avatar-input]');
    if (gAvInp) {
      gAvInp.addEventListener('change', async () => {
        const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
        const hid = sheet ? el(sheet, '[data-ia-msg-group-avatar-url]') : null;
        const prev = sheet ? el(sheet, '[data-ia-msg-group-avatar-preview]') : null;

        const files = gAvInp.files ? Array.from(gAvInp.files) : [];
        gAvInp.value = '';
        if (!files.length) return;
        const f = files[0];

        try {
          const res = await postFileProgress('ia_message_upload', f, {}, null);
          if (res && res.success && res.data && res.data.url) {
            const url = String(res.data.url);
            const st = S(shell);
            st.groupAvatarUrl = url;
            if (hid) hid.value = url;
            if (prev) prev.innerHTML = `<img alt="" src="${esc(url)}">`;
          }
        } catch(e) {}
      });
    }

    
    // Forward sheet: user search (multi-select)
    const qFwd = el(shell, "[data-ia-msg-fwd-q]");
    const sugFwd = el(shell, "[data-ia-msg-fwd-suggest]");

    if (qFwd && sugFwd) {
      qFwd.addEventListener("input", () => {
        const st = S(shell);
        if (st.fwdTimer) clearTimeout(st.fwdTimer);

        const v = String(qFwd.value || "").trim();
        if (v.length < 1) { sugFwd.classList.remove("open"); sugFwd.innerHTML = ""; return; }

        st.fwdTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderSuggest(sugFwd, results, (picked) => {
            addFwdRecipient(shell, picked);
          });
        }, 220);
      });
    }


    // Forward sheet: send + remove recipient chips
    const fwdSheet = el(shell, '[data-ia-msg-sheet="forward"]');
    if (fwdSheet) {
      fwdSheet.addEventListener('click', (e) => {
        const rm = e.target.closest('[data-ia-msg-fwd-remove]');
        if (rm) {
          e.preventDefault();
          const st = S(shell);
          const id = Number(rm.getAttribute('data-ia-msg-fwd-remove') || 0);
          if (id && Array.isArray(st.fwdSelected)) {
            st.fwdSelected = st.fwdSelected.filter(u => Number(u.id||0) !== id);
            renderFwdSelected(shell);
          }
          return;
        }

        const send = e.target.closest('[data-ia-msg-fwd-send]');
        if (send) {
          e.preventDefault();
          sendForward(shell);
          return;
        }
      });
    }

    // Click outside closes forward suggestions
    document.addEventListener('click', (e) => {
      const sheet = el(shell, '[data-ia-msg-sheet="forward"]');
      if (!sheet || !sheet.classList.contains('open')) return;
      const q = el(sheet, '[data-ia-msg-fwd-q]');
      const box = el(sheet, '[data-ia-msg-fwd-suggest]');
      if (!box) return;
      if (e.target === q || box.contains(e.target)) return;
      box.classList.remove('open');
      box.innerHTML = '';
    });

// Click outside closes new chat suggestions
    document.addEventListener('click', (e) => {
      const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
      if (!sheet || !sheet.classList.contains('open')) return;
      const q = el(sheet, '[data-ia-msg-new-q]');
      const box = el(sheet, '[data-ia-msg-new-suggest]');
      if (!box) return;
      if (e.target === q || box.contains(e.target)) return;
      box.classList.remove('open');
      box.innerHTML = '';
    }, { capture: true });

    // Click outside closes group suggestions
    document.addEventListener('click', (e) => {
      const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
      if (!sheet || !sheet.classList.contains('open')) return;
      const q = el(sheet, '[data-ia-msg-group-q]');
      const box = el(sheet, '[data-ia-msg-group-suggest]');
      if (!box) return;
      if (e.target === q || box.contains(e.target)) return;
      box.classList.remove('open');
      box.innerHTML = '';
    }, { capture: true });

const chatQ = el(shell, "[data-ia-msg-chat-q]");
    if (chatQ) {
      chatQ.addEventListener("input", () => {
        applyThreadFilter(shell, chatQ.value || "");
      });
    }
  }

  // ---------------------------
  // Notifications (badge + poll)
  // ---------------------------
  function findChatNavTargets(){
    const out = [];
    // Common patterns: href contains tab=messages or data-tab="messages"
    qa(document, 'a[href*="tab=messages"], button[data-tab="messages"], a[data-tab="messages"]').forEach(el => out.push(el));
    // Fallback: bottom nav item whose text is "Chat"
    qa(document, 'a,button').forEach(el => {
      if (out.includes(el)) return;
      const t = (el.textContent || '').trim().toLowerCase();
      if (t === 'chat') out.push(el);
    });
    // De-dupe
    return Array.from(new Set(out));
  }

  function iaMsgSetBottomNavHeightVar(){
    let h = 0;
    try{
      const vh = window.innerHeight || document.documentElement.clientHeight || 0;
      const targets = findChatNavTargets();
      for (const t of targets){
        const container = (t && t.closest) ? (t.closest('nav') || t.closest('[class*="nav"]') || t.parentElement) : null;
        if (!container || !container.getBoundingClientRect) continue;
        const r = container.getBoundingClientRect();
        // Only treat as bottom nav if it's actually docked at viewport bottom and is a sane height.
        if (r.height >= 30 && r.height <= 160 && Math.abs(vh - r.bottom) <= 6){
          h = Math.round(r.height);
          break;
        }
      }
    }catch(e){}
    try{
      document.documentElement.style.setProperty('--ia-msg-bottom-nav-h', (h > 0 ? h : 0) + 'px');
    }catch(e){}
  }


  function ensureBadge(target){
    if (!target) return null;
    // Prefer positioning relative to the icon itself (not the whole nav item)
    // so the badge overlaps the icon corner on both mobile and desktop.
    let wrap = target.querySelector('.ia-msg-badge-wrap');
    let badge = target.querySelector('.ia-msg-badge');
    if (wrap && badge) return badge;

    // If the target is a nav link/button that contains an icon, wrap the icon.
    const icon = target.querySelector('svg, i, .ia-icon, .dashicons, .fa, .lucide');
    const iconNode = icon || target;

    if (iconNode && iconNode !== wrap){
      // Create wrapper once
      wrap = document.createElement('span');
      wrap.className = 'ia-msg-badge-wrap';

      // Insert wrapper in place of the iconNode
      const parent = iconNode.parentNode;
      if (parent) { parent.insertBefore(wrap, iconNode); wrap.appendChild(iconNode); }
    }

    // Attach badge to wrapper (or target if wrapping wasn't possible)
    const host = wrap || target;
    badge = host.querySelector('.ia-msg-badge');
    if (badge) return badge;
    badge = document.createElement('span');
    badge.className = 'ia-msg-badge';
    badge.textContent = '';
    badge.style.display = 'none';
    host.appendChild(badge);
    return badge;
  }

  async function fetchUnreadCount(){
    try {
      const res = await post("ia_message_unread_count", { nonce: IA_MESSAGE.nonceBoot });
      if (res && res.success && res.data) return Number(res.data.count || 0) || 0;
    } catch(e){}
    return 0;
  }

  async function updateBadges(){
    const count = await fetchUnreadCount();
    const targets = findChatNavTargets();
    targets.forEach(t => {
      const b = ensureBadge(t);
      if (!b) return;
      if (count > 0) {
        b.textContent = String(count);
        b.style.display = '';
      } else {
        b.textContent = '';
        b.style.display = 'none';
      }
    });
  }

  function startBadgeLoop(){
    updateBadges();
    window.setInterval(updateBadges, 15000);
  }

  function activate(){
    const shells = findShells();
    if (!shells.length) return;

    // Start global badge updater (unread count)
    startBadgeLoop();

    shells.forEach(bindOnce);
    setFullscreen(true);
    shells.forEach(loadThreads);

    // Deep-link support: ?ia_msg_to=<phpbb_id>
// Used by Connect "Message" button to open a DM thread.
// IMPORTANT: Atrium tab switching often happens without a full page reload,
// so we support both URL params and in-page navigation events.
const openDmByPhpbbId = async (toPhpbb) => {
  const to = parseInt(String(toPhpbb || 0), 10) || 0;
  if (!to) return;

  // If shells haven't bound yet, store and try again later.
  if (!shells || !shells.length) {
    window.__IA_MESSAGE_PENDING_DM_TO = to;
    return;
  }

  // Use first shell (Messages runs as a single surface in Atrium)
  const shell = shells[0];

  try {
    const res = await post("ia_message_new_dm", { nonce: IA_MESSAGE.nonceBoot, to_phpbb: to, body: "" });
    if (res && res.success) {
      const tid = res.data && res.data.thread_id ? Number(res.data.thread_id) : 0;
      await loadThreads(shell);
      if (tid) await loadThread(shell, tid);
    }
  } catch (e) { /* no-op */ }
};

const maybeRunDeepLink = () => {
  if (window.__IA_MESSAGE_DEEPLINK_DONE) return;

  // 0) localStorage handoff (SPA-safe)
  try {
    const ls = window.localStorage;
    const raw = ls ? (ls.getItem('ia_msg_to') || '') : '';
    const toLs = parseInt(String(raw || '0'), 10) || 0;
    if (toLs) {
      if (ls) ls.removeItem('ia_msg_to');
      window.__IA_MESSAGE_DEEPLINK_DONE = true;
      openDmByPhpbbId(toLs);
      return;
    }
  } catch (e) {}

  // 1) URL param
  try {
    const url = new URL(window.location.href);
    const to = parseInt(url.searchParams.get("ia_msg_to") || "0", 10) || 0;
    if (to) {
      window.__IA_MESSAGE_DEEPLINK_DONE = true;
      openDmByPhpbbId(to);
      return;
    }
  } catch (e) {}

  // 2) Pending in-page request (set by events)
  const pending = parseInt(String(window.__IA_MESSAGE_PENDING_DM_TO || 0), 10) || 0;
  if (pending) {
    window.__IA_MESSAGE_DEEPLINK_DONE = true;
    window.__IA_MESSAGE_PENDING_DM_TO = 0;
    openDmByPhpbbId(pending);
  }
};

// Listen for Atrium in-page navigation requests
try {
  window.addEventListener('ia_atrium:navigate', (ev) => {
    try {
      const d = ev && ev.detail ? ev.detail : null;
      if (!d || d.tab !== 'messages') return;
      const to = parseInt(String(d.ia_msg_to || d.to || 0), 10) || 0;
      if (!to) return;
      window.__IA_MESSAGE_PENDING_DM_TO = to;
      window.__IA_MESSAGE_DEEPLINK_DONE = false; // allow re-run on each navigation
      // Try immediately (if shells already exist) and also after tab activation
      maybeRunDeepLink();
      setTimeout(maybeRunDeepLink, 60);
      setTimeout(maybeRunDeepLink, 200);
    } catch (e) {}
  }, { passive: true });
} catch (e) {}

// Run once on init
maybeRunDeepLink();

// Also re-check shortly after init, in case Atrium mounts the shell slightly later.
setTimeout(maybeRunDeepLink, 60);
setTimeout(maybeRunDeepLink, 200);

  }

  function setFullscreen(on){
    try {
      const mobile = window.matchMedia && window.matchMedia('(max-width: 820px)').matches;
      const enable = !!on && !!mobile;
      document.body.classList.toggle('ia-msg-fullscreen', enable);
    } catch(e) {}
  }

  
function closeMessages(){
  // Exit fullscreen first (so Atrium tabs/nav are visible again).
  setFullscreen(false);

  const stripDeepLinkParams = (url) => {
    try {
      const u = new URL(url);
      // Force connect and remove known deep-link params that can hijack navigation.
      u.searchParams.set('tab','connect');
      ['ia_msg_to','ia_profile','ia_profile_name','iad_topic','iad_post','iad_page','iad_reply','iad_comment'].forEach(k => u.searchParams.delete(k));
      return u.toString();
    } catch(e) { return null; }
  };

  const tryAtrium = () => {
    // If Atrium exposes a JS router, prefer it.
    try {
      if (window.IA_ATRIUM && typeof window.IA_ATRIUM.setTab === 'function') {
        window.IA_ATRIUM.setTab('connect');
        return true;
      }
    } catch(e){}
    try {
      if (window.IA_ATRIUM && typeof window.IA_ATRIUM.openTab === 'function') {
        window.IA_ATRIUM.openTab('connect');
        return true;
      }
    } catch(e){}
    // Some shells listen for this custom event.
    try {
      const ev = new CustomEvent('ia_atrium:requestTab', { detail: { tab: 'connect', source: 'ia-message' } });
      window.dispatchEvent(ev);
      document.dispatchEvent(ev);
      return true;
    } catch(e){}
    return false;
  };

  const clickConnect = () => {
    const sel = [
      'a[href*="tab=connect"]',
      'button[data-tab="connect"]',
      'a[data-tab="connect"]',
      'button[data-ia-tab="connect"]',
      'a[data-ia-tab="connect"]',
      '[data-tab-key="connect"]',
      '[data-ia-tab-key="connect"]'
    ].join(',');
    const btn = document.querySelector(sel);
    if (btn) { try { btn.click(); return true; } catch(e){} }
    return false;
  };

  // Try in order: Atrium router → click Connect tab → URL fallback.
  if (tryAtrium()) return;

  // Tabs may re-appear a tick after fullscreen class is removed, so retry a few times.
  const attempts = [0, 40, 140];
  for (const ms of attempts) {
    if (ms === 0) {
      if (clickConnect()) return;
    } else {
      setTimeout(() => { try { clickConnect(); } catch(_){} }, ms);
    }
  }

  // Deterministic fallback (prevents "Druids" deep-link return).
  const fixed = stripDeepLinkParams(window.location.href);
  if (fixed) { try { window.location.href = fixed; return; } catch(e){} }

  // Final fallback.
  try { window.location.href = (window.location.origin || "") + "/?tab=connect"; } catch(e) {}
}


  // Atrium event (and direct URL load)
  function bindAtrium(){
    const handler = (ev) => {
      const tab = ev && ev.detail && ev.detail.tab;
      if (tab === IA_MESSAGE.panelKey) {
        activate();
      } else {
        setFullscreen(false);
      }
    };
    window.addEventListener("ia_atrium:tabChanged", handler);
    document.addEventListener("ia_atrium:tabChanged", handler);

    try {
      const urlTab = (new URL(window.location.href)).searchParams.get("tab");
      if (urlTab === IA_MESSAGE.panelKey) activate();
      else setFullscreen(false);
    } catch(e){}

    // Keep fullscreen state in sync with viewport.
    const onR = () => {
      const active = (() => {
        try { return (new URL(window.location.href)).searchParams.get('tab') === IA_MESSAGE.panelKey; } catch(e){ return false; }
      })();
      setFullscreen(active);
    };
    window.addEventListener('resize', onR, { passive:true });
    window.addEventListener('orientationchange', onR, { passive:true });

    // Allow in-app deep-linking from IA Notify:
    // dispatch CustomEvent('ia_message:open_thread', { detail:{ thread_id } })
    window.addEventListener('ia_message:open_thread', function(ev){
      try{
        const tid = Number(ev && ev.detail && ev.detail.thread_id ? ev.detail.thread_id : 0) || 0;
        if (!tid) return;
        // Ensure Messages tab is active/visible.
        activate();
        // Load after the shell has rendered.
        setTimeout(function(){
          const sh = (findShells()[0]) || null;
          if (sh) loadThread(sh, tid);
        }, 60);
      }catch(_){ }
    });
  }

  ready(bindAtrium);

  // ---------------------------
  // Media Viewer (fullscreen)
  // ---------------------------
  const IA_MSG_MEDIA_VIEWER = (() => {
    let viewerEl = null;
    let items = [];
    let idx = 0;
    let scale = 1;

    function ensure(){
      if (viewerEl) return viewerEl;
      const el = document.createElement("div");
      el.className = "ia-msg-media-viewer";
      el.innerHTML = `
        <div class="ia-msg-media-viewer__backdrop" data-ia-msg-mv-close="1"></div>
        <div class="ia-msg-media-viewer__frame" role="dialog" aria-modal="true" aria-label="Media viewer">
          <div class="ia-msg-media-viewer__topbar">
            <button type="button" class="ia-msg-media-viewer__btn" data-ia-msg-mv-prev="1" aria-label="Previous">‹</button>
            <div class="ia-msg-media-viewer__title" data-ia-msg-mv-title></div>
            <div class="ia-msg-media-viewer__count" data-ia-msg-mv-count></div>
            <a class="ia-msg-media-viewer__btn" data-ia-msg-mv-download href="#" download aria-label="Download">⬇</a>
            <button type="button" class="ia-msg-media-viewer__btn" data-ia-msg-mv-close="1" aria-label="Close">×</button>
            <button type="button" class="ia-msg-media-viewer__btn" data-ia-msg-mv-next="1" aria-label="Next">›</button>
          </div>
          <div class="ia-msg-media-viewer__body" data-ia-msg-mv-body></div>
          <div class="ia-msg-media-viewer__toolbar" data-ia-msg-mv-toolbar>
            <button type="button" class="ia-msg-media-viewer__btn" data-ia-msg-mv-zoomout="1" aria-label="Zoom out">−</button>
            <button type="button" class="ia-msg-media-viewer__btn" data-ia-msg-mv-zoomreset="1" aria-label="Reset zoom">⟲</button>
            <button type="button" class="ia-msg-media-viewer__btn" data-ia-msg-mv-zoomin="1" aria-label="Zoom in">+</button>
          </div>
        </div>
      `;
      document.body.appendChild(el);
      viewerEl = el;

      // controls
      el.addEventListener("click", (ev) => {
        const t = ev.target;
        if (!(t instanceof Element)) return;
        if (t.closest('[data-ia-msg-mv-close="1"]')) { close(); return; }
        if (t.closest('[data-ia-msg-mv-prev="1"]')) { prev(); return; }
        if (t.closest('[data-ia-msg-mv-next="1"]')) { next(); return; }
        if (t.closest('[data-ia-msg-mv-zoomin="1"]')) { zoomBy(0.15); return; }
        if (t.closest('[data-ia-msg-mv-zoomout="1"]')) { zoomBy(-0.15); return; }
        if (t.closest('[data-ia-msg-mv-zoomreset="1"]')) { zoomSet(1); return; }
      });

      // keyboard
      document.addEventListener("keydown", (ev) => {
        if (!isOpen()) return;
        if (ev.key === "Escape") { close(); }
        else if (ev.key === "ArrowLeft") { prev(); }
        else if (ev.key === "ArrowRight") { next(); }
        else if (ev.key === "+" || ev.key === "=") { zoomBy(0.15); }
        else if (ev.key === "-" || ev.key === "_") { zoomBy(-0.15); }
      });

      return el;
    }

    function isOpen(){ return !!viewerEl && viewerEl.classList.contains("is-open"); }

    function open(list, startIndex){
      ensure();
      items = Array.isArray(list) ? list : [];
      idx = Math.max(0, Math.min(items.length - 1, Number(startIndex)||0));
      viewerEl.classList.add("is-open");
      document.documentElement.classList.add("ia-msg-modal-open");
      render();
    }

    function close(){
      if (!viewerEl) return;
      viewerEl.classList.remove("is-open");
      document.documentElement.classList.remove("ia-msg-modal-open");
      const body = viewerEl.querySelector('[data-ia-msg-mv-body]');
      if (body) body.innerHTML = "";
      scale = 1;
      items = [];
      idx = 0;
    }

    function prev(){ if (!items.length) return; idx = (idx - 1 + items.length) % items.length; scale = 1; render(); }
    function next(){ if (!items.length) return; idx = (idx + 1) % items.length; scale = 1; render(); }

    function zoomSet(v){
      scale = Math.max(0.25, Math.min(5, Number(v)||1));
      applyZoom();
    }
    function zoomBy(delta){ zoomSet(scale + delta); }

    function applyZoom(){
      if (!viewerEl) return;
      const img = viewerEl.querySelector(".ia-msg-media-viewer__img");
      if (img) img.style.transform = `scale(${scale})`;
    }

    function render(){
      if (!viewerEl) return;
      const it = items[idx];
      if (!it) return;

      const title = viewerEl.querySelector('[data-ia-msg-mv-title]');
      const count = viewerEl.querySelector('[data-ia-msg-mv-count]');
      const body = viewerEl.querySelector('[data-ia-msg-mv-body]');
      const dl = viewerEl.querySelector('[data-ia-msg-mv-download]');
      const tb = viewerEl.querySelector('[data-ia-msg-mv-toolbar]');

      const url = String(it.url || "");
      const type = String(it.type || "file");
      const name = it.name ? String(it.name) : fileNameFromUrl(url);

      if (title) title.textContent = name;
      if (count) count.textContent = items.length > 1 ? `${idx+1}/${items.length}` : "";
      if (dl) dl.setAttribute("href", url);

      if (body) body.innerHTML = "";

      // toolbar only meaningful for images
      if (tb) tb.style.display = (type === "image") ? "" : "none";

      if (!body) return;

      if (type === "image") {
        const wrap = document.createElement("div");
        wrap.className = "ia-msg-media-viewer__media";
        wrap.innerHTML = `<img class="ia-msg-media-viewer__img" src="${esc(url)}" alt="${esc(name)}">`;
        body.appendChild(wrap);

        // wheel zoom desktop
        wrap.addEventListener("wheel", (ev) => {
          ev.preventDefault();
          const dir = ev.deltaY > 0 ? -0.1 : 0.1;
          zoomBy(dir);
        }, { passive:false });

        applyZoom();
      } else if (type === "video") {
        body.innerHTML = `<div class="ia-msg-media-viewer__media"><video src="${esc(url)}" controls playsinline></video></div>`;
      } else if (type === "audio") {
        body.innerHTML = `<div class="ia-msg-media-viewer__media"><audio src="${esc(url)}" controls></audio></div>`;
      } else {
        // fallback
        body.innerHTML = `<div class="ia-msg-media-viewer__media"><a class="ia-msg-pill" href="${esc(url)}" target="_blank" rel="noopener noreferrer">📄 ${esc(name)}</a></div>`;
      }
    }

    return { open, close, isOpen };
  })();

  // Delegate clicks on inline media to open viewer with nav across the whole thread
  document.addEventListener("click", (ev) => {
    const t = ev.target instanceof Element ? ev.target : null;
    if (!t) return;
    const btn = t.closest(".ia-msg-media[data-ia-msg-media-url]");
    if (!btn) return;

    ev.preventDefault();

    const url = btn.getAttribute("data-ia-msg-media-url") || "";
    const type = btn.getAttribute("data-ia-msg-media-type") || "file";

    // Build gallery list from current open chat log
    const log = document.querySelector('.ia-msg-shell[data-panel="'+ esc(IA_MESSAGE.panelKey) +'"] [data-ia-msg-chat-messages]') || document.querySelector("[data-ia-msg-chat-messages]");
    const nodes = log ? Array.from(log.querySelectorAll(".ia-msg-media[data-ia-msg-media-url]")) : [];
    const list = nodes.map(n => ({
      url: n.getAttribute("data-ia-msg-media-url") || "",
      type: n.getAttribute("data-ia-msg-media-type") || "file",
      name: fileNameFromUrl(n.getAttribute("data-ia-msg-media-url") || "")
    })).filter(it => it.url);

    let startIndex = 0;
    for (let i=0;i<list.length;i++){
      if (list[i].url === url) { startIndex = i; break; }
    }

    IA_MSG_MEDIA_VIEWER.open(list, startIndex);
  });


  // Follow/Block click handlers
  document.addEventListener('click', async (e)=>{
    const f = e.target && e.target.closest ? e.target.closest('[data-ia-msg-follow-user]') : null;
    const b = e.target && e.target.closest ? e.target.closest('[data-ia-msg-block-user]') : null;
    if (!f && !b) return;
    const relBox = e.target.closest('[data-ia-msg-rel]') || document.querySelector('[data-ia-msg-rel]');
    const target = relBox ? (parseInt(relBox.getAttribute('data-target-phpbb')||'0',10)||0) : 0;
    if (!target) return;
    e.preventDefault(); e.stopPropagation();
    try{
      if (f){
        const r = await post('ia_message_user_follow_toggle', { nonce: IA_MESSAGE.nonceBoot, target_phpbb: String(target) });
        if (r && !r.ok && (r.status === 403 || (r.data && (r.data.message==='Blocked' || r.data.message==='blocked')))){
          iaMsgRelModal({ title:'You are blocked', body:'You can\'t follow or interact with this user right now because a block is active.' });
          return;
        }
        if (r && r.ok && r.data){
          if (r.data.following) f.classList.add('is-on'); else f.classList.remove('is-on');
        }
        return;
      }
      if (b){
        const isOn = b.classList.contains('is-on');
        iaMsgRelModal({
          title: (isOn ? 'Unblock user?' : 'Block user?'),
          body: (isOn ? 'Are you sure you want to unblock this user?' : 'Are you sure you want to block this user? You won\'t be able to see or interact with each other until unblocked.'),
          actions: [
            {label:'Cancel'},
            {label:(isOn ? 'Unblock' : 'Block'), primary:true, onClick: async ()=>{
              const r = await post('ia_message_user_block_toggle', { nonce: IA_MESSAGE.nonceBoot, target_phpbb: String(target) });
              if (r && r.ok && r.data){
                if (r.data.blocked_by_me) b.classList.add('is-on'); else b.classList.remove('is-on');
                if (r.data.blocked_any && !r.data.blocked_by_me){
                  iaMsgRelModal({ title:'You are blocked', body:'You can\'t interact with this user right now. Messaging is disabled while a block is active.' });
                }
              }
            }}
          ]
        });
        return;
      }
    }catch(_){}
  }, true);


  window.addEventListener('resize', iaMsgSetBottomNavHeightVar, { passive:true });
  window.addEventListener('orientationchange', iaMsgSetBottomNavHeightVar, { passive:true });
  setTimeout(iaMsgSetBottomNavHeightVar, 0);

})();
