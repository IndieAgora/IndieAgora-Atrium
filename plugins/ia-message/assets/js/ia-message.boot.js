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

  function ensureChatBottom(shell){
    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (!log) return;
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
          img.addEventListener("load", () => scrollToBottom(log, false), { once: true });
          img.addEventListener("error", () => scrollToBottom(log, false), { once: true });
        }
      });
      const vids = Array.from(log.querySelectorAll("video"));
      vids.forEach(v => {
        v.addEventListener("loadedmetadata", () => scrollToBottom(log, false), { once: true });
      });
    } catch(e){}
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



  async function post(action, payload){
    const fd = new FormData();
    fd.append("action", action);
    for (const k in payload) fd.append(k, payload[k]);
    const res = await fetch(IA_MESSAGE.ajaxUrl, { method:"POST", body: fd, credentials:"same-origin" });
    return res.json();
  }

  async function postFile(action, file, extraPayload){
    const fd = new FormData();
    fd.append("action", action);
    fd.append("nonce", IA_MESSAGE.nonceBoot);
    fd.append("file", file);
    if (extraPayload) {
      for (const k in extraPayload) fd.append(k, extraPayload[k]);
    }
    const res = await fetch(IA_MESSAGE.ajaxUrl, { method:"POST", body: fd, credentials:"same-origin" });
    return res.json();
  }




// XHR upload with progress (used for attachment uploads)
function postFileProgress(action, file, extraPayload, onProgress){
  return new Promise((resolve, reject) => {
    try {
      const fd = new FormData();
      fd.append("action", action);
      fd.append("nonce", IA_MESSAGE.nonceBoot);
      fd.append("file", file);
      if (extraPayload) {
        for (const k in extraPayload) fd.append(k, extraPayload[k]);
      }

      const xhr = new XMLHttpRequest();
      xhr.open("POST", IA_MESSAGE.ajaxUrl, true);
      xhr.withCredentials = true;

      xhr.upload.addEventListener("progress", (ev) => {
        if (!onProgress) return;
        if (ev && ev.lengthComputable) {
          const pct = Math.max(0, Math.min(100, Math.round((ev.loaded / ev.total) * 100)));
          try { onProgress(pct, ev.loaded, ev.total); } catch(_) {}
        } else {
          try { onProgress(null, ev.loaded || 0, ev.total || 0); } catch(_) {}
        }
      });

      xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4) return;
        if (xhr.status >= 200 && xhr.status < 300) {
          try { resolve(JSON.parse(xhr.responseText || "{}")); }
          catch(e){ reject(e); }
        } else {
          reject(new Error("Upload failed (" + xhr.status + ")"));
        }
      };

      xhr.onerror = () => reject(new Error("Upload failed"));
      xhr.send(fd);
    } catch (e) { reject(e); }
  });
}


  // Find message shells (Atrium keeps panels in DOM even if not active)
  function findShells(){
    return qa(document, '.ia-msg-shell[data-panel="' + IA_MESSAGE.panelKey + '"]');
  }

  const memo = new WeakMap();

  function S(shell){
    if (!memo.has(shell)) memo.set(shell, {
      threads: [],
      activeId: 0,
      me: 0,
      sendBusy: false,
      userTimer: null,
      fwdTimer: null,
      fwdSelected: [],
      replyToId: 0,
      replyToMeta: null,
    });
    return memo.get(shell);
  }

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

  function renderMessages(shell, thread){
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

    const msgs = thread && Array.isArray(thread.messages) ? thread.messages : [];
    if (!msgs.length) {
      log.innerHTML = `<div class="ia-msg-empty">No messages in this conversation.</div>`;
      return;
    }

    log.innerHTML = msgs.map(m => {
      // ✅ Backend sends: author_phpbb_user_id + is_mine (see includes/render/messages.php)
      const author = Number(m.author_phpbb_user_id || 0);
      const mine = (m && m.is_mine === true) || (st.me > 0 && author === st.me);

      const side = mine ? "out" : "in";
      const cls = mine ? " mine" : "";

      const body = renderRichBody(m.body || "");
      const when = esc(m.created_at || "");

      return `
        <div class="ia-msg-row" data-ia-msg-side="${side}" data-ia-msg-mid="${Number(m.id||0)}">
          <button type="button" class="ia-msg-replybtn" data-ia-msg-reply-btn="${Number(m.id||0)}" aria-label="Reply">
            ${replyIconSvg()}
          </button>

          <button type="button" class="ia-msg-fwdbtn" data-ia-msg-forward-btn="${Number(m.id||0)}" aria-label="Forward">
            ${forwardIconSvg()}
          </button>

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

    ensureChatBottom(shell);
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
    } catch(e){
      if (list) list.innerHTML = `<div class="ia-msg-empty">Failed to load threads.</div>`;
    }
  }

  async function loadThread(shell, id){
    const st = S(shell);
    st.activeId = Number(id || 0);
    clearReply(shell);
    renderThreads(shell);

    const log = el(shell, "[data-ia-msg-chat-messages]");
    if (log) log.innerHTML = `<div class="ia-msg-empty">Loading…</div>`;

    try {
      const res = await post("ia_message_thread", { nonce: IA_MESSAGE.nonceBoot, thread_id: st.activeId });
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
      renderMessages(shell, thread);
      setMobile(shell, "chat");
      ensureChatBottom(shell);
    } catch(e){
      if (log) log.innerHTML = `<div class="ia-msg-empty">Failed to load messages.</div>`;
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
            ${safeUser ? `<div class="ia-msg-suggest-user">@${safeUser}</div>` : ``}
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

  function openSheet(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    sheet.setAttribute("aria-hidden", "false");
    sheet.classList.add("open");
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
        if (a === "prefs") openPrefs(shell);
        if (a === "back") setMobile(shell, "list");
        if (a === "close") closeMessages();
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
      log.addEventListener("click", (e) => {
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

})();
