(function () {
  "use strict";
  const { qs } = window.IA_DISCUSS_CORE;

  // -----------------------------
  // IA Discuss — Fullscreen image viewer
  // - Opens when clicking uploaded attachment previews (.iad-att-img)
  // - Closes on backdrop, X, or Escape
  // -----------------------------
  let _imgvBound = false;

  function ensureImageViewer() {
    if (_imgvBound) return;
    _imgvBound = true;

    const D = document;

    function lockScroll(lock) {
      try {
        if (lock) {
          D.documentElement.classList.add('iad-noscroll');
          D.body.classList.add('iad-noscroll');
          D.body.style.overflow = 'hidden';
        } else {
          D.documentElement.classList.remove('iad-noscroll');
          D.body.classList.remove('iad-noscroll');
          D.body.style.overflow = '';
        }
      } catch (e) {}
    }

    function getOrCreateViewer() {
      let el = D.querySelector('.iad-imgv');
      if (el) return el;

      el = D.createElement('div');
      el.className = 'iad-imgv';
      el.setAttribute('hidden', '');
      el.innerHTML = `
        <div class="iad-imgv-backdrop" data-iad-imgv-close></div>
        <div class="iad-imgv-inner" role="dialog" aria-modal="true" aria-label="Image viewer">
          <button type="button" class="iad-imgv-close" data-iad-imgv-close aria-label="Close">✕</button>
          <img class="iad-imgv-img" alt="" />
        </div>
      `;
      D.body.appendChild(el);

      el.addEventListener('click', (e) => {
        const t = e.target;
        if (t && t.closest && t.closest('[data-iad-imgv-close]')) {
          e.preventDefault();
          closeViewer();
        }
      });

      D.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          const open = !el.hasAttribute('hidden');
          if (open) {
            e.preventDefault();
            closeViewer();
          }
        }
      });

      return el;
    }

    function openViewer(src) {
      const el = getOrCreateViewer();
      const img = el.querySelector('.iad-imgv-img');
      if (!img) return;
      img.src = String(src || '');
      el.removeAttribute('hidden');
      lockScroll(true);
    }

    function closeViewer() {
      const el = document.querySelector('.iad-imgv');
      if (!el) return;
      const img = el.querySelector('.iad-imgv-img');
      if (img) img.src = '';
      el.setAttribute('hidden', '');
      lockScroll(false);
    }

    // Delegate open for attachment preview images.
    // On mobile, some environments do not reliably emit 'click' (or it arrives late),
    // so we listen to pointer/touch as well and suppress the follow-up click.
    let lastTouchAt = 0;

    function tryOpenFromEvent(e) {
      const t = e && e.target;
      const img = t && t.closest ? t.closest('img.iad-att-img') : null;
      if (!img) return false;

      const src = img.getAttribute('src') || '';
      if (!src) return false;

      if (e && e.preventDefault) e.preventDefault();
      if (e && e.stopPropagation) e.stopPropagation();
      openViewer(src);
      return true;
    }

    // Pointer-first (covers most mobile browsers)
    D.addEventListener('pointerup', (e) => {
      if (e && e.pointerType === 'touch') {
        lastTouchAt = Date.now();
        tryOpenFromEvent(e);
      }
    }, true);

    // Fallback for older mobile browsers
    D.addEventListener('touchend', (e) => {
      lastTouchAt = Date.now();
      tryOpenFromEvent(e);
    }, true);

    // Click fallback (desktop + non-touch)
    D.addEventListener('click', (e) => {
      // Ignore the synthetic click that often follows a touch event.
      if (lastTouchAt && (Date.now() - lastTouchAt) < 650) return;
      tryOpenFromEvent(e);
    }, true);
  }


  function shell() {
    const root = qs("[data-ia-discuss-root]");
    if (!root) return null;

    root.innerHTML = `
      <div class="iad-shell">
        <div class="iad-top">
          <button class="iad-tabs-toggle" type="button" data-iad-tabs-toggle aria-label="Toggle menu" aria-expanded="false">
            <span class="iad-tabs-ico" aria-hidden="true">☰</span>
            <span class="iad-tabs-text">Menu</span>
            <span class="iad-tabs-caret" aria-hidden="true">▾</span>
          </button>
          <div class="iad-tabs" role="tablist">
            <button class="iad-tab" data-view="new" aria-selected="true">New</button>
            <button class="iad-tab" data-view="unread" aria-selected="false">Unread</button>
            <button class="iad-tab" data-view="noreplies" aria-selected="false">0 replies</button>
            <button class="iad-tab" data-view="agoras" aria-selected="false">Agoras</button>
            <button class="iad-btn iad-tab-action" type="button" data-iad-random-topic>Random</button>
            <button class="iad-btn iad-tab-action" type="button" data-iad-create-agora hidden>Create Agora</button>
            <!-- Theme toggle behaves like a pill/tab for consistent layout -->
            <button class="iad-tab iad-tab-theme" type="button" data-iad-theme-toggle title="Toggle light/dark">Light</button>
          </div>

          <div class="iad-search" data-iad-search-wrap>
            <input class="iad-input" data-iad-search placeholder="Search…" autocomplete="off" />
          </div>
        </div>

        <div class="iad-view" data-iad-view></div>
      </div>
    `;

    // Ensure shared fullscreen image viewer is bound once
    ensureImageViewer();

    // Apply saved theme
    try {
      const saved = localStorage.getItem("ia_discuss_theme") || "";
      if (saved) root.setAttribute("data-iad-theme", saved);
    } catch (e) {}

    // Theme toggle
    const tBtn = root.querySelector("[data-iad-theme-toggle]");
    if (tBtn) {
      const sync = () => {
        const cur = root.getAttribute("data-iad-theme") || "dark";
        tBtn.textContent = (cur === "light") ? "Dark" : "Light";
      };
      sync();
      tBtn.addEventListener("click", () => {
        const cur = root.getAttribute("data-iad-theme") || "dark";
        const next = (cur === "light") ? "dark" : "light";
        root.setAttribute("data-iad-theme", next);
        try { localStorage.setItem("ia_discuss_theme", next); } catch (e) {}
        sync();
      });
    }

    // Small-screen tabs collapse/expand
    (function bindTabsToggle(){
      const top = root.querySelector('.iad-top');
      const toggle = root.querySelector('[data-iad-tabs-toggle]');
      const tabs = root.querySelector('.iad-tabs');
      if (!top || !toggle || !tabs) return;

      const key = 'ia_discuss_tabs_open';
      const setOpen = (open) => {
        top.classList.toggle('is-tabs-open', !!open);
        toggle.classList.toggle('is-open', !!open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        const caret = toggle.querySelector('.iad-tabs-caret');
        if (caret) caret.textContent = open ? '▴' : '▾';
        try { localStorage.setItem(key, open ? '1' : '0'); } catch (e) {}
      };

      let open = false;
      try { open = (localStorage.getItem(key) === '1'); } catch (e) {}
      setOpen(open);

      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        setOpen(!top.classList.contains('is-tabs-open'));
      });

      // When selecting a tab/action on small screens, collapse the row.
      tabs.addEventListener('click', (e) => {
        const btn = e.target && e.target.closest('button');
        if (!btn) return;
        if (btn === toggle) return;
        if (
          btn.classList.contains('iad-tab') ||
          btn.hasAttribute('data-iad-create-agora') ||
          btn.hasAttribute('data-iad-random-topic') ||
          btn.hasAttribute('data-iad-theme-toggle')
        ) {
          setOpen(false);
        }
      }, true);
    })();

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
