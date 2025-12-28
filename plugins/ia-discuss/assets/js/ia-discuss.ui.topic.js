(function () {
  "use strict";

  // We rely on IA_DISCUSS_CORE if present, but we also hard-fallback safely.
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

    const back = qs("[data-iad-topic-back]", mount);
    if (back) back.addEventListener("click", () => {
      window.dispatchEvent(new CustomEvent("iad:topic_back"));
    });
  }

  function bindBack(mount) {
    const back = qs("[data-iad-topic-back]", mount);
    if (back) back.addEventListener("click", () => {
      window.dispatchEvent(new CustomEvent("iad:topic_back"));
    });
  }

  function renderTopicHTML(data) {
    const topicTitle = esc(data.topic_title || "Topic");
    const forumName  = esc(data.forum_name || "agora");
    const ago        = timeAgo(data.topic_time || data.last_post_time || 0);

    const posts = Array.isArray(data.posts) ? data.posts : [];

    // NOTE: your PHP returns posts[].content_html (NOT message_html)
    const postsHtml = posts.map((p) => {
      const author = esc(p.poster_username || ("user#" + (p.poster_id || 0)));
      const pAgo   = timeAgo(p.post_time || 0);
      const body   = (p.content_html != null) ? String(p.content_html) : (p.message_html != null ? String(p.message_html) : "");

      const collapsed = String(p.collapsed_default || 0) === "1";
      return `
        <article class="iad-post ${collapsed ? "is-collapsed" : ""}">
          <div class="iad-post-meta">
            <button type="button" class="iad-user-link" data-open-user data-username="${esc(p.poster_username || "")}" data-user-id="${esc(String(p.poster_id || 0))}">
              ${author}
            </button>
            <span class="iad-dotsep">•</span>
            <span class="iad-muted">${esc(pAgo)}</span>
          </div>

          <div class="iad-post-body">${body}</div>

          <div class="iad-post-actions">
            <button type="button" class="iad-link" data-iad-quote data-quote-author="${esc(p.poster_username || "")}" data-quote-text="${esc(CORE && CORE.toPlain ? CORE.toPlain(body) : "")}">
              Quote
            </button>
          </div>
        </article>
      `;
    }).join("");

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
            <div class="iad-card-meta" style="margin-bottom:10px;">
              <button type="button" class="iad-sub iad-agora-link" data-iad-topic-open-agora title="Back to agora">
                agora/${forumName}
              </button>
              <span class="iad-dotsep">•</span>
              <span class="iad-time">${esc(ago)}</span>
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

  function openConnectProfile(payload) {
    const p = payload || {};
    const username = (p.username || "").trim();
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

  function fetchTopic(topicId, offset) {
    if (!window.IA_DISCUSS_API || typeof window.IA_DISCUSS_API.post !== "function") {
      return Promise.resolve({ success: false, data: { message: "IA_DISCUSS_API missing" } });
    }
    return window.IA_DISCUSS_API.post("ia_discuss_topic", {
      topic_id: topicId,
      offset: offset || 0
    });
  }

  function renderInto(root, topicId, opts) {
    opts = opts || {};
    const mount = root ? qs("[data-iad-view]", root) : null;
    if (!mount) return;

    mount.innerHTML = `<div class="iad-loading">Loading topic…</div>`;

    let currentOffset = 0;
    const pageSize = 25;

    function apply(res, append) {
      if (!res || !res.success) {
        const msg = (res && res.data && res.data.message) ? res.data.message : "Failed to load topic";
        renderError(mount, msg);
        return;
      }

      // Normalize payload to what renderTopicHTML expects
      const data = res.data || {};
      const payload = {
        topic_id: data.topic_id,
        topic_title: data.topic_title,
        forum_id: data.forum_id,
        forum_name: data.forum_name,
        topic_time: data.topic_time,
        last_post_time: data.last_post_time,
        posts: Array.isArray(data.posts) ? data.posts : [],
        has_more: !!data.has_more
      };

      if (!append) {
        mount.innerHTML = renderTopicHTML(payload);
        bindBack(mount);

        // mark as read (local)
        try {
          if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.markRead === "function") {
            window.IA_DISCUSS_STATE.markRead(topicId);
          }
        } catch (e) {}

        // Optional: scroll to comments (post #2)
        try {
          if (opts.scroll === "comments") {
            const posts = mount.querySelectorAll(".iad-post");
            if (posts && posts.length > 1) posts[1].scrollIntoView({ behavior: "smooth", block: "start" });
          }
        } catch (e2) {}

        // mount reply composer
        try {
          const cm = qs("[data-iad-topic-composer]", mount);
          if (cm) {
            window.dispatchEvent(new CustomEvent("iad:mount_composer", {
              detail: { mount: cm, mode: "reply", topic_id: topicId }
            }));
          }
        } catch (e3) {}
      } else {
        // Append posts into existing modal body (before "Load more" button)
        const body = qs(".iad-modal-body", mount);
        if (!body) return;

        const moreBtn = qs("[data-iad-more]", body);

        const tmp = document.createElement("div");
        tmp.innerHTML = (payload.posts || []).map((p) => {
          const author = esc(p.poster_username || ("user#" + (p.poster_id || 0)));
          const pAgo   = timeAgo(p.post_time || 0);
          const html   = (p.content_html != null) ? String(p.content_html) : "";

          return `
            <article class="iad-post">
              <div class="iad-post-meta">
                <button type="button" class="iad-user-link" data-open-user data-username="${esc(p.poster_username || "")}" data-user-id="${esc(String(p.poster_id || 0))}">
                  ${author}
                </button>
                <span class="iad-dotsep">•</span>
                <span class="iad-muted">${esc(pAgo)}</span>
              </div>
              <div class="iad-post-body">${html}</div>
            </article>
          `;
        }).join("");

        // Insert before Load more if present, else append to end
        if (moreBtn) body.insertBefore(tmp, moreBtn);
        else body.appendChild(tmp);

        if (!payload.has_more && moreBtn) moreBtn.remove();
      }

      // bind open user -> Connect
      mount.querySelectorAll("[data-open-user]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          openConnectProfile({
            username: btn.getAttribute("data-username") || "",
            user_id: btn.getAttribute("data-user-id") || "0"
          });
        });
      });

      // bind "agora/name" -> back
      const openAgora = qs("[data-iad-topic-open-agora]", mount);
      if (openAgora) {
        openAgora.addEventListener("click", () => {
          window.dispatchEvent(new CustomEvent("iad:topic_back"));
        });
      }

      // bind Load more
      const more = qs("[data-iad-more]", mount);
      if (more) {
        more.onclick = function () {
          currentOffset += pageSize;
          more.disabled = true;
          more.textContent = "Loading…";
          fetchTopic(topicId, currentOffset).then((r) => {
            more.disabled = false;
            more.textContent = "Load more replies";
            apply(r, true);
          }).catch(() => {
            more.disabled = false;
            more.textContent = "Load more replies";
            alert("Could not load more replies.");
          });
        };
      }
    }

    fetchTopic(topicId, 0)
      .then((res) => apply(res, false))
      .catch((err) => renderError(mount, (err && err.message) ? err.message : "Unknown error"));
  }

  // ✅ define the symbol boot.js requires
  window.IA_DISCUSS_UI_TOPIC = { renderInto };
})();
