(function () {
  "use strict";

  const U = window.IA_DISCUSS_TOPIC_UTILS || {};
  const R = window.IA_DISCUSS_TOPIC_RENDER || {};
  const A = window.IA_DISCUSS_TOPIC_ACTIONS || {};

  const qs = U.qs;

  function fetchTopic(topicId, offset) {
    if (!window.IA_DISCUSS_API || typeof window.IA_DISCUSS_API.post !== "function") {
      return Promise.resolve({ success: false, data: { message: "IA_DISCUSS_API missing" } });
    }
    return window.IA_DISCUSS_API.post("ia_discuss_topic", {
      topic_id: topicId,
      offset: offset || 0
    });
  }

  function openConnectProfile(payload) {
    const p = payload || {};
    const username = (p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username, user_id, ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try { window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } })); } catch (e2) {}

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
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
        R.renderError(mount, msg);
        R.bindBack(mount);
        return;
      }

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
        mount.innerHTML = R.renderTopicHTML(payload);
        R.bindBack(mount);

        // bind quote/reply actions (modal)
        if (A.bindTopicActions) A.bindTopicActions(mount, topicId);

        try {
          if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.markRead === "function") {
            window.IA_DISCUSS_STATE.markRead(topicId);
          }
        } catch (e) {}

        try {
          const cm = qs("[data-iad-topic-composer]", mount);
          if (cm) {
            window.dispatchEvent(new CustomEvent("iad:mount_composer", {
              detail: { mount: cm, mode: "reply", topic_id: topicId }
            }));
          }
        } catch (e2) {}

        // After a new reply submit, router can request a scroll + temporary highlight.
        try {
          const scrollPostId = parseInt(opts.scroll_post_id || "0", 10) || 0;
          if (scrollPostId > 0) {
            setTimeout(() => {
              const el = mount.querySelector(`[data-post-id="${scrollPostId}"]`);
              if (!el) return;
              try {
                el.scrollIntoView({ behavior: "smooth", block: "center" });
              } catch (e3) {}

              if (opts.highlight_new) {
                const prevOutline = el.style.outline;
                const prevBox = el.style.boxShadow;
                el.style.outline = "2px solid rgba(255,255,255,0.25)";
                el.style.boxShadow = "0 0 0 4px rgba(255,255,255,0.06)";
                setTimeout(() => {
                  el.style.outline = prevOutline;
                  el.style.boxShadow = prevBox;
                }, 2200);
              }
            }, 60);
          }
        } catch (e3) {}
      } else {
        const body = qs(".iad-modal-body", mount);
        if (!body) return;

        const moreBtn = qs("[data-iad-more]", body);
        const existingCount = (body.querySelectorAll(".iad-post") || []).length;

        const tmp = document.createElement("div");
        tmp.innerHTML = (payload.posts || []).map((p, idx) => R.renderPostHTML(p, idx, existingCount)).join("");

        if (moreBtn) body.insertBefore(tmp, moreBtn);
        else body.appendChild(tmp);

        if (!payload.has_more && moreBtn) moreBtn.remove();
      }

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

      const openAgora = qs("[data-iad-topic-open-agora]", mount);
      if (openAgora) openAgora.addEventListener("click", () => {
        window.dispatchEvent(new CustomEvent("iad:topic_back"));
      });

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
      .catch(() => {
        R.renderError(mount, "Network error");
        R.bindBack(mount);
      });
  }

  window.IA_DISCUSS_UI_TOPIC = { renderInto };
})();
