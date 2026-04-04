  function bindResultsClicks(mount) {
    const box = qs("[data-iad-search-results]", mount);
    if (!box || box.__iadBound) return;
    box.__iadBound = true;

    box.addEventListener("click", (e) => {
      const row = e.target.closest("button");
      if (!row) return;

      if (row.hasAttribute("data-sr-user")) {
        openConnectProfile({
          username: row.getAttribute("data-username") || "",
          user_id: row.getAttribute("data-user-id") || "0"
        });
        return;
      }

      if (row.hasAttribute("data-sr-agora")) {
        window.dispatchEvent(new CustomEvent("iad:open_agora", {
          detail: {
            forum_id: row.getAttribute("data-forum-id") || "0",
            forum_name: row.getAttribute("data-forum-name") || ""
          }
        }));
        return;
      }

      if (row.hasAttribute("data-sr-topic")) {
        window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
          detail: { topic_id: row.getAttribute("data-topic-id") || "0" }
        }));
        return;
      }

      if (row.hasAttribute("data-sr-reply")) {
        window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
          detail: {
            topic_id: row.getAttribute("data-topic-id") || "0",
            scroll_post_id: row.getAttribute("data-post-id") || "0",
            highlight_new: 1
          }
        }));
        return;
      }
    });
  }

