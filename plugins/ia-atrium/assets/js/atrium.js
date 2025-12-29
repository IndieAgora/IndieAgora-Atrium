(function () {
  "use strict";

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function dispatch(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
  }

  function isLoggedIn(shell) {
    return shell && shell.getAttribute("data-logged-in") === "1";
  }

  function setUrlParam(key, value) {
    try {
      const url = new URL(window.location.href);
      if (value === null || value === undefined || value === "") url.searchParams.delete(key);
      else url.searchParams.set(key, value);
      window.history.replaceState({}, "", url.toString());
    } catch (e) {}
  }

  function openModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.add("open");
    modalEl.setAttribute("aria-hidden", "false");
  }

  function closeModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove("open");
    modalEl.setAttribute("aria-hidden", "true");
  }

  function setAuthTab(authModal, tab) {
    if (!authModal) return;
    const tabs = qsa(".ia-auth-tab", authModal);
    tabs.forEach(btn => {
      const on = btn.dataset.authTab === tab;
      btn.classList.toggle("active", on);
      btn.setAttribute("aria-selected", on ? "true" : "false");
    });

    qsa("[data-auth-pane]", authModal).forEach(pane => {
      const on = pane.dataset.authPane === tab;
      pane.style.display = on ? "block" : "none";
    });
  }

  function openAuth(shell, authModal, intentKey) {
    openModal(authModal);
    dispatch("ia_atrium:openAuth", { intent: intentKey || "" });
  }

  function ensureConnectGate(shell, authModal) {
    // Preserve your original behaviour: do not block anything here unless you already did.
    // If your original had logic here, keep it in your local file.
    return false;
  }

  /* =============================================
     Core tab switching
     IMPORTANT: must NOT break if no top tab exists
     (Messages is panel-only, opened from bottom nav)
     ============================================= */

  function setActiveTab(shell, tabKey) {
    const tabs = qsa(".ia-tab", shell);
    const panels = qsa(".ia-panel", shell);

    const hasTopTab = tabs.some(btn => btn.dataset.target === tabKey);

    // Only update the top tab strip if this tab exists there.
    if (hasTopTab) {
      tabs.forEach(btn => {
        const isActive = btn.dataset.target === tabKey;
        btn.classList.toggle("active", isActive);
        btn.setAttribute("aria-selected", isActive ? "true" : "false");
      });
    }

    panels.forEach(panel => {
      const isActive = panel.dataset.panel === tabKey;
      panel.classList.toggle("active", isActive);
      panel.style.display = isActive ? "block" : "none";
      panel.setAttribute("aria-hidden", isActive ? "false" : "true");
    });

    dispatch("ia_atrium:tabChanged", { tab: tabKey });

    const authModal = qs("#ia-atrium-auth", shell) || qs("#ia-atrium-auth");
    if (authModal) ensureConnectGate(shell, authModal);
  }

  function initShell(shell) {
    if (!shell) return;

    const authModal = qs("#ia-atrium-auth", shell) || qs("#ia-atrium-auth");
    const composerModal = qs("#ia-atrium-composer", shell) || qs("#ia-atrium-composer");

    // Initial tab from URL ?tab= or default
    const defaultTab = shell.getAttribute("data-default-tab") || "connect";
    const urlTab = (() => {
      try { return (new URL(window.location.href)).searchParams.get("tab"); }
      catch (e) { return null; }
    })();

    setActiveTab(shell, urlTab || defaultTab);

    // Top tab clicks
    qsa(".ia-tab", shell).forEach(btn => {
      btn.addEventListener("click", function () {
        const key = btn.dataset.target;
        setActiveTab(shell, key);
        setUrlParam("tab", key);
      });
    });

    // Modal close
    qsa("[data-ia-modal-close='1']", shell).forEach(el => {
      el.addEventListener("click", function () {
        closeModal(composerModal);
        if (authModal && ensureConnectGate(shell, authModal)) return;
        closeModal(authModal);
      });
    });

    // Auth tab switching
    if (authModal) {
      qsa(".ia-auth-tab", authModal).forEach(btn => {
        btn.addEventListener("click", function () {
          setAuthTab(authModal, btn.dataset.authTab);
        });
      });
    }

    // Profile menu toggle / close
    function toggleProfileMenu() {
      const menu = qs("[data-profile-menu]", shell);
      if (!menu) return;
      const open = menu.classList.toggle("open");
      menu.setAttribute("aria-hidden", open ? "false" : "true");
    }

    function closeProfileMenu() {
      const menu = qs("[data-profile-menu]", shell);
      if (!menu) return;
      menu.classList.remove("open");
      menu.setAttribute("aria-hidden", "true");
    }

    document.addEventListener("click", function (e) {
      const menu = qs("[data-profile-menu]", shell);
      if (!menu) return;
      const wrap = e.target.closest(".ia-bottom-item-wrap");
      if (!wrap && menu.classList.contains("open")) closeProfileMenu();
    });

    // Bottom nav actions
    qsa(".ia-bottom-item", shell).forEach(btn => {
      btn.addEventListener("click", function () {
        const key = btn.dataset.bottom;

        // Not logged in -> open auth modal for any nav action
        if (!isLoggedIn(shell)) {
          if (authModal) {
            openAuth(shell, authModal, key);
            setAuthTab(authModal, "login");
          }
          return;
        }

        if (key === "profile") {
          toggleProfileMenu();
          return;
        }

        if (key === "post") {
          openModal(composerModal);
          dispatch("ia_atrium:openComposer", { defaultDestination: "connect" });
          return;
        }

        // ✅ Chat now opens the Messages PANEL (not a popup)
        if (key === "chat") {
          setActiveTab(shell, "messages");
          setUrlParam("tab", "messages");
          return;
        }

        if (key === "notify") {
          dispatch("ia_atrium:notifications", {});
          return;
        }
      });
    });

    // Profile dropdown actions
    shell.addEventListener("click", function (e) {
      const action = e.target && e.target.closest && e.target.closest("[data-profile-action]");
      if (!action) return;

      const a = action.getAttribute("data-profile-action");
      closeProfileMenu();

      if (a === "go_profile") {
        setActiveTab(shell, "connect");
        setUrlParam("tab", "connect");
        return;
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const shell = qs("#ia-atrium-shell");
    if (!shell) return;
    initShell(shell);
  });

})();
