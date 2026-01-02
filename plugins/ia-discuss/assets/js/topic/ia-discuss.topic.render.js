(function () {
  "use strict";

  const U = window.IA_DISCUSS_TOPIC_UTILS || {};
  const M = window.IA_DISCUSS_TOPIC_MEDIA || {};

  const qs = U.qs;
  const esc = U.esc;
  const timeAgo = U.timeAgo;

  function renderError(mount, msg) {
    if (!mount) return;
    mount.innerHTML = `
      <div class="iad-modal" style="position:relative;inset:auto;z-index:auto;">
        <div class="iad-modal-sheet" style="position:relative;left:auto;top:auto;transform:none;width:100%;max-height:none;">
          <div class="iad-modal-top">
            <button type="button" class="iad-x" data-iad-topic-back aria-label="Back">←</button>
            <div class="iad-modal-title">Topic</div>
          </div>
          <div class="iad-modal-body">
            <div class="iad-empty">Topic failed: ${esc(msg || "Unknown error")}</div>
          </div>
        </div>
      </div>
    `;
  }

  function bindBack(mount) {
    const back = qs("[data-iad-topic-back]", mount);
    if (back) back.addEventListener("click", () => {
      window.dispatchEvent(new CustomEvent("iad:topic_back"));
    });
  }

  function b64utf8(s) {
    try { return btoa(unescape(encodeURIComponent(String(s || "")))); } catch (e) { return ""; }
  }

  // stroke icons (currentColor)
  const I = {
    quote: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M7 17h4V7H6v6h3v4zm10 0h4V7h-5v6h3v4z" fill="currentColor"/></svg>`,
    reply: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M10 9V5l-7 7 7 7v-4c6 0 8 2 11 6-1-8-4-12-11-12z" fill="currentColor"/></svg>`,
    link:  `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M10 13a5 5 0 0 1 0-7l1.5-1.5a5 5 0 0 1 7 7L17 13" fill="none" stroke="currentColor" stroke-width="2"/><path d="M14 11a5 5 0 0 1 0 7L12.5 19.5a5 5 0 0 1-7-7L7 11" fill="none" stroke="currentColor" stroke-width="2"/></svg>`,
    edit:  `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M3 17.25V21h3.75L17.8 9.95l-3.75-3.75L3 17.25z" fill="currentColor"/><path d="M20.7 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>`,
    del:   `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z" fill="currentColor"/></svg>`,
    kick:  `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 6h2c0-3 3-4 6-4s6 1 6 4h2c0-4-4-6-8-6z" fill="currentColor"/><path d="M19 8h4v2h-4z" fill="currentColor"/></svg>`,
    add:   `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 6h2c0-3 3-4 6-4s6 1 6 4h2c0-4-4-6-8-6z" fill="currentColor"/><path d="M19 7h2v4h4v2h-4v4h-2v-4h-4v-2h4z" fill="currentColor"/></svg>`
  };

  function renderPostHTML(p, index, baseIndex) {
    const i = (baseIndex || 0) + (index || 0);

    const posterId = (p.poster_id != null) ? p.poster_id : (p.user_id || 0);
    const author = esc(p.poster_username || p.username || ("user#" + (posterId || 0)));
    const pAgo   = timeAgo(p.post_time || p.time || 0);

    const body   = (p.content_html != null) ? String(p.content_html) : (p.html != null ? String(p.html) : "");
    const media  = (p.media && typeof p.media === "object") ? p.media : {};

    const isOP = (i === 0);
    const isAlt = (!isOP && (i % 2 === 1));

    const postId = (p.post_id || 0);
    const postIdAttr = esc(String(postId));

    const adminBadge = p.is_admin
      ? `<span class="iad-admin-badge" title="Administrator">ADMIN</span>`
      : ``;

    const modBadge = (!adminBadge && p.is_moderator)
      ? `<span class="iad-mod-badge" title="Moderator">MOD</span>`
      : ``;

    const canEdit = String(p.can_edit || 0) === "1";
    const canDelete = String(p.can_delete || 0) === "1";
    const canBan = String(p.can_ban || 0) === "1";
    const isBanned = String(p.is_banned || 0) === "1";

    const rawB64 = b64utf8(p.raw_text || "");
    const forumId = parseInt(p.forum_id || "0", 10) || 0;

    return `
      <article class="iad-post ${isOP ? "is-op" : "is-reply"} ${isAlt ? "is-alt" : ""}" data-post-id="${postIdAttr}">
        <div class="iad-post-meta">
          <button type="button" class="iad-user-link" data-open-user data-username="${esc(p.poster_username || p.username || "")}" data-user-id="${esc(String(posterId || 0))}">
            ${author}
          </button>

          ${canBan ? (isBanned ? `
            <button type="button" class="iad-iconbtn iad-iconbtn-mini"
              data-iad-unban
              data-user-id="${esc(String(posterId || 0))}"
              data-forum-id="${esc(String(forumId || 0))}"
              data-username="${esc(p.poster_username || p.username || "")}"
              title="Reinstate user in this Agora">
              ${I.add}
            </button>
          ` : `
            <button type="button" class="iad-iconbtn iad-iconbtn-mini"
              data-iad-kick
              data-user-id="${esc(String(posterId || 0))}"
              data-forum-id="${esc(String(forumId || 0))}"
              data-username="${esc(p.poster_username || p.username || "")}"
              title="Block user from this Agora">
              ${I.kick}
            </button>
          `) : ``}

          ${adminBadge}${modBadge}
          <span class="iad-dotsep">•</span>
          <span class="iad-muted">${esc(pAgo)}</span>
        </div>

        <div class="iad-post-body">${body}</div>

        ${M.inlineMediaHTML ? M.inlineMediaHTML(media) : ""}
        ${M.attachmentPillsHTML ? M.attachmentPillsHTML(media) : ""}

        <div class="iad-post-actions iad-post-actions-icons">
          <button type="button" class="iad-iconbtn" data-iad-quote data-quote-author="${esc(p.poster_username || p.username || "")}" title="Quote">${I.quote}</button>
          <button type="button" class="iad-iconbtn" data-iad-reply title="Reply">${I.reply}</button>
          <button type="button" class="iad-iconbtn" data-iad-copylink data-post-id="${postIdAttr}" title="Copy link">${I.link}</button>
          ${canEdit ? `<button type="button" class="iad-iconbtn" data-iad-edit="${postIdAttr}" data-iad-edit-raw="${rawB64}" title="Edit">${I.edit}</button>` : ``}
          ${canDelete ? `<button type="button" class="iad-iconbtn" data-iad-del="${postIdAttr}" title="Delete">${I.del}</button>` : ``}
        </div>
      </article>
    `;
  }

  function renderTopicHTML(data) {
    const topicTitle = esc(data.topic_title || "Topic");
    const forumName  = esc(data.forum_name || "agora");
    const ago        = timeAgo(data.topic_time || data.last_post_time || 0);

    const posts = Array.isArray(data.posts) ? data.posts : [];
    const postsHtml = posts.map((p, idx) => renderPostHTML(p, idx, 0)).join("");

    // ✅ NEW: Post reply pill at top of topic
    const postReplyPill = `
      <button type="button" class="iad-pill iad-pill-primary" data-iad-post-reply title="Post reply">
        Post reply
      </button>
    `;

    return `
      <div class="iad-modal" style="position:relative;inset:auto;z-index:auto;">
        <div class="iad-modal-sheet" style="position:relative;left:auto;top:auto;transform:none;width:100%;max-height:none;">
          <div class="iad-modal-top">
            <button type="button" class="iad-x" data-iad-topic-back aria-label="Back">←</button>
            <div class="iad-modal-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              ${topicTitle}
            </div>
          </div>

          <div class="iad-modal-body">
            <div class="iad-card-meta" style="margin-bottom:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <button type="button" class="iad-sub iad-agora-link" data-iad-topic-open-agora title="Back to agora">
                agora/${forumName}
              </button>
              <span class="iad-dotsep">•</span>
              <span class="iad-time">${esc(ago)}</span>
              <span style="flex:1 1 auto;"></span>
              ${postReplyPill}
            </div>

            ${postsHtml || `<div class="iad-empty">No posts found.</div>`}

            ${data.has_more ? `
              <button type="button" class="iad-more" data-iad-more>Load more replies</button>
            ` : ``}

            <div style="margin-top:12px;" data-iad-topic-composer></div>
          </div>
        </div>
      </div>
    `;
  }

  window.IA_DISCUSS_TOPIC_RENDER = {
    renderError,
    bindBack,
    renderPostHTML,
    renderTopicHTML
  };
})();
