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

      // optional focus (e.g. focus=comments)
      const focus = u.searchParams.get('focus') || '';
      const opts = focus ? { focus: focus } : {};

      // Delay slightly so the shell is rendered before the modal.
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

    // init tabs + restore state
    NS.ui.shell.boot();

    // initial load
    const t = NS.state.activeTab || "feed";
    if (t === "channels") {
      Promise.resolve(NS.ui.channels.load()).then(openFromUrlIfPresent);
    } else {
      Promise.resolve(NS.ui.feed.load()).then(openFromUrlIfPresent);
    }
  }

  function start(retries) {
    retries = (typeof retries === "number") ? retries : 40; // ~2s at 50ms

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
