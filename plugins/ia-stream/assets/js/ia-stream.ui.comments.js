/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.comments.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.comments = NS.ui.comments || {};

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function modal() {
    return NS.util.qs(".ia-stream-modal", document);
  }

  function mount() {
    const M = modal();
    if (!M) return null;
    return NS.util.qs(".ia-stream-modal-comments", M);
  }



function isLoggedIn() {
  const shell = NS.util.qs("#ia-stream-shell");
  return !!(shell && shell.getAttribute("data-logged-in") === "1");
}

function composerHtml(videoId) {
  if (!isLoggedIn()) return "";
  return (
    '<div class="ia-stream-comment-composer" data-video-id="' + esc(videoId) + '">' +
      '<textarea class="ia-stream-comment-input" rows="3" placeholder="Write a comment…"></textarea>' +
      '<div class="ia-stream-comment-actions">' +
        '<button type="button" class="ia-stream-comment-send">Post</button>' +
        '<div class="ia-stream-comment-status"></div>' +
      '</div>' +
    '</div>'
  );
}

function bindComposer(C) {
  if (!C || C.__iaComposerBound) return;
  C.__iaComposerBound = true;

  NS.util.on(C, "click", async function (ev) {
    const btn = ev && ev.target ? ev.target.closest(".ia-stream-comment-send") : null;
    if (!btn) return;

    const wrap = btn.closest(".ia-stream-comment-composer");
    if (!wrap) return;

    const videoId = wrap.getAttribute("data-video-id") || "";
    const ta = NS.util.qs(".ia-stream-comment-input", wrap);
    const status = NS.util.qs(".ia-stream-comment-status", wrap);
    const text = ta ? (ta.value || "").trim() : "";

    if (!videoId) { if (status) status.textContent = "Missing video id."; return; }
    if (!text) { if (status) status.textContent = "Write something first."; return; }

    if (status) status.textContent = "Posting…";
    btn.disabled = true;

    const res = await NS.api.commentCreate(videoId, text);

    btn.disabled = false;

    if (res && res.ok !== false) {
      if (ta) ta.value = "";
      if (status) status.textContent = "Posted.";
      // reload comments list
      NS.ui.comments.load(videoId);
    } else {
      if (status) status.textContent = (res && (res.message || res.error)) ? (res.message || res.error) : "Post failed.";
    }
  });
}

  function renderPlaceholder(msg) {
    const C = mount();
    if (!C) return;
    C.innerHTML = '<div class="ia-stream-placeholder">' + esc(msg || "Loading…") + "</div>";
  }

  function commentHtml(c) {
    const author = c && c.author ? c.author : {};
    const name = author && author.name ? author.name : "User";
    const avatar = author && author.avatar ? author.avatar : "";
    const time = c && c.created_ago ? c.created_ago : "";
    const text = c && c.text ? c.text : "";
    const replies = c && c.replies_count ? parseInt(c.replies_count, 10) : 0;

    return (
      '<div class="ia-stream-comment">' +
        '<div class="ia-stream-comment-header">' +
          '<div class="ia-stream-comment-avatar" style="' + (avatar ? 'background-image:url(' + esc(avatar) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
          '<div class="ia-stream-comment-author">' + esc(name) + '</div>' +
          '<div class="ia-stream-comment-time">' + esc(time) + '</div>' +
        '</div>' +
        '<div class="ia-stream-comment-text">' + esc(text) + '</div>' +
        (replies > 0 ? '<div style="margin-top:8px;color:var(--ia-muted);font-size:0.8rem">Replies: ' + esc(String(replies)) + '</div>' : '') +
      '</div>'
    );
  }

  NS.ui.comments.load = async function (videoId) {
    videoId = (videoId === null || videoId === undefined) ? "" : String(videoId).trim();
    if (!videoId) return;

    renderPlaceholder("Loading comments…");

    const res = await NS.api.fetchComments({ video_id: videoId, page: 1, per_page: 20 });

    if (!res) {
      renderPlaceholder("No response (network).");
      return;
    }

    if (res.ok === false) {
      renderPlaceholder(res.error || "Comments error.");
      return;
    }

    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      const note = res.meta && res.meta.note ? res.meta.note : "";
      const C = mount();
      if (C) {
        C.innerHTML = composerHtml(videoId) + '<div class="ia-stream-placeholder">' + esc(note ? ("No comments. " + note) : "No comments.") + '</div>';
        bindComposer(C);
      } else {
        renderPlaceholder(note ? ("No comments. " + note) : "No comments.");
      }
      return;
    }

    const C = mount();
    if (!C) return;

    C.innerHTML = composerHtml(videoId) + items.map(commentHtml).join("");
    bindComposer(C);
  };
})();
