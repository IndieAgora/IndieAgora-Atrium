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

  function syncSearchTabVisibility() {
    const R = root();
    if (!R) return;
    const btn = NS.util.qs('[data-ia-stream-search-tab]', R);
    if (!btn) return;
    const hasSearch = !!String((NS.state && NS.state.query) || '').trim();
    if (hasSearch) btn.removeAttribute('hidden');
    else btn.setAttribute('hidden', 'hidden');
  }

  function streamOwnsUrl() {
    try {
      const atriumShell = document.querySelector('#ia-atrium-shell');
      const active = atriumShell ? String(atriumShell.getAttribute('data-active-tab') || '').trim().toLowerCase() : '';
      if (active) return active === 'stream';
    } catch (e) {}
    try {
      const u = new URL(window.location.href);
      const tab = String(u.searchParams.get('tab') || '').trim().toLowerCase();
      if (tab) return tab === 'stream';
    } catch (e2) {}
    return false;
  }

  function setTab(name) {
    const R = root();
    if (!R) return;

    syncSearchTabVisibility();

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

    if (name === 'search' && !String((NS.state && NS.state.query) || '').trim()) name = 'browse';

    NS.state = NS.state || {};
    if (name === 'discover') {
      NS.state.channelHandle = '';
      NS.state.channelName = '';
      if (NS.state.route) NS.state.route.subscriptions = false;
    } else if (name === 'subscriptions') {
      NS.state.channelHandle = '';
      NS.state.channelName = '';
      if (NS.state.route) NS.state.route.subscriptions = true;
    } else if (name === 'browse') {
      if (NS.state.route) NS.state.route.subscriptions = false;
    }

    NS.state.activeTab = name;
    NS.store.set("tab", name);

    try {
      if (streamOwnsUrl()) {
        const u = new URL(window.location.href);
        u.searchParams.set('tab', 'stream');
        if (name === 'subscriptions') u.searchParams.set('stream_subscriptions', '1');
        else u.searchParams.delete('stream_subscriptions');
        if (name !== 'browse') {
          u.searchParams.delete('stream_channel');
          u.searchParams.delete('stream_channel_name');
        }
        if (name !== 'search') {
          u.searchParams.delete('stream_view');
          u.searchParams.delete('stream_q');
          u.searchParams.delete('stream_scope');
          u.searchParams.delete('stream_sort');
        }
        window.history.replaceState({}, '', u.toString());
      }
    } catch (e) {}

    NS.util.dispatch("ia:stream:tab", { tab: name });
  }

  function bindTabs() {
    const R = root();
    if (!R) return;

    NS.util.qsa(".ia-stream-tab", R).forEach((btn) => {
      NS.util.on(btn, "click", function () {
        const tab = btn.getAttribute("data-tab") || "discover";
        setTab(tab);
      });
    });
  }

  function readConnectStyle() {
    try {
      const shell = document.querySelector('#ia-atrium-shell');
      if (shell) {
        const v = String(shell.getAttribute('data-iac-style') || '').trim().toLowerCase();
        if (v) return v;
      }
    } catch (e) {}
    try {
      const hv = String(document.documentElement.getAttribute('data-iac-style') || '').trim().toLowerCase();
      if (hv) return hv;
    } catch (e2) {}
    try {
      const bv = String(document.body && document.body.getAttribute('data-iac-style') || '').trim().toLowerCase();
      if (bv) return bv;
    } catch (e3) {}
    return '';
  }

  function syncThemeBridge() {
    const R = root();
    if (!R) return;
    const next = readConnectStyle();
    const allowed = ['black','calm','dawn','earth','flame','leaf','night','sun','twilight','water'];
    if (allowed.indexOf(next) !== -1) {
      R.setAttribute('data-ia-stream-theme', next);
      R.classList.toggle('ia-stream-theme-black', next === 'black');
      return;
    }
    R.removeAttribute('data-ia-stream-theme');
    R.classList.remove('ia-stream-theme-black');
  }

  function bindThemeBridge() {
    syncThemeBridge();

    try {
      document.addEventListener('ia:connect-style-changed', syncThemeBridge);
    } catch (e) {}

    try {
      const mo = new MutationObserver(syncThemeBridge);
      const html = document.documentElement;
      const body = document.body;
      const shell = document.querySelector('#ia-atrium-shell');
      if (html) mo.observe(html, { attributes: true, attributeFilter: ['data-iac-style'] });
      if (body) mo.observe(body, { attributes: true, attributeFilter: ['data-iac-style'] });
      if (shell) mo.observe(shell, { attributes: true, attributeFilter: ['data-iac-style'] });
    } catch (e2) {}
  }


  NS.ui.shell.boot = function () {
    const R = root();
    if (!R) return;

    bindTabs();
    bindThemeBridge();
    syncSearchTabVisibility();
    setTab(NS.state.activeTab || "discover");
  };

  NS.ui.shell.setTab = setTab;
  NS.ui.shell.syncSearchTabVisibility = syncSearchTabVisibility;
})();
