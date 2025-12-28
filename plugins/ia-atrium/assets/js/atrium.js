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

  function getUrlParam(name) {
    try {
      const url = new URL(window.location.href);
      return url.searchParams.get(name);
    } catch (e) {
      return null;
    }
  }

  function setUrlParam(name, value) {
    const url = new URL(window.location.href);
    if (value === null || value === undefined || value === "") {
      url.searchParams.delete(name);
    } else {
      url.searchParams.set(name, value);
    }
    window.history.replaceState({}, "", url.toString());
  }

  /* =========================================================
     CONNECT GATE (logged out)
     - Only applies when Connect tab is active
     - Discuss/Stream remain viewable while logged out
     ========================================================= */

  function applyConnectLock(shell, shouldLock) {
    shell.classList.toggle("ia-connect-locked", !!shouldLock);
  }

  function isConnectActive(shell) {
    const connectPanel = qs('.ia-panel[data-panel="connect"]', shell);
    return !!(connectPanel && connectPanel.classList.contains("active"));
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.documentElement.classList.add("ia-no-scroll");
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.documentElement.classList.remove("ia-no-scroll");
  }

  function setAuthTab(authModal, tab) {
    if (!authModal) return;

    const tabs = qsa(".ia-auth-tab", authModal);
    const panels = qsa(".ia-auth-panel", authModal);

    tabs.forEach(b => {
      const active = b.dataset.authTab === tab;
      b.classList.toggle("active", active);
      b.setAttribute("aria-selected", active ? "true" : "false");
    });

    panels.forEach(p => {
      const active = p.dataset.authPanel === tab;
      p.classList.toggle("active", active);
      p.style.display = active ? "block" : "none";
    });
  }

  function openAuth(shell, authModal, intendedKey) {
    if (!authModal) return;

    shell.setAttribute("data-ia-intended", intendedKey);

    const url = new URL(window.location.href);
    url.searchParams.set("ia_nav", intendedKey);

    qsa("[data-ia-redirect-to]", authModal).forEach(inp => {
      inp.value = url.toString();
    });

    openModal(authModal);
  }

  function ensureConnectGate(shell, authModal) {
    // If logged in -> no gate
    if (isLoggedIn(shell)) {
      applyConnectLock(shell, false);
      return false;
    }

    // Gate only on Connect
    if (!isConnectActive(shell)) {
      applyConnectLock(shell, false);

      // If auth modal is open due to Connect gating, close it when leaving Connect
      if (authModal && authModal.classList.contains("open")) {
        closeModal(authModal);
      }
      return false;
    }

    // Connect active + logged out -> lock Connect + force auth modal open
    applyConnectLock(shell, true);
    openAuth(shell, authModal, "profile");
    setAuthTab(authModal, "login");
    return true;
  }

  /* =========================================================
     Core tab switching
     ========================================================= */

  function setActiveTab(shell, tabKey) {
    const tabs = qsa(".ia-tab", shell);
    const panels = qsa(".ia-panel", shell);

    tabs.forEach(btn => {
      const isActive = btn.dataset.target === tabKey;
      btn.classList.toggle("active", isActive);
      btn.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    panels.forEach(panel => {
      const isActive = panel.dataset.panel === tabKey;
      panel.classList.toggle("active", isActive);
      panel.style.display = isActive ? "block" : "none";
    });

    dispatch("ia_atrium:tabChanged", { tab: tabKey });

    // Apply Connect-only gate after switching
    const authModal = qs("#ia-atrium-auth", shell) || qs("#ia-atrium-auth");
    if (authModal) ensureConnectGate(shell, authModal);
  }

  function escapeHtml(str) {
    return (str || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function initReadMore(shell) {
    qsa("[data-ia-max-words]", shell).forEach(el => {
      if (el.getAttribute("data-ia-readmore-ready") === "1") return;

      const maxWords = parseInt(el.getAttribute("data-ia-max-words"), 10) || 50;
      const fullHtml = (el.innerHTML || "").trim();
      const fullText = (el.textContent || "").trim();
      const words = fullText.split(/\s+/).filter(Boolean);

      if (words.length <= maxWords) {
        el.setAttribute("data-ia-readmore-ready", "1");
        return;
      }

      const excerpt = words.slice(0, maxWords).join(" ");

      el.setAttribute("data-ia-full-html", fullHtml);
      el.setAttribute("data-ia-is-collapsed", "1");
      el.setAttribute("data-ia-readmore-ready", "1");

      el.innerHTML = `
        <div class="ia-excerpt">${escapeHtml(excerpt)}…</div>
        <button type="button" class="ia-pill ia-readmore" data-ia-readmore="1">Read more</button>
      `;
    });

    shell.addEventListener("click", function (e) {
      const btn = e.target.closest("[data-ia-readmore]");
      if (!btn) return;

      const container = btn.closest("[data-ia-max-words]");
      if (!container) return;

      const collapsed = container.getAttribute("data-ia-is-collapsed") === "1";
      const fullHtml = container.getAttribute("data-ia-full-html") || "";

      if (collapsed) {
        container.setAttribute("data-ia-is-collapsed", "0");
        container.innerHTML = `
          <div class="ia-full">${fullHtml}</div>
          <button type="button" class="ia-pill ia-readmore" data-ia-readmore="1">Show less</button>
        `;
      } else {
        container.setAttribute("data-ia-is-collapsed", "1");
        container.innerHTML = fullHtml;
        container.removeAttribute("data-ia-readmore-ready");
        initReadMore(shell);
      }
    });
  }

  function runNavIntent(shell, intentKey) {
    if (!intentKey) return;
    if (!isLoggedIn(shell)) return;

    setUrlParam("ia_nav", null);

    if (intentKey === "profile") {
      setActiveTab(shell, "connect");
      dispatch("ia_atrium:profile", {
        userId: (window.IA_ATRIUM && IA_ATRIUM.userId) ? IA_ATRIUM.userId : 0
      });
      return;
    }

    if (intentKey === "post") {
      const composer = qs("#ia-atrium-composer", shell);
      openModal(composer);
      dispatch("ia_atrium:openComposer", { defaultDestination: "connect" });
      return;
    }

    if (intentKey === "chat") {
      dispatch("ia_atrium:chat", {});
      return;
    }

    if (intentKey === "notify") {
      dispatch("ia_atrium:notifications", {});
      return;
    }
  }

  function closeProfileMenu(shell) {
    const menu = qs("[data-profile-menu]", shell);
    if (!menu) return;
    menu.setAttribute("aria-hidden", "true");
    menu.classList.remove("open");
  }

  function toggleProfileMenu(shell) {
    const menu = qs("[data-profile-menu]", shell);
    if (!menu) return;
    const open = menu.classList.contains("open");
    if (open) {
      closeProfileMenu(shell);
    } else {
      menu.classList.add("open");
      menu.setAttribute("aria-hidden", "false");
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    const shell = qs("#ia-atrium-shell");
    if (!shell) return;

    const composerModal = qs("#ia-atrium-composer", shell) || qs("#ia-atrium-composer");
    const authModal = qs("#ia-atrium-auth", shell) || qs("#ia-atrium-auth");

    const defaultTab = shell.getAttribute("data-default-tab") || "connect";
    setActiveTab(shell, defaultTab);

    // Apply Connect-only gate on load
    if (authModal) ensureConnectGate(shell, authModal);

    // Tabs (always allow switching)
    qsa(".ia-tab", shell).forEach(btn => {
      btn.addEventListener("click", function () {
        const target = btn.dataset.target || "connect";
        setActiveTab(shell, target);
      });
    });

    // Composer close + auth close
    shell.addEventListener("click", function (e) {
      if (e.target.closest("[data-ia-modal-close]")) {
        closeModal(composerModal);
      }

      const authClose = e.target.closest("[data-ia-auth-close]");
      if (authClose) {
        // If Connect is active and user is logged out, keep modal open
        if (authModal && ensureConnectGate(shell, authModal)) return;
        closeModal(authModal);
      }
    });

    // Auth tab switching
    if (authModal) {
      qsa(".ia-auth-tab", authModal).forEach(btn => {
        btn.addEventListener("click", function () {
          setAuthTab(authModal, btn.dataset.authTab);
        });
      });
    }

    // Close profile menu if clicking elsewhere
    document.addEventListener("click", function (e) {
      const insideProfileWrap = e.target.closest(".ia-bottom-item-wrap");
      const menu = qs("[data-profile-menu]", shell);

      if (!insideProfileWrap && menu && menu.classList.contains("open")) {
        closeProfileMenu(shell);
      }
    });

    // Bottom nav actions
    qsa(".ia-bottom-item", shell).forEach(btn => {
      btn.addEventListener("click", function () {
        const key = btn.dataset.bottom;

        // If user not logged in, show auth modal for ANY nav action (unchanged)
        if (!isLoggedIn(shell)) {
          if (authModal) {
            openAuth(shell, authModal, key);
            setAuthTab(authModal, "login");
          }
          return;
        }

        if (key === "profile") {
          toggleProfileMenu(shell);
          return;
        }

        if (key === "post") {
          openModal(composerModal);
          dispatch("ia_atrium:openComposer", { defaultDestination: "connect" });
          return;
        }

        if (key === "chat") {
          dispatch("ia_atrium:chat", {});
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
      const act = e.target.closest("[data-profile-action]");
      if (!act) return;

      const action = act.getAttribute("data-profile-action");
      if (action === "go_profile") {
        closeProfileMenu(shell);
        setActiveTab(shell, "connect");
        dispatch("ia_atrium:profile", {
          userId: (window.IA_ATRIUM && IA_ATRIUM.userId) ? IA_ATRIUM.userId : 0
        });
      }
    });

    window.addEventListener("ia_atrium:navigate", function (ev) {
      if (!ev || !ev.detail || !ev.detail.tab) return;
      setActiveTab(shell, ev.detail.tab);
    });

    window.addEventListener("ia_atrium:closeComposer", function () {
      closeModal(composerModal);
    });

    window.addEventListener("ia_atrium:refreshReadMore", function () {
      initReadMore(shell);
    });

    initReadMore(shell);

    const intent = getUrlParam("ia_nav");
    if (intent) {
      runNavIntent(shell, intent);
    }
  });

})();
