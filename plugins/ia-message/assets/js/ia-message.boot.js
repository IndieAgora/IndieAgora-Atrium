(() => {
  function ready(fn){ if(document.readyState!=="loading") fn(); else document.addEventListener("DOMContentLoaded", fn); }
  function q(root, sel){ return root.querySelector(sel); }
  function qa(root, sel){ return Array.from(root.querySelectorAll(sel)); }

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

  // ===========================
  // PANEL MODE (no modal)
  // ===========================

  function modalEl(){ return null; } // no modal in panel mode
  function closeModal(){ /* no-op (messages is a panel now) */ }

  function findShells(){
    const atrium = document.querySelector("#ia-atrium-shell");
    if (!atrium) return [];
    const panel = atrium.querySelector('.ia-panel[data-panel="' + IA_MESSAGE.panelKey + '"]');
    if (!panel) return [];
    return qa(panel, ".ia-msg-shell");
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

      // Leaving messages: close any open sheets (keeps UX tidy)
      if (tab !== IA_MESSAGE.panelKey) closeSheet("newchat");
    };

    window.addEventListener("ia_atrium:tabChanged", handler);
    document.addEventListener("ia_atrium:tabChanged", handler);

    // First paint: if Messages is already active, activate now
    try {
      const atrium = document.querySelector("#ia-atrium-shell");
      const def = atrium ? (atrium.getAttribute("data-default-tab") || "connect") : "connect";
      const urlTab = (new URL(window.location.href)).searchParams.get("tab");
      const active = urlTab || def;
      if (active === IA_MESSAGE.panelKey) activateMessages();
    } catch(e){}
  }

  // ===========================
  // Existing UI logic (kept)
  // ===========================

  const state = {
    shells: new WeakMap(),
  };

  function shellState(shell){
    if (!state.shells.has(shell)) state.shells.set(shell, {
      threads: [],
      activeThreadId: 0,
      activeThread: null,
      isLoadingThreads: false,
      isLoadingThread: false,
      isSending: false,
      mobileView: "list", // list|chat
    });
    return state.shells.get(shell);
  }

  function setMobileView(shell, view){
    shell.setAttribute("data-ia-msg-mobile", view);
  }

  function setStatus(shell, kind, on){
    shell.setAttribute("data-ia-msg-" + kind, on ? "1" : "0");
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
      const title = escapeHtml(t.title || t.display || "Conversation");
      const last = escapeHtml(t.last_preview || "");
      const active = (String(t.id) === String(st.activeThreadId)) ? "active" : "";
      const unread = t.unread_count && Number(t.unread_count) > 0 ? `<span class="ia-msg-badge">${Number(t.unread_count)}</span>` : "";
      return `
        <button type="button" class="ia-msg-thread ${active}" data-ia-msg-thread="${t.id}">
          <div class="ia-msg-thread-top">
            <div class="ia-msg-thread-title">${title}</div>
            ${unread}
          </div>
          <div class="ia-msg-thread-sub">${last}</div>
        </button>
      `;
    }).join("");
  }

  function renderChat(shell){
    const st = shellState(shell);
    const headTitle = q(shell, "[data-ia-msg-chat-title]");
    const msgWrap = q(shell, "[data-ia-msg-chat-messages]");
    if (headTitle) headTitle.textContent = (st.activeThread && (st.activeThread.title || st.activeThread.display)) ? (st.activeThread.title || st.activeThread.display) : "Messages";

    if (!msgWrap) return;

    const msgs = (st.activeThread && st.activeThread.messages) ? st.activeThread.messages : [];
    if (!msgs.length) {
      msgWrap.innerHTML = `<div class="ia-msg-empty">No messages yet.</div>`;
      return;
    }

    msgWrap.innerHTML = msgs.map(m => {
      const body = escapeHtml(m.body || "");
      const mine = m.is_mine ? "mine" : "theirs";
      return `<div class="ia-msg-bubble ${mine}"><div class="ia-msg-body">${body}</div></div>`;
    }).join("");

    msgWrap.scrollTop = msgWrap.scrollHeight;
  }

  async function loadThreads(shell){
    const st = shellState(shell);
    if (st.isLoadingThreads) return;
    st.isLoadingThreads = true;
    setStatus(shell, "loading", true);

    try {
      const res = await post("ia_message_threads", { nonce: IA_MESSAGE.nonceBoot });
      if (!res || !res.ok) throw new Error(res && res.error ? res.error : "Failed to load threads");
      st.threads = res.threads || [];
      renderThreads(shell);

      // auto select first thread on desktop if none selected
      if (!st.activeThreadId && st.threads.length) {
        st.activeThreadId = st.threads[0].id;
        await loadThread(shell, st.activeThreadId);
      }
    } catch (e) {
      const list = q(shell, "[data-ia-msg-threads]");
      if (list) list.innerHTML = `<div class="ia-msg-empty">Error loading threads.</div>`;
    } finally {
      st.isLoadingThreads = false;
      setStatus(shell, "loading", false);
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
    setStatus(shell, "chatloading", true);

    try {
      const res = await post("ia_message_thread", { nonce: IA_MESSAGE.nonceBoot, thread_id: st.activeThreadId });
      if (!res || !res.ok) throw new Error(res && res.error ? res.error : "Failed to load thread");
      st.activeThread = res.thread || null;
      renderChat(shell);

      // on mobile, switch to chat view
      setMobileView(shell, "chat");
    } catch (e) {
      st.activeThread = null;
      renderChat(shell);
    } finally {
      st.isLoadingThread = false;
      setStatus(shell, "chatloading", false);
    }
  }

  async function sendMessage(shell, body){
    const st = shellState(shell);
    const tid = st.activeThreadId;
    if (!tid || !body) return;

    if (st.isSending) return;
    st.isSending = true;
    setStatus(shell, "sending", true);

    try {
      const res = await post("ia_message_send", { nonce: IA_MESSAGE.nonceBoot, thread_id: tid, body: body });
      if (!res || !res.ok) throw new Error(res && res.error ? res.error : "Failed to send");
      await loadThread(shell, tid);
      await loadThreads(shell);
    } catch (e) {
      // ignore for now
    } finally {
      st.isSending = false;
      setStatus(shell, "sending", false);
    }
  }

  function openSheet(name){
    const shells = findShells();
    shells.forEach(shell => {
      const sheet = q(shell, `[data-ia-msg-sheet="${name}"]`);
      if (!sheet) return;
      sheet.classList.add("open");
      sheet.setAttribute("aria-hidden", "false");
    });
  }

  function closeSheet(name){
    const shells = findShells();
    shells.forEach(shell => {
      const sheet = q(shell, `[data-ia-msg-sheet="${name}"]`);
      if (!sheet) return;
      sheet.classList.remove("open");
      sheet.setAttribute("aria-hidden", "true");
    });
  }

  function bindGlobalClose(){
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-ia-msg-sheet-close='1']")) closeSheet("newchat");
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        const shells = findShells();
        shells.forEach(shell => {
          const sheet = q(shell, `[data-ia-msg-sheet="newchat"]`);
          if (sheet && sheet.classList.contains("open")) closeSheet("newchat");
        });
      }
    });
  }

  function bindActions(shell){
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
        if (a === "new") openSheet("newchat");
        if (a === "back") setMobileView(shell, "list");
      }
    });

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

    const newForm = q(shell, "[data-ia-msg-new-form]");
    if (newForm) {
      newForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const to = q(shell, "[data-ia-msg-new-to]");
        const body = q(shell, "[data-ia-msg-new-body]");
        const toVal = to ? String(to.value || "").trim() : "";
        const bodyVal = body ? String(body.value || "").trim() : "";
        if (!toVal || !bodyVal) return;

        try {
          const res = await post("ia_message_new_dm", { nonce: IA_MESSAGE.nonceBoot, to: toVal, body: bodyVal });
          if (!res || !res.ok) throw new Error(res && res.error ? res.error : "Failed to create DM");
          closeSheet("newchat");
          await loadThreads(shell);
          if (res.thread_id) await loadThread(shell, res.thread_id);
        } catch (err) {
          // ignore
        }
      });
    }
  }

  function initShellOnce(shell){
    if (shell.getAttribute("data-ia-msg-ready") === "1") return;
    shell.setAttribute("data-ia-msg-ready", "1");

    // initial view state
    setMobileView(shell, "list");

    bindActions(shell);
  }

  // ===========================
  // Boot
  // ===========================

  ready(() => {
    bindGlobalClose();
    bindAtriumMessagesTab();
  });

})();
