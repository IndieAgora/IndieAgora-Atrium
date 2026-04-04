  function bindSearchBox(root) {
    const input = qs("[data-iad-search]", root);
    if (!input || input.__iadBound) return;
    input.__iadBound = true;

    const box = ensureSuggestBox(root, input);

    // Keep portal aligned
    const onReflow = () => {
      if (!box) return;
      if (box.style.display === "none") return;
      positionSuggestBox(box, input);
    };
    window.addEventListener("scroll", onReflow, true);
    window.addEventListener("resize", onReflow);

    const runSuggest = debounce((q) => {
      q = String(q || "").trim();
      if (!q || q.length < 2) return hideSuggest(box);

      API.post("ia_discuss_search_suggest", { q }).then((res) => {
        if (!res || !res.success) return hideSuggest(box);

        const d = res.data || {};
        const users = Array.isArray(d.users) ? d.users : [];
        const agoras = Array.isArray(d.agoras) ? d.agoras : [];
        const topics = Array.isArray(d.topics) ? d.topics : [];
        const replies = Array.isArray(d.replies) ? d.replies : [];

        // Suggestions dropdown
        // Order required by Atrium UX: Replies, Topics, Agoras, Users
        const parts = [];

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

        if (users.length) {
          parts.push(suggestGroup("Users", users.map((u) => `
            <button type="button" class="iad-sug-row" data-iad-sug-user data-user-id="${u.user_id}" data-username="${esc(u.display || u.username || "")}">
              ${avatarHTML(u.username, u.avatar_url || "")}
              <div class="iad-sug-text">
                <div class="iad-sug-main">${esc(u.display || u.username || "")}</div>
              </div>
              <div class="iad-sug-icon" aria-hidden="true">${iconSVG("user")}</div>
            </button>
          `).join("")));
        }

        if (!parts.length) {
          hideSuggest(box);
          return;
        }

        box.innerHTML = parts.join("");
        showSuggest(box, input);
      });
    }, 160);

    const runLive = debounce((q) => {
      q = String(q || "").trim();
      if (!q || q.length < 2) return;
      window.dispatchEvent(new CustomEvent("iad:search_live", { detail: { q } }));
    }, 220);


    input.addEventListener("input", () => { runSuggest(input.value); runLive(input.value); });

    input.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        hideSuggest(box);
        return;
      }
      if (e.key === "Enter") {
        e.preventDefault();
        const q = String(input.value || "").trim();
        if (q.length >= 2) {
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_search", { detail: { q } }));
        }
      }
    });

    document.addEventListener("click", (e) => {
      if (!box) return;
      // Portal is in <body>; close if click isn't inside the input or the dropdown.
      if (box.contains(e.target)) return;
      if (input.contains(e.target)) return;
      hideSuggest(box);
    });

    if (box) {
      box.addEventListener("click", (e) => {
        const btn = e.target.closest("button");
        if (!btn) return;

        if (btn.hasAttribute("data-iad-sug-open-search")) {
          const q = btn.getAttribute("data-q") || "";
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_search", { detail: { q } }));
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
