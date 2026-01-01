(function () {
  "use strict";

  const CORE = window.IA_DISCUSS_CORE || {};

  function qs(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function esc(s) {
    if (CORE.esc) return CORE.esc(s);
    return String(s || "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function timeAgo(ts) {
    if (CORE.timeAgo) return CORE.timeAgo(ts);
    ts = parseInt(ts || "0", 10);
    if (!ts) return "";
    const diff = Math.max(0, Math.floor(Date.now() / 1000) - ts);
    if (diff < 60) return `${diff}s`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
  }

  window.IA_DISCUSS_TOPIC_UTILS = { qs, esc, timeAgo };
})();
