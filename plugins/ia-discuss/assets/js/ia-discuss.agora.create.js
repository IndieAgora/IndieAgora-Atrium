(function () {
  "use strict";

  const CORE = window.IA_DISCUSS_CORE;
  const API  = window.IA_DISCUSS_API;

  function qs(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function esc(s) {
    return CORE && CORE.esc ? CORE.esc(s) : String(s)
      .replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function ensureModal() {
    let m = document.querySelector("[data-iad-agora-create-modal]");
    if (m) return m;

    m = document.createElement("div");
    m.setAttribute("data-iad-agora-create-modal", "1");
    m.setAttribute("aria-hidden", "true");
    m.style.cssText = "position:fixed;inset:0;z-index:99999;display:none;";

    m.innerHTML = `
      <div class="iad-modal-overlay" data-iad-agora-create-overlay></div>
      <div class="iad-modal-sheet" role="dialog" aria-modal="true" aria-label="Create Agora">
        <div class="iad-modal-head">
          <div class="iad-modal-title">Create Agora</div>
          <button type="button" class="iad-modal-x" data-iad-agora-create-close aria-label="Close">×</button>
        </div>

        <div class="iad-modal-body">
          <label class="iad-field">
            <div class="iad-field-label">Title</div>
            <input type="text" maxlength="120" class="iad-input" data-iad-agora-title />
          </label>

          <label class="iad-field">
            <div class="iad-field-label">Description</div>
            <textarea class="iad-textarea" rows="6" data-iad-agora-desc></textarea>
          </label>

          <div class="iad-modal-actions">
            <button type="button" class="iad-btn" data-iad-agora-create-cancel>Cancel</button>
            <button type="button" class="iad-btn iad-primary" data-iad-agora-create-submit>Create</button>
          </div>

          <div class="iad-modal-error" data-iad-agora-create-error hidden></div>
        </div>
      </div>
    `;

    document.body.appendChild(m);

    // Close wiring
    const close = () => closeModal();
    qs("[data-iad-agora-create-close]", m).addEventListener("click", close);
    qs("[data-iad-agora-create-cancel]", m).addEventListener("click", close);
    qs("[data-iad-agora-create-overlay]", m).addEventListener("click", close);

    // Submit wiring
    qs("[data-iad-agora-create-submit]", m).addEventListener("click", submit);

    return m;
  }

  function openModal() {
    const m = ensureModal();
    m.style.display = "block";
    m.setAttribute("aria-hidden", "false");
    const t = qs("[data-iad-agora-title]", m);
    if (t) setTimeout(() => t.focus(), 0);
  }

  function closeModal() {
    const m = ensureModal();
    m.style.display = "none";
    m.setAttribute("aria-hidden", "true");
    const err = qs("[data-iad-agora-create-error]", m);
    if (err) { err.hidden = true; err.textContent = ""; }
  }

  async function submit() {
    const m = ensureModal();
    const titleEl = qs("[data-iad-agora-title]", m);
    const descEl  = qs("[data-iad-agora-desc]", m);
    const errEl   = qs("[data-iad-agora-create-error]", m);

    const title = (titleEl ? titleEl.value : "").trim();
    const desc  = (descEl ? descEl.value : "").trim();

    if (!title) {
      if (errEl) { errEl.hidden = false; errEl.textContent = "Title required."; }
      return;
    }

    if (errEl) { errEl.hidden = true; errEl.textContent = ""; }

    const res = await API.post("ia_discuss_create_agora", { title, desc });

    if (!res || !res.success) {
      const msg = (res && res.data && res.data.message) ? res.data.message : "Failed to create agora.";
      if (errEl) { errEl.hidden = false; errEl.textContent = String(msg); }
      return;
    }

    const forumId = res.data && res.data.forum_id ? parseInt(res.data.forum_id, 10) : 0;
    const forumName = (res.data && res.data.forum_name) ? String(res.data.forum_name) : title;

    closeModal();

    // ✅ Tell the existing router to open the Agora (do NOT remount, do NOT use unused URL params)
    if (forumId) {
      window.dispatchEvent(new CustomEvent("iad:open_agora", {
        detail: { forum_id: forumId, forum_name: forumName }
      }));
    }
  }

  function wireOnce() {
    if (window.__IA_DISCUSS_CREATE_AGORA_WIRED) return;
    window.__IA_DISCUSS_CREATE_AGORA_WIRED = true;

    document.addEventListener("click", (e) => {
      const b = e.target && e.target.closest ? e.target.closest("[data-iad-create-agora]") : null;
      if (!b) return;
      e.preventDefault();
      openModal();
    });
  }

  wireOnce();
})();
