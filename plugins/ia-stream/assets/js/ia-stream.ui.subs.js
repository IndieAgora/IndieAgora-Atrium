/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.subs.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.subs = NS.ui.subs || {};

  function mount() {
    const R = NS.util.qs("#ia-stream-shell");
    if (!R) return null;
    return NS.util.qs('[data-panel="subs"] .ia-stream-subs', R);
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

  function creatorPill(c) {
    const name = c && (c.display_name || c.name) ? (c.display_name || c.name) : "Creator";
    const avatar = c && c.avatar ? c.avatar : "";
    const handle = c && c.handle ? c.handle : "";
    return (
      '<button type="button" class="ia-stream-creator-pill" data-handle="' + esc(handle) + '">' +
        '<span class="ia-stream-creator-ava" style="' + (avatar ? 'background-image:url(' + esc(avatar) + ');background-size:cover;background-position:center;' : '') + '"></span>' +
        '<span class="ia-stream-creator-name">' + esc(name) + '</span>' +
      '</button>'
    );
  }

  function miniCard(v) {
    const id = v && v.id ? String(v.id) : "";
    const title = v && v.title ? v.title : "";
    const thumb = v && v.thumbnail ? v.thumbnail : "";
    const ch = v && v.channel ? v.channel : {};
    const chName = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : "";
    const counts = v && v.counts ? v.counts : {};
    const likes = counts.likes || 0;
    const dislikes = counts.dislikes || 0;
    const comments = counts.comments || 0;

    return (
      '<article class="ia-stream-card ia-stream-card-mini" data-video-id="' + esc(id) + '">' +
        '<div class="ia-stream-card-body">' +
          (thumb ? '<img src="' + esc(thumb) + '" alt="' + esc(title) + '" />' : '<div class="ia-stream-player-overlay">No thumbnail</div>') +
        '</div>' +
        '<div class="ia-stream-card-header">' +
          '<div class="ia-stream-meta">' +
            '<div class="ia-stream-title">' + esc(title) + '</div>' +
            '<div class="ia-stream-sub">' + esc(chName) + '</div>' +
          '</div>' +
          '<button type="button" class="ia-stream-open" data-open-video="' + esc(id) + '">Open</button>' +
        '</div>' +
        '<div class="ia-stream-card-footer">' +
          '<div class="ia-stream-actions" data-video-id="' + esc(id) + '">' +
            '<button type="button" class="ia-stream-act ia-stream-act-vote" data-act="vote" data-vote="like" title="Upvote"><span class="ia-ico">⬆</span><span class="ia-count">' + esc(String(likes)) + '</span></button>' +
            '<button type="button" class="ia-stream-act ia-stream-act-vote" data-act="vote" data-vote="dislike" title="Downvote"><span class="ia-ico">⬇</span><span class="ia-count">' + esc(String(dislikes)) + '</span></button>' +
            '<button type="button" class="ia-stream-act ia-stream-act-reply" data-act="open" title="Open"><span class="ia-ico">💬</span><span class="ia-count">' + esc(String(comments)) + '</span></button>' +
            '<span class="ia-stream-act-status"></span>' +
          '</div>' +
        '</div>' +
      '</article>'
    );
  }

  function bumpCard(card) {
    if (!card) return;
    const parent = card.parentElement;
    if (!parent) return;
    parent.insertBefore(card, parent.firstChild);
  }

  function bind(M) {
    if (!M || M.__iaSubsBound) return;
    M.__iaSubsBound = true;

    NS.util.on(M, "click", async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      const openBtn = (t.closest ? t.closest("[data-open-video]") : null);
      if (openBtn) {
        const id = openBtn.getAttribute("data-open-video") || "";
        if (id && NS.ui.video && NS.ui.video.open) NS.ui.video.open(id);
        ev.preventDefault(); ev.stopPropagation();
        return;
      }

      const actBtn = (t.closest ? t.closest(".ia-stream-act") : null);
      if (!actBtn) return;

      const actions = actBtn.closest(".ia-stream-actions");
      const card = actBtn.closest(".ia-stream-card");
      const videoId = actions ? (actions.getAttribute("data-video-id") || "") : "";
      const status = actions ? actions.querySelector(".ia-stream-act-status") : null;
      const act = actBtn.getAttribute("data-act") || "";

      function setStatus(msg){ if (status) status.textContent = msg || ""; }

      if (act === "open") {
        if (videoId && NS.ui.video && NS.ui.video.open) NS.ui.video.open(videoId);
        ev.preventDefault(); ev.stopPropagation();
        return;
      }

      if (act === "vote") {
        if (!videoId) { setStatus("Missing video."); return; }
        const vote = actBtn.getAttribute("data-vote") || "like";
        setStatus("Working…");
        const r = await NS.api.rateVideo(videoId, vote);
        if (r && r.ok) {
          setStatus(vote === "like" ? "Upvoted." : "Downvoted.");
          const cEl = actBtn.querySelector(".ia-count");
          if (cEl) {
            const cur = parseInt(String(cEl.textContent||"0").replace(/[^\d]/g,''),10);
            if (isFinite(cur)) cEl.textContent = String(cur + 1);
          }
          bumpCard(card);
        } else {
          setStatus((r && (r.message || r.error)) || "Vote failed.");
        }
        ev.preventDefault(); ev.stopPropagation();
      }
    });
  }

  NS.ui.subs.load = async function () {
    renderPlaceholder("Loading subscriptions…");

    const res = await NS.api.fetchMySubs({ page: 1, per_page: 24, sort: "-publishedAt" });

    if (!res) { renderPlaceholder("No response (network)."); return; }
    if (res.ok === false) { renderPlaceholder(res.error || "Subscriptions error."); return; }

    const channels = Array.isArray(res.channels) ? res.channels : [];
    const videos = Array.isArray(res.videos) ? res.videos : [];

    const M = mount();
    if (!M) return;

    M.innerHTML =
      '<div class="ia-stream-subs-carousel">' +
        (channels.length ? channels.map(creatorPill).join("") : '<div class="ia-stream-placeholder">No subscriptions yet.</div>') +
      '</div>' +
      '<div class="ia-stream-subs-feed">' +
        (videos.length ? videos.map(miniCard).join("") : '<div class="ia-stream-placeholder">No videos from subscriptions.</div>') +
      '</div>';

    bind(M);
  };

  NS.util.on(window, "ia:stream:tab", function (ev) {
    const tab = ev && ev.detail ? ev.detail.tab : "";
    if (tab === "subs") NS.ui.subs.load();
  });
})();
