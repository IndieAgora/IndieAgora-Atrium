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

  function isLoggedIn() {
    const shell = NS.util.qs("#ia-stream-shell");
    return !!(shell && shell.getAttribute("data-logged-in") === "1");
  }

  function deriveChannelUri(channelUrl, channelName) {
    try {
      if (channelUrl) {
        const u = new URL(channelUrl);
        const host = u.host || "";
        const name = (channelName || "").replace(/^@/, "");
        if (host && name) return "@" + name + "@" + host;
      }
    } catch (e) {}
    return (channelName || "").replace(/^@/, "");
  }


function bumpCard(card) {
  if (!card) return;
  const parent = card.parentElement;
  if (!parent) return;
  parent.insertBefore(card, parent.firstChild);
}

function commentHtml(c) {
  const author = (c && c.author) ? c.author : {};
  const name = author && author.name ? author.name : "User";
  const avatar = author && author.avatar ? author.avatar : "";
  const time = c && c.created_ago ? c.created_ago : "";
  const text = c && c.text ? c.text : "";
  const replies = c && c.replies_count ? parseInt(c.replies_count, 10) : 0;

  return (
    '<div class="ia-stream-inline-comment">' +
      '<div class="ia-stream-inline-comment-head">' +
        '<div class="ia-stream-inline-comment-avatar" style="' + (avatar ? 'background-image:url(' + esc(avatar) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
        '<div class="ia-stream-inline-comment-author">' + esc(name) + '</div>' +
        (time ? '<div class="ia-stream-inline-comment-time">' + esc(time) + '</div>' : '') +
      '</div>' +
      '<div class="ia-stream-inline-comment-text">' + esc(text) + '</div>' +
      (replies > 0 ? '<div class="ia-stream-inline-comment-replies">Replies: ' + esc(String(replies)) + '</div>' : '') +
    '</div>'
  );
}

async function loadInlineComments(videoId, wrap) {
  if (!wrap) return;
  const list = NS.util.qs(".ia-stream-inline-comments-list", wrap);
  if (!list) return;

  list.innerHTML = '<div class="ia-stream-placeholder">Loading comments…</div>';

  const res = await NS.api.fetchComments({ video_id: videoId, page: 1, per_page: 20 });

  if (!res) {
    list.innerHTML = '<div class="ia-stream-placeholder">No response.</div>';
    return;
  }
  if (res.ok === false) {
    list.innerHTML = '<div class="ia-stream-placeholder">' + esc(res.error || "Comments error.") + '</div>';
    return;
  }

  const items = Array.isArray(res.items) ? res.items : [];
  if (!items.length) {
    list.innerHTML = '<div class="ia-stream-placeholder">No comments yet.</div>';
    return;
  }

  list.innerHTML = items.map(commentHtml).join("");
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
    const dislikes = fmtNum(counts.dislikes || 0);
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
  (isLoggedIn() ? (
    '<div class="ia-stream-actions" data-video-id="' + esc(id) + '" data-channel-url="' + esc(ch && ch.url ? ch.url : '') + '" data-channel-name="' + esc(ch && (ch.name || ch.display_name) ? (ch.name || ch.display_name) : '') + '">' +
      '<button type="button" class="ia-stream-act ia-stream-act-vote" data-act="vote" data-vote="like" title="Upvote">' +
        '<span class="ia-ico">⬆</span><span class="ia-count" data-count="likes">' + esc(likes) + '</span>' +
      '</button>' +
      '<button type="button" class="ia-stream-act ia-stream-act-vote" data-act="vote" data-vote="dislike" title="Downvote">' +
        '<span class="ia-ico">⬇</span><span class="ia-count" data-count="dislikes">' + esc(dislikes) + '</span>' +
      '</button>' +
      '<button type="button" class="ia-stream-act ia-stream-act-subscribe" data-act="subscribe" title="Subscribe">' +
        '<span class="ia-ico">👤</span><span class="ia-label">Subscribe</span>' +
      '</button>' +
      '<button type="button" class="ia-stream-act ia-stream-act-reply" data-act="reply" title="Reply">' +
        '<span class="ia-ico">💬</span><span class="ia-count" data-count="comments">' + esc(comments) + '</span>' +
      '</button>' +
      '<span class="ia-stream-act-status" aria-live="polite"></span>' +
    '</div>'
  ) : (
    '<div class="ia-stream-card-stats">' +
      '<span>👁 ' + esc(views) + '</span>' +
      '<span>⬆ ' + esc(likes) + '</span>' +
      '<span>⬇ ' + esc(dislikes) + '</span>' +
      '<span>💬 ' + esc(comments) + '</span>' +
    '</div>'
  )) +
'</div>' +
(isLoggedIn() ? (
  '<div class="ia-stream-inline-comments" hidden>' +
    '<div class="ia-stream-inline-comments-list"></div>' +
    '<div class="ia-stream-inline-compose">' +
      '<textarea class="ia-stream-inline-text" rows="2" placeholder="Write a reply…"></textarea>' +
      '<button type="button" class="ia-stream-inline-send">Reply</button>' +
    '</div>' +
  '</div>'
) : '') +
      '</article>'
    );
  }

  function bindOpenDelegation() {
    const M = mount();
    if (!M) return;

    // One handler for all card interactions (open + inline actions)
    NS.util.on(M, "click", async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;
// Inline actions (Reddit-like bar)
const actBtn = (t.closest ? t.closest(".ia-stream-act") : null);
if (actBtn) {
  const act = actBtn.getAttribute("data-act") || "";
  const card = actBtn.closest(".ia-stream-card");
  const actions = card ? card.querySelector(".ia-stream-actions") : null;
  const videoId = actions ? (actions.getAttribute("data-video-id") || "") : "";
  const status = actions ? actions.querySelector(".ia-stream-act-status") : null;

  function setStatus(msg){ if (status) status.textContent = msg || ""; }

  // Toggle + load replies
  if (act === "reply") {
    const wrap = card ? card.querySelector(".ia-stream-inline-comments") : null;
    if (wrap) {
      const open = !wrap.hasAttribute("hidden");
      if (open) wrap.setAttribute("hidden", "hidden");
      else {
        wrap.removeAttribute("hidden");
        if (videoId) loadInlineComments(videoId, wrap);
      }
    }
    ev.preventDefault(); ev.stopPropagation();
    return;
  }

  try {
    if (act === "vote") {
      if (!videoId) { setStatus("Missing video id."); ev.preventDefault(); ev.stopPropagation(); return; }
      const vote = actBtn.getAttribute("data-vote") || "like";
      setStatus("Working…");
      const r = await NS.api.rateVideo(videoId, vote);
      if (r && r.ok) {
        setStatus(vote === "like" ? "Upvoted." : "Downvoted.");
        // optimistic counter bump
        const countSpan = actBtn.querySelector(".ia-count");
        if (countSpan) {
          const cur = parseInt(String(countSpan.textContent||"0").replace(/[^\d]/g,''),10);
          if (isFinite(cur)) countSpan.textContent = String(cur + 1);
        }
        bumpCard(card);
      } else {
        setStatus((r && (r.message || r.error)) || "Vote failed.");
      }
      ev.preventDefault(); ev.stopPropagation();
      return;
    }

    if (act === "subscribe") {
      const url = actions ? (actions.getAttribute("data-channel-url") || "") : "";
      const name = actions ? (actions.getAttribute("data-channel-name") || "") : "";
      const uri = deriveChannelUri(url, name);
      if (!uri) { setStatus("Missing channel."); ev.preventDefault(); ev.stopPropagation(); return; }
      setStatus("Working…");
      const r = await NS.api.subscribe(uri);
      if (r && r.ok) {
        setStatus("Subscribed.");
        bumpCard(card);
      } else {
        setStatus((r && (r.message || r.error)) || "Subscribe failed.");
      }
      ev.preventDefault(); ev.stopPropagation();
      return;
    }
  } catch (e) {
    setStatus((e && e.message) ? e.message : "Action failed.");
    ev.preventDefault(); ev.stopPropagation();
    return;
  }
}

// Inline composer send
const sendBtn = (t.closest ? t.closest(".ia-stream-inline-send") : null);
if (sendBtn) {
  const card = sendBtn.closest(".ia-stream-card");
  const actions = card ? card.querySelector(".ia-stream-actions") : null;
  const videoId = actions ? (actions.getAttribute("data-video-id") || "") : "";
  const status = actions ? actions.querySelector(".ia-stream-act-status") : null;
  const wrap = sendBtn.closest(".ia-stream-inline-comments");
  const ta = wrap ? wrap.querySelector(".ia-stream-inline-text") : null;
  const text = ta ? (ta.value || "").trim() : "";

  function setStatus(msg){ if (status) status.textContent = msg || ""; }

  if (!videoId) { setStatus("Missing video id."); ev.preventDefault(); ev.stopPropagation(); return; }
  if (!text) { setStatus("Write a reply."); ev.preventDefault(); ev.stopPropagation(); return; }

  setStatus("Posting…");
  try {
    const r = await NS.api.commentCreate(videoId, text);
    if (r && r.ok) {
      if (ta) ta.value = "";
      setStatus("Posted.");
      bumpCard(card);
      if (wrap) loadInlineComments(videoId, wrap);
    } else {
      setStatus((r && (r.message || r.error)) || "Reply failed.");
    }
  } catch (e) {
    setStatus((e && e.message) ? e.message : "Reply failed.");
  }

  ev.preventDefault(); ev.stopPropagation();
  return;
}

// Open video modal
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
