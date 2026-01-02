"use strict";
          class="iad-attachpill"
          href="${esc(url)}"
          target="_blank"
          rel="noopener noreferrer"
          title="${esc(filename)}">
          ${esc(filename)}
        </a>
      `;
    }).join("");

    return pills ? `<div class="iad-attachrow">${pills}</div>` : "";
  }

  function feedCard(item, view) {
    var author = item.topic_poster_username || ("user#" + (item.topic_poster_id || 0));
    var authorId = parseInt(item.topic_poster_id || "0", 10) || 0;
    var me = currentUserId();

    var ago = timeAgo(item.last_post_time || item.topic_time);

    var forumId = parseInt(item.forum_id || "0", 10) || 0;
    var forumName = item.forum_name || "agora";

    var canEdit = !!(me && authorId && me === authorId);

    // ✅ ADDED: inline uploaded media (video first, then image)
    // This does NOT replace your link-based media block; it only renders attachments.
    var inlineAttMedia = attachmentInlineMediaHTML(item);

    return `
      <article class="iad-card"
        data-topic-id="${item.topic_id}"
        data-forum-id="${forumId}"
        data-forum-name="${esc(forumName)}"
        data-author-id="${esc(String(authorId))}">

        <div class="iad-card-main">
          <div class="iad-card-meta">
            <button
              type="button"
              class="iad-sub iad-agora-link"
              data-open-agora
              data-forum-id="${forumId}"
              aria-label="Open agora ${esc(forumName)}"
              title="Open agora">
              agora/${esc(forumName)}
            </button>

            <span class="iad-dotsep">•</span>

            <button
              type="button"
              class="iad-user-link"
              data-open-user
              data-username="${esc(author)}"
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
;
