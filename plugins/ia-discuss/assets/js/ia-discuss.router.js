(function () {
  "use strict";

  function depsReady() {
    return (
      window.IA_DISCUSS &&
      window.IA_DISCUSS_CORE &&
      window.IA_DISCUSS_API &&
      window.IA_DISCUSS_UI_SHELL &&
      window.IA_DISCUSS_UI_FEED &&
      window.IA_DISCUSS_UI_AGORA &&
      window.IA_DISCUSS_UI_TOPIC &&
      window.IA_DISCUSS_UI_COMPOSER &&
      window.IA_DISCUSS_UI_SEARCH
    );
  }

  function safeQS(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function setParam(key, val) {
    try {
      const url = new URL(window.location.href);
      if (val === null || val === undefined || val === "") url.searchParams.delete(key);
      else url.searchParams.set(key, String(val));
      window.history.pushState({ ia: 1 }, "", url.toString());
    } catch (e) {}
  }

  function mount() {
    if (!depsReady()) return;

    const qs = (window.IA_DISCUSS_CORE && window.IA_DISCUSS_CORE.qs) ? window.IA_DISCUSS_CORE.qs : safeQS;
    const root = qs("[data-ia-discuss-root]");
    if (!root) return;

    window.IA_DISCUSS_UI_SHELL.shell();

    // bind search UX
    try {
      window.IA_DISCUSS_UI_SEARCH.bindSearchBox(root);
    } catch (e) {}

    let inAgora = false;
    let lastListView = "new";
    let lastForumId = 0;
    let lastForumName = "";

    let inSearch = false;
    let searchPrev = { view: "new", forum_id: 0, forum_name: "" };
    let lastSearchQ = "";

    function render(view, forumId, forumName) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      inSearch = false;

      if (view === "agoras") {
        inAgora = false;
        lastListView = "agoras";
        lastForumId = 0;
        lastForumName = "";

        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        setParam("iad_topic", "");

        mountEl.innerHTML = `<div class="iad-loading">Loading…</div>`;

        window.IA_DISCUSS_API.post("ia_discuss_agoras", { offset: 0, q: "" }).then((res) => {
          if (!res || !res.success) {
            mountEl.innerHTML = `<div class="iad-empty">Failed to load agoras.</div>`;
            return;
          }

          const items = (res.data && res.data.items) ? res.data.items : [];

          mountEl.innerHTML = `
            <div class="iad-agoras">
              ${items.map((f) => `
                <button
                  type="button"
                  class="iad-agora-row"
                  data-forum-id="${f.forum_id}"
                  data-forum-name="${(f.forum_name || "")}">
                  <div class="iad-agora-row-name">agora/${(f.forum_name || "")}</div>
                  <div class="iad-agora-row-sub">${(f.topics || 0)} topics • ${(f.posts || 0)} posts</div>
                </button>
              `).join("")}
            </div>
          `;

          mountEl.querySelectorAll("[data-forum-id]").forEach((b) => {
            b.addEventListener("click", () => {
              const id = parseInt(b.getAttribute("data-forum-id") || "0", 10);
              const nm = b.getAttribute("data-forum-name") || "";
              render("agora", id, nm);
            });
          });
        });

        return;
      }

      if (view === "agora") {
        inAgora = true;

        lastListView = "agora";
        lastForumId = forumId || 0;
        lastForumName = forumName || "";

        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        setParam("iad_topic", "");

        window.IA_DISCUSS_UI_AGORA.renderAgora(root, lastForumId, lastForumName);
        return;
      }

      inAgora = false;

      lastListView = view;
      lastForumId = 0;
      lastForumName = "";

      window.IA_DISCUSS_UI_SHELL.setActiveTab(view);
      setParam("iad_topic", "");

      window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, 0);
    }

    function openTopicPage(topicId, opts) {
      topicId = parseInt(topicId || "0", 10) || 0;
      if (!topicId) return;

      opts = opts || {};
      setParam("iad_topic", String(topicId));

      window.IA_DISCUSS_UI_TOPIC.renderInto(root, topicId, opts);
    }

    function openSearchPage(q) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      // remember where we came from
      inSearch = true;
      lastSearchQ = String(q || "").trim();
      searchPrev = { view: lastListView, forum_id: lastForumId, forum_name: lastForumName };

      // clear topic param (search is its own view)
      setParam("iad_topic", "");

      window.IA_DISCUSS_UI_SEARCH.renderSearchPageInto(mountEl, lastSearchQ);
    }

    root.querySelectorAll(".iad-tab").forEach((b) => {
      b.addEventListener("click", () => {
        const v = b.getAttribute("data-view");
        if (!v) return;
        render(v, 0, "");
      });
    });

    window.addEventListener("iad:open_topic_page", (e) => {
      const d = (e && e.detail) ? e.detail : {};
      const tid = d.topic_id ? d.topic_id : 0;
      if (!tid) return;

      // ✅ allow post scroll
      const scrollPostId = d.scroll_post_id ? parseInt(d.scroll_post_id, 10) : 0;
      const highlight = d.highlight_new ? 1 : 0;

      openTopicPage(tid, {
        scroll_post_id: scrollPostId || 0,
        highlight_new: highlight ? 1 : 0
      });
    });

    window.addEventListener("iad:open_agora", (e) => {
      const fid = e.detail && e.detail.forum_id ? parseInt(e.detail.forum_id, 10) : 0;
      const nm  = (e.detail && e.detail.forum_name) ? String(e.detail.forum_name) : "";
      if (!fid) return;
      render("agora", fid, nm);
    });

    window.addEventListener("iad:go_agoras", () => { render("agoras", 0, ""); });

    window.addEventListener("iad:topic_back", () => {
      setParam("iad_topic", "");
      if (inSearch) return openSearchPage(lastSearchQ);
      if (lastListView === "agora" && lastForumId) return render("agora", lastForumId, lastForumName || "");
      if (lastListView === "agoras") return render("agoras", 0, "");
      render(lastListView || "new", 0, "");
    });

    // ✅ NEW: open search
    window.addEventListener("iad:open_search", (e) => {
      const q = (e.detail && e.detail.q) ? String(e.detail.q) : "";
      if (!q || q.trim().length < 2) return;
      openSearchPage(q);
    });

    // ✅ NEW: back from search
    window.addEventListener("iad:search_back", () => {
      inSearch = false;

      // go back to prior view
      if (searchPrev.view === "agora" && searchPrev.forum_id) {
        render("agora", searchPrev.forum_id, searchPrev.forum_name || "");
        return;
      }
      if (searchPrev.view === "agoras") { render("agoras", 0, ""); return; }
      render(searchPrev.view || "new", 0, "");
    });

    window.addEventListener("iad:render_feed", (e) => {
      const d = e.detail || {};
      const view = d.view || "new";
      const forumId = parseInt(d.forum_id || "0", 10) || 0;
      const mountEl = d.mount || null;

      if (inAgora) return window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, forumId);
      render(view, 0, "");
    });

    // composer mount
    window.addEventListener("iad:mount_composer", (e) => {
      const d = e.detail || {};
      if (!d.mount) return;

      const mode = d.mode || "topic";
      d.mount.innerHTML = window.IA_DISCUSS_UI_COMPOSER.composerHTML({ mode, submitLabel: d.submitLabel || null });

      window.IA_DISCUSS_UI_COMPOSER.bindComposer(d.mount, {
        prefillBody: d.prefillBody || "",
        mode,
        startOpen: !!d.start_open,
        onSubmit(payload, ui) {
          if (IA_DISCUSS.loggedIn !== "1") {
            window.dispatchEvent(new CustomEvent("ia:login_required"));
            return;
          }

          let body = payload.body || "";
          if (payload.attachments && payload.attachments.length) {
            const json = JSON.stringify(payload.attachments);
            const b64 = btoa(unescape(encodeURIComponent(json)));
            body = body + "\n\n[ia_attachments]" + b64;
          }

          // Edit flow
          if (d.edit_post_id) {
            window.IA_DISCUSS_API.post("ia_discuss_edit_post", {
              post_id: d.edit_post_id,
              body
            }).then((res) => {
              if (!res || !res.success) {
                ui.setError((res && res.data && res.data.message) ? res.data.message : "Edit failed");
                return;
              }
              window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));
              openTopicPage(d.topic_id, {});
            });
            return;
          }

          // Reply flow
          window.IA_DISCUSS_API.post("ia_discuss_reply", {
            topic_id: d.topic_id,
            body
          }).then((res) => {
            if (!res || !res.success) {
              ui.setError((res && res.data && res.data.message) ? res.data.message : "Reply failed");
              return;
            }

            const postId = res.data && res.data.post_id ? parseInt(res.data.post_id, 10) : 0;

            window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));

            openTopicPage(d.topic_id, {
              scroll_post_id: postId || 0,
              highlight_new: 1
            });
          });
        }
      });
    });

    // initial view
    render("new", 0, "");
  }

  window.IA_DISCUSS_ROUTER = { mount };
})();
