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

    // --- Panel-scroll mode: save current panel scrollTop before switching.
    // Atrium uses display:none/block for panels; by default the page scroll (window)
    // is shared across tabs. In panel-scroll mode we disable window scrolling and
    // make each panel its own scroll container.
    try {
      const cur = qs('.ia-panel.active', shell);
      if (cur) {
        const curKey = cur.dataset.panel || "";
        const st = (cur.scrollTop || 0);
        sessionStorage.setItem('ia_atrium_panel_scroll_' + curKey, String(st));
      }
    } catch (e) {}

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

    // Track current tab for other UI (e.g., topbar search scope).
    try { shell.setAttribute('data-active-tab', String(tabKey || '')); } catch (e) {}

    // --- Panel-scroll mode: restore target panel scrollTop after switching.
    try {
      const next = qs('.ia-panel.active', shell);
      if (next) {
        const nextKey = next.dataset.panel || "";
        const raw = sessionStorage.getItem('ia_atrium_panel_scroll_' + nextKey);
        const st = raw ? (parseInt(raw, 10) || 0) : 0;
        // Let layout settle before restoring.
        requestAnimationFrame(() => {
          try { next.scrollTop = st; } catch (e1) {}
        });
      }
    } catch (e) {}

    dispatch("ia_atrium:tabChanged", { tab: tabKey });
  }

  function enablePanelScrollMode(shell) {
    if (!shell) return;
    try {
      shell.setAttribute('data-ia-scroll-mode', 'panels');
      document.documentElement.classList.add('ia-atrium-mode');
      document.body.classList.add('ia-atrium-mode');
    } catch (e) {}

    const topbar = qs('.ia-atrium-topbar', shell);
    const bottom = qs('.ia-bottom-nav', shell);
    const main = qs('.ia-atrium-main', shell);
    if (!main) return;

    function setHeights() {
      try {
        const vh = window.innerHeight || document.documentElement.clientHeight || 0;
        const th = topbar ? topbar.getBoundingClientRect().height : 0;
        const bh = bottom ? bottom.getBoundingClientRect().height : 0;
        const topbarHidden = shell.getAttribute('data-ia-topbar-hidden') === '1';
        const visibleTopbar = topbarHidden ? 0 : th;
        // shell has margin-top (main) and internal spacing; keep a small cushion.
        const cushion = 24;
        const h = Math.max(200, Math.floor(vh - visibleTopbar - bh - cushion));
        main.style.height = h + 'px';
      } catch (e) {}
    }

    shell.__iaSetHeights = setHeights;
    setHeights();
    window.addEventListener('resize', setHeights);
    window.addEventListener('orientationchange', setHeights);
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

  function initAutoHideTopbar(shell) {
    if (!shell) return;

    let lastY = 0;
    let hidden = false;
    let ticking = false;

    function applyHidden(nextHidden) {
      nextHidden = !!nextHidden;
      if (hidden === nextHidden) return;
      hidden = nextHidden;
      shell.setAttribute('data-ia-topbar-hidden', hidden ? '1' : '0');
      try { if (typeof shell.__iaSetHeights === 'function') shell.__iaSetHeights(); } catch (e) {}
    }

    function activePanel() {
      return qs('.ia-panel.active', shell);
    }

    function syncFromActive() {
      const panel = activePanel();
      lastY = panel ? (panel.scrollTop || 0) : 0;
      if (lastY <= 24) applyHidden(false);
    }

    function onPanelScroll(panel) {
      if (!panel) return;
      const y = panel.scrollTop || 0;
      const delta = y - lastY;
      lastY = y;

      if (y <= 24) {
        applyHidden(false);
        return;
      }

      if (delta > 10) {
        applyHidden(true);
        return;
      }

      if (delta < -6) {
        applyHidden(false);
      }
    }

    function bindPanel(panel) {
      if (!panel || panel.__iaTopbarAutohideBound) return;
      panel.__iaTopbarAutohideBound = true;
      panel.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
          ticking = false;
          onPanelScroll(panel);
        });
      }, { passive: true });
    }

    qsa('.ia-panel', shell).forEach(bindPanel);
    window.addEventListener('ia_atrium:tabChanged', syncFromActive);
    syncFromActive();
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

  // Topbar: tab dropdown label
  function tabLabelFromKey(shell, key) {
    const item = shell ? qs('.ia-atrium-tabmenu-item[data-target="' + key + '"]', shell) : null;
    if (item) return (item.textContent || '').trim();
    // Fallback: map keys to reasonable labels.
    const map = { connect: 'Connect', discuss: 'Discuss', stream: 'Stream', messages: 'Messages' };
    return map[String(key || '').toLowerCase()] || String(key || '').trim() || 'Connect';
  }

  function syncTopbarLabel(shell, key) {
    const labelEl = qs('[data-ia-current-tab-label]', shell);
    if (labelEl) labelEl.textContent = tabLabelFromKey(shell, key);
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

    // Enable independent scrolling per tab by making each panel its own scroll container.
    // This is the only reliable way to prevent "all tabs scroll together" given the
    // current shell structure.
    enablePanelScrollMode(shell);
    initAutoHideTopbar(shell);

    // Ensure the browser is allowed to restore scroll positions when using back/forward.
    // (Some environments default to manual; we prefer native behaviour.)
    try { if ("scrollRestoration" in window.history) window.history.scrollRestoration = "auto"; } catch (e) {}

    const composerModal = qs("#ia-atrium-composer", shell);
    const authModal = qs("#ia-atrium-auth", shell);

    // ------------------------------------------------------------
    // Discuss: prevent full page reload when clicking topic/reply links
    // ------------------------------------------------------------
    // Some Discuss-rendered snippets include <a href="...?tab=discuss&iad_topic=...">.
    // If the browser follows these links, you lose scroll/pagination state and back
    // returns to the top. We intercept same-origin Discuss topic links at CAPTURE
    // phase and route them through Discuss' SPA event.
    document.addEventListener("click", function (e) {
      try {
        const a = e.target && e.target.closest ? e.target.closest("a[href]") : null;
        if (!a) return;
        if (a.getAttribute("target") === "_blank") return;
        if (a.hasAttribute("download")) return;

        const href = a.getAttribute("href") || "";
        if (!href || href.startsWith("#") || href.startsWith("javascript:")) return;

        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin) return;

        const tid = parseInt(url.searchParams.get("iad_topic") || "0", 10) || 0;
        if (!tid) return;

        // Only hijack if the click is within the Discuss panel, OR the URL tab is discuss.
        const discussPanel = qs('.ia-panel[data-panel="discuss"]', shell);
        const insideDiscuss = discussPanel ? discussPanel.contains(a) : false;
        const urlTab = (url.searchParams.get("tab") || "").toLowerCase();
        if (!insideDiscuss && urlTab !== "discuss") return;

        // Prevent browser navigation.
        e.preventDefault();
        e.stopPropagation();

        // Ensure Discuss panel is visible.
        setActiveTab(shell, "discuss");
        setUrlParam("tab", "discuss");

        const postId = parseInt(url.searchParams.get("iad_post") || "0", 10) || 0;

        // Route through Discuss SPA.
        window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
          detail: {
            topic_id: tid,
            scroll_post_id: postId || 0,
            highlight_new: 0,
            open_reply: 0
          }
        }));
      } catch (err) {
        // fail open: allow normal navigation
      }
    }, true);

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

    // Topbar label reflects initial tab (even if the legacy tab strip is hidden).
    syncTopbarLabel(shell, initialKey);

    // Topbar: dropdown menu behaviour (Reddit-like).
    (function bindTabMenu(){
      const toggle = qs('[data-ia-tabmenu-toggle]', shell);
      const menu   = qs('[data-ia-tabmenu]', shell);
      if (!toggle || !menu) return;

      const open = () => {
        menu.classList.add('open');
        menu.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
      };
      const close = () => {
        menu.classList.remove('open');
        menu.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
      };
      const isOpen = () => menu.classList.contains('open');

      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        isOpen() ? close() : open();
      });

      menu.addEventListener('click', (e) => {
        const it = e.target.closest('[data-ia-tabmenu-item]');
        if (!it) return;
        const key = String(it.dataset.target || '').trim();
        if (!key) return;
        close();
        // Use the same gating rules as the legacy tab buttons.
        if (key === 'connect' && authModal && !isLoggedIn(shell)) {
          setUrlParam('tab', 'connect');
          openAuth(shell, authModal, 'profile');
          return;
        }
        setActiveTab(shell, key);
        setUrlParam('tab', key);
      });

      // Close when clicking elsewhere.
      document.addEventListener('click', (e) => {
        if (!isOpen()) return;
        const inside = e.target.closest('[data-ia-tabmenu]') || e.target.closest('[data-ia-tabmenu-toggle]');
        if (!inside) close();
      });

      // Keep label synced when anything changes the tab.
      window.addEventListener('ia_atrium:tabChanged', (ev) => {
        try {
          const key = ev && ev.detail && ev.detail.tab ? String(ev.detail.tab) : '';
          if (key) syncTopbarLabel(shell, key);
        } catch (e) {}
      });
    })();

    
// Topbar: fullscreen search overlay.
//
// IMPORTANT: IA Discuss can re-render the topbar / shell without a full page reload.
// If we bind directly to specific DOM nodes once, the search UI can appear "dead"
// after closing a topic (because the button/input nodes were replaced).
// We therefore use event delegation + lazy element lookup.
(function bindSearch(){
  // IA Discuss can re-render / replace the entire Atrium shell node without a full
  // page reload. If we close over the original `shell` reference, scoped queries
  // can become "dead" after replacement. Always resolve the current shell lazily.
  function getShell() {
    return document.getElementById('ia-atrium-shell');
  }

  // IA Discuss sometimes re-renders the shell and drops the search overlay markup
  // (while the search button remains). If that happens, the delegated click handler
  // will find no overlay/input/results and appear "disabled" until a full refresh.
  // To make search reliably multi-use, we re-inject the overlay when it is missing.
  function ensureSearchOverlay() {
    const s = getShell();
    if (!s) return;
    if (qs('[data-ia-search]', s)) return;

    // Mirror the markup from templates/atrium-shell.php.
    const wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="ia-atrium-search" data-ia-search aria-hidden="true">' +
        '<div class="ia-atrium-search-backdrop" data-ia-search-close></div>' +
        '<div class="ia-atrium-search-panel" role="dialog" aria-modal="true" aria-label="Search">' +
          '<div class="ia-atrium-search-top">' +
            '<input class="ia-atrium-search-input" type="search" inputmode="search" placeholder="Search users, posts, replies" autocomplete="off" data-ia-search-input />' +
            '<button type="button" class="ia-atrium-search-close" data-ia-search-close aria-label="Close">×</button>' +
          '</div>' +
          '<div class="ia-atrium-search-results" data-ia-search-results></div>' +
        '</div>' +
      '</div>';

    // Prefer to place it after the topbar header (like the template), but fall back
    // to appending to the shell if the header cannot be located.
    const header = qs('header.ia-atrium-topbar', s) || qs('header', s);
    if (header && header.parentNode) {
      header.parentNode.insertBefore(wrap.firstChild, header.nextSibling);
    } else {
      s.appendChild(wrap.firstChild);
    }
  }

  function getEls() {
    ensureSearchOverlay();
    const s = getShell();
    const overlay = qs('[data-ia-search]', s || document);
    const openBtn = qs('[data-ia-search-open]', s || document);
    const input   = qs('[data-ia-search-input]', s || document);
    const results = qs('[data-ia-search-results]', s || document);
    return { overlay, openBtn, input, results };
  }

  function isReady() {
    const els = getEls();
    return !!(els.overlay && els.openBtn && els.input && els.results);
  }

  function isOpen() {
    const els = getEls();
    return !!(els.overlay && els.overlay.classList.contains('open'));
  }

  function open() {
    const { overlay, input } = getEls();
    if (!overlay || !input) return;

    // Update placeholder based on current tab.
    try {
      const s = getShell();
      const t = String((s && s.getAttribute('data-active-tab')) || getUrlParam('tab') || '') || 'connect';
      try { overlay.setAttribute('data-scope', t); } catch (e) {}
      if (t === 'discuss') input.setAttribute('placeholder', 'Search Discuss');
      else if (t === 'connect') input.setAttribute('placeholder', 'Search Connect');
      else input.setAttribute('placeholder', 'Search');
    } catch (e) {}

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('ia-no-scroll');
    setTimeout(() => { try { input.focus(); } catch (e) {} }, 0);
  }

  function close() {
    const { overlay, input, results } = getEls();
    if (!overlay) return;

    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('ia-no-scroll');
    try { if (input) input.value = ''; } catch (e) {}
    try { if (results) results.innerHTML = ''; } catch (e) {}
  }

  // "Belt and braces" hard close used after navigation events.
  function knownHardClose() {
    const { overlay, input, results } = getEls();
    if (!overlay) return;
    try { overlay.classList.remove('open'); } catch (e) {}
    try { overlay.setAttribute('aria-hidden', 'true'); } catch (e) {}
    try { document.documentElement.classList.remove('ia-no-scroll'); } catch (e) {}
    try { if (input) input.blur(); } catch (e) {}
    try { if (input) input.value = ''; } catch (e) {}
    try { if (results) results.innerHTML = ''; } catch (e) {}

    // Nudge layout without mutating inline display.
    // (Toggling display here caused a race where subsequent hard-closes would
    // capture prev='none' and permanently hide the overlay until refresh.)
    try { void overlay.getBoundingClientRect(); } catch (e) {}
  }

  function postAjax(action, data) {
    const fd = new FormData();
    fd.append('action', action);
    Object.keys(data || {}).forEach(k => fd.append(k, data[k]));
    return fetch((window.IA_ATRIUM && IA_ATRIUM.ajaxUrl) ? IA_ATRIUM.ajaxUrl : '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(r => r.json());
  }

  function renderEmpty(msg) {
    const { results } = getEls();
    if (!results) return;
    results.innerHTML = '<div class="ia-atrium-search-empty">' + escapeHtml(msg || 'No results') + '</div>';
  }

  function renderSection(title, itemsHtml) {
    return (
      '<div class="ia-atrium-search-section">' +
        '<div class="ia-atrium-search-title">' + escapeHtml(title) + '</div>' +
        itemsHtml +
      '</div>'
    );
  }

  function buildHref(opts) {
    opts = opts || {};
    try {
      const u = new URL(window.location.href);
      const tab = String(opts.tab || '').trim();
      if (!tab) return u.toString();
      u.searchParams.set('tab', tab);

      // Clear cross-tab params that could conflict.
      ['iad_view','iad_q','iad_topic','iad_post','iad_forum','iad_forum_name','ia_profile','ia_profile_name','ia_post','ia_comment','ia_connect_focus'].forEach((k) => {
        try { u.searchParams.delete(k); } catch (e) {}
      });

      if (tab === 'connect') {
        const profile = String(opts.profile || '').trim();
        if (profile) {
          if (profile.startsWith('post:')) {
            const pid = parseInt(profile.slice(5) || '0', 10) || 0;
            if (pid > 0) u.searchParams.set('ia_post', String(pid));
            u.searchParams.delete('ia_comment');
          } else if (profile.startsWith('comment:')) {
            const bits = profile.split(':');
            const postId = parseInt(bits[1] || '0', 10) || 0;
            const commentId = parseInt(bits[2] || '0', 10) || 0;
            if (postId > 0) u.searchParams.set('ia_post', String(postId));
            if (commentId > 0) u.searchParams.set('ia_comment', String(commentId));
            else u.searchParams.delete('ia_comment');
          } else {
            u.searchParams.delete('ia_post');
            u.searchParams.delete('ia_comment');
            u.searchParams.set('ia_profile', profile);
          }
        }
        return u.toString();
      }

      if (tab === 'discuss') {
        const topicId = parseInt(String(opts.iadTopic || '0'), 10) || 0;
        const q = String(opts.iadQ || '').trim();
        const fid = parseInt(String(opts.profile || '0'), 10) || 0; // forum_id stored in profile

        if (topicId) {
          u.searchParams.set('iad_topic', String(topicId));
          return u.toString();
        }
        if (fid) {
          u.searchParams.set('iad_forum', String(fid));
          return u.toString();
        }
        if (q) {
          u.searchParams.set('iad_view', 'search');
          u.searchParams.set('iad_q', q);
        }
        return u.toString();
      }

      return u.toString();
    } catch (e) {
      return '#';
    }
  }

  function itemHtml(opts) {
    const img = opts.avatar ? ('<img src="' + escapeHtml(opts.avatar) + '" alt="" />') : '<span style="width:34px;height:34px;display:block"></span>';
    const href = buildHref(opts);
    return (
      '<a class="ia-atrium-search-item" href="' + escapeHtml(href) + '" ' +
        'data-ia-search-nav="1" ' +
        'data-tab="' + escapeHtml(opts.tab || '') + '" ' +
        'data-iad-q="' + escapeHtml(opts.iadQ || '') + '" ' +
        'data-iad-topic="' + escapeHtml(opts.iadTopic || '') + '" ' +
        'data-ia-profile="' + escapeHtml(opts.profile || '') + '" ' +
      '>' +
        img +
        '<div class="ia-atrium-search-meta">' +
          '<div class="ia-atrium-search-primary">' + escapeHtml(opts.primary || '') + '</div>' +
          (opts.secondary ? '<div class="ia-atrium-search-secondary">' + escapeHtml(opts.secondary) + '</div>' : '') +
        '</div>' +
      '</a>'
    );
  }

  let t = 0;
  let lastQ = '';

  function run(q) {
    const { results } = getEls();
    if (!results) return;

    q = String(q || '').trim();
    if (q.length < 2) {
      results.innerHTML = '';
      return;
    }

    lastQ = q;
    results.innerHTML = '<div class="ia-atrium-search-empty">Searching…</div>';

    const loggedIn = !!(window.IA_ATRIUM && IA_ATRIUM.isLoggedIn);
    const nonces = (window.IA_ATRIUM && IA_ATRIUM.nonces) ? IA_ATRIUM.nonces : {};

    const s = getShell();
    const scope = String((s && s.getAttribute('data-active-tab')) || getUrlParam('tab') || '').trim() || 'connect';
    const wantDiscuss = (scope === 'discuss') ? true : (scope === 'connect' ? false : true);
    const wantConnect = (scope === 'connect') ? true : (scope === 'discuss' ? false : true);

    const reqs = [];

    if (wantDiscuss) {
      const discussNonce = (window.IA_DISCUSS && IA_DISCUSS.nonce) ? String(IA_DISCUSS.nonce) : '';
      reqs.push(
        postAjax('ia_discuss_search_suggest', { q: q, nonce: discussNonce })
          .then(res => ({ src: 'discuss', res: res }))
          .catch(() => ({ src: 'discuss', res: null }))
      );
    }

    if (wantConnect) {
      reqs.push(
        postAjax('ia_connect_user_search', { q: q, nonce: nonces.connect_user_search || '' })
          .then(res => ({ src: 'connect_users', res: res }))
          .catch(() => ({ src: 'connect_users', res: null }))
      );
      reqs.push(
        postAjax('ia_connect_wall_search', { q: q, nonce: nonces.connect_wall_search || '' })
          .then(res => ({ src: 'connect_wall', res: res }))
          .catch(() => ({ src: 'connect_wall', res: null }))
      );
    }

    Promise.all(reqs).then(parts => {
      // If input changed since request started, ignore.
      const { input, results: rNow } = getEls();
      if (input && String(input.value || '').trim() !== lastQ) return;
      if (!rNow) return;

      const discuss = (parts.find(p => p.src === 'discuss') || {}).res;
      const cu = (parts.find(p => p.src === 'connect_users') || {}).res;
      const cw = (parts.find(p => p.src === 'connect_wall') || {}).res;

      const html = [];

      // CONNECT USERS
      // ia-connect historically returned { users: [...] } but newer builds return { results: [...] }.
      const users = (cu && cu.success && cu.data)
        ? (Array.isArray(cu.data.users) ? cu.data.users : (Array.isArray(cu.data.results) ? cu.data.results : []))
        : [];
      if (loggedIn && users.length) {
        html.push(renderSection('Users', users.map(u => itemHtml({
          tab: 'connect',
          profile: String(u.phpbb_user_id || u.wp_user_id || ''),
          avatar: u.avatarUrl || '',
          primary: u.display || u.username || 'User',
          secondary: u.username ? ('@' + u.username) : ''
        })).join('')));
      }

      // DISCUSS
      const d = (discuss && discuss.success && discuss.data) ? discuss.data : null;
      if (d) {
        const agoras = Array.isArray(d.agoras) ? d.agoras : [];
        const topics = Array.isArray(d.topics) ? d.topics : [];
        const replies = Array.isArray(d.replies) ? d.replies : [];
        const dUsers = Array.isArray(d.users) ? d.users : [];

        if (scope === 'discuss' && dUsers.length) {
          html.push(renderSection('Users', dUsers.map(u => itemHtml({
            tab: 'connect',
            profile: String(u.user_id || ''),
            avatar: u.avatar_url || '',
            primary: u.username || 'User',
            secondary: 'Discuss user'
          })).join('')));
        }

        if (agoras.length) {
          html.push(renderSection('Agoras', agoras.map(a => itemHtml({
            tab: 'discuss',
            primary: a.forum_name || 'Agora',
            secondary: 'Discuss',
            profile: String(a.forum_id || ''),
            iadTopic: ''
          })).join('')));
        }

        if (topics.length) {
          html.push(renderSection('Topics', topics.map(t => itemHtml({
            tab: 'discuss',
            primary: t.topic_title || 'Topic',
            secondary: (t.forum_name ? (t.forum_name + ' • ') : '') + 'Discuss',
            iadTopic: String(t.topic_id || ''),
            profile: ''
          })).join('')));
        }

        if (replies.length) {
          html.push(renderSection('Replies', replies.map(r => itemHtml({
            tab: 'discuss',
            primary: r.topic_title || 'Reply',
            secondary: (r.username ? (r.username + ' • ') : '') + 'Discuss',
            iadTopic: String(r.topic_id || ''),
            profile: ''
          })).join('')));
        }
      }

      // CONNECT posts/comments
      const posts = (cw && cw.success && cw.data && Array.isArray(cw.data.posts)) ? cw.data.posts : [];
      const comments = (cw && cw.success && cw.data && Array.isArray(cw.data.comments)) ? cw.data.comments : [];

      if (loggedIn && posts.length) {
        html.push(renderSection('Connect posts', posts.map(p => itemHtml({
          tab: 'connect',
          primary: p.title || 'Post',
          secondary: p.author ? ('by ' + p.author) : 'Connect',
          profile: 'post:' + String(p.id || ''),
          avatar: p.author_avatar || ''
        })).join('')));
      }

      if (loggedIn && comments.length) {
        html.push(renderSection('Connect comments', comments.map(c => itemHtml({
          tab: 'connect',
          primary: (c.body || '').replace(/\s+/g,' ').trim().slice(0, 60) + ((c.body||'').length>60 ? '…' : ''),
          secondary: c.author ? ('by ' + c.author) : 'Connect',
          profile: 'comment:' + String(c.post_id || '') + ':' + String(c.id || ''),
          avatar: c.author_avatar || ''
        })).join('')));
      }

      if (!html.length) {
        if (scope === 'connect' && !loggedIn) renderEmpty('Log in to search Connect');
        else renderEmpty('No results');
        return;
      }

      rNow.innerHTML = html.join('');
    });
  }

  function goToDiscussSearch(q) {
    q = String(q || '').trim();
    if (!q) return;
    // Ensure tab + params are correct for "full" search results.
    const s = getShell();
    if (s) setActiveTab(s, 'discuss');
    setUrlParam('tab', 'discuss');
    setUrlParam('iad_view', 'search');
    setUrlParam('iad_q', q);

    // IA Discuss listens for this to render the search results view.
    window.dispatchEvent(new CustomEvent('iad:open_search', { detail: { q: q } }));
    // Ensure overlay is gone.
    setTimeout(knownHardClose, 0);
  }

  // Delegated open/close interactions.
  document.addEventListener('click', (e) => {
    if (!isReady()) return;

    if (e.target.closest('[data-ia-search-open]')) {
      e.preventDefault();
      open();
      return;
    }

    if (e.target.closest('[data-ia-search-close]') && isOpen()) {
      e.preventDefault();
      close();
      return;
    }
  });

  document.addEventListener('keydown', (e) => {
    if (!isReady()) return;

    if (e.key === 'Escape' && isOpen()) {
      close();
      return;
    }

    // Enter in the search input should open the detailed Discuss search view when in Discuss.
    const { input } = getEls();
    if (!input) return;
    if (e.key === 'Enter' && document.activeElement === input) {
      const s = getShell();
      const scope = String((s && s.getAttribute('data-active-tab')) || getUrlParam('tab') || '').trim() || 'connect';
      const q = String(input.value || '').trim();
      if (scope === 'discuss') {
        e.preventDefault();
        close();
        knownHardClose();
        goToDiscussSearch(q);
      }
    }
  });

  document.addEventListener('input', (e) => {
    if (!isReady()) return;
    const { input } = getEls();
    if (!input) return;
    if (e.target !== input) return;

    clearTimeout(t);
    const q = input.value;
    t = setTimeout(() => run(q), 180);
  });

  // Navigation handler (delegated). Use pointer/touch first so the overlay closes reliably on mobile.
  function handleNav(e) {
    if (!isReady()) return;
    const it = e.target.closest('[data-ia-search-nav]');
    if (!it) return;

    try {
      if (e.button && e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (String(it.getAttribute('target') || '').toLowerCase() === '_blank') return;
    } catch (err) {}

    try { e.preventDefault(); } catch (err) {}

    const tab = String(it.getAttribute('data-tab') || '').trim();
    if (!tab) return;

    // Close overlay then navigate.
    close();
    knownHardClose();
    setTimeout(knownHardClose, 0);

    if (tab === 'connect') {
      try {
        const profile = String(it.getAttribute('data-ia-profile') || '').trim();
        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'connect');
        // Clear discuss params
        ['iad_view','iad_q','iad_topic','iad_post','iad_forum','iad_forum_name'].forEach(k => { try { url.searchParams.delete(k); } catch (e) {} });

        if (profile) {
          if (profile.startsWith('post:')) {
            const pid = parseInt(profile.slice(5) || '0', 10) || 0;
            if (pid > 0) url.searchParams.set('ia_post', String(pid));
            url.searchParams.delete('ia_comment');
            url.searchParams.delete('ia_profile');
            url.searchParams.delete('ia_profile_name');
          } else if (profile.startsWith('comment:')) {
            const bits = profile.split(':');
            const postId = parseInt(bits[1] || '0', 10) || 0;
            const commentId = parseInt(bits[2] || '0', 10) || 0;
            if (postId > 0) url.searchParams.set('ia_post', String(postId));
            if (commentId > 0) url.searchParams.set('ia_comment', String(commentId));
            else url.searchParams.delete('ia_comment');
            url.searchParams.delete('ia_profile');
            url.searchParams.delete('ia_profile_name');
          } else {
            url.searchParams.delete('ia_post');
            url.searchParams.delete('ia_comment');
            url.searchParams.set('ia_profile', profile);
          }
        }
        window.location.href = url.toString();
      } catch (e) {
        const s = getShell();
        if (s) setActiveTab(s, 'connect');
        setUrlParam('tab', 'connect');
      }
      return;
    }

    if (tab === 'discuss') {
      const s = getShell();
      if (s) setActiveTab(s, 'discuss');
      setUrlParam('tab', 'discuss');

      const topicId = parseInt(it.getAttribute('data-iad-topic') || '0', 10) || 0;
      const forumMaybe = String(it.getAttribute('data-ia-profile') || '').trim();
      if (topicId) {
        setUrlParam('iad_topic', String(topicId));
        window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: topicId, scroll_post_id: 0 } }));
        setTimeout(knownHardClose, 0);
        return;
      }
      const fid = parseInt(forumMaybe || '0', 10) || 0;
      if (fid) {
        window.dispatchEvent(new CustomEvent('iad:open_agora', { detail: { forum_id: fid } }));
        setTimeout(knownHardClose, 0);
        return;
      }

      // Fall back to detailed search view.
      goToDiscussSearch(String((getEls().input && getEls().input.value) || '').trim());
    }
  }

  document.addEventListener('pointerup', handleNav);
  document.addEventListener('touchend', handleNav);
  document.addEventListener('click', handleNav);

  // If IA Discuss navigates without reload, ensure search overlay doesn't linger.
  ['iad:open_topic_page','iad:open_agora','iad:open_search'].forEach((evt) => {
    window.addEventListener(evt, () => {
      setTimeout(knownHardClose, 0);
    });
  });

})();

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

    // ------------------------------------------------------------
    // Discuss deep-link interception (prevent full page reload)
    // ------------------------------------------------------------
    // Some Discuss-rendered snippets contain real <a href="..."> links
    // (e.g. excerpt_html). If those navigate, you lose scroll position
    // because it becomes a full page reload.
    //
    // We intercept same-origin links that include ?iad_topic= and route them
    // through the Discuss router event instead.
    document.addEventListener("click", function (e) {
      try {
        if (!e || e.defaultPrevented) return;
        if (e.button && e.button !== 0) return; // left click only
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const a = e.target && e.target.closest ? e.target.closest("a[href]") : null;
        if (!a) return;
        const target = (a.getAttribute("target") || "").toLowerCase();
        if (target === "_blank") return;

        const href = a.getAttribute("href") || "";
        if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:")) return;

        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin) return;

        const tid = parseInt(url.searchParams.get("iad_topic") || "0", 10) || 0;
        if (!tid) return;

        // Only intercept when the click is within the Discuss panel OR we're already on Discuss.
        const discussPanel = qs('.ia-panel[data-panel="discuss"]', shell);
        const insideDiscuss = discussPanel ? discussPanel.contains(a) : false;
        const currentTab = getUrlParam("tab") || "";
        if (!insideDiscuss && String(currentTab).toLowerCase() !== "discuss") return;

        const postId = parseInt(url.searchParams.get("iad_post") || "0", 10) || 0;

        e.preventDefault();
        e.stopPropagation();

        // Ensure Discuss is visible before dispatching.
        setActiveTab(shell, "discuss");
        setUrlParam("tab", "discuss");

        window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
          detail: {
            topic_id: tid,
            scroll_post_id: postId || 0
          }
        }));
      } catch (err) {
        // fail open (allow normal navigation)
      }
    }, true);

    // If redirected back after login/register with intent, run it now
    const intent = getUrlParam("ia_nav");
    if (intent) {
      runNavIntent(shell, intent);
    }
  });

})();