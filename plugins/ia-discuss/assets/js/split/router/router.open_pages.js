    function openTopicPage(topicId, opts) {
      topicId = parseInt(topicId || "0", 10) || 0;
      if (!topicId) return;

      opts = opts || {};
      const noHist = !!opts.no_history;

      // Save the feed scroll position immediately before switching the view.
      if (!noHist) {
        saveFeedScroll();
        setParams({
          iad_view: null,
          iad_forum: null,
          iad_forum_name: null,
            iad_topic: String(topicId),
          iad_post: opts.scroll_post_id ? String(parseInt(opts.scroll_post_id, 10) || 0) : null,
          iad_q: null
        });
      }

      window.IA_DISCUSS_UI_TOPIC.renderInto(root, topicId, opts);
    }

    function openSearchPage(q, opts) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      opts = opts || {};
      const noHist = !!opts.no_history;

      // remember where we came from
      inSearch = true;
      lastSearchQ = String(q || "").trim();
      searchPrev = { view: lastListView, forum_id: lastForumId, forum_name: lastForumName };

      // update URL (search is its own view)
      if (!noHist) {
        setParams({
          iad_view: "search",
          iad_q: lastSearchQ,
          iad_forum: null,
          iad_forum_name: null,
            iad_topic: null,
          iad_post: null
        });
      }


      try {
        window.IA_DISCUSS_UI_SEARCH.renderSearchPageInto(mountEl, lastSearchQ);
      } catch (e) {}

    }

    root.querySelectorAll(".iad-tab").forEach((b) => {
      b.addEventListener("click", () => {
        const v = b.getAttribute("data-view");
        if (!v) return;
        render(v, 0, "");
      });
    });

    function viewToTab(v) {
      if (v === 'noreplies') return 'no_replies';
      if (v === 'replies' || v === 'unread') return 'latest_replies';
      return 'new_posts';
    }

    // Random topic (from current list context)
