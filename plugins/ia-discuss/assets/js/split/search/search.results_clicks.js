"use strict";
          <div class="iad-sr-mid">
            <div class="iad-sr-title">${esc(item.username || "")}</div>
            <div class="iad-sr-sub">User</div>
          </div>
          <div class="iad-sr-right">${iconBubble("user")}</div>
        </button>
      `;
    }

    if (type === "agoras") {
      return `
        <button type="button" class="iad-card iad-sr-row${altClass}" data-sr-agora data-forum-id="${item.forum_id}" data-forum-name="${esc(item.forum_name || "")}">
          <div class="iad-sr-left">${iconBubble("agora")}</div>
          <div class="iad-sr-mid">
            <div class="iad-sr-title">agora/${esc(item.forum_name || "")}</div>
            <div class="iad-sr-sub">${esc(stripMarkup(item.forum_desc || ""))}</div>
          </div>
        </button>
      `;
    }

    if (type === "replies") {
      return `
        <button type="button" class="iad-card iad-sr-row${altClass}" data-sr-reply data-topic-id="${item.topic_id}" data-post-id="${item.post_id}">
          <div class="iad-sr-left">${iconBubble("reply")}</div>
          <div class="iad-sr-mid">
            <div class="iad-sr-title">${esc(item.topic_title || "")}</div>
            <div class="iad-sr-sub">agora/${esc(item.forum_name || "")} • by ${esc(item.username || "")} • ${esc(timeAgo(item.post_time || 0))}</div>
            <div class="iad-sr-sn">${esc(stripMarkup(item.snippet || ""))}</div>
          </div>
        </button>
      `;
    }

    // topics
    return `
      <button type="button" class="iad-card iad-sr-row${altClass}" data-sr-topic data-topic-id="${item.topic_id}">
        <div class="iad-sr-left">${iconBubble("topic")}</div>
        <div class="iad-sr-mid">
          <div class="iad-sr-title">${esc(item.topic_title || "")}</div>
          <div class="iad-sr-sub">agora/${esc(item.forum_name || "")} • by ${esc(item.username || "")} • ${esc(timeAgo(item.topic_time || 0))}</div>
          <div class="iad-sr-sn">${esc(stripMarkup(item.snippet || ""))}</div>
        </div>
      </button>
    `;
  }

  function bindResultsClicks(mount) {
    var box = qs("[data-iad-search-results]", mount);
    if (!box || box.__iadBound) return;
    box.__iadBound = true;

    box.addEventListener("click", (e) => {
      var row = e.target.closest("button");
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
;
