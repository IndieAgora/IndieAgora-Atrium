(function () {
  "use strict";

  const CORE = window.IA_DISCUSS_CORE;
  const API  = window.IA_DISCUSS_API;

  const qs = CORE.qs;
  const esc = CORE.esc;
  const timeAgo = CORE.timeAgo;

  function debounce(fn, ms) {
    let t = 0;
    return function () {
      const args = arguments;
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), ms);
    };
  }

  // ---------------------------------------------
  // Strip noisy markup (escaped HTML + phpBB BBCode)
  // ---------------------------------------------
  function stripMarkup(input) {
    let s = String(input || "");

    // decode a few common entities first so we can strip tags reliably
    s = s
      .replace(/&lt;/g, "<")
      .replace(/&gt;/g, ">")
      .replace(/&amp;/g, "&")
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'");

    // remove HTML tags
    s = s.replace(/<[^>]*>/g, " ");

    // remove common BBCode tags: [b], [i], [quote], [url=...], etc.
    // (also remove nested [tag=...] variants)
    s = s.replace(/\[\/?[a-z0-9_*]+(?:=[^\]]+)?\]/gi, " ");

    // remove phpBB attachment / media leftovers
    s = s.replace(/\[attachment[^\]]*\][\s\S]*?\[\/attachment\]/gi, " ");
    s = s.replace(/\[img\][\s\S]*?\[\/img\]/gi, " ");
    s = s.replace(/\[url[^\]]*\]([\s\S]*?)\[\/url\]/gi, "$1");

    // collapse whitespace
    s = s.replace(/\s+/g, " ").trim();

    // keep snippets compact
    if (s.length > 180) s = s.slice(0, 177) + "...";

    return s;
  }

  function avatarHTML(username, avatarUrl) {
    const u = String(username || "").trim();
    const initial = u ? u[0].toUpperCase() : "?";
    if (avatarUrl) {
      return `<img class="iad-av" src="${esc(avatarUrl)}" alt="" />`;
    }
    return `<div class="iad-av iad-av-fallback">${esc(initial)}</div>`;
  }

  // ---------------------------------------------
  // Reddit-ish inline SVG icons (small, clean)
  // ---------------------------------------------
  function iconSVG(type) {
    // Using currentColor so CSS controls the look.
    if (type === "topic") {
      return `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M7 7h10a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 2v-2H7a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm0 2v9h10V9H7Zm2 2h6v2H9v-2Zm0 3h4v2H9v-2Z"/>
        </svg>`;
    }
    if (type === "reply") {
      return `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M10 9V5L3 12l7 7v-4h4c4 0 7 2 7 6v-2c0-6-3-10-11-10h-1Z"/>
        </svg>`;
    }
    if (type === "agora") {
      return `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M12 2a8 8 0 0 1 8 8c0 3.6-2.4 6.7-5.7 7.7L15 22l-4.2-3.2A8 8 0 1 1 12 2Zm0 2a6 6 0 1 0 0 12 6.2 6.2 0 0 0 1.7-.25l.7-.2L14.8 18l.2 1.3 1.9-1.5.3-.2.4-.1A6 6 0 0 0 18 10a6 6 0 0 0-6-6Z"/>
        </svg>`;
    }
    // user
    return `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z"/>
      </svg>`;
  }

  // -------------------------------------------------
  // Connect open helper (MUST match feed behaviour)
  // -------------------------------------------------
  function openConnectProfile(payload) {
    const p = payload || {};
    const username = String(p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username,
        user_id,
        ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try {
      window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } }));
    } catch (e) {}

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // ---------------------------
  // Suggestions dropdown
  // ---------------------------
  function ensureSuggestBox(root) {
    const wrap = qs("[data-iad-search-wrap]", root) || (qs(".iad-search", root) || null);
    if (!wrap) return null;

    let box = qs("[data-iad-suggest]", wrap);
    if (box) return box;

    wrap.style.position = "relative";
    box = document.createElement("div");
    box.setAttribute("data-iad-suggest", "1");
    box.className = "iad-suggest";
    box.style.display = "none";
    wrap.appendChild(box);
    return box;
  }

  function hideSuggest(box) {
    if (!box) return;
    box.style.display = "none";
    box.innerHTML = "";
  }

  function showSuggest(box) {
    if (!box) return;
    box.style.display = "block";
  }

  function suggestGroup(title, itemsHTML) {
    return `
      <div class="iad-sg">
        <div class="iad-sg-title">${esc(title)}</div>
        <div class="iad-sg-items">${itemsHTML}</div>
      </div>
    `;
  }

  function bindSearchBox(root) {
    const input = qs("[data-iad-search]", root);
    if (!input || input.__iadBound) return;
    input.__iadBound = true;

    const box = ensureSuggestBox(root);

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

        const parts = [];

        parts.push(`
          <button type="button" class="iad-sug-row is-cta" data-iad-sug-open-search data-q="${esc(q)}">
            Search for <span class="iad-sug-q">“${esc(q)}”</span>
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
        const q = String(input.value || "").trim();
        if (q.length >= 2) {
          hideSuggest(box);
          window.dispatchEvent(new CustomEvent("iad:open_search", { detail: { q } }));
        }
      }
    });

    document.addEventListener("click", (e) => {
      if (!box) return;
      const wrap = qs(".iad-search", root);
      if (!wrap) return;
      if (!wrap.contains(e.target)) hideSuggest(box);
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
        <button type="button" class="iad-card iad-sr-row${altClass}" data-sr-user data-user-id="${item.user_id}" data-username="${esc(item.username || "")}">
          <div class="iad-sr-left">${avatarHTML(item.username, item.avatar_url || "")}</div>
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
    const box = qs("[data-iad-search-results]", mount);
    if (!box || box.__iadBound) return;
    box.__iadBound = true;

    box.addEventListener("click", (e) => {
      const row = e.target.closest("button");
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
      }
    });
  }

  function loadResults(mount, q, type, offset) {
    const box = qs("[data-iad-search-results]", mount);
    if (!box) return;

    box.innerHTML = `<div class="iad-loading">Loading…</div>`;

    API.post("ia_discuss_search", { q, type, offset: offset || 0, limit: 25 }).then((res) => {
      if (!res || !res.success) {
        box.innerHTML = `<div class="iad-empty">Search failed.</div>`;
        return;
      }

      const d = res.data || {};
      const items = Array.isArray(d.items) ? d.items : [];
      const hasMore = !!d.has_more;

      box.innerHTML = `
        <div class="iad-sr-list">
          ${items.length ? items.map((it, i) => renderResultRow(type, it, i)).join("") : `<div class="iad-empty">No results.</div>`}
          ${hasMore ? `<button type="button" class="iad-more" data-iad-sr-more>Load more</button>` : ``}
        </div>
      `;

      const more = qs("[data-iad-sr-more]", box);
      if (more) {
        more.addEventListener("click", () => {
          more.disabled = true;
          API.post("ia_discuss_search", { q, type, offset: (offset || 0) + 25, limit: 25 }).then((res2) => {
            if (!res2 || !res2.success) { more.disabled = false; return; }
            const d2 = res2.data || {};
            const items2 = Array.isArray(d2.items) ? d2.items : [];
            const hasMore2 = !!d2.has_more;

            const list = qs(".iad-sr-list", box);
            if (!list) return;

            // current rows count for alternating
            const existing = list.querySelectorAll(".iad-sr-row").length;

            const tmp = document.createElement("div");
            tmp.innerHTML = items2.map((it, i) => renderResultRow(type, it, existing + i)).join("");
            list.insertBefore(tmp, more);

            if (!hasMore2) more.remove();
            else more.disabled = false;

            offset = (offset || 0) + 25;
          });
        });
      }

      bindResultsClicks(mount);
    });
  }

  function renderSearchPageInto(mount, q) {
    if (!mount) return;
    q = String(q || "").trim();
    mount.innerHTML = resultsShellHTML(q);

    const back = qs("[data-iad-search-back]", mount);
    if (back) back.addEventListener("click", () => {
      window.dispatchEvent(new CustomEvent("iad:search_back"));
    });

    let activeType = "topics";

    mount.querySelectorAll(".iad-stab").forEach((b) => {
      b.addEventListener("click", () => {
        const t = b.getAttribute("data-type") || "topics";
        activeType = t;
        setActiveType(mount, activeType);
        loadResults(mount, q, activeType, 0);
      });
    });

    loadResults(mount, q, activeType, 0);
  }

  window.IA_DISCUSS_UI_SEARCH = {
    bindSearchBox,
    renderSearchPageInto
  };
})();
