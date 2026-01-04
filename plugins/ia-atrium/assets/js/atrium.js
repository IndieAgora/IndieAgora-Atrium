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
    try {
      const url = new URL(window.location.href);
      if (value === null || value === undefined || value === "") url.searchParams.delete(name);
      else url.searchParams.set(name, value);
      // Avoid reload: update address bar only
      window.history.replaceState({}, "", url.toString());
    } catch (e) {}
  }

  // Robust tab switching:
  // - Works even if the tab has NO top-strip button (e.g. messages).
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

  // Defensive: ensure scroll is never stuck disabled across reloads.
  // If a prior crash left ia-no-scroll on <html>, clear it on boot.
  try { document.documentElement.classList.remove("ia-no-scroll"); } catch (e) {}

  function escapeHtml(str) {
    return (str || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  // Read more pill: collapse anything marked data-ia-max-words="50"
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

  // Auth modal: set redirect_to to return here and perform intended nav action
  function openAuth(shell, authModal, intendedKey) {
    if (!authModal) return;

    // Remember intent
    shell.setAttribute("data-ia-intended", intendedKey);

    // Build redirect_to = current URL + ia_nav=intended
    const url = new URL(window.location.href);
    url.searchParams.set("ia_nav", intendedKey);

    // Update all redirect inputs inside auth modal
    qsa("[data-ia-redirect-to]", authModal).forEach(inp => {
      inp.value = url.toString();
    });

    openModal(authModal);
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

  // After login redirect: perform the intended action
  function runNavIntent(shell, intentKey) {
    if (!intentKey) return;

    // Only run if logged in now
    if (!isLoggedIn(shell)) return;

    // Prevent rerun if user refreshes
    setUrlParam("ia_nav", null);

    if (intentKey === "profile") {
      setActiveTab(shell, "connect");
      // If a specific profile was requested (e.g. clicking a username in Discuss), honor it.
      const pid = parseInt(getUrlParam("ia_profile") || "0", 10) || 0;
      const uname = getUrlParam("ia_profile_name") || "";
      dispatch("ia_atrium:profile", {
        userId: pid || ((window.IA_ATRIUM && IA_ATRIUM.userId) ? IA_ATRIUM.userId : 0),
        username: uname
      });
      return;
    }

    if (intentKey === "post") {
      const composer = qs("#ia-atrium-composer", shell);
      openModal(composer);
      dispatch("ia_atrium:openComposer", { defaultDestination: "connect" });
      return;
    }

    // If you still want a micro-plugin hook for chat after login,
    // keep dispatch here; chat click itself now opens messages panel.
    if (intentKey === "chat") {
      // Prefer messages panel if it exists
      setActiveTab(shell, "messages");
      setUrlParam("tab", "messages");
      return;
    }

    if (intentKey === "notify") {
      dispatch("ia_atrium:notifications", {});
      return;
    }
  }

  // Profile dropdown menu
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

    const composerModal = qs("#ia-atrium-composer", shell);
    const authModal = qs("#ia-atrium-auth", shell);

    // Initial tab:
    // - respect ?tab= if present (e.g. tab=messages)
    // - else use data-default-tab
    const defaultTab = shell.getAttribute("data-default-tab") || "connect";
    const urlTab = getUrlParam("tab");
    const initialKey = urlTab || defaultTab;
    if (initialKey === "connect" && authModal && !isLoggedIn(shell)) {
      openAuth(shell, authModal, "profile");
    } else {
      setActiveTab(shell, initialKey);
    }

    // Tab switching (no refresh)
    qsa(".ia-tab", shell).forEach(btn => {
      btn.addEventListener("click", function () {
        const key = btn.dataset.target;
        // Connect is profile-gated: logged-out users must authenticate first.
        if (key === "connect" && authModal && !isLoggedIn(shell)) {
          // If Discuss (or other plugins) stored an intended profile, persist it into URL so
          // post-login intent can open the correct profile.
          try {
            const existing = getUrlParam("ia_profile");
            if (!existing) {
              const raw = localStorage.getItem("ia_connect_last_profile");
              if (raw) {
                const obj = JSON.parse(raw);
                const pid = obj && obj.user_id ? String(obj.user_id) : "";
                const uname = obj && obj.username ? String(obj.username) : "";
                if (pid) setUrlParam("ia_profile", pid);
                if (uname) setUrlParam("ia_profile_name", uname);
              }
            }
          } catch (e) {}
          setUrlParam("tab", "connect");
          openAuth(shell, authModal, "profile");
          return;
        }
        setActiveTab(shell, key);
        setUrlParam("tab", key);
      });
    });

    // Composer/Auth close
    shell.addEventListener("click", function (e) {
      if (e.target.closest("[data-ia-modal-close]")) {
        closeModal(composerModal);
      }
      if (e.target.closest("[data-ia-auth-close]")) {
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

      // In-panel links/buttons that request a tab/panel change (e.g. "Forgot password?" / "Continue")
      authModal.addEventListener("click", function (e) {
        const go = e.target.closest("[data-ia-auth-goto]");
        if (!go) return;
        e.preventDefault();
        const target = go.getAttribute("data-ia-auth-goto") || "login";
        setAuthTab(authModal, target);
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

        // If user not logged in:
        // - allow CHAT to open Messages panel (no auth modal)
        // - everything else (including profile) opens auth modal
        if (!isLoggedIn(shell)) {
          if (key === "chat") {
            setActiveTab(shell, "messages");
            setUrlParam("tab", "messages");
            return;
          }

          openAuth(shell, authModal, key);
          setAuthTab(authModal, "login");
          return;
        }

        // Logged in behavior
        if (key === "profile") {
          // Toggle dropdown menu (instead of immediate redirect)
          toggleProfileMenu(shell);
          return;
        }

        if (key === "post") {
          openModal(composerModal);
          dispatch("ia_atrium:openComposer", { defaultDestination: "connect" });
          return;
        }

        // ✅ Chat opens the Messages panel (not a popup)
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
      const act = e.target.closest("[data-profile-action]");
      if (!act) return;

      const action = act.getAttribute("data-profile-action");
      if (action === "go_profile") {
        closeProfileMenu(shell);
        setActiveTab(shell, "connect");
        setUrlParam("tab", "connect");
        dispatch("ia_atrium:profile", {
          userId: (window.IA_ATRIUM && IA_ATRIUM.userId) ? IA_ATRIUM.userId : 0
        });
      }
    });

    // Micro-plugins can request navigation without reload
    window.addEventListener("ia_atrium:navigate", function (ev) {
      if (!ev || !ev.detail || !ev.detail.tab) return;
      const target = String(ev.detail.tab || "");

      // Connect is profile-gated even when other plugins navigate there.
      // Logged-out users should always see the auth modal, never a readable profile.
      if (target === "connect" && authModal && !isLoggedIn(shell)) {
        try {
          const existing = getUrlParam("ia_profile");
          if (!existing) {
            const raw = localStorage.getItem("ia_connect_last_profile");
            if (raw) {
              const obj = JSON.parse(raw);
              const pid = obj && obj.user_id ? String(obj.user_id) : "";
              const uname = obj && obj.username ? String(obj.username) : "";
              if (pid) setUrlParam("ia_profile", pid);
              if (uname) setUrlParam("ia_profile_name", uname);
            }
          }
        } catch (e) {}
        setUrlParam("tab", "connect");
        openAuth(shell, authModal, "profile");
        return;
      }

      setActiveTab(shell, target);
      setUrlParam("tab", target);
    });

    // Micro-plugins can close composer
    window.addEventListener("ia_atrium:closeComposer", function () {
      closeModal(composerModal);
    });

    // Allow micro-plugins to refresh excerpt collapsing after AJAX append
    window.addEventListener("ia_atrium:refreshReadMore", function () {
      initReadMore(shell);
    });

    // Initialize excerpt collapsing
    initReadMore(shell);

    // If redirected back after login/register with intent, run it now
    const intent = getUrlParam("ia_nav");
    if (intent) {
      runNavIntent(shell, intent);
    }
  });

})();