    function routeFromURL(options) {
      options = options || {};
      try {
        const u = new URL(window.location.href);
        const topicId = parseInt(u.searchParams.get("iad_topic") || "0", 10) || 0;
        const postId  = parseInt(u.searchParams.get("iad_post")  || "0", 10) || 0;
        const forumId = parseInt(u.searchParams.get("iad_forum") || "0", 10) || 0;
        const forumName = String(u.searchParams.get("iad_forum_name") || "").trim();
        const view    = String(u.searchParams.get("iad_view") || "").trim();
        const q       = String(u.searchParams.get("iad_q") || "").trim();

        if (topicId) {
          openTopicPage(topicId, {
            scroll_post_id: postId || 0,
            highlight_new: postId ? 1 : 0,
            no_history: 1
          });
          return true;
        }

        if (view === "search" && q) {
          openSearchPage(q, { no_history: 1 });
          return true;
        }

        if (forumId) {
          render("agora", forumId, forumName || "", { no_history: 1 });
          return true;
        }

        if (view) {
          render(view, 0, "", { no_history: 1 });
          return true;
        }
      } catch (e) {}

      render("new", 0, "", { no_history: 1 });
      return true;
    }

    // Respond to Android back button / iOS swipe-back / browser back & forward
    window.addEventListener("popstate", () => {
      routeFromURL({ pop: 1 });
    });

    // -----------------------------
    // Initial view (supports deep links)
    // -----------------------------
    routeFromURL({ init: 1 });
  }
