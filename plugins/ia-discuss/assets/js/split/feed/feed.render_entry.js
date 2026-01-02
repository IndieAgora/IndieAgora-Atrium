"use strict";

  function renderFeed(root, view, forumId) {
    var mount = root ? root.querySelector("[data-iad-view]") : null;
    renderFeedInto(mount, view, forumId);
  }

  window.IA_DISCUSS_UI_FEED = { renderFeed, renderFeedInto };
;
