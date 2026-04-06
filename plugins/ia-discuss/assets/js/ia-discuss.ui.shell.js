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

    let pressTimer = 0;
    let pressActive = false;
    let pressMoved = false;
    let pressSrc = '';
    let pressX = 0;
    let pressY = 0;

    function clearPress() {
      pressActive = false;
      pressMoved = false;
      pressSrc = '';
      pressX = 0;
      pressY = 0;
      if (pressTimer) {
        try { clearTimeout(pressTimer); } catch (e) {}
        pressTimer = 0;
      }
    }

    function dist2(x1,y1,x2,y2){ const dx=x2-x1, dy=y2-y1; return dx*dx+dy*dy; }

    D.addEventListener('pointerdown', (e) => {
      if (!e || e.pointerType !== 'touch') return;
      const t = e.target;
      const img = t && t.closest ? t.closest('img.iad-att-img') : null;
      if (!img) return;

      lastTouchAt = Date.now();

      pressActive = true;
      pressMoved = false;
      pressSrc = img.getAttribute('src') || '';
      pressX = (typeof e.clientX === 'number') ? e.clientX : 0;
      pressY = (typeof e.clientY === 'number') ? e.clientY : 0;

      pressTimer = setTimeout(() => {
        if (!pressActive || pressMoved || !pressSrc) return;
        tryOpenFromEvent(e);
      }, 280);
    }, true);

    D.addEventListener('pointermove', (e) => {
      if (!pressActive) return;
      if (!e || e.pointerType !== 'touch') return;
      const x = (typeof e.clientX === 'number') ? e.clientX : pressX;
      const y = (typeof e.clientY === 'number') ? e.clientY : pressY;
      if (dist2(pressX, pressY, x, y) > (12*12)) {
        pressMoved = true;
        clearPress();
      }
    }, true);

    D.addEventListener('pointerup', (e) => {
      if (e && e.pointerType === 'touch') {
        lastTouchAt = Date.now();
        clearPress();
      }
    }, true);

    D.addEventListener('pointercancel', (e) => {
      if (e && e.pointerType === 'touch') {
        lastTouchAt = Date.now();
        clearPress();
      }
    }, true);

    D.addEventListener('click', (e) => {
      if (lastTouchAt && (Date.now() - lastTouchAt) < 650) return;
      tryOpenFromEvent(e);
    }, true);
  }

  function getDiscussRoot() {
    return qs('[data-ia-discuss-root]');
  }

  function getSidebar(root) {
    return root ? root.querySelector('[data-iad-sidebar]') : null;
  }

  function getTopbarToggle() {
    return document.querySelector('[data-iad-topbar-menu-toggle]');
  }

  function setSidebarOpen(root, open) {
    if (!root) return;
    const sidebar = getSidebar(root);
    const backdrop = root.querySelector('[data-iad-sidebar-backdrop]');
    if (!sidebar || !backdrop) return;

    const isOpen = !!open;
    root.classList.toggle('is-sidebar-open', isOpen);
    sidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    backdrop.hidden = !isOpen;

    const btn = getTopbarToggle();
    if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function closeSidebar(root) {
    setSidebarOpen(root || getDiscussRoot(), false);
  }

  function installTopbarToggle(root) {
    const atriumShell = document.querySelector('#ia-atrium-shell');
    const brand = atriumShell ? atriumShell.querySelector('.ia-atrium-brand') : null;
    if (!brand) return;

    let btn = getTopbarToggle();
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'iad-discuss-topbar-toggle';
      btn.setAttribute('data-iad-topbar-menu-toggle', '');
      btn.setAttribute('aria-label', 'Discuss menu');
      btn.setAttribute('aria-expanded', 'false');
      btn.innerHTML = '<span class="iad-discuss-topbar-toggle-ico" aria-hidden="true">☰</span>';
      if (brand.firstChild) brand.insertBefore(btn, brand.firstChild);
      else brand.appendChild(btn);

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const currentRoot = getDiscussRoot();
        if (!currentRoot) return;
        setSidebarOpen(currentRoot, !currentRoot.classList.contains('is-sidebar-open'));
      });
    }

    function syncVisibility() {
      const shell = document.querySelector('#ia-atrium-shell');
      const activeTab = shell ? String(shell.getAttribute('data-active-tab') || '') : '';
      const show = activeTab === 'discuss';
      btn.hidden = !show;
      if (!show) closeSidebar(root);
    }

    syncVisibility();
    if (!btn.__iadTabChangedBound) {
      btn.__iadTabChangedBound = true;
      window.addEventListener('ia_atrium:tabChanged', syncVisibility);
    }
  }

  function bindSidebar(root) {
    if (!root || root.__iadSidebarBound) return;
    root.__iadSidebarBound = true;

    root.addEventListener('click', function (e) {
      const t = e.target;
      if (!t || !t.closest) return;

      if (t.closest('[data-iad-sidebar-close]')) {
        e.preventDefault();
        closeSidebar(root);
        return;
      }

      const actionBtn = t.closest('.iad-sidebar .iad-tab, .iad-sidebar [data-iad-random-topic], .iad-sidebar [data-iad-create-agora], .iad-sidebar [data-iad-moderation]');
      if (actionBtn) {
        window.setTimeout(function () { closeSidebar(root); }, 0);
      }
    }, true);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSidebar(root);
    });

    window.addEventListener('ia_atrium:tabChanged', function (ev) {
      const tab = ev && ev.detail ? String(ev.detail.tab || '') : '';
      if (tab !== 'discuss') closeSidebar(root);
    });
  }

  function getThemeOptions() {
    return [
      { value: 'dark', label: 'Dark', desc: 'Current Discuss dark styling.' },
      { value: 'light', label: 'Light', desc: 'Clean light styling for Discuss.' },
      { value: 'legacy', label: 'Blue', desc: 'Classic blue forum styling inspired by the default forum skins.' },
      { value: 'black', label: 'Black', desc: 'MyBB default black colour scheme.' },
      { value: 'calm', label: 'Calm', desc: 'MyBB muted green colour scheme.' },
      { value: 'dawn', label: 'Dawn', desc: 'MyBB orange dawn colour scheme.' },
      { value: 'earth', label: 'Earth', desc: 'MyBB brown earth colour scheme.' },
      { value: 'flame', label: 'Flame', desc: 'MyBB deep red colour scheme.' },
      { value: 'leaf', label: 'Leaf', desc: 'MyBB green leaf colour scheme.' },
      { value: 'night', label: 'Night', desc: 'MyBB deep blue colour scheme.' },
      { value: 'sun', label: 'Sun', desc: 'MyBB yellow sun colour scheme.' },
      { value: 'twilight', label: 'Twilight', desc: 'MyBB slate blue colour scheme.' },
      { value: 'water', label: 'Water', desc: 'MyBB teal water colour scheme.' }
    ];
  }

  function isClassicTheme(value) {
    const v = String(value || '').toLowerCase();
    return [
      'legacy', 'black', 'calm', 'dawn', 'earth', 'flame', 'leaf', 'night', 'sun', 'twilight', 'water'
    ].indexOf(v) !== -1;
  }

  function normaliseTheme(value) {
    const v = String(value || '').toLowerCase();
    return getThemeOptions().some((opt) => opt.value === v) ? v : 'dark';
  }

  function getConnectStyleValue() {
    try {
      const rootStyle = document.documentElement.getAttribute('data-iac-style');
      if (rootStyle) return String(rootStyle || '').toLowerCase();
    } catch (e) {}
    try {
      const bodyStyle = document.body ? document.body.getAttribute('data-iac-style') : '';
      if (bodyStyle) return String(bodyStyle || '').toLowerCase();
    } catch (e) {}
    return 'default';
  }

  function getThemeFromConnectStyle() {
    const v = getConnectStyleValue();
    return ['black','calm','dawn','earth','flame','leaf','night','sun','twilight','water'].includes(v) ? v : 'dark';
  }


  function syncClassicThemeVars(root, el) {
    if (!root || !el || !window.getComputedStyle) return;
    const vars = [
      '--iad-btn-border', '--iad-btn-text', '--iad-modal-top-a', '--iad-modal-top-b',
      '--iad-btn-top', '--iad-btn-bottom', '--iad-btn-hover-bottom', '--iad-pill-primary-border',
      '--iad-active-top', '--iad-active-bottom', '--iad-active-border', '--iad-active-text',
      '--iad-link', '--iad-suggest-hover', '--iad-muted', '--iad-text', '--iad-border'
    ];
    try {
      const cs = window.getComputedStyle(root);
      vars.forEach((name) => {
        const val = cs.getPropertyValue(name);
        if (val) el.style.setProperty(name, val.trim());
      });
    } catch (e) {}
  }

  function applyTheme(root, value) {
    if (!root) return 'dark';
    const next = normaliseTheme(value);
    root.setAttribute('data-iad-theme', next);
    root.classList.toggle('iad-theme-classic', isClassicTheme(next));
    try { localStorage.setItem('ia_discuss_theme', next); } catch (e) {}
    const btn = root.querySelector('[data-iad-theme-toggle]');
    if (btn) {
      const current = getThemeOptions().find((opt) => opt.value === next);
      btn.textContent = current ? current.label : 'Theme';
    }
    root.querySelectorAll('[data-iad-theme-pick]').forEach((choice) => {
      const on = choice.getAttribute('data-iad-theme-pick') === next;
      choice.classList.toggle('is-active', on);
      choice.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    const modal = document.querySelector('[data-iad-theme-modal]');
    if (modal) {
      modal.setAttribute('data-iad-theme', next);
      modal.classList.toggle('iad-theme-classic', isClassicTheme(next));
      syncClassicThemeVars(root, modal);
      modal.querySelectorAll('[data-iad-theme-choice]').forEach((choice) => {
        const on = choice.getAttribute('data-iad-theme-choice') === next;
        choice.classList.toggle('is-active', on);
        choice.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    const suggest = document.querySelector('.iad-suggest--portal');
    if (suggest) {
      suggest.setAttribute('data-iad-theme', next);
      suggest.classList.toggle('iad-theme-classic', isClassicTheme(next));
      syncClassicThemeVars(root, suggest);
    }
    return next;
  }

  function ensureThemeModal(root) {
    let modal = document.querySelector('[data-iad-theme-modal]');
    if (modal) {
      applyTheme(root, root.getAttribute('data-iad-theme') || 'dark');
      return modal;
    }

    modal = document.createElement('div');
    modal.className = 'iad-modal iad-theme-modal';
    modal.setAttribute('data-iad-theme-modal', '');
    modal.setAttribute('hidden', '');
    const optionsHtml = getThemeOptions().map((opt) => `
      <button type="button" class="iad-theme-choice" data-iad-theme-choice="${opt.value}" aria-pressed="false">
        <span class="iad-theme-choice-head">
          <span class="iad-theme-choice-title">${opt.label}</span>
          <span class="iad-theme-choice-check" aria-hidden="true">✓</span>
        </span>
        <span class="iad-theme-choice-desc">${opt.desc}</span>
      </button>
    `).join('');

    modal.innerHTML = `
      <div class="iad-modal-backdrop" data-iad-theme-close></div>
      <div class="iad-modal-sheet iad-theme-sheet" role="dialog" aria-modal="true" aria-label="Choose theme">
        <div class="iad-modal-top">
          <div class="iad-modal-title">Discuss theme</div>
          <button type="button" class="iad-x" data-iad-theme-close aria-label="Close">×</button>
        </div>
        <div class="iad-modal-body">
          <div class="iad-theme-copy">Choose how Discuss should look in this browser.</div>
          <div class="iad-theme-list">${optionsHtml}</div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    modal.addEventListener('click', function (e) {
      const t = e.target;
      if (!t || !t.closest) return;
      if (t.closest('[data-iad-theme-close]')) {
        e.preventDefault();
        modal.setAttribute('hidden', '');
        return;
      }
      const choice = t.closest('[data-iad-theme-choice]');
      if (choice) {
        e.preventDefault();
        applyTheme(getDiscussRoot(), choice.getAttribute('data-iad-theme-choice') || 'dark');
        modal.setAttribute('hidden', '');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
        modal.setAttribute('hidden', '');
      }
    });

    applyTheme(root, root.getAttribute('data-iad-theme') || 'dark');
    return modal;
  }

  function openThemeModal(root) {
    const modal = ensureThemeModal(root || getDiscussRoot());
    if (!modal) return;
    modal.removeAttribute('hidden');
    applyTheme(root || getDiscussRoot(), (root || getDiscussRoot()).getAttribute('data-iad-theme') || 'dark');
  }

  function normaliseLayoutMode(mode) {
    return String(mode || '').trim().toLowerCase() === 'agorabb' ? 'agorabb' : 'atrium';
  }

  function applyLayoutMode(root, mode) {
    if (!root) return 'atrium';
    const next = normaliseLayoutMode(mode);
    root.setAttribute('data-iad-layout', next);
    try { localStorage.setItem('ia_discuss_layout_mode', next); } catch (e) {}
    const btn = root.querySelector('[data-iad-layout-toggle]');
    if (btn) {
      const on = next === 'agorabb';
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.textContent = on ? 'AgoraBB: On' : 'AgoraBB Mode';
      btn.setAttribute('title', on ? 'Switch back to Atrium view' : 'Switch Discuss to AgoraBB mode');
    }
    return next;
  }

  function syncThemeFromConnectStyle(root) {
    if (!root) return 'dark';
    return applyTheme(root, getThemeFromConnectStyle());
  }

  function bindConnectStyleBridge(root) {
    if (!root || root.__iadConnectStyleBridgeBound) return;
    root.__iadConnectStyleBridgeBound = true;

    const sync = function () {
      syncThemeFromConnectStyle(root);
    };

    document.addEventListener('ia:connect-style-changed', function () {
      sync();
    });

    if (window.MutationObserver) {
      try {
        const observer = new MutationObserver(function () {
          sync();
        });
        if (document.documentElement) {
          observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-iac-style'] });
        }
        if (document.body) {
          observer.observe(document.body, { attributes: true, attributeFilter: ['data-iac-style'] });
        }
      } catch (e) {}
    }
  }

  function shell() {
    const root = qs('[data-ia-discuss-root]');
    if (!root) return null;

    root.innerHTML = `
      <div class="iad-shell">
        <div class="iad-sidebar-backdrop" data-iad-sidebar-backdrop data-iad-sidebar-close hidden></div>
        <aside class="iad-sidebar" data-iad-sidebar aria-hidden="true">
          <div class="iad-sidebar-head">
            <div class="iad-sidebar-title">Discuss</div>
            <button class="iad-sidebar-close" type="button" data-iad-sidebar-close aria-label="Close menu">✕</button>
          </div>
          <div class="iad-sidebar-search iad-search" data-iad-search-wrap>
            <input class="iad-input" data-iad-search placeholder="Search…" autocomplete="off" />
          </div>
          <div class="iad-sidebar-group" role="tablist" aria-label="Discuss sections">
            <button class="iad-tab" data-view="new" aria-selected="true">Topics</button>
            <button class="iad-tab" data-view="replies" aria-selected="false">Replies</button>
            <button class="iad-tab" data-view="noreplies" aria-selected="false">0 replies</button>
            <button class="iad-tab" data-view="agoras" aria-selected="false">Agoras</button>
          </div>
          <div class="iad-sidebar-group iad-sidebar-actions">
            <button class="iad-btn iad-tab-action" type="button" data-iad-random-topic>Random</button>
            <button class="iad-btn iad-tab-action" type="button" data-iad-create-agora hidden>Create Agora</button>
            <button class="iad-btn iad-tab-action" type="button" data-iad-moderation hidden>Moderation</button>
          </div>
          <div class="iad-sidebar-divider" aria-hidden="true"></div>
          <div class="iad-sidebar-group iad-sidebar-personal" role="tablist" aria-label="Your Discuss activity">
            <div class="iad-sidebar-subtitle">My</div>
            <button class="iad-tab" data-view="mytopics" aria-selected="false">My Topics</button>
            <button class="iad-tab" data-view="myreplies" aria-selected="false">My Replies</button>
            <button class="iad-tab" data-view="myhistory" aria-selected="false">My History</button>
          </div>
        </aside>
        <div class="iad-view" data-iad-view></div>
      </div>
    `;

    ensureImageViewer();

    try { localStorage.removeItem('ia_discuss_theme'); } catch (e) {}
    try { localStorage.removeItem('ia_discuss_layout_mode'); } catch (e) {}
    syncThemeFromConnectStyle(root);
    applyLayoutMode(root, 'atrium');

    bindConnectStyleBridge(root);
    bindSidebar(root);
    installTopbarToggle(root);
    closeSidebar(root);

    return root;
  }

  function setActiveTab(view, contextView) {
    const root = qs('[data-ia-discuss-root]');
    if (!root) return;

    const ctx = (contextView !== undefined && contextView !== null && String(contextView) !== '')
      ? String(contextView)
      : String(view || '');
    try { root.setAttribute('data-iad-current-view', ctx); } catch (e) {}

    root.querySelectorAll('.iad-tab').forEach((b) => {
      const on = b.getAttribute('data-view') === view;
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      b.classList.toggle('is-active', on);
    });

    const btn = root.querySelector('[data-iad-create-agora]');
    if (btn) {
      const loggedIn = root.getAttribute('data-logged-in') === '1';
      btn.hidden = !loggedIn;
    }

    const modBtn = root.querySelector('[data-iad-moderation]');
    if (modBtn) {
      const loggedIn = root.getAttribute('data-logged-in') === '1';
      const curView = String(root.getAttribute('data-iad-current-view') || '');
      const canHereAttr = root.getAttribute('data-iad-can-moderate-here');
      const canAnyAttr  = root.getAttribute('data-iad-can-moderate');
      const canHere     = (canHereAttr === '1') || (canAnyAttr === '1');
      const unknown     = (canHereAttr === null || canHereAttr === '');
      modBtn.hidden = !(loggedIn && curView === 'agora' && (unknown || canHere));
    }
  }

  window.IA_DISCUSS_UI_SHELL = {
    shell,
    setActiveTab,
    openMenu: function(){ const root = getDiscussRoot(); setSidebarOpen(root, true); },
    closeMenu: function(){ closeSidebar(getDiscussRoot()); },
    getLayoutMode: function(){ const root = getDiscussRoot(); return normaliseLayoutMode(root ? (root.getAttribute('data-iad-layout') || 'atrium') : 'atrium'); },
    applyLayoutMode: function(mode){ const root = getDiscussRoot(); return applyLayoutMode(root, mode); }
  };
})();
