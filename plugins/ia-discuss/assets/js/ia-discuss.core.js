(function () {
  "use strict";
  const D = document;

  function qs(sel, root) { return (root || D).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || D).querySelectorAll(sel)); }

  function esc(s) {
    return String(s || "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function timeAgo(ts) {
    ts = parseInt(ts || "0", 10);
    if (!ts) return "";
    const diff = Math.max(0, Math.floor(Date.now() / 1000) - ts);
    if (diff < 60) return `${diff}s`;
    if (diff < 3600) return `${Math.floor(diff/60)}m`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h`;
    return `${Math.floor(diff/86400)}d`;
  }

  window.IA_DISCUSS_CORE = { qs, qsa, esc, timeAgo };
})();
