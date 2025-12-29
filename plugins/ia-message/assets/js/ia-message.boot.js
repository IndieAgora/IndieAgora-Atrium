(() => {
  function ready(fn){ if(document.readyState!=="loading") fn(); else document.addEventListener("DOMContentLoaded", fn); }
  function q(root, sel){ try { return (root||document).querySelector(sel); } catch(e){ return null; } }
  function qa(root, sel){ try { return Array.from((root||document).querySelectorAll(sel)); } catch(e){ return []; } }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function post(action, payload){
    const fd = new FormData();
    fd.append("action", action);
    for (const k in payload) fd.append(k, payload[k]);
    const res = await fetch(IA_MESSAGE.ajaxUrl, { method: "POST", body: fd });
    return res.json();
  }

  function findShells(){
    const atrium = document.querySelector("#ia-atrium-shell");
    if (!atrium) return [];
    const panel = atrium.querySelector('.ia-panel[data-panel="' + IA_MESSAGE.panelKey + '"]');
    if (!panel) return [];
    return qa(panel, ".ia-msg-shell");
  }

  const state = { shells: new WeakMap() };

  function shellState(shell){
    if (!state.shells.has(shell)) state.shells.set(shell, {
      threads: [],
      activeThreadId: 0,
      activeThread: null,
      isLoadingThreads: false,
      isLoadingThread: false,
      isSending: false,
      userSearchTimer: null,
      newSearchTimer: null,
    });
    return state.shells.get(shell);
  }

  function setMobileView(shell, view){
    // CSS uses data-mobile-view
    shell.setAttribute("data-mobile-view", view);
  }

  function renderThreads(shell){
    const st = shellState(shell);
    const list = q(shell, "[data-ia-msg-threads]");
    if (!list) return;

    if (!st.threads.length) {
      list.innerHTML = `<div class="ia-msg-empty">No conversations yet.</div>`;
      return;
    }

    list.innerHTML = st.threads.map(t => {
      const title = escapeHtml(t.title || "Conversation");
      const last = escapeHtml(t.last_preview || "");
      const active = (String(t.id) === String(st.activeThreadId)) ? "active" : "";
      return `
        <button type="button" class="ia-msg-thread ${active}" data-ia-msg-thread="${t.id}">
          <div class="ia-msg-thread-title">${title}</div>
          <div class="ia-msg-thread-meta">${last}</div>
        </button>
      `;
    }).join("");
  }

  function renderChat(shell){
    const st = shellState(shell);
    const headTitle = q(shell, "[data-ia-msg-chat-title]");
    const msgWrap = q(shell, "[data-ia-msg-chat-messages]");
    if (headTitle) headTitle.textContent = st.activeThread ? (st.activeThread.title || "Messages") : "Select a conversation";
    if (!msgWrap) return;

    const msgs = (st.activeThread && st.activeThread.messages) ? st.activeThread.messages : [];
    if (!st.activeThread) {
      msgWrap.innerHTML = `<div class="ia-msg-empty">Select a conversation.</div>`;
      return;
    }
    if (!msgs.length) {
      msgWrap.innerHTML = `<div class="ia-msg-empty">No messages yet.</div>`;
      return;
    }

    msgWrap.innerHTML = msgs.map(m => {
      const body = escapeHtml(m.body || "");
      const mine = m.is_mine ? "ia-msg-bubble-mine" : "";
      return `<div class="ia-msg-bubble ${mine}"><div class="ia-msg-body">${body}</div></div>`;
    }).join("");

    msgWrap.scrollTop = msgWrap.scrollHeight;
  }

  async function loadThreads(shell){
    const st = shellState(shell);
    if (st.isLoadingThreads) return;
    st.isLoadingThreads = true;

    const list = q(shell, "[data-ia-msg-threads]");
    if (list) list.innerHTML = `<div class="ia-msg-empty">Loading…</div>`;

    try {
      const res = await post("ia_message_threads", { nonce: IA_MESSAGE.nonceBoot });
      if (!res || !res.success) throw new Error((res && res.data && res.data.error) ? res.data.error : "Failed");
      st.threads = (res.data && res.data.threads) ? res.data.threads : [];
      renderThreads(shell);

      if (!st.activeThreadId && st.threads.length) {
        st.activeThreadId = Number(st.threads[0].id) || 0;
        if (st.activeThreadId) await loadThread(shell, st.activeThreadId);
      } else {
        renderChat(shell);
      }
    } catch (e) {
      if (list) list.innerHTML = `<div class="ia-msg-empty">Error loading threads.</div>`;
    } finally {
      st.isLoadingThreads = false;
    }
  }

  async function loadThread(shell, threadId){
    const st = shellState(shell);
    st.activeThreadId = Number(threadId) || 0;
    renderThreads(shell);

    if (!st.activeThreadId) {
      st.activeThread = null;
      renderChat(shell);
      return;
    }

    if (st.isLoadingThread) return;
    st.isLoadingThread = true;

    try {
      const res = await post("ia_message_thread", { nonce: IA_MESSAGE.nonceBoot, thread_id: st.activeThreadId });
      if (!res || !res.success) throw new Error((res && res.data && res.data.error) ? res.data.error : "Failed");
      st.activeThread = (res.data && res.data.thread) ? res.data.thread : null;
      renderChat(shell);
      setMobileView(shell, "chat");
    } catch (e) {
      st.activeThread = null;
      renderChat(shell);
    } finally {
      st.isLoadingThread = false;
    }
  }

  async function sendMessage(shell, body){
    const st = shellState(shell);
    const tid = st.activeThreadId;
    if (!tid || !body) return;
    if (st.isSending) return;
    st.isSending = true;

    try {
      const res = await post("ia_message_send", { nonce: IA_MESSAGE.nonceBoot, thread_id: tid, body });
      if (!res || !res.success) throw new Error((res && res.data && res.data.error) ? res.data.error : "Failed");
      await loadThread(shell, tid);
      await loadThreads(shell);
    } catch (e) {
      // noop for now
    } finally {
      st.isSending = false;
    }
  }

  function openSheet(shell, name){
    const sheet = q(shell, `[data-ia-msg-sheet="${name}"]`);
    if (!sheet) return;
    sheet.classList.add("open");
    sheet.setAttribute("aria-hidden", "false");
  }

  function closeSheet(shell, name){
    const sheet = q(shell, `[data-ia-msg-sheet="${name}"]`);
    if (!sheet) return;
    sheet.classList.remove("open");
    sheet.setAttribute("aria-hidden", "true");
  }

  function renderSuggest(box, results, onPick){
    if (!box) return;
    if (!results || !results.length) {
      box.classList.remove("open");
      box.innerHTML = "";
      return;
    }
    box.classList.add("open");
    box.innerHTML = results.map(r => {
      const label = escapeHtml(r.label || r.username || ("User #" + r.phpbb_user_id));
      const sub   = escapeHtml(r.email || "");
      const id    = Number(r.phpbb_user_id) || 0;
      return `
        <button type="button" class="ia-msg-suggest-item" data-pick="${id}">
          ${label}
          ${sub ? `<span class="ia-msg-suggest-sub">${sub}</span>` : ``}
        </button>
      `;
    }).join("");

    box.onclick = (e) => {
      const btn = e.target.closest("[data-pick]");
      if (!btn) return;
      const id = Number(btn.getAttribute("data-pick")) || 0;
      const picked = results.find(x => Number(x.phpbb_user_id) === id);
      if (picked) onPick(picked);
    };
  }

  async function userSearch(qstr){
    const res = await post("ia_message_user_search", { nonce: IA_MESSAGE.nonceBoot, q: qstr });
    if (!res || !res.success) return [];
    return (res.data && res.data.results) ? res.data.results : [];
  }

  function bindActions(shell){
    // Thread clicks / actions
    shell.addEventListener("click", async (e) => {
      const thr = e.target.closest("[data-ia-msg-thread]");
      if (thr) {
        const id = thr.getAttribute("data-ia-msg-thread");
        await loadThread(shell, id);
        return;
      }

      const act = e.target.closest("[data-ia-msg-action]");
      if (act) {
        const a = act.getAttribute("data-ia-msg-action");
        if (a === "new") {
          // reset new sheet fields
          const qNew = q(shell, "[data-ia-msg-new-q]");
          const body = q(shell, "[data-ia-msg-new-body]");
          const hid  = q(shell, "[data-ia-msg-new-to-phpbb]");
          const start= q(shell, "[data-ia-msg-new-start]");
          const sug  = q(shell, "[data-ia-msg-new-suggest]");
          if (qNew) qNew.value = "";
          if (body) body.value = "";
          if (hid) hid.value = "";
          if (start) start.disabled = true;
          if (sug) { sug.classList.remove("open"); sug.innerHTML = ""; }
          openSheet(shell, "newchat");
        }
        if (a === "back") setMobileView(shell, "list");
      }

      const close = e.target.closest("[data-ia-msg-sheet-close='1']");
      if (close) closeSheet(shell, "newchat");
    });

    // Send form
    const form = q(shell, "[data-ia-msg-send-form]");
    if (form) {
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const input = q(shell, "[data-ia-msg-send-input]");
        if (!input) return;
        const val = String(input.value || "").trim();
        if (!val) return;
        input.value = "";
        await sendMessage(shell, val);
      });
    }

    // Left search box can start new DM quickly (optional UX)
    const leftQ = q(shell, "[data-ia-msg-user-q]");
    const leftSug = q(shell, "[data-ia-msg-suggest]");
    if (leftQ && leftSug) {
      leftQ.addEventListener("input", () => {
        const st = shellState(shell);
        clearTimeout(st.userSearchTimer);
        const v = String(leftQ.value || "").trim();
        if (v.length < 2) { leftSug.classList.remove("open"); leftSug.innerHTML = ""; return; }
        st.userSearchTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderSuggest(leftSug, results, (picked) => {
            // open sheet with picked user prefilled
            const qNew = q(shell, "[data-ia-msg-new-q]");
            const hid  = q(shell, "[data-ia-msg-new-to-phpbb]");
            const start= q(shell, "[data-ia-msg-new-start]");
            const sug  = q(shell, "[data-ia-msg-new-suggest]");
            if (qNew) qNew.value = picked.label || picked.username || "";
            if (hid) hid.value = String(picked.phpbb_user_id || "");
            if (start) start.disabled = !(Number(hid.value) > 0);
            if (sug) { sug.classList.remove("open"); sug.innerHTML = ""; }
            openSheet(shell, "newchat");
          });
        }, 220);
      });
    }

    // New chat sheet search + start
    const qNew = q(shell, "[data-ia-msg-new-q]");
    const sugNew = q(shell, "[data-ia-msg-new-suggest]");
    const hid = q(shell, "[data-ia-msg-new-to-phpbb]");
    const start = q(shell, "[data-ia-msg-new-start]");
    const body = q(shell, "[data-ia-msg-new-body]");
    const selfBtn = q(shell, "[data-ia-msg-new-self='1']");

    if (qNew && sugNew && hid && start) {
      qNew.addEventListener("input", () => {
        const st = shellState(shell);
        clearTimeout(st.newSearchTimer);

        hid.value = "";
        start.disabled = true;

        const v = String(qNew.value || "").trim();
        if (v.length < 2) { sugNew.classList.remove("open"); sugNew.innerHTML = ""; return; }

        st.newSearchTimer = setTimeout(async () => {
          const results = await userSearch(v);
          renderSuggest(sugNew, results, (picked) => {
            qNew.value = picked.label || picked.username || "";
            hid.value = String(picked.phpbb_user_id || "");
            start.disabled = !(Number(hid.value) > 0);
            sugNew.classList.remove("open");
            sugNew.innerHTML = "";
          });
        }, 220);
      });

      start.addEventListener("click", async () => {
        const toPhpbb = Number(hid.value) || 0;
        const msg = body ? String(body.value || "").trim() : "";
        if (toPhpbb <= 0) return;

        try {
          const res = await post("ia_message_new_dm", { nonce: IA_MESSAGE.nonceBoot, to_phpbb: toPhpbb, body: msg });
          if (!res || !res.success) return;

          closeSheet(shell, "newchat");
          await loadThreads(shell);

          const tid = res.data && res.data.thread_id ? Number(res.data.thread_id) : 0;
          if (tid) await loadThread(shell, tid);
        } catch(e){}
      });
    }

    if (selfBtn) {
      selfBtn.addEventListener("click", async () => {
        try {
          const res = await post("ia_message_new_dm", { nonce: IA_MESSAGE.nonceBoot, to_phpbb: -1, body: "" });
          // We don’t actually know “me” phpbb id on the client; use quick server-side workaround:
          // Instead: just start a DM to self by selecting first thread once created via normal create route.
          // So: do nothing here (button stays decorative) unless you want self-DM explicitly later.
        } catch(e){}
      });
    }
  }

  function initShellOnce(shell){
    if (shell.getAttribute("data-ia-msg-ready") === "1") return;
    shell.setAttribute("data-ia-msg-ready", "1");
    setMobileView(shell, "list");
    bindActions(shell);
  }

  function activateMessages(){
    const shells = findShells();
    if (!shells.length) return;
    shells.forEach(initShellOnce);
    shells.forEach(loadThreads);
  }

  function bindAtriumMessagesTab(){
    const handler = (ev) => {
      const tab = ev && ev.detail && ev.detail.tab;
      if (tab === IA_MESSAGE.panelKey) activateMessages();
    };
    window.addEventListener("ia_atrium:tabChanged", handler);
    document.addEventListener("ia_atrium:tabChanged", handler);

    // First paint
    try {
      const atrium = document.querySelector("#ia-atrium-shell");
      const def = atrium ? (atrium.getAttribute("data-default-tab") || "connect") : "connect";
      const urlTab = (new URL(window.location.href)).searchParams.get("tab");
      const active = urlTab || def;
      if (active === IA_MESSAGE.panelKey) activateMessages();
    } catch(e){}
  }

  ready(() => {
    bindAtriumMessagesTab();
  });
})();
