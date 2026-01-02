  "use strict";
  var CORE = window.IA_DISCUSS_CORE || {};
  var esc = CORE.esc || function (s) {
    return String(s || "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  };
  var timeAgo = CORE.timeAgo || function () { return ""; };

  var API = window.IA_DISCUSS_API;
  var STATE = window.IA_DISCUSS_STATE;

  function currentUserId() {
    // If your localized IA_DISCUSS includes userId later, we’ll pick it up.
    // Otherwise edit buttons just won’t show.
    try {
      var v = (window.IA_DISCUSS && (IA_DISCUSS.userId || IA_DISCUSS.user_id || IA_DISCUSS.wpUserId)) || 0;
      return parseInt(v, 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  function makeTopicUrl(topicId) {
    try {
      var u = new URL(window.location.href);
      u.searchParams.set("iad_topic", String(topicId));
      return u.toString();
    } catch (e) {
      return window.location.href;
    }
  }

  async function copyToClipboard(text) {
    var t = String(text || "");
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(t);
        return true;
      }
    } catch (e) {}
    try {
      var ta = document.createElement("textarea");
      ta.value = t;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.top = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
      return true;
    } catch (e) {}
    return false;
  }

  function openConnectProfile(payload) {
    var p = payload || {};
    var username = (p.username || "").trim();
    var user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username,
        user_id,
        ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try {
      window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } }));
    } catch (e) {}

    var tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // -----------------------------
  // Icons (inline SVG)
  // -----------------------------
  function ico(name) {
    var common = `width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"`;
    if (name === "reply") return `<svg ${common}><path d="M9 17l-4-4 4-4"/><path d="M20 19v-2a4 4 0 0 0-4-4H5"/></svg>`;
    if (name === "link")  return `<svg ${common}><path d="M10 13a5 5 0 0 1 0-7l1-1a5 5 0 0 1 7 7l-1 1"/><path d="M14 11a5 5 0 0 1 0 7l-1 1a5 5 0 0 1-7-7l1-1"/></svg>`;
    if (name === "share") return `<svg ${common}><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v13"/></svg>`;
    if (name === "edit")  return `<svg ${common}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>`;
    if (name === "dots")  return `<svg ${common}><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>`;
    return "";
  }

;
