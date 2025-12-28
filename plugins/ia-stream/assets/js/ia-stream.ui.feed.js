/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.feed.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.feed = NS.ui.feed || {};

  function mount() {
    const R = NS.util.qs("#ia-stream-shell");
    if (!R) return null;
    return NS.util.qs('[data-panel="feed"] .ia-stream-feed', R);
  }

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function renderPlaceholder(msg) {
    const M = mount();
    if (!M) return;
    M.innerHTML = '<div class="ia-stream-placeholder">' + esc(msg || "Loading…") + "</div>";
  }

  function fmtNum(n) {
    n = parseInt(n || 0, 10);
    if (!isFinite(n) || n < 0) n = 0;
    if (n >= 1000000) return (Math.round(n / 100000) / 10) + "m";
    if (n >= 1000) return (Math.round(n / 100) / 10) + "k";
    return String(n);
  }

  function cardHtml(v) {
    const id = v && v.id ? String(v.id) : "";
    const title = v && v.title ? v.title : "";
    const excerpt = v && v.excerpt ? v.excerpt : "";
    const ago = v && v.published_ago ? v.published_ago : "";
    const url = v && v.url ? v.url : "";
    const embed = v && v.embed_url ? v.embed_url : "";
    const thumb = v && v.thumbnail ? v.thumbnail : "";
    const ch = (v && v.channel) ? v.channel : {};
    const chName = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : "";
    const chAvatar = ch && ch.avatar ? ch.avatar : "";
    const counts = (v && v.counts) ? v.counts : {};
    const views = fmtNum(counts.views || 0);
    const likes = fmtNum(counts.likes || 0);
    const comments = fmtNum(counts.comments || 0);

    // In-feed player stays (as requested)
    const player = embed
      ? '<div class="ia-stream-player"><iframe loading="lazy" src="' + esc(embed) + '" allow="fullscreen; autoplay; encrypted-media" allowfullscreen></iframe></div>'
      : (thumb
          ? '<div class="ia-stream-player"><img src="' + esc(thumb) + '" alt="' + esc(title) + '" style="width:100%;height:100%;object-fit:cover;display:block" /></div>'
          : '<div class="ia-stream-player"><div class="ia-stream-player-overlay">No embed/thumbnail</div></div>');

    // Add an "Open" button that opens the modal (rather than navigating away)
    const openBtn =
      '<button type="button" class="ia-stream-open" data-open-video="' + esc(id) + '"' +
      ' style="margin-left:auto;background:transparent;border:1px solid var(--ia-border);color:var(--ia-text);padding:6px 10px;border-radius:999px;cursor:pointer;font-size:0.85rem">' +
      'Open' +
      '</button>';

    return (
      '<article class="ia-stream-card" data-video-id="' + esc(id) + '">' +
        '<div class="ia-stream-card-header">' +
          '<div class="ia-stream-avatar" style="' + (chAvatar ? 'background-image:url(' + esc(chAvatar) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
          '<div class="ia-stream-meta">' +
            '<div class="ia-stream-title">' + esc(title) + '</div>' +
            '<div class="ia-stream-sub">' +
              (chName ? esc(chName) : 'Unknown channel') +
              (ago ? ' • ' + esc(ago) : '') +
              (url ? ' • <a href="' + esc(url) + '" target="_blank" rel="noopener">PeerTube</a>' : '') +
            '</div>' +
          '</div>' +
          openBtn +
        '</div>' +

        '<div class="ia-stream-card-body">' + player + '</div>' +

        (excerpt
          ? '<div style="padding:10px 12px;color:var(--ia-text);font-size:0.9rem;line-height:1.35">' + esc(excerpt) + '</div>'
          : '') +

        '<div class="ia-stream-card-footer">' +
          '<span>👁 ' + esc(views) + '</span>' +
          '<span>⬆ ' + esc(likes) + '</span>' +
          '<span>💬 ' + esc(comments) + '</span>' +
        '</div>' +
      '</article>'
    );
  }

  function bindOpenDelegation() {
    const M = mount();
    if (!M) return;

    // One handler for all cards
    NS.util.on(M, "click", function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      // Button click
      const btn = (t.closest ? t.closest("[data-open-video]") : null);
      if (!btn) return;

      const id = btn.getAttribute("data-open-video") || "";
      if (!id) return;

      ev.preventDefault();
      ev.stopPropagation();

      if (NS.ui.video && NS.ui.video.open) NS.ui.video.open(id);
    });
  }

  NS.ui.feed.load = async function () {
    renderPlaceholder("Loading video feed…");

    const res = await NS.api.fetchFeed({ page: 1, per_page: 10 });

    if (!res) {
      renderPlaceholder("No response (network).");
      return;
    }

    if (res.ok === false) {
      renderPlaceholder(res.error || "Feed error.");
      return;
    }

    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      const note = res.meta && res.meta.note ? res.meta.note : "";
      renderPlaceholder(note ? ("No videos. " + note) : "No videos returned.");
      return;
    }

    const M = mount();
    if (!M) return;

    M.innerHTML = items.map(cardHtml).join("");
    bindOpenDelegation();
  };

  NS.util.on(window, "ia:stream:tab", function (ev) {
    const tab = ev && ev.detail ? ev.detail.tab : "";
    if (tab === "feed") NS.ui.feed.load();
  });
})();
