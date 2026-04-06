/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.boot.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  function depsReady() {
    return (
      window.IA_STREAM &&
      window.IA_STREAM.util &&
      window.IA_STREAM.api &&
      window.IA_STREAM.ui &&
      window.IA_STREAM.ui.shell &&
      window.IA_STREAM.ui.feed &&
      typeof window.IA_STREAM.ui.feed.load === "function" &&
      window.IA_STREAM.ui.channels &&
      typeof window.IA_STREAM.ui.channels.load === "function" &&
      window.IA_STREAM.ui.video &&
      typeof window.IA_STREAM.ui.video.open === "function"
    );
  }

  function openFromUrlIfPresent() {
    try {
      const u = new URL(window.location.href);
      const tab = u.searchParams.get('tab') || '';
      const vid = u.searchParams.get('video') || u.searchParams.get('v') || '';
      if (tab !== 'stream' || !vid) return;

      const focus = u.searchParams.get('focus') || '';
      const highlightCommentId = u.searchParams.get('stream_comment') || u.searchParams.get('stream_reply') || '';
      const opts = {};
      if (focus) opts.focus = focus;
      if (highlightCommentId) opts.highlightCommentId = String(highlightCommentId);

      setTimeout(function () {
        try { NS.ui.video.open(String(vid), opts); } catch (e) {}
      }, 50);
    } catch (e) {}
  }

  function boot() {
    if (NS.state.booted) return;
    const shell = NS.util.qs("#ia-stream-shell");
    if (!shell) return;

    NS.state.booted = true;

    NS.ui.shell.boot();

    const t = NS.state.activeTab || "discover";
    if (t === "browse" || t === "search" || t === "subscriptions") {
      Promise.resolve(NS.ui.feed.load(t)).then(openFromUrlIfPresent);
    } else {
      Promise.resolve(NS.ui.feed.load('discover')).then(openFromUrlIfPresent);
    }

    try {
      window.addEventListener('ia_atrium:tabChanged', function (e) {
        const tab = e && e.detail ? String(e.detail.tab || '') : '';
        if (tab !== 'stream') return;
        const routeSearch = !!(NS.state && NS.state.route && NS.state.route.search);
        const hasVideo = !!(NS.state.route && NS.state.route.video);
        const hasChannel = !!String(NS.state.channelHandle || '').trim();
        const subs = !!(NS.state.route && NS.state.route.subscriptions);
        if (routeSearch || hasVideo || hasChannel || subs) {
          if (NS.ui.shell && typeof NS.ui.shell.setTab === 'function') NS.ui.shell.setTab(routeSearch ? 'search' : (subs ? 'subscriptions' : 'browse'));
          return;
        }
        if (NS.ui.shell && typeof NS.ui.shell.setTab === 'function') NS.ui.shell.setTab('discover');
      });
    } catch (e) {}
  }

  function start(retries) {
    retries = (typeof retries === "number") ? retries : 40;

    if (!depsReady()) {
      if (retries <= 0) return;
      setTimeout(function () { start(retries - 1); }, 50);
      return;
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
      boot();
    }
  }

  start();
})();
