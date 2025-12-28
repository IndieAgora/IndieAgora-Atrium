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

  function modalEl(){ return document.getElementById("ia-message-modal"); }

  function openModal(){
    const modal = modalEl();
    if (!modal) return;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    qa(modal, ".ia-msg-shell").forEach(initShellOnce);
    qa(modal, ".ia-msg-shell").forEach(loadThreads);
  }

  function closeModal(){
    const modal = modalEl();
    if (!modal) return;
    closeSheet("newchat");
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
  }

  function openSheet(name){
    const modal = modalEl();
    if (!modal) return;
    const sheet = q(modal, `[data-ia-msg-sheet="${name}"]`);
    if (!sheet) return;

    sheet.classList.add("open");
    sheet.setAttribute("aria-hidden", "false");
    sheet.dataset.selectedEmail = "";

    const go = q(sheet, "[data-ia-msg-newchat-go]");
    if (go) go.disabled = true;

    ensureSuggestBox(modal);

    const input = q(sheet, "[data-ia-msg-newchat-q]");
    if (input) setTimeout(() => input.focus(), 0);
  }

  function closeSheet(name){
    const modal = modalEl();
    if (!modal) return;
    const sheet = q(modal, `[data-ia-msg-sheet="${name}"]`);
    if (!sheet) return;

    sheet.classList.remove("open");
    sheet.setAttribute("aria-hidden", "true");
    sheet.dataset.selectedEmail = "";

    const input = q(sheet, "[data-ia-msg-newchat-q]");
    if (input) input.value = "";

    const box = q(modal, ".ia-msg-suggest");
    if (box){
      box.innerHTML = "";
      box.classList.remove("open");
    }
  }

  // --- Atrium intent wiring (surgical, no renames) ---
  function bindAtriumChatIntent(){
    const handler = () => openModal();

    // cover both emission styles (Atrium manual shows dispatch("ia_atrium:chat") )
    window.addEventListener("ia_atrium:chat", handler);
    document.addEventListener("ia_atrium:chat", handler);

    // hard fallback: if someone clicks an intent element directly
    document.addEventListener("click", (e) => {
      const el = e.target.closest('[data-ia-intent="chat"], [data-intent="chat"], [data-target="chat"]');
      if (el) handler();
    });
  }

  function bindGlobalClose(){
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-ia-msg-close='1']")) closeModal();
      if (e.target.closest("[data-ia-msg-sheet-close='1']")) closeSheet("newchat");
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        const modal = modalEl();
        if (!modal) return;
        const sheet = q(modal, `[data-ia-msg-sheet="newchat"]`);
        if (sheet && sheet.classList.contains("open")) closeSheet("newchat");
        else closeModal();
      }
    });
  }

  function setStatus(shell, msg){
    let bar = q(shell, ".ia-msg-status");
    if (!bar) {
      bar = document.createElement("div");
      bar.className = "ia-msg-status";
      shell.prepend(bar);
    }
    bar.textContent = msg || "";
    bar.style.display = msg ? "block" : "none";
  }

  function setMobileView(shell, view){
    shell.dataset.mobileView = view; // "list" | "chat"
  }

  function initShellOnce(shell){
    if (shell.dataset.iaMsgInit === "1") return;
    shell.dataset.iaMsgInit = "1";
    shell.dataset.threadId = "0";
    setStatus(shell, "Click New to start a chat (or select a conversation).");
    setMobileView(shell, "list");

    shell.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-ia-msg-action]");
      if (!btn) return;
      const a = btn.getAttribute("data-ia-msg-action");
      if (a === "send") sendMessage(shell);
      if (a === "new")  openSheet("newchat");
      if (a === "back") setMobileView(shell, "list");
    });

    const modal = modalEl();
    if (modal) {
      modal.addEventListener("click", async (e) => {
        if (e.target.closest("[data-ia-msg-newchat-self='1']")) {
          await createChat(shell, { self: 1 });
        }
        if (e.target.closest("[data-ia-msg-newchat-go='1']")) {
          const sheet = q(modal, `[data-ia-msg-sheet="newchat"]`);
          const email = (sheet?.dataset?.selectedEmail || "").trim();
          if (email) await createChat(shell, { email });
        }
      });

      const qInput = q(modal, "[data-ia-msg-newchat-q]");
      if (qInput) {
        qInput.addEventListener("input", () => suggestUsers(modal, shell, qInput.value));
        qInput.addEventListener("keydown", async (e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            // If user typed an exact email, allow enter-to-start:
            const typed = qInput.value.trim();
            if (typed.includes("@")) {
              await createChat(shell, { email: typed });
            }
          }
        });
      }
    }

    const ta = q(shell, '[data-ia-msg-slot="composer"]');
    if (ta) {
      ta.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          sendMessage(shell);
        }
      });
    }
  }

  function renderThreadItem(t){
    const el = document.createElement("button");
    el.type = "button";
    el.className = "ia-msg-thread";
    el.dataset.threadId = String(t.id);
    el.innerHTML = `
      <div class="ia-msg-thread-title">${escapeHtml(t.title || "Thread")}</div>
      <div class="ia-msg-thread-meta">${escapeHtml(t.thread_key || "")}</div>
    `;
    return el;
  }

  function renderMsgItem(m){
    const div = document.createElement("div");
    div.className = m.is_mine ? "ia-msg-bubble ia-msg-bubble-mine" : "ia-msg-bubble";
    div.innerHTML = `<div class="ia-msg-bubble-body">${escapeHtml(m.body || "")}</div>`;
    return div;
  }

  async function loadThreads(shell){
    const slot = q(shell, '[data-ia-msg-slot="threadlist"]');
    if (!slot) return;

    slot.innerHTML = `<div class="ia-msg-empty">Loading…</div>`;
    const j = await post("ia_message_threads", { nonce: IA_MESSAGE.nonceBoot, limit: 50, offset: 0 });

    if (!j || !j.success) {
      slot.innerHTML = `<div class="ia-msg-empty">Not logged in (or identity not resolved).</div>`;
      return;
    }

    slot.innerHTML = "";
    const threads = (j.data && j.data.threads) ? j.data.threads : [];

    if (!threads.length) {
      slot.innerHTML = `<div class="ia-msg-empty">No threads yet.</div>`;
      return;
    }

    threads.forEach(t => {
      const node = renderThreadItem(t);
      node.addEventListener("click", () => openThread(shell, t.id, t.title || "Conversation"));
      slot.appendChild(node);
    });
  }

  async function openThread(shell, threadId, title){
    shell.dataset.threadId = String(threadId);
    setStatus(shell, "");
    setMobileView(shell, "chat");

    const head = q(shell, '[data-ia-msg-slot="threadhead"] .ia-msg-threadname');
    if (head) head.textContent = title;

    const log = q(shell, '[data-ia-msg-slot="log"]');
    if (!log) return;

    log.innerHTML = `<div class="ia-msg-empty">Loading…</div>`;
    const j = await post("ia_message_thread", { nonce: IA_MESSAGE.nonceBoot, thread_id: threadId });

    if (!j || !j.success) {
      log.innerHTML = `<div class="ia-msg-empty">Failed to load thread.</div>`;
      return;
    }

    const messages = (j.data && j.data.messages) ? j.data.messages : [];
    log.innerHTML = "";

    if (!messages.length) {
      log.innerHTML = `<div class="ia-msg-empty">No messages yet.</div>`;
      return;
    }

    messages.forEach(m => log.appendChild(renderMsgItem(m)));
    log.scrollTop = log.scrollHeight;
  }

  async function sendMessage(shell){
    const threadId = parseInt(shell.dataset.threadId || "0", 10);
    if (!threadId) {
      setStatus(shell, "No conversation selected. Click New (or choose a thread) first.");
      return;
    }

    const ta = q(shell, '[data-ia-msg-slot="composer"]');
    if (!ta) return;

    const body = (ta.value || "").trim();
    if (!body) return;

    ta.value = "";
    const j = await post("ia_message_send", { nonce: IA_MESSAGE.nonceBoot, thread_id: threadId, body });

    if (!j || !j.success) {
      ta.value = body;
      setStatus(shell, "Send failed. (Permission or identity issue.)");
      return;
    }

    const title = q(shell, '[data-ia-msg-slot="threadhead"] .ia-msg-threadname')?.textContent || "Conversation";
    await openThread(shell, threadId, title);
  }

  async function createChat(shell, opts){
    setStatus(shell, "");
    let payload = { nonce: IA_MESSAGE.nonceBoot };

    if (opts.self === 1) payload.self = 1;
    else payload.email = (opts.email || "").trim();

    const j = await post("ia_message_new_dm", payload);
    if (!j || !j.success) {
      setStatus(shell, "Could not create chat. (User must resolve via identity map / phpbb_users.)");
      return;
    }

    closeSheet("newchat");

    await loadThreads(shell);
    const tid = j.data.thread_id;
    const title = (opts.self === 1) ? "Notes (Self)" : "Conversation";
    await openThread(shell, tid, title);
  }

  function ensureSuggestBox(modal){
    let box = modal.querySelector(".ia-msg-suggest");
    if (box) return box;
    box = document.createElement("div");
    box.className = "ia-msg-suggest";
    const card = modal.querySelector('[data-ia-msg-sheet="newchat"] .ia-msg-sheet-card') || modal.querySelector(".ia-msg-sheet-card");
    if (card) card.appendChild(box);
    return box;
  }

  // Frontend search: username-like input.
  // Backend resolves to email (email-only join doctrine).
  async function suggestUsers(modal, shell, qstr){
    const box = ensureSuggestBox(modal);
    if (!box) return;

    const s = String(qstr || "").trim();
    if (s.length < 2) {
      box.innerHTML = "";
      box.classList.remove("open");
      const sheet = q(modal, `[data-ia-msg-sheet="newchat"]`);
      if (sheet) sheet.dataset.selectedEmail = "";
      const go = q(modal, "[data-ia-msg-newchat-go='1'], [data-ia-msg-newchat-go]");
      if (go) go.disabled = true;
      return;
    }

    const j = await post("ia_message_user_search", { nonce: IA_MESSAGE.nonceBoot, q: s });
    if (!j || !j.success) return;

    const rows = (j.data && j.data.results) ? j.data.results : [];
    if (!rows.length) {
      box.innerHTML = "";
      box.classList.remove("open");
      return;
    }

    box.innerHTML = rows.map(r => `
      <button type="button" class="ia-msg-suggest-item" data-email="${escapeHtml(r.email)}">
        <span class="ia-msg-suggest-name">${escapeHtml(r.label || r.username || r.email)}</span>
        <span class="ia-msg-suggest-sub">${escapeHtml(r.email)}</span>
      </button>
    `).join("");

    box.classList.add("open");

    box.querySelectorAll(".ia-msg-suggest-item").forEach(btn => {
      btn.addEventListener("click", async () => {
        const email = (btn.getAttribute("data-email") || "").trim();
        const sheet = q(modal, `[data-ia-msg-sheet="newchat"]`);
        if (sheet) sheet.dataset.selectedEmail = email;

        const go = q(modal, "[data-ia-msg-newchat-go='1'], [data-ia-msg-newchat-go]");
        if (go) go.disabled = !email;

        box.classList.remove("open");
      });
    });
  }

  ready(() => {
    bindAtriumChatIntent();
    bindGlobalClose();
  });
})();
