/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.state.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.store = NS.store || {};

  NS.store.get = function (key, fallback) {
    try {
      const v = window.localStorage.getItem("ia_stream_" + key);
      if (v === null || v === undefined) return fallback;
      return v;
    } catch (e) {
      return fallback;
    }
  };

  NS.store.set = function (key, val) {
    try { window.localStorage.setItem("ia_stream_" + key, String(val)); } catch (e) {}
  };

  NS.state.activeTab = NS.store.get("tab", NS.state.activeTab || "feed");
})();
