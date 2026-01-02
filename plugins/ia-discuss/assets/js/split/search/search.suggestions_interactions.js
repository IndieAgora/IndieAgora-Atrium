"use strict";
          </button>
        `);

        if (users.length) {
          parts.push(suggestGroup("Users", users.map((u) => `
            <button type="button" class="iad-sug-row" data-iad-sug-user data-user-id="${u.user_id}" data-username="${esc(u.username || "")}">
              ${avatarHTML(u.username, u.avatar_url || "")}
              <div class="iad-sug-text">
                <div class="iad-sug-main">${esc(u.username || "")}</div>
              </div>
              <div class="iad-sug-icon" aria-hidden="true">${iconSVG("user")}</div>
            </button>
          `).join("")));
        }

        if (agoras.length) {
          parts.push(suggestGroup("Agoras", agoras.map((a) => `
            <button type="button" class="iad-sug-row" data-iad-sug-agora data-forum-id="${a.forum_id}" data-forum-name="${esc(a.forum_name || "")}">
              <div class="iad-sug-ico">${iconSVG("agora")}</div>
              <div class="iad-sug-text">
                <div class="iad-sug-main">agora/${esc(a.forum_name || "")}</div>
              </div>
            </button>
          `).join("")));
        }

        if (topics.length) {
          parts.push(suggestGroup("Topics", topics.map((t) => `
            <button type="button" class="iad-sug-row" data-iad-sug-topic data-topic-id="${t.topic_id}">
              <div class="iad-sug-ico">${iconSVG("topic")}</div>
              <div class="iad-sug-text">
                <div class="iad-sug-main">${esc(t.topic_title || "")}</div>
                <div class="iad-sug-sub">agora/${esc(t.forum_name || "")} • ${esc(timeAgo(t.topic_time || 0))}</div>
                ${t.snippet ? `<div class="iad-sug-sn">${esc(stripMarkup(t.snippet))}</div>` : ``}
              </div>
            </button>
          `).join("")));
        }

        if (replies.length) {
          parts.push(suggestGroup("Replies", replies.map((r) => `
            <button type="button" class="iad-sug-row" data-iad-sug-reply data-topic-id="${r.topic_id}" data-post-id="${r.post_id}">
              <div class="iad-sug-ico">${iconSVG("reply")}</div>
              <div class="iad-sug-text">
                <div class="iad-sug-main">${esc(r.topic_title || "")}</div>
                <div class="iad-sug-sub">by ${esc(r.username || "")} • ${esc(timeAgo(r.post_time || 0))}</div>
                <div class="iad-sug-sn">${esc(stripMarkup(r.snippet || ""))}</div>
              </div>
            </button>
          `).join("")));
        }

        box.innerHTML = parts.join("");
        showSuggest(box);
      });
    }, 160);

    input.addEventListener("input", () => runSuggest(input.value));

    input.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        hideSuggest(box);
        return;
      }
      if (e.key === "Enter") {
        e.preventDefault();
        var q = String(input.value || "").trim();
        if (q.length >= 2) {
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_search", { detail: { q } }));
        }
      }
    });

    document.addEventListener("click", (e) => {
      if (!box) return;
      var wrap = qs(".iad-search", root);
      if (!wrap) return;
      if (!wrap.contains(e.target)) hideSuggest(box);
    });

    if (box) {
      box.addEventListener("click", (e) => {
        var btn = e.target.closest("button");
        if (!btn) return;

        if (btn.hasAttribute("data-iad-sug-open-search")) {
          var q = btn.getAttribute("data-q") || "";
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_search", { detail: { q } }));
;
