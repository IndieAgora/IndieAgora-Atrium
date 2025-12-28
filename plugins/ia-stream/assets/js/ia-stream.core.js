/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.core.js */
(function () {
  "use strict";

  // Global namespace (mirror Discuss discipline)
  if (!window.IA_STREAM) window.IA_STREAM = {};

  const NS = window.IA_STREAM;

  NS.VERSION = NS.VERSION || "0.1.0";

  NS.util = NS.util || {};

  NS.util.qs = function (sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  };

  NS.util.qsa = function (sel, root) {
    try { return Array.from((root || document).querySelectorAll(sel)); } catch (e) { return []; }
  };

  NS.util.on = function (el, ev, fn, opts) {
    if (!el) return;
    try { el.addEventListener(ev, fn, opts || false); } catch (e) {}
  };

  NS.util.dispatch = function (name, detail) {
    try {
      window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    } catch (e) {}
  };

  NS.util.isEl = function (x) {
    return x && typeof x === "object" && x.nodeType === 1;
  };

  NS.util.safeJson = function (txt, fallback) {
    try { return JSON.parse(txt); } catch (e) { return fallback; }
  };

  // Basic state bucket
  NS.state = NS.state || {
    activeTab: "feed",
    booted: false
  };

  // Small logger (optional; avoid noise in production)
  NS.log = function () {
    // Uncomment to debug
    // console.log.apply(console, ["[IA_STREAM]"].concat([].slice.call(arguments)));
  };
})();
