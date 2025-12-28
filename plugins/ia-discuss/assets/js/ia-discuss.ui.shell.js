(function () {
  "use strict";
  const { qs, esc } = window.IA_DISCUSS_CORE;

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
          </div>
        </div>

        <div class="iad-body">
          <div class="iad-view" data-iad-view></div>
        </div>
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
  }

  window.IA_DISCUSS_UI_SHELL = { shell, setActiveTab };
})();
