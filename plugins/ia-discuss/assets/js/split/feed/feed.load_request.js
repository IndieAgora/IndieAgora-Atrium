  async function loadFeed(view, forumId, offset, orderKey, page) {
    let tab = "new_posts";
    if (view === "noreplies") tab = "no_replies";
    if (view === "replies" || view === "unread") tab = "latest_replies";
    if (view === "mytopics") tab = "my_topics";
    if (view === "myreplies") tab = "my_replies";
    if (view === "myhistory") tab = "my_history";

    const payload = {
      tab,
      offset: offset || 0,
      forum_id: forumId || 0,
      order: orderKey || ''
    };

    if (page && parseInt(page, 10) > 0) {
      payload.page = parseInt(page, 10);
    }

    const res = await API.post("ia_discuss_feed", payload);

    if (!res || !res.success) {
      return {
        items: [],
        has_more: false,
        next_offset: (offset || 0),
        total_count: 0,
        total_pages: 0,
        current_page: page || 1,
        error: true
      };
    }
    return res.data || {};
  }
