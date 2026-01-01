(function () {
  "use strict";

  function ensureModal() {
    let modal = document.querySelector("[data-iad-compose-modal]");
    if (modal) return modal;

    modal = document.createElement("div");
    modal.setAttribute("data-iad-compose-modal", "1");
    modal.setAttribute("aria-hidden", "true");
    modal.style.cssText = "position:fixed;inset:0;z-index:99999;display:none;";

    modal.innerHTML = `
      <div data-iad-compose-overlay class="iad-compose-overlay"></div>

      <div role="dialog" aria-modal="true" class="iad-compose-sheet">
        <div class="iad-compose-top">
          <div class="iad-compose-title" data-iad-compose-title>Reply</div>
          <button type="button" class="iad-compose-x" data-iad-compose-close aria-label="Close">✕</button>
        </div>
        <div class="iad-compose-body">
          <div data-iad-compose-mount></div>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    function close() { closeComposerModal(); }

    const overlay = modal.querySelector("[data-iad-compose-overlay]");
    if (overlay) overlay.addEventListener("click", close);

    const closeBtn = modal.querySelector("[data-iad-compose-close]");
    if (closeBtn) closeBtn.addEventListener("click", close);

    modal.addEventListener("keydown", (e) => {
      if (e.key === "Escape") { e.preventDefault(); close(); }
    });

    // optional global close
    window.addEventListener("iad:close_composer_modal", close);

    return modal;
  }

  function openComposerModal(title) {
    const modal = ensureModal();
    const t = modal.querySelector("[data-iad-compose-title]");
    if (t) t.textContent = String(title || "Reply");

    modal.style.display = "block";
    modal.setAttribute("aria-hidden", "false");

    try { modal.tabIndex = -1; modal.focus(); } catch (e) {}
    return modal;
  }

  function closeComposerModal() {
    const modal = document.querySelector("[data-iad-compose-modal]");
    if (!modal) return;

    const m = modal.querySelector("[data-iad-compose-mount]");
    if (m) m.innerHTML = "";

    modal.style.display = "none";
    modal.setAttribute("aria-hidden", "true");
  }

  function getComposerMount() {
    const modal = ensureModal();
    return modal.querySelector("[data-iad-compose-mount]");
  }

  window.IA_DISCUSS_TOPIC_MODAL = {
    openComposerModal,
    closeComposerModal,
    getComposerMount
  };
})();
