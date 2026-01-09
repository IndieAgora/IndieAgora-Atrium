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

        const CORE = window.IA_DISCUSS_CORE || {};
        const esc = CORE.esc || function (s) { return String(s || ""); };

        let agOffset = 0;
        let agHasMore = true;
        let agLoading = false;

        function agRowHTML(f) {
          const fid = parseInt(f.forum_id || "0", 10) || 0;
          const name = String(f.forum_name || "");

          // Prefer pre-sanitised HTML from the API; fall back to plain text.
          const descHtml = (f.forum_desc_html !== undefined && f.forum_desc_html !== null) ? String(f.forum_desc_html) : "";
          const descText = (f.forum_desc !== undefined && f.forum_desc !== null) ? String(f.forum_desc) : "";

          const topics = parseInt(String(
            (f.topics !== undefined && f.topics !== null) ? f.topics :
            ((f.topics_count !== undefined && f.topics_count !== null) ? f.topics_count :
            ((f.forum_topics !== undefined && f.forum_topics !== null) ? f.forum_topics : 0))
          ), 10) || 0;

          const posts = parseInt(String(
            (f.posts !== undefined && f.posts !== null) ? f.posts :
            ((f.posts_count !== undefined && f.posts_count !== null) ? f.posts_count :
            ((f.forum_posts !== undefined && f.forum_posts !== null) ? f.forum_posts : 0))
          ), 10) || 0;

          // NOTE: descHtml is already sanitised server-side; do not escape it again.
          const descBlock = descHtml
            ? `<div class="iad-agora-row__desc">${descHtml}</div>`
            : (descText ? `<div class="iad-agora-row__desc">${esc(descText)}</div>` : ``);

          return `
            <button
              type="button"
              class="iad-agora-row"
              data-forum-id="${fid}"
              data-forum-name="${esc(name)}"
            >
              <div class="iad-agora-row__name">${esc(name)}</div>
              ${descBlock}
              <div class="iad-agora-row__meta">${topics} topics • ${posts} posts</div>
            </button>
          `;
        }

        function renderShell() {
          mountEl.innerHTML = `
            <div class="iad-agoras">
              <div class="iad-agoras-list"></div>
              <div class="iad-agoras-more"></div>
            </div>
          `;
        }

        function renderMoreButton() {
          const wrap = mountEl.querySelector(".iad-agoras-more");
          if (!wrap) return;
          if (!agHasMore) { wrap.innerHTML = ""; return; }
          wrap.innerHTML = `<button type="button" class="iad-more" data-iad-agoras-more>Load more</button>`;
        }

        async function loadAgorasNext() {
          if (agLoading) return;
          agLoading = true;

          const moreBtn = mountEl.querySelector("[data-iad-agoras-more]");
          if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = "Loading…"; }

          const res = await window.IA_DISCUSS_API.post("ia_discuss_agoras", { offset: agOffset, q: "" });
          if (!res || !res.success) {
            agLoading = false;
            if (moreBtn) { moreBtn.disabled = false; moreBtn.textContent = "Load more"; }
            if (!mountEl.querySelector(".iad-agoras-list")) mountEl.innerHTML = `<div class="iad-empty">Failed to load agoras.</div>`;
            return;
          }

          const d = res.data || {};
          const items = Array.isArray(d.items) ? d.items : [];

          if (!mountEl.querySelector(".iad-agoras-list")) renderShell();

          const list = mountEl.querySelector(".iad-agoras-list");
          if (list) {
            list.insertAdjacentHTML("beforeend", items.map(agRowHTML).join(""));
            if (!list.children.length) list.innerHTML = `<div class="iad-empty">No agoras.</div>`;
          }

          agHasMore = !!d.has_more || (items.length === 50);
          agOffset = (typeof d.next_offset === "number") ? d.next_offset : (agOffset + items.length);

          renderMoreButton();
          agLoading = false;
        }

        mountEl.onclick = function (e) {
          const t = e.target;

          const more = t.closest && t.closest("[data-iad-agoras-more]");
          if (more) {
            e.preventDefault();
            e.stopPropagation();
            loadAgorasNext();
            return;
          }

          const row = t.closest && t.closest("[data-forum-id]");
          if (row) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(row.getAttribute("data-forum-id") || "0", 10);
            const nm = row.getAttribute("data-forum-name") || "";
            render("agora", id, nm);
          }
        };

        renderShell();
        loadAgorasNext();

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

      // Allow callers (e.g. feed reply icon) to request opening the reply composer.
      const openReply = d.open_reply ? 1 : 0;

      openTopicPage(tid, {
        scroll_post_id: scrollPostId || 0,
        highlight_new: highlight ? 1 : 0,
        open_reply: openReply ? 1 : 0
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

      // Capture a stable context for this composer instance.
      // (Prevents stale topic/forum IDs being used if the user navigates while a composer remains mounted.)
      const ctx = {
        mode: d.mode || "topic",
        forum_id: parseInt(d.forum_id || "0", 10) || 0,
        topic_id: parseInt(d.topic_id || "0", 10) || 0,
        edit_post_id: parseInt(d.edit_post_id || "0", 10) || 0
      };

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

          // Prefer the URL topic when available (deep links / tab navigation)
          let urlTopicId = 0;
          try {
            const u = new URL(window.location.href);
            urlTopicId = parseInt(u.searchParams.get("iad_topic") || "0", 10) || 0;
          } catch (e2) {}

          const effectiveTopicId = urlTopicId || ctx.topic_id;
          const effectiveForumId = ctx.forum_id;

          // Edit flow
          if (ctx.edit_post_id) {
            window.IA_DISCUSS_API.post("ia_discuss_edit_post", {
              post_id: ctx.edit_post_id,
              body
            }).then((res) => {
              if (!res || !res.success) {
                ui.setError((res && res.data && res.data.message) ? res.data.message : "Edit failed");
                return;
              }
              window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));
              openTopicPage(effectiveTopicId, {});
            });
            return;
          }

          // Topic (new thread) flow
          if (payload.mode === "topic") {
            const forumId = effectiveForumId;
            const title = (payload.title || "").trim();
            if (!forumId) { ui.setError("Missing forum_id"); return; }
            if (!title) { ui.setError("Title required"); return; }
            if (!body.trim()) { ui.setError("Body required"); return; }

            window.IA_DISCUSS_API.post("ia_discuss_new_topic", {
              forum_id: forumId,
              title,
              body
            }).then((res) => {
              if (!res || !res.success) {
                ui.setError((res && res.data && res.data.message) ? res.data.message : "New topic failed");
                return;
              }

              const topicId = res.data && res.data.topic_id ? parseInt(res.data.topic_id, 10) : 0;
              ui.clear();

              // After creating, open the topic view.
              if (topicId) {
                openTopicPage(topicId, {});
              } else {
                // Fallback: return to current forum feed.
                window.dispatchEvent(new CustomEvent("iad:render_feed", {
                  detail: { view: "new", forum_id: forumId }
                }));
              }
            });
            return;
          }

          // Reply flow
          window.IA_DISCUSS_API.post("ia_discuss_reply", {
            topic_id: effectiveTopicId,
            body
          }).then((res) => {
            if (!res || !res.success) {
              ui.setError((res && res.data && res.data.message) ? res.data.message : "Reply failed");
              return;
            }

            const postId = res.data && res.data.post_id ? parseInt(res.data.post_id, 10) : 0;

            window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));

            openTopicPage(effectiveTopicId, {
              scroll_post_id: postId || 0,
              highlight_new: 1
            });
          });
        }
      });
    });

    // -----------------------------
    // Initial view (supports deep links)
    // -----------------------------
    try {
      const u = new URL(window.location.href);
      const topicId = parseInt(u.searchParams.get("iad_topic") || "0", 10) || 0;
      const postId  = parseInt(u.searchParams.get("iad_post") || "0", 10) || 0;
      const forumId = parseInt(u.searchParams.get("iad_forum") || "0", 10) || 0;
      const view    = String(u.searchParams.get("iad_view") || "").trim();

      if (topicId) {
        openTopicPage(topicId, {
          scroll_post_id: postId || 0,
          highlight_new: postId ? 1 : 0
        });
        return;
      }

      if (forumId) {
        render("agora", forumId, "");
        return;
      }

      if (view) {
        render(view, 0, "");
        return;
      }
    } catch (e) {}

    render("new", 0, "");
  }

  window.IA_DISCUSS_ROUTER = { mount };
})();
