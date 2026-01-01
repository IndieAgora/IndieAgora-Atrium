(function () {
  "use strict";
  const { qs } = window.IA_DISCUSS_CORE;

  function shell() {
    const root = qs("[data-ia-discuss-root]");
    if (!root) return null;

    root.innerHTML = `
      <div class="iad-shell">
        <div class="iad-top">
          <div class="iad-tabs" role="tablist">
            <button class="iad-tab" data-view="new" aria-selected="true">New</button>
            <button class="iad-tab" data-view="unread" aria-selected="false">Unread</button>
            <button class="iad-tab" data-view="agoras" aria-selected="false">Agoras</button>
            <button class="iad-btn iad-tab-action" type="button" data-iad-create-agora hidden>Create Agora</button>
          </div>

          <div class="iad-search" data-iad-search-wrap>
            <input class="iad-input" data-iad-search placeholder="Search…" autocomplete="off" />
          </div>
        </div>

        <div class="iad-view" data-iad-view></div>
      </div>
    `;

    return root;
  }

  function setActiveTab(view) {
    const root = qs("[data-ia-discuss-root]");
    if (!root) return;

    root.querySelectorAll(".iad-tab").forEach((b) => {
      const on = b.getAttribute("data-view") === view;
      b.setAttribute("aria-selected", on ? "true" : "false");
      b.classList.toggle("is-active", on);
    });

    const btn = root.querySelector("[data-iad-create-agora]");
    if (btn) {
      const loggedIn = root.getAttribute("data-logged-in") === "1";
      btn.hidden = !loggedIn;
    }
  }

  window.IA_DISCUSS_UI_SHELL = { shell, setActiveTab };
})();
