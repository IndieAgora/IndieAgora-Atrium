"use strict";
      var nm  = (e.detail && e.detail.forum_name) ? String(e.detail.forum_name) : "";
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
      var q = (e.detail && e.detail.q) ? String(e.detail.q) : "";
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
      var d = e.detail || {};
      var view = d.view || "new";
      var forumId = parseInt(d.forum_id || "0", 10) || 0;
      var mountEl = d.mount || null;

      if (inAgora) return window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, forumId);
      render(view, 0, "");
    });

    // composer mount
    window.addEventListener("iad:mount_composer", (e) => {
      var d = e.detail || {};
      if (!d.mount) return;

      var mode = d.mode || "topic";
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

          var body = payload.body || "";
          if (payload.attachments && payload.attachments.length) {
            var json = JSON.stringify(payload.attachments);
            var b64 = btoa(unescape(encodeURIComponent(json)));
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
;
