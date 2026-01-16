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
      window.IA_STREAM.ui.upload &&
      typeof window.IA_STREAM.ui.upload.boot === "function"
    );
  }

  function boot() {
    if (NS.state.booted) return;
    const shell = NS.util.qs("#ia-stream-shell");
    if (!shell) return;

    NS.state.booted = true;

    // init tabs + restore state
    NS.ui.shell.boot();

    // upload modal binder (only shows button for logged-in users)
    NS.ui.upload.boot();

    // allow upload success to trigger refresh
    NS.util.on(window, "ia:stream:refresh", function(){
      const t2 = NS.state.activeTab || "feed";
      if (t2 === "channels") NS.ui.channels.load();
      else NS.ui.feed.load();
    });

    // initial load
    const t = NS.state.activeTab || "feed";
    if (t === "channels") NS.ui.channels.load();
    else NS.ui.feed.load();
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
