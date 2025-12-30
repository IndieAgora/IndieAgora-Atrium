(() => {
  function ready(fn){ if(document.readyState!=="loading") fn(); else document.addEventListener("DOMContentLoaded", fn); }
  function q(root, sel){ try { return (root||document).querySelector(sel); } catch(e){ return null; } }
  function qa(root, sel){ try { return Array.from((root||document).querySelectorAll(sel)); } catch(e){ return []; } }

  function esc(s){
    return String(s ?? "").replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function post(action, payload){
    const fd = new FormData();
    fd.append("action", action);
    for (const k in payload) fd.append(k, payload[k]);
    const res = await fetch(IA_MESSAGE.ajaxUrl, { method:"POST", body: fd, credentials:"same-origin" });
    return res.json();
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
      const on = (id && id === st.activeId) ? " active" : "";
      return `
        <button type="button" class="ia-msg-thread${on}" data-ia-msg-thread="${id}">
          <div class="ia-msg-thread-name">${title}</div>
          <div class="ia-msg-thread-last">${prev}</div>
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
    if (titleEl) titleEl.textContent = title;

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

      const body = esc(m.body || "");
      const when = esc(m.created_at || "");

      return `
        <div class="ia-msg-bubble${cls}" data-ia-msg-side="${side}">
          <div class="ia-msg-body">${body}</div>
          <div class="ia-msg-when">${when}</div>
        </div>
      `;
    }).join("");

    try { log.scrollTop = log.scrollHeight; } catch(e){}
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
      st.threads = (res.data && res.data.threads) ? res.data.threads : [];

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
      renderMessages(shell, thread);
      setMobile(shell, "chat");
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
      const res = await post("ia_message_send", { nonce: IA_MESSAGE.nonceBoot, thread_id: tid, body });
      if (res && res.success) {
        if (ta) ta.value = "";
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
      box.innerHTML = `<div class="ia-msg-empty">No results</div>`;
      box.classList.add("open");
      return;
    }

    box.innerHTML = arr.map(r => {
      const id = Number(r.phpbb_user_id || 0);
      const label = esc(r.label || r.username || ("User #" + id));
      return `<button type="button" class="ia-msg-suggest-item" data-pick="${id}" data-label="${label}">${label}</button>`;
    }).join("");
    box.classList.add("open");

    box.onclick = (e) => {
      const b = e.target.closest("[data-pick]");
      if (!b) return;
      onPick({ id: Number(b.getAttribute("data-pick")||0), label: b.getAttribute("data-label")||"" });
    };
  }

  function openSheet(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    sheet.setAttribute("aria-hidden", "false");
    sheet.classList.add("open");
  }

  function closeSheet(shell){
    const sheet = el(shell, '[data-ia-msg-sheet="newchat"]');
    if (!sheet) return;
    sheet.setAttribute("aria-hidden", "true");
    sheet.classList.remove("open");
  }

  function bindOnce(shell){
    if (shell.getAttribute("data-ia-msg-ready") === "1") return;
    shell.setAttribute("data-ia-msg-ready", "1");

    shell.addEventListener("click", (e) => {
      const act = e.target.closest("[data-ia-msg-action]");
      if (act) {
        const a = act.getAttribute("data-ia-msg-action");
        if (a === "new") openSheet(shell);
        if (a === "back") setMobile(shell, "list");
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

        if (v.length < 2) { sugNew.classList.remove("open"); sugNew.innerHTML = ""; return; }

        st.userTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderSuggest(sugNew, results, (picked) => {
            qNew.value = picked.label || "";
            hid.value = String(picked.id || "");
            start.disabled = !(Number(hid.value) || 0);
            sugNew.classList.remove("open");
            sugNew.innerHTML = "";
          });
        }, 220);
      });
    }
  }

  function activate(){
    const shells = findShells();
    if (!shells.length) return;

    shells.forEach(bindOnce);
    shells.forEach(loadThreads);
  }

  // Atrium event (and direct URL load)
  function bindAtrium(){
    const handler = (ev) => {
      const tab = ev && ev.detail && ev.detail.tab;
      if (tab === IA_MESSAGE.panelKey) activate();
    };
    window.addEventListener("ia_atrium:tabChanged", handler);
    document.addEventListener("ia_atrium:tabChanged", handler);

    try {
      const urlTab = (new URL(window.location.href)).searchParams.get("tab");
      if (urlTab === IA_MESSAGE.panelKey) activate();
    } catch(e){}
  }

  ready(bindAtrium);
})();
