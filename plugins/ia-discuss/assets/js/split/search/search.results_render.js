"use strict";
          return;
        }

        if (btn.hasAttribute("data-iad-sug-user")) {
          hideSuggest(box);
          openConnectProfile({
            username: btn.getAttribute("data-username") || "",
            user_id: btn.getAttribute("data-user-id") || "0"
          });
          return;
        }

        if (btn.hasAttribute("data-iad-sug-agora")) {
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_agora", {
            detail: {
              forum_id: btn.getAttribute("data-forum-id") || "0",
              forum_name: btn.getAttribute("data-forum-name") || ""
            }
          }));
          return;
        }

        if (btn.hasAttribute("data-iad-sug-topic")) {
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
            detail: { topic_id: btn.getAttribute("data-topic-id") || "0" }
          }));
          return;
        }

        if (btn.hasAttribute("data-iad-sug-reply")) {
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
            detail: {
              topic_id: btn.getAttribute("data-topic-id") || "0",
              scroll_post_id: btn.getAttribute("data-post-id") || "0",
              highlight_new: 1
            }
          }));
          return;
        }
      });
    }
  }

  // ---------------------------
  // Results page (tabbed)
  // ---------------------------
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
      var on = b.getAttribute("data-type") === type;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-selected", on ? "true" : "false");
    });
  }

  function iconBubble(type) {
    return `<div class="iad-sr-ico" aria-hidden="true">${iconSVG(type)}</div>`;
  }

  function renderResultRow(type, item, idx) {
    var altClass = (idx % 2 === 1) ? " is-alt" : "";

    if (type === "users") {
      return `
        <button type="button" class="iad-card iad-sr-row${altClass}" data-sr-user data-user-id="${item.user_id}" data-username="${esc(item.username || "")}">
          <div class="iad-sr-left">${avatarHTML(item.username, item.avatar_url || "")}</div>
;
