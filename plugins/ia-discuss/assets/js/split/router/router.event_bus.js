    (function bindRandomTopic(){
      const btn = root.querySelector('[data-iad-random-topic]');
      if (!btn || !window.IA_DISCUSS_API) return;

      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        // Determine current context: list view + (optional) agora forum.
        const v = (inSearch ? (searchPrev && searchPrev.view) : lastListView) || 'new';
        const forum = (v === 'agora' || inAgora) ? (lastForumId || 0) : 0;

        btn.disabled = true;
        const oldTxt = btn.textContent;
        btn.textContent = 'Random…';

        let tid = 0;
        try {
          const res = await window.IA_DISCUSS_API.post('ia_discuss_random_topic', {
            tab: viewToTab(v),
            forum_id: forum,
            q: ''
          });
          if (res && res.success && res.data && res.data.topic_id) {
            tid = parseInt(res.data.topic_id, 10) || 0;
          }
        } catch (err) {}

        btn.disabled = false;
        btn.textContent = oldTxt || 'Random';

        if (tid) {
          // Track random history for topic Prev/Next navigation.
          try {
            const baseView = (inSearch ? (searchPrev && searchPrev.view) : lastListView) || 'new';
            const baseForum = (baseView === 'agora' || inAgora) ? (lastForumId || 0) : 0;
            const s = (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.get === 'function') ? window.IA_DISCUSS_STATE.get() : {};
            const existing = s && s.topic_nav && s.topic_nav.view === 'random' ? s.topic_nav : null;
            const ids = existing && Array.isArray(existing.ids) ? existing.ids.slice(0) : [];
            if (!ids.includes(tid)) ids.push(tid);
            if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
              window.IA_DISCUSS_STATE.set({
                topic_nav: {
                  view: 'random',
                  base_view: String(baseView),
                  forum_id: baseForum || 0,
                  ids: ids,
                  ts: Date.now()
                }
              });
            }
          } catch (eNav) {}

          openTopicPage(tid, {});
        }
      });
    })();

    window.addEventListener("iad:open_topic_page", (e) => {
      const d = (e && e.detail) ? e.detail : {};
      const tid = d.topic_id ? d.topic_id : 0;
      if (!tid) return;

      // ✅ allow post scroll
      const scrollPostId = d.scroll_post_id ? parseInt(d.scroll_post_id, 10) : 0;
      const highlight = d.highlight_new ? 1 : 0;

      // Allow callers (e.g. feed reply icon) to request opening the reply composer.
      const openReply = d.open_reply ? 1 : 0;

      // Allow callers to jump straight to the latest reply (fetch last page + highlight)
      const gotoLast = d.goto_last ? 1 : 0;

      openTopicPage(tid, {
        scroll_post_id: scrollPostId || 0,
        highlight_new: highlight ? 1 : 0,
        open_reply: openReply ? 1 : 0,
        goto_last: gotoLast ? 1 : 0
      });
    });

    window.addEventListener("iad:open_agora", (e) => {
      const fid = e.detail && e.detail.forum_id ? parseInt(e.detail.forum_id, 10) : 0;
      const nm  = (e.detail && e.detail.forum_name) ? String(e.detail.forum_name) : "";
      if (!fid) return;
      render("agora", fid, nm);
    });

    window.addEventListener("iad:go_agoras", () => { render("agoras", 0, ""); });
    window.addEventListener("iad:go_moderation", () => { render("moderation", 0, ""); });

    // Close topic view: restore the previous Discuss view (no browser back)
    window.addEventListener("iad:topic_close", () => {
      // Clear topic params without pushing history
      setParams({
        iad_topic: null,
        iad_post: null
      }, true);

      if (inSearch) { openSearchPage(lastSearchQ, { no_history: true }); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agora" && lastForumId) { render("agora", lastForumId, lastForumName || "", { no_history: true }); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agoras") { render("agoras", 0, "", { no_history: true }); restoreFeedScrollAfterFeed(); return; }
      render(lastListView || "new", 0, "", { no_history: true });
      restoreFeedScrollAfterFeed();
    });


    window.addEventListener("iad:topic_back", () => {
      // Prefer real browser history so Android back / iOS swipe-back behave naturally.
      try {
        if (window.history && window.history.length > 1) {
          window.history.back();
          return;
        }
      } catch (e) {}
      if (inSearch) { openSearchPage(lastSearchQ); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agora" && lastForumId) { render("agora", lastForumId, lastForumName || ""); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agoras") { render("agoras", 0, ""); restoreFeedScrollAfterFeed(); return; }
      render(lastListView || "new", 0, "");
      restoreFeedScrollAfterFeed();
    });

    // ✅ NEW: open search
    window.addEventListener("iad:open_search", (e) => {
      const q = (e.detail && e.detail.q) ? String(e.detail.q) : "";
      if (!q || q.trim().length < 2) return;
      openSearchPage(q);
    });

    // live search updates while typing (no history spam)
    window.addEventListener("iad:search_live", (e) => {
      const q = (e.detail && e.detail.q) ? String(e.detail.q) : "";
      if (!q || q.trim().length < 2) return;

      // If we're already in search view, update results + URL via replaceState
      const view = getParam("iad_view") || "";
      if (view === "search") {
        try {
          setParams({ iad_view: "search", iad_q: q.trim() }, true);
        } catch (err) {}
        openSearchPage(q, { no_history: 1 });
        return;
      }

      // If not in search yet, open it normally
      openSearchPage(q);
    });


    // ✅ NEW: back from search
    window.addEventListener("iad:search_back", () => {
      try {
        if (window.history && window.history.length > 1) {
          window.history.back();
          return;
        }
      } catch (e) {}
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

      function composerError(ui, msg) {
        try {
          if (ui && typeof ui.setError === "function") return ui.setError(msg);
          if (ui && typeof ui.error === "function") return ui.error(msg);
        } catch (e) {}
      }

      window.IA_DISCUSS_UI_COMPOSER.bindComposer(d.mount, {
        prefillBody: d.prefillBody || "",
        mode,
        topicId: ctx.topic_id || 0,
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
            return window.IA_DISCUSS_API.post("ia_discuss_edit_post", {
              post_id: ctx.edit_post_id,
              body
            }).then((res) => {
              if (!res || !res.success) {
                composerError(ui, (res && res.data && res.data.message) ? res.data.message : "Edit failed");
                return;
              }
              try { ui.clear(); } catch (e) {}
              window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));
              openTopicPage(effectiveTopicId, {});
            });
            return;
          }

          // Topic (new thread) flow
          if (payload.mode === "topic") {
            const forumId = effectiveForumId;
            const title = (payload.title || "").trim();
            if (!forumId) { composerError(ui, "Missing forum_id"); return; }
            if (!title) { composerError(ui, "Title required"); return; }
            if (!body.trim()) { composerError(ui, "Body required"); return; }
            return window.IA_DISCUSS_API.post("ia_discuss_new_topic", {
              forum_id: forumId,
              title,
              body,
              notify: (payload && payload.notify != null) ? payload.notify : 1
            }).then((res) => {
              if (!res || !res.success) {
                composerError(ui, (res && res.data && res.data.message) ? res.data.message : "New topic failed");
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
          return window.IA_DISCUSS_API.post("ia_discuss_reply", {
            topic_id: effectiveTopicId,
            body
          }).then((res) => {
            if (!res || !res.success) {
              composerError(ui, (res && res.data && res.data.message) ? res.data.message : "Reply failed");
              return;
            }

            const postId = res.data && res.data.post_id ? parseInt(res.data.post_id, 10) : 0;

            try { ui.clear(); } catch (e) {}
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
    // Browser back/forward integration
    // -----------------------------
