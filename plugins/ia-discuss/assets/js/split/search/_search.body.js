  "use strict";

  const CORE = window.IA_DISCUSS_CORE;
  const API  = window.IA_DISCUSS_API;

  const qs = CORE.qs;
  const esc = CORE.esc;
  const timeAgo = CORE.timeAgo;

  function debounce(fn, ms) {
    let t = 0;
    return function () {
      const args = arguments;
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), ms);
    };
  }

  // ---------------------------------------------
  // Strip noisy markup (escaped HTML + phpBB BBCode)
  // ---------------------------------------------
  function stripMarkup(input) {
    let s = String(input || "");

    // decode a few common entities first so we can strip tags reliably
    s = s
      .replace(/&lt;/g, "<")
      .replace(/&gt;/g, ">")
      .replace(/&amp;/g, "&")
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'");

    // remove HTML tags
    s = s.replace(/<[^>]*>/g, " ");

    // remove common BBCode tags: [b], [i], [quote], [url=...], etc.
    // (also remove nested [tag=...] variants)
    s = s.replace(/\[\/?[a-z0-9_*]+(?:=[^\]]+)?\]/gi, " ");

    // remove phpBB attachment / media leftovers
    s = s.replace(/\[attachment[^\]]*\][\s\S]*?\[\/attachment\]/gi, " ");
    s = s.replace(/\[img\][\s\S]*?\[\/img\]/gi, " ");
    s = s.replace(/\[url[^\]]*\]([\s\S]*?)\[\/url\]/gi, "$1");

    // collapse whitespace
    s = s.replace(/\s+/g, " ").trim();

    // keep snippets compact
    if (s.length > 180) s = s.slice(0, 177) + "...";

    return s;
  }

  function avatarHTML(username, avatarUrl) {
    const u = String(username || "").trim();
    const initial = u ? u[0].toUpperCase() : "?";
    if (avatarUrl) {
      return `<img class="iad-av" src="${esc(avatarUrl)}" alt="" />`;
    }
    return `<div class="iad-av iad-av-fallback">${esc(initial)}</div>`;
  }

  // ---------------------------------------------
  // Reddit-ish inline SVG icons (small, clean)
  // ---------------------------------------------
  function iconSVG(type) {
    // Using currentColor so CSS controls the look.
    if (type === "topic") {
      return `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M7 7h10a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 2v-2H7a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm0 2v9h10V9H7Zm2 2h6v2H9v-2Zm0 3h4v2H9v-2Z"/>
        </svg>`;
    }
    if (type === "reply") {
      return `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M10 9V5L3 12l7 7v-4h4c4 0 7 2 7 6v-2c0-6-3-10-11-10h-1Z"/>
        </svg>`;
    }
    if (type === "agora") {
      return `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M12 2a8 8 0 0 1 8 8c0 3.6-2.4 6.7-5.7 7.7L15 22l-4.2-3.2A8 8 0 1 1 12 2Zm0 2a6 6 0 1 0 0 12 6.2 6.2 0 0 0 1.7-.25l.7-.2L14.8 18l.2 1.3 1.9-1.5.3-.2.4-.1A6 6 0 0 0 18 10a6 6 0 0 0-6-6Z"/>
        </svg>`;
    }
    // user
    return `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z"/>
      </svg>`;
  }

  // -------------------------------------------------
  // Connect open helper (MUST match feed behaviour)
  // -------------------------------------------------
  function openConnectProfile(payload) {
    const p = payload || {};
    const username = String(p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

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

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // ---------------------------
  // Suggestions dropdown
  // ---------------------------
  // NOTE: Discuss frequently renders inside other tab shells (Connect profile, Messages fullscreen, etc)
  // where ancestor containers can have overflow:hidden; an absolutely-positioned dropdown will be clipped.
  // To match the previous behaviour (and Connect), suggestions are rendered in a fixed-position "portal"
  // attached to <body>, and positioned under the search input.
