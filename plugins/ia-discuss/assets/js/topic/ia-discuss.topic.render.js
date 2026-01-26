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
      <div class="iad-modal iad-topic-modal is-fullscreen">
        <div class="iad-modal-backdrop" data-iad-topic-back></div>
        <div class="iad-modal-sheet">
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
    const els = mount ? mount.querySelectorAll("[data-iad-topic-back]") : [];
    els.forEach((el) => {
      el.addEventListener("click", () => {
        window.dispatchEvent(new CustomEvent("iad:topic_back"));
      });
    });
  }

  function b64utf8(s) {
    try {
      return btoa(unescape(encodeURIComponent(String(s || ""))));
    } catch (e) {
      return "";
    }
  }

  // stroke icons (currentColor)
  const I = {
    collapse: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    quote: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M7 17h4V7H6v6h3v4zm10 0h4V7h-5v6h3v4z" fill="currentColor"/></svg>`,
    reply: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M10 9V5l-7 7 7 7v-4c6 0 8 2 11 6-1-8-4-12-11-12z" fill="currentColor"/></svg>`,
    link: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M10 13a5 5 0 0 1 0-7l1.5-1.5a5 5 0 0 1 7 7L17 13" fill="none" stroke="currentColor" stroke-width="2"/><path d="M14 11a5 5 0 0 1 0 7L12.5 19.5a5 5 0 0 1-7-7L7 11" fill="none" stroke="currentColor" stroke-width="2"/></svg>`,
    edit: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M3 17.25V21h3.75L17.8 9.95l-3.75-3.75L3 17.25z" fill="currentColor"/><path d="M20.7 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>`,
    del: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z" fill="currentColor"/></svg>`,
    kick: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 6h2c0-3 3-4 6-4s6 1 6 4h2c0-4-4-6-8-6z" fill="currentColor"/><path d="M19 8h4v2h-4z" fill="currentColor"/></svg>`,
    add: `<svg viewBox="0 0 24 24" class="iad-ico"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 6h2c0-3 3-4 6-4s6 1 6 4h2c0-4-4-6-8-6z" fill="currentColor"/><path d="M19 7h2v4h4v2h-4v4h-2v-4h-4v-2h4z" fill="currentColor"/></svg>`,
  };

  function renderPostHTML(p, index, baseIndex, opts) {
    opts = opts || {};
    const i = (baseIndex || 0) + (index || 0);

    const posterId = p.poster_id != null ? p.poster_id : p.user_id || 0;
    const author = esc(p.poster_username || p.username || "user#" + (posterId || 0));
    const pAgo = timeAgo(p.post_time || p.time || 0);

    const body =
      p.content_html != null
        ? String(p.content_html)
        : p.html != null
        ? String(p.html)
        : "";
    const media = p.media && typeof p.media === "object" ? p.media : {};

    // If the post body already contains an inline video embed, do not render the media.video_url block again.
    // This prevents the "first video link" duplicating inline + at the bottom.
    const bodyHasInlineVideo =
      /<iframe\b[^>]*(youtube\.com\/embed|youtu\.be\/|player\.|peertube)/i.test(body) ||
      /\bclass=["'][^"']*iad-embed-video/i.test(body) ||
      /\bdata-ia-embed=["']video/i.test(body);

    const isOP = i === 0;
    const isAlt = !isOP && i % 2 === 1;

    const postId = p.post_id || 0;
    const postIdAttr = esc(String(postId));

    const adminBadge = p.is_admin
      ? `<span class="iad-admin-badge" title="Administrator">ADMIN</span>`
      : ``;

    const modBadge = !adminBadge && p.is_moderator
      ? `<span class="iad-mod-badge" title="Moderator">MOD</span>`
      : ``;

    const canEdit = String(p.can_edit || 0) === "1";
    const canDelete = String(p.can_delete || 0) === "1";
    const canBan = String(p.can_ban || 0) === "1";
    const isBanned = String(p.is_banned || 0) === "1";

    const rawB64 = b64utf8(p.raw_text || "");
    const forumId = parseInt(p.forum_id || "0", 10) || 0;

    return `
      <article class="iad-post ${isOP ? "is-op" : "is-reply"} ${isAlt ? "is-alt" : ""} " data-post-id="${postIdAttr}">
        <div class="iad-post-meta">
          <button type="button" class="iad-user-link" data-open-user data-username="${esc(
            p.poster_username || p.username || ""
          )}" data-user-id="${esc(String(posterId || 0))}">
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

        <div class="iad-post-content">
          <div class="iad-post-body">${body}</div>

          ${(!bodyHasInlineVideo && M.inlineMediaHTML) ? M.inlineMediaHTML(media) : ""}
          ${M.attachmentPillsHTML ? M.attachmentPillsHTML(media) : ""}
        </div>

        <div class="iad-post-actions iad-post-actions-icons">
          <button type="button" class="iad-iconbtn" data-iad-quote data-quote-author="${esc(
            p.poster_username || p.username || ""
          )}" title="Quote">${I.quote}</button>
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
    const forumName = esc(data.forum_name || "agora");
    const ago = timeAgo(data.topic_time || data.last_post_time || 0);

    const posts = Array.isArray(data.posts) ? data.posts : [];
    const shown = data.shown_count != null ? parseInt(data.shown_count, 10) : posts.length;
    const total = data.posts_total != null ? parseInt(data.posts_total, 10) : 0;
    const postsHtml = posts.map((p, idx) => renderPostHTML(p, idx, 0, {})).join("");

    // ✅ Top controls (reply, pagination, jump)
    const postReplyPill = `
      <button type="button" class="iad-pill iad-pill-primary" data-iad-post-reply title="Post reply">
        Post reply
      </button>
    `;

    const lastReplyPill = `
      <button type="button" class="iad-pill" data-iad-goto-last title="Go to last reply">
        Last reply
      </button>
    `;

    const notifyOn = (data && (data.notify_enabled === 1 || data.notify_enabled === '1')) ? 1 : 0;
    const notifyToggle = `
      <label class="iad-pill" style="display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;">
        <input type="checkbox" data-iad-topic-notify ${notifyOn ? 'checked' : ''} />
        <span>Email replies</span>
      </label>
    `;

    const pager = `
      <div class="iad-topic-pager">
        <div class="iad-topic-pager-left">
          <span class="iad-topic-count" data-iad-topic-count></span>
        </div>
        <div class="iad-topic-pager-right" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          ${postReplyPill}
          ${lastReplyPill}
          ${(data && data.viewer && data.viewer.phpbb_user_id) ? notifyToggle : ``}
        </div>
      </div>
    `;

    return `
      <div class="iad-modal iad-topic-modal is-fullscreen">
        <div class="iad-modal-backdrop" data-iad-topic-back></div>
        <div class="iad-modal-sheet">
          <div class="iad-modal-top">
            <button type="button" class="iad-x" data-iad-topic-back aria-label="Back">←</button>
            <div class="iad-modal-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              ${topicTitle}
            </div>
          </div>

          <div class="iad-modal-body">
            <div class="iad-card-meta" style="margin-bottom:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <button type="button" class="iad-sub iad-agora-link" data-iad-topic-open-agora data-forum-id="${esc(
                String(data.forum_id || 0)
              )}" data-forum-name="${forumName}" title="Back to agora">
                agora/${forumName}
              </button>
              <span class="iad-dotsep">•</span>
              <span class="iad-time">${esc(ago)}</span>
              <span style="flex:1 1 auto;"></span>
            </div>

            ${pager}

            <!-- back-to-top is now handled via the centered topic-nav button -->

            ${postsHtml || `<div class="iad-empty">No posts found.</div>`}

            ${(data.can_load_more || data.has_more) ? `
              <button type="button" class="iad-more" data-iad-more>Load more replies</button>
            ` : ``}

          </div>

          <div class="iad-topic-nav" aria-label="Topic navigation">
            <button type="button" class="iad-topic-navbtn is-prev" data-iad-topic-prev aria-label="Previous topic" title="Previous topic">←</button>
            <button type="button" class="iad-topic-navbtn is-top" data-iad-topic-top aria-label="Back to top" title="Back to top">↑</button>
            <button type="button" class="iad-topic-navbtn is-next" data-iad-topic-next aria-label="Next topic" title="Next topic">→</button>
          </div>
        </div>
      </div>
    `;
  }

  window.IA_DISCUSS_TOPIC_RENDER = {
    renderError,
    bindBack,
    renderPostHTML,
    renderTopicHTML,
  };
})();
