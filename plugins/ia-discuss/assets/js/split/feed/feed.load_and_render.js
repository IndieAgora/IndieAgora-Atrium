"use strict";

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

  async function loadFeed(view, forumId) {
    var tab = "new_posts";
    var res = await API.post("ia_discuss_feed", {
      tab,
      offset: 0,
      forum_id: forumId || 0
    });

    if (!res || !res.success) return { items: [], error: true };
    return res.data;
  }

  function renderFeedInto(mount, view, forumId) {
    if (!mount) return;

    mount.innerHTML = `<div class="iad-loading">Loading…</div>`;

    loadFeed(view, forumId).then((data) => {
      var items = data.items || [];

      if (view === "unread") {
        items = items.filter((it) => !STATE.isRead(it.topic_id));
      }

      mount.innerHTML = `
        <div class="iad-feed">
          <div class="iad-feed-list">
            ${items.length ? items.map((it) => feedCard(it, view)).join("") : `<div class="iad-empty">Nothing here yet.</div>`}
          </div>
        </div>
      `;

      function openTopicFromEvent(e, scroll) {
        e.preventDefault();
        e.stopPropagation();
        var card = e.target.closest("[data-topic-id]");
        if (!card) return;
        var tid = parseInt(card.getAttribute("data-topic-id") || "0", 10);
        if (!tid) return;

        window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
          detail: { topic_id: tid, scroll: scroll || "" }
        }));
      }

      // title/excerpt -> open topic (top)
      mount.querySelectorAll("[data-open-topic-title]").forEach((el) => el.addEventListener("click", (e) => openTopicFromEvent(e, "")));
      mount.querySelectorAll("[data-open-topic-excerpt]").forEach((el) => el.addEventListener("click", (e) => openTopicFromEvent(e, "")));

      // comments icon -> open topic AND scroll to comments
      mount.querySelectorAll("[data-open-topic-comments]").forEach((btn) => btn.addEventListener("click", (e) => openTopicFromEvent(e, "comments")));

      // open agora
      mount.querySelectorAll("[data-open-agora]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          var fid = parseInt(btn.getAttribute("data-forum-id") || "0", 10);
          if (!fid) return;

          var forumName = "";
          var card = btn.closest("[data-topic-id]");
          if (card) forumName = card.getAttribute("data-forum-name") || "";

          window.dispatchEvent(new CustomEvent("iad:open_agora", {
            detail: { forum_id: fid, forum_name: forumName }
          }));
        });
      });

      // open user
      mount.querySelectorAll("[data-open-user]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          openConnectProfile({
;
