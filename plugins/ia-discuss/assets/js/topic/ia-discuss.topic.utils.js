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

  // -------------------------------------------------
  // Simple list modal (used for attachments in topic)
  // Reuses the same styling as the feed links modal.
  // -------------------------------------------------
  function ensureListModal() {
    let m = document.querySelector("[data-iad-linksmodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-linksmodal" data-iad-linksmodal hidden>
        <div class="iad-linksmodal-backdrop" data-iad-linksmodal-close></div>
        <div class="iad-linksmodal-sheet" role="dialog" aria-modal="true" aria-label="List">
          <div class="iad-linksmodal-top">
            <div class="iad-linksmodal-title" data-iad-linksmodal-title>Items</div>
            <button class="iad-x" type="button" data-iad-linksmodal-close aria-label="Close">×</button>
          </div>
          <div class="iad-linksmodal-body" data-iad-linksmodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-linksmodal]");

    m.querySelectorAll("[data-iad-linksmodal-close]").forEach((x) => {
      x.addEventListener("click", () => m.setAttribute("hidden", ""));
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        m.setAttribute("hidden", "");
      }
    });

    return m;
  }

  function openListModal(titleText, items) {
    const m = ensureListModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    title.textContent = String(titleText || "Items");

    const safe = Array.isArray(items) ? items : [];
    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">Nothing to show.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((it) => {
            const u = String(it && it.url ? it.url : "");
            const label = String(it && (it.label || it.filename) ? (it.label || it.filename) : u);
            const shown = label.length > 80 ? (label.slice(0, 80) + "…") : label;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(shown || u)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
  }

  window.IA_DISCUSS_TOPIC_UTILS = { qs, esc, timeAgo, openListModal };
})();
