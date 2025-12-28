/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.shell.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.shell = NS.ui.shell || {};

  function root() {
    return NS.util.qs("#ia-stream-shell");
  }

  function setTab(name) {
    const R = root();
    if (!R) return;

    const tabs = NS.util.qsa(".ia-stream-tab", R);
    const panels = NS.util.qsa(".ia-stream-panel", R);

    tabs.forEach((b) => {
      const is = (b.getAttribute("data-tab") === name);
      b.classList.toggle("is-active", is);
      b.setAttribute("aria-selected", is ? "true" : "false");
    });

    panels.forEach((p) => {
      const is = (p.getAttribute("data-panel") === name);
      p.classList.toggle("is-active", is);
      if (is) p.removeAttribute("hidden");
      else p.setAttribute("hidden", "hidden");
    });

    NS.state.activeTab = name;
    NS.store.set("tab", name);

    NS.util.dispatch("ia:stream:tab", { tab: name });
  }

  function bindTabs() {
    const R = root();
    if (!R) return;

    NS.util.qsa(".ia-stream-tab", R).forEach((btn) => {
      NS.util.on(btn, "click", function () {
        const tab = btn.getAttribute("data-tab") || "feed";
        setTab(tab);
      });
    });
  }

  NS.ui.shell.boot = function () {
    const R = root();
    if (!R) return;

    bindTabs();

    // Restore last tab
    setTab(NS.state.activeTab || "feed");
  };

  NS.ui.shell.setTab = setTab;
})();
