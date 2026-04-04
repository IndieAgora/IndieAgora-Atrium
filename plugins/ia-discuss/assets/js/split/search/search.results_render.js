  function resultsShellHTML(q) {
    return `
      <div class="iad-search-page">
        <div class="iad-search-top">
          <button type="button" class="iad-x" data-iad-search-back aria-label="Back">←</button>
          <div class="iad-search-title">Search: <span class="iad-search-q">“${esc(q)}”</span></div>
        </div>

        <div class="iad-search-tabs" role="tablist">
          <button class="iad-stab is-active" data-type="topics" aria-selected="true">Topics</button>
          <button class="iad-stab" data-type="replies" aria-selected="false">Replies</button>
          <button class="iad-stab" data-type="agoras" aria-selected="false">Agoras</button>
          <button class="iad-stab" data-type="users" aria-selected="false">Users</button>
        </div>

        <div class="iad-search-results" data-iad-search-results>
          <div class="iad-loading">Loading…</div>
        </div>
      </div>
    `;
  }

  function setActiveType(mount, type) {
    mount.querySelectorAll(".iad-stab").forEach((b) => {
      const on = b.getAttribute("data-type") === type;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-selected", on ? "true" : "false");
    });
  }

  function iconBubble(type) {
    return `<div class="iad-sr-ico" aria-hidden="true">${iconSVG(type)}</div>`;
  }

  function renderResultRow(type, item, idx) {
    const altClass = (idx % 2 === 1) ? " is-alt" : "";

    if (type === "users") {
      return `
        <button type="button" class="iad-card iad-sr-row${altClass}" data-sr-user data-user-id="${item.user_id}" data-username="${esc(item.display || item.username || "")}">
          <div class="iad-sr-left">${avatarHTML(item.username, item.avatar_url || "")}</div>
          <div class="iad-sr-mid">
            <div class="iad-sr-title">${esc(item.display || item.username || "")}</div>
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

