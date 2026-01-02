"use strict";
          `;

          mountEl.querySelectorAll("[data-forum-id]").forEach((b) => {
            b.addEventListener("click", () => {
              var id = parseInt(b.getAttribute("data-forum-id") || "0", 10);
              var nm = b.getAttribute("data-forum-name") || "";
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
      var mountEl = qs("[data-iad-view]", root);
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
        var v = b.getAttribute("data-view");
        if (!v) return;
        render(v, 0, "");
      });
    });

    window.addEventListener("iad:open_topic_page", (e) => {
      var d = (e && e.detail) ? e.detail : {};
      var tid = d.topic_id ? d.topic_id : 0;
      if (!tid) return;

      // ✅ allow post scroll
      var scrollPostId = d.scroll_post_id ? parseInt(d.scroll_post_id, 10) : 0;
      var highlight = d.highlight_new ? 1 : 0;

      openTopicPage(tid, {
        scroll_post_id: scrollPostId || 0,
        highlight_new: highlight ? 1 : 0
      });
    });

    window.addEventListener("iad:open_agora", (e) => {
      var fid = e.detail && e.detail.forum_id ? parseInt(e.detail.forum_id, 10) : 0;
;
