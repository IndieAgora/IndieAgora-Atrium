  function feedCard(item, view) {
    // Compatibility: some routes may still pass legacy view keys (e.g. "unread")
    // but should behave like Replies.
    const showLast = (view === 'replies' || view === 'unread');

    const username = showLast
      ? (item.last_poster_username || ("user#" + (item.last_poster_id || 0)))
      : (item.topic_poster_username || ("user#" + (item.topic_poster_id || 0)));

    const author = showLast
      ? (item.last_poster_display || item.last_poster_username || ("user#" + (item.last_poster_id || 0)))
      : (item.topic_poster_display || item.topic_poster_username || ("user#" + (item.topic_poster_id || 0)));

    const authorId = showLast
      ? (parseInt(item.last_poster_id || "0", 10) || 0)
      : (parseInt(item.topic_poster_id || "0", 10) || 0);

    const authorAvatar = showLast
      ? (item.last_poster_avatar_url || "")
      : (item.topic_poster_avatar_url || "");
    const me = currentUserId();

    const ago = timeAgo(item.last_post_time || item.topic_time);

    const forumId = parseInt(item.forum_id || "0", 10) || 0;
    const forumName = item.forum_name || "agora";

    const canEdit = !!(me && !showLast && authorId && me === authorId);

    // Read/unread glow
    let readClass = '';
    try {
      const isRead = (STATE && typeof STATE.isRead === 'function') ? !!STATE.isRead(item.topic_id) : false;
      readClass = isRead ? ' is-read' : ' is-unread';
    } catch (eR) {}

    const openPostId = showLast
      ? (parseInt(item.last_post_id || "0", 10) || parseInt(item.first_post_id || "0", 10) || 0)
      : (parseInt(item.first_post_id || "0", 10) || 0);

    // ✅ ADDED: inline uploaded media (video first, then image)
    // This does NOT replace your link-based media block; it only renders attachments.
    const inlineAttMedia = attachmentInlineMediaHTML(item);

    return `
      <article class="iad-card${readClass}"
        data-topic-id="${item.topic_id}"
        data-first-post-id="${esc(String(item.first_post_id || 0))}"
        data-last-post-id="${esc(String(item.last_post_id || 0))}"
        data-open-post-id="${esc(String(openPostId || 0))}"
        data-forum-id="${forumId}"
        data-forum-name="${esc(forumName)}"
        data-author-id="${esc(String(authorId))}">

        <div class="iad-card-main">
          <div class="iad-card-meta">
            ${authorAvatar ? `<img class="iad-uava" src="${esc(authorAvatar)}" alt="" />` : ""}
            <button
              type="button"
              class="iad-sub iad-agora-link"
              data-open-agora
              data-forum-id="${forumId}"
              data-forum-name="${esc(forumName)}"
              aria-label="Open agora ${esc(forumName)}"
              title="Open agora">
              agora/${esc(forumName)}
            </button>

            <span class="iad-dotsep">•</span>

            <button
              type="button"
              class="iad-user-link"
              data-open-user
              data-username="${esc(username)}"
              data-user-id="${esc(String(authorId))}"
              aria-label="Open profile ${esc(author)}"
              title="Open profile">
              ${esc(author)}
            </button>

            <span class="iad-dotsep">•</span>
            <span class="iad-time">${esc(ago)}</span>
          </div>

          <h3 class="iad-card-title" data-open-topic-title>${esc(item.topic_title || "")}</h3>

          ${inlineAttMedia ? `<div class="iad-attwrap">${inlineAttMedia}</div>` : ""}

          <div class="iad-card-excerpt" data-open-topic-excerpt>${item.excerpt_html || ""}</div>

          ${mediaBlockHTML(item, view)}

          ${attachmentPillsHTML(item)}

          <div class="iad-card-actions">
            <!-- Reply/comments icon (scrolls to comments) -->
            <button type="button" class="iad-iconbtn" data-open-topic-comments title="Open comments" aria-label="Open comments">
              ${ico("reply")}
            </button>

            <!-- Copy link -->
            <button type="button" class="iad-iconbtn" data-copy-topic-link title="Copy link" aria-label="Copy link">
              ${ico("link")}
            </button>

            <!-- Share to Connect -->
            <button type="button" class="iad-iconbtn" data-share-topic title="Share to Connect" aria-label="Share to Connect">
              ${ico("share")}
            </button>

            <!-- Last reply -->
            <button type="button" class="iad-pill is-muted" data-open-topic-lastreply title="Last reply" aria-label="Last reply">
              ${ico("last")} <span>Last reply</span>
            </button>

            ${canEdit ? `
              <button type="button" class="iad-iconbtn" data-edit-topic title="Edit (coming soon)" aria-label="Edit">
                ${ico("edit")}
              </button>
            ` : ""}

            <span class="iad-muted">${esc(String(item.views || 0))} views</span>
          </div>
        </div>
      </article>
    `;
  }

