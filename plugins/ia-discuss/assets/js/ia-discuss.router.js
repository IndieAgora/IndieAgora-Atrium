(function () {
  "use strict";

  function depsReady() {
    return (
      window.IA_DISCUSS &&
      window.IA_DISCUSS_CORE &&
      window.IA_DISCUSS_API &&
      window.IA_DISCUSS_UI_SHELL &&
      window.IA_DISCUSS_UI_FEED &&
      window.IA_DISCUSS_UI_AGORA &&
      window.IA_DISCUSS_UI_TOPIC &&
      window.IA_DISCUSS_UI_COMPOSER &&
      window.IA_DISCUSS_UI_SEARCH &&
      window.IA_DISCUSS_UI_MODERATION &&
      window.IA_DISCUSS_UI_RULES
    );
  }

  function safeQS(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function setParam(key, val) {
    try {
      const url = new URL(window.location.href);
      if (val === null || val === undefined || val === "") url.searchParams.delete(key);
      else url.searchParams.set(key, String(val));
      window.history.pushState({ ia: 1 }, "", url.toString());
    } catch (e) {}
  }

  // Set multiple query params in one push/replace.
  // map: { key: value|null } where null deletes the param.
  function setParams(map, replace) {
    try {
      const url = new URL(window.location.href);
      Object.keys(map || {}).forEach((k) => {
        const v = map[k];
        if (v === null || v === undefined || v === "") url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
      });
      const st = { ia: 1 };
      if (replace) window.history.replaceState(st, "", url.toString());
      else window.history.pushState(st, "", url.toString());
    } catch (e) {}
  }

  function mount() {
    if (!depsReady()) return;

    const qs = (window.IA_DISCUSS_CORE && window.IA_DISCUSS_CORE.qs) ? window.IA_DISCUSS_CORE.qs : safeQS;
    const root = qs("[data-ia-discuss-root]");
    if (!root) return;

    window.IA_DISCUSS_UI_SHELL.shell();

    let inAgora = false;
    let lastListView = "new";
    let lastForumId = 0;
    let lastForumName = "";

    let inSearch = false;
    let searchPrev = { view: "new", forum_id: 0, forum_name: "" };
    let lastSearchQ = "";

    function discussSiteTitle() {
      try {
        const raw = window.IA_DISCUSS && String(window.IA_DISCUSS.siteTitle || '').trim();
        if (raw) return raw;
      } catch (e) {}
      return 'IndieAgora';
    }

    function discussIsActiveSurface() {
      try {
        const shell = document.querySelector('#ia-atrium-shell');
        const active = shell ? String(shell.getAttribute('data-active-tab') || '').trim().toLowerCase() : '';
        if (active) return active === 'discuss';
      } catch (e2) {}
      try {
        const url = new URL(window.location.href);
        const tab = String(url.searchParams.get('tab') || '').trim().toLowerCase();
        if (tab) return tab === 'discuss';
      } catch (e) {}
      try {
        const panel = document.querySelector('#ia-atrium-shell .ia-panel[data-panel="discuss"]');
        if (panel) return panel.classList.contains('active') || panel.getAttribute('aria-hidden') === 'false';
      } catch (e3) {}
      return false;
    }

    function applyDiscussTitle(rawTitle) {
      try {
        if (!discussIsActiveSurface()) return;
        const site = discussSiteTitle();
        const clean = String(rawTitle || '').trim();
        const full = clean ? (clean + ' | ' + site) : site;
        if (!full) return;
        document.title = full;
        let og = document.head ? document.head.querySelector('meta[property="og:title"]') : null;
        if (!og && document.head) {
          og = document.createElement('meta');
          og.setAttribute('property', 'og:title');
          document.head.appendChild(og);
        }
        if (og) og.setAttribute('content', full);
        let tw = document.head ? document.head.querySelector('meta[name="twitter:title"]') : null;
        if (!tw && document.head) {
          tw = document.createElement('meta');
          tw.setAttribute('name', 'twitter:title');
          document.head.appendChild(tw);
        }
        if (tw) tw.setAttribute('content', full);
      } catch (e) {}
    }

    function discussFeedTitle(view, orderKey) {
      const order = String(orderKey || '').trim().toLowerCase();
      if (view === 'new') {
        if (order === 'most_replies') return 'Most Replies';
        if (order === 'least_replies') return 'Least Replies';
        if (order === 'oldest') return 'Oldest Posts';
        if (order === 'created') return 'Date Created';
        return 'Latest Posts';
      }
      if (view === 'replies' || view === 'unread') {
        if (order === 'most_replies') return 'Most Replies';
        if (order === 'least_replies') return 'Least Replies';
        if (order === 'oldest') return 'Oldest Replies';
        return 'Latest Replies';
      }
      if (view === 'noreplies') return '0 Replies';
      if (view === 'mytopics') return 'My Topics';
      if (view === 'myreplies') return 'My Replies';
      if (view === 'myhistory') return 'My History';
      if (view === 'moderation') return 'Moderation';
      if (view === 'search') return 'Search';
      if (view === 'agoras') return 'Agora List';
      return 'Discuss';
    }

    function applyDiscussContextTitle(view, forumName, orderKey) {
      if (view === 'agora') {
        const agoraName = String(forumName || '').trim() || 'Agora';
        const order = String(orderKey || '').trim().toLowerCase();
        let sortLabel = 'Most recent';
        if (order === 'most_replies') sortLabel = 'Most replies';
        else if (order === 'least_replies') sortLabel = 'Least replies';
        else if (order === 'oldest') sortLabel = 'Oldest first';
        else if (order === 'created') sortLabel = 'Date created';
        applyDiscussTitle(agoraName + ' | ' + sortLabel);
        return;
      }
      if (view === 'agoras') {
        applyDiscussTitle('Agora List');
        return;
      }
      applyDiscussTitle(discussFeedTitle(view, orderKey));
    }

    // bind search UX
    try {
      window.IA_DISCUSS_UI_SEARCH.bindSearchBox(root);
    } catch (e) {}

    // Bind Rules modal click-handlers
    try {
      window.IA_DISCUSS_UI_RULES.bind();
    } catch (e) {}

    // Bind Moderation UX and pre-load permissions
    try {
      // IMPORTANT: bind needs the Discuss root to attach event listeners.
      window.IA_DISCUSS_UI_MODERATION.bind(root);
      // IMPORTANT: pass root so the moderation module can set data-iad-can-moderate.
      // Also compute capability from the returned items length (some builds don't return a count).
      window.IA_DISCUSS_UI_MODERATION.loadMyModeration(root).then((data) => {
        try {
          const itemsLen = data && Array.isArray(data.items) ? data.items.length : (data && data.loaded && data.items ? (data.items.length||0) : 0);
          const isAdmin = (data && String(data.global_admin||'0') === '1');
          const canAny = !!(isAdmin || itemsLen > 0 || parseInt(String(data.count||'0'), 10) > 0);

          // Back-compat (older shell logic)
          root.setAttribute('data-iad-can-moderate', canAny ? '1' : '0');
          // New context flags
          root.setAttribute('data-iad-can-moderate-any', canAny ? '1' : '0');
          // Default "here" to any; it will be refined when entering a specific Agora.
          root.setAttribute('data-iad-can-moderate-here', canAny ? '1' : '0');

          window.IA_DISCUSS_UI_SHELL.setActiveTab(lastListView || 'new');
        } catch (e) {}
      });
    } catch (e) {}


    function render(view, forumId, forumName, opts) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      opts = opts || {};
      const noHist = !!opts.no_history;

      // Back-compat: older URLs used "unread". Map to the new Replies view.
      if (view === 'unread') view = 'replies';

      inSearch = false;
      applyDiscussContextTitle(view, forumName, '');

      // Update moderation visibility flags for the current context.
      // - data-iad-can-moderate-any: user can moderate at least one forum (or is admin)
      // - data-iad-can-moderate-here: user can moderate the *current* forum (admin always)
      async function setModerationContext(curView, curForumId) {
        try {
          const d = await window.IA_DISCUSS_UI_MODERATION.loadMyModeration(root);
          const items = (d && Array.isArray(d.items)) ? d.items : [];
          const isAdmin = !!(d && (parseInt(String(d.global_admin||'0'),10) === 1));
          const canAny = !!(isAdmin || items.length > 0);
          let canHere = canAny;
          if (curView === 'agora') {
            const fid = parseInt(String(curForumId||0),10) || 0;
            canHere = !!(isAdmin || (fid && items.some((x) => (parseInt(String(x.forum_id||0),10) === fid))));
          }
          root.setAttribute('data-iad-can-moderate-any', canAny ? '1' : '0');
          root.setAttribute('data-iad-can-moderate-here', canHere ? '1' : '0');
        } catch (e) {}
      }

      if (view === "moderation") {
        inAgora = false;
        lastListView = "moderation";
        lastForumId = 0;
        lastForumName = "";

        setModerationContext('moderation', 0);
        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        if (!noHist) {
          setParams({
            iad_view: "moderation",
            iad_forum: null,
            iad_forum_name: null,
            iad_topic: null,
            iad_post: null,
            iad_q: null
          });
        }

        mountEl.innerHTML = `<div class="iad-loading">Loading…</div>`;
        try {
          // renderModerationView expects the Discuss root so it can locate [data-iad-view]
          // and manage internal state correctly.
          window.IA_DISCUSS_UI_MODERATION.renderModerationView(root);
        } catch (e) {
          mountEl.innerHTML = `<div class="iad-empty">Moderation failed to load.</div>`;
        }
        return;
      }


      if (view === "agoras") {
        inAgora = false;
        lastListView = "agoras";
        lastForumId = 0;
        lastForumName = "";

        // In list views, show moderation pill if user can moderate any forum.
        setModerationContext('agoras', 0);
        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        if (!noHist) {
          setParams({
            iad_view: "agoras",
            iad_forum: null,
            iad_forum_name: null,
            iad_topic: null,
            iad_post: null,
            iad_q: null
          });
        }

                
        mountEl.innerHTML = `<div class="iad-loading">Loading…</div>`;

        const CORE = window.IA_DISCUSS_CORE || {};
        const esc = CORE.esc || function (s) { return String(s || ""); };
        const timeAgo = CORE.timeAgo || function(){ return ''; };

        const pageSize = 20;
        let serverOffset = 0;
        let serverHasMore = true;
        let agLoading = false;

        // Order key:
        //   ''              -> Newest
        //   'oldest'        -> Oldest
        //   'latest_topics' -> Latest Topics
        //   'latest_replies'-> Latest Replies
        //   'empty'         -> Empty
        let orderKey = "newest";
        let cached = [];
        let shownCount = 0;

        function agRowHTML(f) {
          const fid = parseInt(f.forum_id || "0", 10) || 0;
          const name = String(f.forum_name || "");

          // Prefer pre-sanitised HTML from the API; fall back to plain text.
          const descHtml = (f.forum_desc_html !== undefined && f.forum_desc_html !== null) ? String(f.forum_desc_html) : "";
          const descText = (f.forum_desc !== undefined && f.forum_desc !== null) ? String(f.forum_desc) : "";

          const topics = parseInt(String(
            (f.topics !== undefined && f.topics !== null) ? f.topics :
            ((f.topics_count !== undefined && f.topics_count !== null) ? f.topics_count :
            ((f.forum_topics !== undefined && f.forum_topics !== null) ? f.forum_topics : 0))
          ), 10) || 0;

          const posts = parseInt(String(
            (f.posts !== undefined && f.posts !== null) ? f.posts :
            ((f.posts_count !== undefined && f.posts_count !== null) ? f.posts_count :
            ((f.forum_posts !== undefined && f.forum_posts !== null) ? f.forum_posts : 0))
          ), 10) || 0;

          // NOTE: descHtml is already sanitised server-side; do not escape it again.
          const descBlock = descHtml
            ? `<div class="iad-agora-row__desc">${descHtml}</div>`
            : (descText ? `<div class="iad-agora-row__desc">${esc(descText)}</div>` : ``);

          const joined = (String(f.joined||'0') === '1');
          const bell = (String(f.bell||'0') === '1');
          const banned = (String(f.banned||'0') === '1');
          const cover = (f.cover_url !== undefined && f.cover_url !== null) ? String(f.cover_url) : "";

          const bellSvg = `<svg class="iad-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.2 2.2 0 0 0 2.2-2.2h-4.4A2.2 2.2 0 0 0 12 22Zm7-6.2v-5.2a7 7 0 1 0-14 0v5.2L3.6 17.2c-.6.6-.2 1.6.7 1.6h15.4c.9 0 1.3-1 .7-1.6L19 15.8Z" fill="currentColor"/></svg>`;

          const joinLabel = banned ? 'Kicked' : (joined ? 'Joined' : 'Join');
          const joinDisabled = banned ? 'disabled aria-disabled="true"' : '';

          const lt = (f && f.latest_topic && typeof f.latest_topic === 'object') ? f.latest_topic : null;
          const lr = (f && f.latest_reply && typeof f.latest_reply === 'object') ? f.latest_reply : null;

          function previewHTML(kind, obj) {
            if (!obj) return '';
            const label = kind === 'topic' ? 'Latest topic' : 'Latest reply';
            const topicId = parseInt(String(obj.topic_id||0),10) || 0;
            const postId = kind === 'reply' ? (parseInt(String(obj.post_id||0),10) || 0) : 0;
            const title = String(obj.title||'');
            const author = String(obj.author_name||'');
            const avatar = String(obj.author_avatar||'');
            const when = obj.time ? String(timeAgo(obj.time)) : '';
            return `
              <a href="#" class="iad-agora-prev" data-iad-agora-preview-open data-kind="${kind}" data-topic-id="${topicId}" data-post-id="${postId}">
                <div class="iad-agora-prev__label">${label}</div>
                <div class="iad-agora-prev__row">
                  <div class="iad-agora-prev__av">${avatar ? `<img src="${esc(avatar)}" alt="">` : ``}</div>
                  <div class="iad-agora-prev__text">
                    <div class="iad-agora-prev__line"><span class="iad-agora-prev__who">${esc(author)}</span> <span class="iad-agora-prev__title">${esc(title)}</span></div>
                  </div>
                  <div class="iad-agora-prev__when">${esc(when)}</div>
                </div>
              </a>
            `;
          }

          const previews = (lt || lr) ? `
            <div class="iad-agora-row__previews">
              ${previewHTML('topic', lt)}
              ${previewHTML('reply', lr)}
            </div>
          ` : '';

          return `
            <div
              class="iad-agora-row ${joined ? 'iad-joined' : ''} ${banned ? 'iad-banned' : ''}"
              data-iad-agora-row
              data-forum-id="${fid}"
              data-forum-name="${esc(name)}"
              data-iad-cover="${esc(cover)}"
              data-joined="${joined?1:0}" data-bell="${bell?1:0}">
              <button type="button" class="iad-agora-row__open">
                <div class="iad-agora-row__thumb" ${cover ? `style="background-image:url(${esc(cover)})"` : ''}></div>
                <div class="iad-agora-row__main">
                  <div class="iad-agora-row__name">${esc(name)}</div>
                  ${descBlock}
                  <div class="iad-agora-row__meta">${topics} topics • ${posts} replies</div>
                  ${previews}
                </div>
              </button>

              <div class="iad-agora-row__actions">
                <button type="button" class="iad-bell ${bell ? 'is-on' : ''}" data-iad-bell="${fid}" aria-label="Notifications" aria-pressed="${bell ? 'true':'false'}">${bellSvg}</button>
                <button type="button" class="iad-join ${joined ? 'is-joined' : ''}" data-iad-join="${fid}" ${joinDisabled}>${joinLabel}</button>
              </div>
            </div>
          `;
        }

        function renderShell(
) {
          mountEl.innerHTML = `
            <div class="iad-agoras">
              <div class="iad-feed-controls">
                <label class="iad-feed-controls-label" for="iad-agoras-sort">Sort</label>
                <select class="iad-select" data-iad-agoras-sort id="iad-agoras-sort">
                  <option value="newest">Newest</option>
                  <option value="oldest">Oldest</option>
                  <option value="latest_topics">Latest Topics</option>
                  <option value="latest_replies">Latest Replies</option>
                  <option value="empty">Empty</option>
                </select>
              </div>

              <div class="iad-agoras-list"></div>
              <div class="iad-agoras-more"></div>
            </div>
          `;

          // Keep selected option stable across rerenders
          try {
            const sel = mountEl.querySelector("[data-iad-agoras-sort]");
            if (sel) sel.value = orderKey || "newest";
          } catch (e) {}
        }

        function renderMoreButton() {
          const wrap = mountEl.querySelector(".iad-agoras-more");
          if (!wrap) return;

          const remainingCached = cached.length - shownCount;
          const canMore = remainingCached > 0 || !!serverHasMore;

          if (!canMore) {
            wrap.innerHTML = "";
            return;
          }

          wrap.innerHTML = `<button type="button" class="iad-more" data-iad-agoras-more>Load more</button>`;
          const btn = wrap.querySelector("[data-iad-agoras-more]");
          if (btn && agLoading) { btn.disabled = true; btn.textContent = "Loading…"; }
        }

        function appendFromCache() {
          if (!mountEl.querySelector(".iad-agoras-list")) renderShell();

          const list = mountEl.querySelector(".iad-agoras-list");
          if (!list) return;

          const remainingCached = cached.length - shownCount;
          if (remainingCached <= 0) return;

          const take = Math.min(pageSize, remainingCached);
          const slice = cached.slice(shownCount, shownCount + take);
          shownCount += slice.length;

          list.insertAdjacentHTML("beforeend", slice.map(agRowHTML).join(""));

          // Cache the loaded Agora order so topic Prev/Next can advance across agoras.
          try {
            const rows = mountEl.querySelectorAll('[data-forum-id]');
            const ids = [];
            const names = {};
            rows.forEach((r) => {
              const id = parseInt(r.getAttribute('data-forum-id') || '0', 10) || 0;
              if (!id) return;
              ids.push(id);
              const nm = (r.getAttribute('data-forum-name') || '').trim();
              if (nm) names[String(id)] = nm;
            });
            if (ids.length && window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
              window.IA_DISCUSS_STATE.set({ agora_list: { ids, names, ts: Date.now() } });
            }
          } catch (eAgList) {}
        }

        async function loadAgorasNext() {
          if (agLoading) return;

          // Reveal from cache first.
          const remainingCached = cached.length - shownCount;
          if (remainingCached > 0) {
            appendFromCache();
            renderMoreButton();
            return;
          }

          if (!serverHasMore) {
            renderMoreButton();
            return;
          }

          agLoading = true;

          const moreBtn = mountEl.querySelector("[data-iad-agoras-more]");
          if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = "Loading…"; }

          const res = await window.IA_DISCUSS_API.post("ia_discuss_agoras", { offset: serverOffset, q: "", order: orderKey || "newest" });
          if (!res || !res.success) {
            agLoading = false;
            if (moreBtn) { moreBtn.disabled = false; moreBtn.textContent = "Load more"; }
            if (!mountEl.querySelector(".iad-agoras-list")) mountEl.innerHTML = `<div class="iad-empty">Failed to load agoras.</div>`;
            return;
          }

          const d = res.data || {};
          const items = Array.isArray(d.items) ? d.items : [];

          serverHasMore = !!d.has_more;
          serverOffset = (typeof d.next_offset === "number") ? d.next_offset : (serverOffset + items.length);

          if (!mountEl.querySelector(".iad-agoras-list")) renderShell();

          // If this is the first page and it's empty, show a targeted message.
          if (serverOffset === items.length && items.length === 0 && !serverHasMore) {
            const list = mountEl.querySelector(".iad-agoras-list");
            if (list) {
              list.innerHTML = (orderKey === 'empty')
                ? `<div class="iad-empty">There are no Agoras matching this criteria.</div>`
                : `<div class="iad-empty">No agoras.</div>`;
            }
            agLoading = false;
            renderMoreButton();
            return;
          }

          cached = cached.concat(items);

          appendFromCache();
          agLoading = false;
          renderMoreButton();
        }

        // Sort selector: reset state and reload.
        mountEl.onchange = function (e) {
          const t = e && e.target ? e.target : null;
          const sel = t && t.matches ? (t.matches("[data-iad-agoras-sort]") ? t : null) : null;
          if (!sel) return;

          orderKey = String(sel.value || "newest");
          serverOffset = 0;
          serverHasMore = true;
          cached = [];
          shownCount = 0;

          renderShell();
          loadAgorasNext();
        };

        // Initial render + first page
        renderShell();
        loadAgorasNext();
mountEl.onclick = function (e) {
          const t = e.target;

          const prev = t && t.closest ? t.closest('[data-iad-agora-preview-open]') : null;
          if (prev) {
            e.preventDefault();
            e.stopPropagation();
            const topicId = parseInt(prev.getAttribute('data-topic-id') || '0', 10) || 0;
            const postId = parseInt(prev.getAttribute('data-post-id') || '0', 10) || 0;
            if (topicId) {
              if (postId) {
                window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: topicId, scroll_post_id: postId } }));
              } else {
                window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: topicId, scroll: '' } }));
              }
            }
            return;
          }

          const more = t.closest && t.closest("[data-iad-agoras-more]");
          if (more) {
            e.preventDefault();
            e.stopPropagation();
            loadAgorasNext();
            return;
          }

          const row = t.closest && t.closest("[data-forum-id]");
          if (row) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(row.getAttribute("data-forum-id") || "0", 10);
            const nm = row.getAttribute("data-forum-name") || "";
            render("agora", id, nm, { joined: (row.getAttribute("data-joined")==="1"), bell: (row.getAttribute("data-bell")==="1") });
          }
        };

        renderShell();
        loadAgorasNext();

        return;
      }


      if (view === "agora") {
        inAgora = true;

        lastListView = "agora";
        lastForumId = forumId || 0;
        lastForumName = forumName || "";

        // If arriving via refresh/deep-link, the URL may have only iad_forum.
        // Fetch the agora name once so the header is correct.
        if (!lastForumName && lastForumId) {
          try {
            // NOTE: the canonical meta endpoint is ia_discuss_forum_meta (see includes/modules/forum-meta.php)
            window.IA_DISCUSS_API.post("ia_discuss_forum_meta", { forum_id: String(lastForumId) }).then((r) => {
              if (r && r.success && r.data && r.data.forum_name) {
                lastForumName = String(r.data.forum_name || "");
                // Store in the URL so back/forward and refresh keep the identity.
                setParams({ iad_forum_name: lastForumName }, true);
                try { window.IA_DISCUSS_UI_AGORA.renderAgora(root, lastForumId, lastForumName, opts||null); } catch (e) {}
              }
            });
          } catch (e) {}
        }

        // Update context moderation visibility for this specific forum.
        try {
          root.setAttribute('data-iad-current-forum-id', String(lastForumId||0));
          // Ensure moderation cache is loaded, then decide if user can moderate THIS Agora.
          window.IA_DISCUSS_UI_MODERATION.loadMyModeration(root).then((data) => {
            try {
              const isAdmin = (data && parseInt(String(data.global_admin||'0'),10) === 1);
              const items = (data && Array.isArray(data.items)) ? data.items : [];
              const canHere = isAdmin || items.some((x) => (parseInt(String(x.forum_id||0),10) === (parseInt(String(lastForumId||0),10) || 0)));
              root.setAttribute('data-iad-can-moderate-here', canHere ? '1' : '0');
              const canAny = isAdmin || (items && items.length > 0);
              root.setAttribute('data-iad-can-moderate-any', canAny ? '1' : '0');
              root.setAttribute('data-iad-can-moderate', canAny ? '1' : '0');
              // Refresh tab row visibility (Moderation pill) while keeping Agora context.
              window.IA_DISCUSS_UI_SHELL.setActiveTab('agoras', 'agora');
            } catch (e) {}
          });
        } catch (e) {}

        // Highlight the Agoras tab, but set the logical context to 'agora' so
        // the Moderation pill can be shown/hidden per-forum.
        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras", 'agora');
        // In an Agora view, show moderation pill only if user can moderate THIS forum.
        setModerationContext('agora', lastForumId);
        if (!noHist) {
          setParams({
            iad_view: null,
            iad_forum: String(lastForumId || 0),
            iad_forum_name: String(lastForumName || ""),
            iad_topic: null,
            iad_post: null,
            iad_q: null
          });
        }

        window.IA_DISCUSS_UI_AGORA.renderAgora(root, lastForumId, lastForumName, opts||null);
        return;
      }

      inAgora = false;

      lastListView = view;
      lastForumId = 0;
      lastForumName = "";

      window.IA_DISCUSS_UI_SHELL.setActiveTab(view);
      if (!noHist) {
        setParams({
          iad_view: String(view || "new"),
          iad_forum: null,
          iad_forum_name: null,
            iad_topic: null,
          iad_post: null,
          iad_q: null
        });
      }

      window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, 0);
    }

    // -----------------------------
    // Feed scroll preservation
    // -----------------------------
    // Atrium can run in "panel scroll" mode (each tab scrolls inside the panel).
    // If so, preserving window.scrollY won't help; we must preserve the panel's
    // scrollTop. We pick the best available scroller in priority order.

    function getDiscussScroller() {
      try {
        const panel = document.querySelector('.ia-panel[data-panel="discuss"]');
        if (panel && panel.scrollHeight > panel.clientHeight) return panel;
      } catch (e) {}
      try {
        // Fallback: normal document scrolling
        return document.scrollingElement || document.documentElement;
      } catch (e2) {
        return document.documentElement;
      }
    }

    // We save more than a raw scrollTop because "Load more" changes how much of the
    // feed is present when you return. If we only restore scrollTop and the feed has
    // fewer items, the browser clamps scrollTop back to ~0.

    let savedFeedState = null;
    let lastTopicNav = null; // { view, forum_id, ids: number[], ts }

    function getFeedMount() {
      try { return qs("[data-iad-view]", root); } catch (e) { return null; }
    }

    function computeFeedAnchor(sc, mountEl) {
      try {
        const cards = mountEl ? Array.from(mountEl.querySelectorAll('[data-topic-id]')) : [];
        if (!cards.length) return { topic_id: 0, delta: 0 };
        const scRect = sc.getBoundingClientRect();
        let chosen = null;
        for (const el of cards) {
          const r = el.getBoundingClientRect();
          if (r.bottom > (scRect.top + 10)) { chosen = { el, r }; break; }
        }
        if (!chosen) chosen = { el: cards[0], r: cards[0].getBoundingClientRect() };
        const tid = parseInt(chosen.el.getAttribute('data-topic-id') || '0', 10) || 0;
        const delta = Math.round(chosen.r.top - scRect.top);
        return { topic_id: tid, delta: delta };
      } catch (e) {
        return { topic_id: 0, delta: 0 };
      }
    }

    function saveFeedScroll() {
      try {
        const sc = getDiscussScroller();
        const mountEl = getFeedMount();
        const scrollTop = (sc && typeof sc.scrollTop === 'number') ? sc.scrollTop : 0;
        const itemCount = mountEl ? (mountEl.querySelectorAll('[data-topic-id]') || []).length : 0;
        const ctl = (mountEl && mountEl.__iadFeedCtl) ? mountEl.__iadFeedCtl : null;

        // Only capture/overwrite feed scroll + topic navigation state when we are
        // actually in a feed list context. When navigating topic-to-topic inside
        // the topic view, the feed controller isn't mounted, and capturing here
        // would collapse the nav list to a single item (breaking Prev).
        if (!ctl) return;

        // Atrium keeps panels in the DOM; the feed controller property can remain attached
        // even after we replace the mount's HTML with topic view. In that case, querying
        // [data-topic-id] would return 0/1 elements and would overwrite topic navigation
        // state with a collapsed list (breaking Prev/Next after the first hop).
        //
        // Only capture navigation/scroll state when we clearly have a feed list.
        if (itemCount < 2) return;
        const st = (ctl && typeof ctl.getState === 'function') ? ctl.getState() : null;
        const anchor = (sc && mountEl) ? computeFeedAnchor(sc, mountEl) : { topic_id: 0, delta: 0 };

        // Capture the current feed order so topic view can do prev/next navigation
        // without returning to the feed.
        let ids = [];
        try {
          ids = mountEl ? Array.from(mountEl.querySelectorAll('[data-topic-id]')).map((el) => {
            return parseInt(el.getAttribute('data-topic-id') || '0', 10) || 0;
          }).filter((n) => n > 0) : [];
        } catch (eIds) { ids = []; }

        // Defensive: don't overwrite an existing nav list with a tiny/invalid one.
        if (ids.length < 2) return;

        lastTopicNav = {
          view: String(lastListView || 'new'),
          forum_id: lastForumId || 0,
          ids: ids,
          server_offset: (st && st.server_offset) ? parseInt(st.server_offset, 10) : 0,
          has_more: !!(st && st.has_more),
          ts: Date.now()
        };

        try {
          if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
            window.IA_DISCUSS_STATE.set({ topic_nav: lastTopicNav });
          }
        } catch (eNav) {}

        savedFeedState = {
          view: String(lastListView || 'new'),
          forum_id: lastForumId || 0,
          scroll_top: scrollTop,
          item_count: itemCount,
          pages_loaded: st && st.pages_loaded ? parseInt(st.pages_loaded, 10) : 0,
          server_offset: st && st.server_offset ? parseInt(st.server_offset, 10) : 0,
          load_more_clicks: st && st.load_more_clicks ? parseInt(st.load_more_clicks, 10) : 0,
          pagination_mode: st && st.pagination_mode ? String(st.pagination_mode) : 'loadmore',
          current_page: st && st.current_page ? parseInt(st.current_page, 10) : 1,
          anchor_topic_id: anchor.topic_id || 0,
          anchor_delta: anchor.delta || 0,
          ts: Date.now()
        };
      } catch (e) {
        savedFeedState = null;
      }
    }

    function restoreFeedScrollAfterFeed() {
      const state = savedFeedState;
      if (!state) return;

      // Only restore into the same feed context.
      if (String(state.view || '') !== String(lastListView || '')) return;
      if ((state.forum_id || 0) !== (lastForumId || 0)) return;

      const desiredCount = parseInt(state.item_count || 0, 10) || 0;
      const desiredPages = parseInt(state.pages_loaded || 0, 10) || 0;
      const desiredOffset = parseInt(state.server_offset || 0, 10) || 0;
      const desiredClicks = parseInt(state.load_more_clicks || 0, 10) || 0;
      const desiredPaginationMode = String(state.pagination_mode || 'loadmore');
      const desiredCurrentPage = parseInt(state.current_page || 1, 10) || 1;
      const anchorTid = parseInt(state.anchor_topic_id || 0, 10) || 0;
      const anchorDelta = parseInt(state.anchor_delta || 0, 10) || 0;
      const desiredRaw = Math.max(0, parseInt(state.scroll_top || 0, 10) || 0);

      let done = false;
      let loops = 0;

      const finish = () => {
        if (done) return;
        done = true;
        window.removeEventListener('iad:feed_loaded', onLoaded);
        window.removeEventListener('iad:feed_page_appended', onPage);
      };

      const tryRestore = () => {
        if (done) return;
        const sc = getDiscussScroller();
        const mountEl = getFeedMount();
        if (!sc || !mountEl) return;

        if (anchorTid) {
          const card = mountEl.querySelector('[data-topic-id="' + anchorTid + '"]');
          if (card) {
            const scRect = sc.getBoundingClientRect();
            const r = card.getBoundingClientRect();
            const cardTopInScroller = (r.top - scRect.top) + sc.scrollTop;
            const target = Math.max(0, Math.round(cardTopInScroller - anchorDelta));
            try { sc.scrollTop = target; } catch (e) {}
            setTimeout(() => { try { sc.scrollTop = target; } catch (e2) {} }, 120);
            setTimeout(() => { try { sc.scrollTop = target; } catch (e3) {} }, 320);
            // Only finish if we were not clamped (i.e. the feed is tall enough for this target).
            setTimeout(() => {
              if (done) return;
              const now = (typeof sc.scrollTop === 'number') ? sc.scrollTop : 0;
              const max = Math.max(0, (sc.scrollHeight || 0) - (sc.clientHeight || 0));
              const clamped = (now + 4 < target) && (max + 4 < target);
              if (!clamped) finish();
            }, 80);
            return;
          }
        }

        const raw = Math.max(0, parseInt(state.scroll_top || 0, 10) || 0);
        if (raw > 0) {
          try { sc.scrollTop = raw; } catch (e4) {}
          setTimeout(() => { try { sc.scrollTop = raw; } catch (e5) {} }, 120);
          setTimeout(() => {
            if (done) return;
            const now = (typeof sc.scrollTop === 'number') ? sc.scrollTop : 0;
            const max = Math.max(0, (sc.scrollHeight || 0) - (sc.clientHeight || 0));
            const clamped = (now + 4 < raw) && (max + 4 < raw);
            if (!clamped) finish();
          }, 80);
        }
      };

      const maybeLoadMore = () => {
        if (done) return;
        loops++;
        if (loops > 30) { tryRestore(); finish(); return; }

        const mountEl = getFeedMount();
        if (!mountEl) { tryRestore(); return; }

        const sc = getDiscussScroller();
        const maxScroll = sc ? Math.max(0, (sc.scrollHeight || 0) - (sc.clientHeight || 0)) : 0;
        const needMoreHeight = (desiredRaw && (maxScroll + 24 < desiredRaw));

        const countNow = (mountEl.querySelectorAll('[data-topic-id]') || []).length;
        const needMoreItems = (desiredCount && countNow < desiredCount);
        const needAnchor = (anchorTid && !mountEl.querySelector('[data-topic-id="' + anchorTid + '"]'));

        const ctl = mountEl.__iadFeedCtl;
        const st = (ctl && typeof ctl.getState === 'function') ? ctl.getState() : null;
        const currentMode = st && st.pagination_mode ? String(st.pagination_mode) : 'loadmore';

        if (desiredPaginationMode === 'pages' && ctl && typeof ctl.goToPage === 'function' && currentMode === 'pages') {
          const pageNow = st && st.current_page ? parseInt(st.current_page, 10) : 1;
          if (pageNow !== desiredCurrentPage) {
            try { ctl.goToPage(desiredCurrentPage); } catch (e) {}
            return;
          }
        }

        const canLoad = !!(ctl && typeof ctl.loadMore === 'function' && currentMode !== 'pages');
        const pagesNow = st && st.pages_loaded ? parseInt(st.pages_loaded, 10) : 0;
        const offsetNow = st && st.server_offset ? parseInt(st.server_offset, 10) : 0;
        const clicksNow = st && st.load_more_clicks ? parseInt(st.load_more_clicks, 10) : 0;
        const needMorePages = (desiredPages && pagesNow && pagesNow < desiredPages);
        const needMoreOffset = (desiredOffset && offsetNow && offsetNow < desiredOffset);
        const needMoreClicks = (desiredClicks && clicksNow < desiredClicks);
        const hasMore = !!(st ? st.has_more : mountEl.querySelector('[data-iad-feed-more]'));

        // Prefer deterministic depth metrics (pages_loaded/server_offset) over item_count,
        // because unread filtering and other view transforms can make item counts smaller.
        // Strongest signal: the number of times the user pressed "Load more".
        // This avoids edge cases where item counts vary (unread filtering) or the
        // server offset advances inconsistently.
        if ((needMoreHeight || needMoreClicks || needMorePages || needMoreOffset || needMoreItems || needAnchor) && canLoad && hasMore) {
          try { ctl.loadMore(); } catch (e) {}
          return;
        }

        tryRestore();
      };

      const onLoaded = () => {
        requestAnimationFrame(() => requestAnimationFrame(() => {
          maybeLoadMore();
          setTimeout(tryRestore, 120);
        }));
      };

      const onPage = () => {
        requestAnimationFrame(() => requestAnimationFrame(() => {
          maybeLoadMore();
          setTimeout(tryRestore, 80);
        }));
      };

      window.addEventListener('iad:feed_loaded', onLoaded);
      window.addEventListener('iad:feed_page_appended', onPage);

      // Fallback in case events don't fire.
      setTimeout(() => { tryRestore(); finish(); }, 1600);
    }


    function openTopicPage(topicId, opts) {
      topicId = parseInt(topicId || "0", 10) || 0;
      if (!topicId) return;

      opts = opts || {};
      const noHist = !!opts.no_history;

      // Save the feed scroll position immediately before switching the view.
      if (!noHist) {
        saveFeedScroll();
        setParams({
          iad_view: null,
          iad_forum: null,
          iad_forum_name: null,
            iad_topic: String(topicId),
          iad_post: opts.scroll_post_id ? String(parseInt(opts.scroll_post_id, 10) || 0) : null,
          iad_q: null
        });
      }

      applyDiscussTitle('Topic');
      window.IA_DISCUSS_UI_TOPIC.renderInto(root, topicId, opts);
    }

    function openSearchPage(q, opts) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      opts = opts || {};
      const noHist = !!opts.no_history;

      // remember where we came from
      inSearch = true;
      lastSearchQ = String(q || "").trim();
      searchPrev = { view: lastListView, forum_id: lastForumId, forum_name: lastForumName };

      // update URL (search is its own view)
      if (!noHist) {
        setParams({
          iad_view: "search",
          iad_q: lastSearchQ,
          iad_forum: null,
          iad_forum_name: null,
            iad_topic: null,
          iad_post: null
        });
      }


      applyDiscussTitle(lastSearchQ ? ('Search: ' + lastSearchQ) : 'Search');
      try {
        window.IA_DISCUSS_UI_SEARCH.renderSearchPageInto(mountEl, lastSearchQ);
      } catch (e) {}

    }

    root.querySelectorAll(".iad-tab").forEach((b) => {
      b.addEventListener("click", () => {
        const v = b.getAttribute("data-view");
        if (!v) return;
        render(v, 0, "");
      });
    });

    function viewToTab(v) {
      if (v === 'noreplies') return 'no_replies';
      if (v === 'replies' || v === 'unread') return 'latest_replies';
      return 'new_posts';
    }

    // Random topic (from current list context)

    (function bindRandomTopic(){
      const btn = root.querySelector('[data-iad-random-topic]');
      if (!btn || !window.IA_DISCUSS_API) return;

      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        // Determine current context: list view + (optional) agora forum.
        const v = (inSearch ? (searchPrev && searchPrev.view) : lastListView) || 'new';
        const forum = (v === 'agora' || inAgora) ? (lastForumId || 0) : 0;

        btn.disabled = true;
        const oldTxt = btn.textContent;
        btn.textContent = 'Random…';

        let tid = 0;
        try {
          const res = await window.IA_DISCUSS_API.post('ia_discuss_random_topic', {
            tab: viewToTab(v),
            forum_id: forum,
            q: ''
          });
          if (res && res.success && res.data && res.data.topic_id) {
            tid = parseInt(res.data.topic_id, 10) || 0;
          }
        } catch (err) {}

        btn.disabled = false;
        btn.textContent = oldTxt || 'Random';

        if (tid) {
          // Track random history for topic Prev/Next navigation.
          try {
            const baseView = (inSearch ? (searchPrev && searchPrev.view) : lastListView) || 'new';
            const baseForum = (baseView === 'agora' || inAgora) ? (lastForumId || 0) : 0;
            const s = (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.get === 'function') ? window.IA_DISCUSS_STATE.get() : {};
            const existing = s && s.topic_nav && s.topic_nav.view === 'random' ? s.topic_nav : null;
            const ids = existing && Array.isArray(existing.ids) ? existing.ids.slice(0) : [];
            if (!ids.includes(tid)) ids.push(tid);
            if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
              window.IA_DISCUSS_STATE.set({
                topic_nav: {
                  view: 'random',
                  base_view: String(baseView),
                  forum_id: baseForum || 0,
                  ids: ids,
                  ts: Date.now()
                }
              });
            }
          } catch (eNav) {}

          openTopicPage(tid, {});
        }
      });
    })();

    window.addEventListener("iad:open_topic_page", (e) => {
      const d = (e && e.detail) ? e.detail : {};
      const tid = d.topic_id ? d.topic_id : 0;
      if (!tid) return;

      // ✅ allow post scroll
      const scrollPostId = d.scroll_post_id ? parseInt(d.scroll_post_id, 10) : 0;
      const highlight = d.highlight_new ? 1 : 0;

      // Allow callers (e.g. feed reply icon) to request opening the reply composer.
      const openReply = d.open_reply ? 1 : 0;

      // Allow callers to jump straight to the latest reply (fetch last page + highlight)
      const gotoLast = d.goto_last ? 1 : 0;

      openTopicPage(tid, {
        scroll_post_id: scrollPostId || 0,
        highlight_new: highlight ? 1 : 0,
        open_reply: openReply ? 1 : 0,
        goto_last: gotoLast ? 1 : 0
      });
    });

    window.addEventListener("iad:open_agora", (e) => {
      const fid = e.detail && e.detail.forum_id ? parseInt(e.detail.forum_id, 10) : 0;
      const nm  = (e.detail && e.detail.forum_name) ? String(e.detail.forum_name) : "";
      if (!fid) return;
      render("agora", fid, nm);
    });

    window.addEventListener("iad:go_agoras", () => { render("agoras", 0, ""); });
    window.addEventListener("iad:go_moderation", () => { render("moderation", 0, ""); });

    // Close topic view: restore the previous Discuss view (no browser back)
    window.addEventListener("iad:topic_close", () => {
      // Clear topic params without pushing history
      setParams({
        iad_topic: null,
        iad_post: null
      }, true);

      if (inSearch) { openSearchPage(lastSearchQ, { no_history: true }); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agora" && lastForumId) { render("agora", lastForumId, lastForumName || "", { no_history: true }); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agoras") { render("agoras", 0, "", { no_history: true }); restoreFeedScrollAfterFeed(); return; }
      render(lastListView || "new", 0, "", { no_history: true });
      restoreFeedScrollAfterFeed();
    });


    window.addEventListener("iad:topic_back", () => {
      // Prefer real browser history so Android back / iOS swipe-back behave naturally.
      try {
        if (window.history && window.history.length > 1) {
          window.history.back();
          return;
        }
      } catch (e) {}
      if (inSearch) { openSearchPage(lastSearchQ); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agora" && lastForumId) { render("agora", lastForumId, lastForumName || ""); restoreFeedScrollAfterFeed(); return; }
      if (lastListView === "agoras") { render("agoras", 0, ""); restoreFeedScrollAfterFeed(); return; }
      render(lastListView || "new", 0, "");
      restoreFeedScrollAfterFeed();
    });

    // ✅ NEW: open search
    window.addEventListener("iad:open_search", (e) => {
      const q = (e.detail && e.detail.q) ? String(e.detail.q) : "";
      if (!q || q.trim().length < 2) return;
      openSearchPage(q);
    });

    // live search updates while typing (no history spam)
    window.addEventListener("iad:search_live", (e) => {
      const q = (e.detail && e.detail.q) ? String(e.detail.q) : "";
      if (!q || q.trim().length < 2) return;

      // If we're already in search view, update results + URL via replaceState
      const view = getParam("iad_view") || "";
      if (view === "search") {
        try {
          setParams({ iad_view: "search", iad_q: q.trim() }, true);
        } catch (err) {}
        openSearchPage(q, { no_history: 1 });
        return;
      }

      // If not in search yet, open it normally
      openSearchPage(q);
    });


    // ✅ NEW: back from search
    window.addEventListener("iad:search_back", () => {
      try {
        if (window.history && window.history.length > 1) {
          window.history.back();
          return;
        }
      } catch (e) {}
      inSearch = false;

      // go back to prior view
      if (searchPrev.view === "agora" && searchPrev.forum_id) {
        render("agora", searchPrev.forum_id, searchPrev.forum_name || "");
        return;
      }
      if (searchPrev.view === "agoras") { render("agoras", 0, ""); return; }
      render(searchPrev.view || "new", 0, "");
    });


    window.addEventListener('iad:feed_loaded', (e) => {
      try {
        const d = (e && e.detail) ? e.detail : {};
        const curOrder = String(d.order || '');
        const curView = (inAgora && lastForumId) ? 'agora' : String(d.view || lastListView || 'new');
        const curForumName = (inAgora && lastForumId) ? (lastForumName || '') : '';
        applyDiscussContextTitle(curView, curForumName, curOrder);
        if (curView === 'agora') {
          setParams({ iad_order: curOrder || null }, true);
        }
      } catch (err) {}
    });

    window.addEventListener('iad:topic_loaded', (e) => {
      try {
        const d = (e && e.detail) ? e.detail : {};
        const topicTitle = String(d.topic_title || '').trim();
        applyDiscussTitle(topicTitle || 'Topic');
      } catch (err) {}
    });

    window.addEventListener("iad:render_feed", (e) => {
      const d = e.detail || {};
      const view = d.view || "new";
      const forumId = parseInt(d.forum_id || "0", 10) || 0;
      const mountEl = d.mount || null;

      if (inAgora) return window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, forumId);
      render(view, 0, "");
    });

    // composer mount
    window.addEventListener("iad:mount_composer", (e) => {
      const d = e.detail || {};
      if (!d.mount) return;

      // Capture a stable context for this composer instance.
      // (Prevents stale topic/forum IDs being used if the user navigates while a composer remains mounted.)
      const ctx = {
        mode: d.mode || "topic",
        forum_id: parseInt(d.forum_id || "0", 10) || 0,
        topic_id: parseInt(d.topic_id || "0", 10) || 0,
        edit_post_id: parseInt(d.edit_post_id || "0", 10) || 0
      };

      const mode = d.mode || "topic";
      d.mount.innerHTML = window.IA_DISCUSS_UI_COMPOSER.composerHTML({ mode, submitLabel: d.submitLabel || null });

      function composerError(ui, msg) {
        try {
          if (ui && typeof ui.setError === "function") return ui.setError(msg);
          if (ui && typeof ui.error === "function") return ui.error(msg);
        } catch (e) {}
      }

      window.IA_DISCUSS_UI_COMPOSER.bindComposer(d.mount, {
        prefillBody: d.prefillBody || "",
        mode,
        topicId: ctx.topic_id || 0,
        startOpen: !!d.start_open,
        onSubmit(payload, ui) {
          if (IA_DISCUSS.loggedIn !== "1") {
            window.dispatchEvent(new CustomEvent("ia:login_required"));
            return;
          }

          let body = payload.body || "";
          if (payload.attachments && payload.attachments.length) {
            const json = JSON.stringify(payload.attachments);
            const b64 = btoa(unescape(encodeURIComponent(json)));
            body = body + "\n\n[ia_attachments]" + b64;
          }

          // Prefer the URL topic when available (deep links / tab navigation)
          let urlTopicId = 0;
          try {
            const u = new URL(window.location.href);
            urlTopicId = parseInt(u.searchParams.get("iad_topic") || "0", 10) || 0;
          } catch (e2) {}

          const effectiveTopicId = urlTopicId || ctx.topic_id;
          const effectiveForumId = ctx.forum_id;

          // Edit flow
          if (ctx.edit_post_id) {
            return window.IA_DISCUSS_API.post("ia_discuss_edit_post", {
              post_id: ctx.edit_post_id,
              body
            }).then((res) => {
              if (!res || !res.success) {
                composerError(ui, (res && res.data && res.data.message) ? res.data.message : "Edit failed");
                return;
              }
              try { ui.clear(); } catch (e) {}
              window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));
              openTopicPage(effectiveTopicId, {});
            });
            return;
          }

          // Topic (new thread) flow
          if (payload.mode === "topic") {
            const forumId = effectiveForumId;
            const title = (payload.title || "").trim();
            if (!forumId) { composerError(ui, "Missing forum_id"); return; }
            if (!title) { composerError(ui, "Title required"); return; }
            if (!body.trim()) { composerError(ui, "Body required"); return; }
            return window.IA_DISCUSS_API.post("ia_discuss_new_topic", {
              forum_id: forumId,
              title,
              body,
              notify: (payload && payload.notify != null) ? payload.notify : 1
            }).then((res) => {
              if (!res || !res.success) {
                composerError(ui, (res && res.data && res.data.message) ? res.data.message : "New topic failed");
                return;
              }

              const topicId = res.data && res.data.topic_id ? parseInt(res.data.topic_id, 10) : 0;
              ui.clear();

              // After creating, open the topic view.
              if (topicId) {
                openTopicPage(topicId, {});
              } else {
                // Fallback: return to current forum feed.
                window.dispatchEvent(new CustomEvent("iad:render_feed", {
                  detail: { view: "new", forum_id: forumId }
                }));
              }
            });
            return;
          }

          // Reply flow
          return window.IA_DISCUSS_API.post("ia_discuss_reply", {
            topic_id: effectiveTopicId,
            body
          }).then((res) => {
            if (!res || !res.success) {
              composerError(ui, (res && res.data && res.data.message) ? res.data.message : "Reply failed");
              return;
            }

            const postId = res.data && res.data.post_id ? parseInt(res.data.post_id, 10) : 0;

            try { ui.clear(); } catch (e) {}
            window.dispatchEvent(new CustomEvent("iad:close_composer_modal"));

            openTopicPage(effectiveTopicId, {
              scroll_post_id: postId || 0,
              highlight_new: 1
            });
          });
        }
      });
    });

    // -----------------------------
    // Browser back/forward integration
    // -----------------------------

    function routeFromURL(options) {
      options = options || {};
      try {
        const u = new URL(window.location.href);
        const topicId = parseInt(u.searchParams.get("iad_topic") || "0", 10) || 0;
        const postId  = parseInt(u.searchParams.get("iad_post")  || "0", 10) || 0;
        const forumId = parseInt(u.searchParams.get("iad_forum") || "0", 10) || 0;
        const forumName = String(u.searchParams.get("iad_forum_name") || "").trim();
        const view    = String(u.searchParams.get("iad_view") || "").trim();
        const q       = String(u.searchParams.get("iad_q") || "").trim();

        if (topicId) {
          openTopicPage(topicId, {
            scroll_post_id: postId || 0,
            highlight_new: postId ? 1 : 0,
            no_history: 1
          });
          return true;
        }

        if (view === "search" && q) {
          openSearchPage(q, { no_history: 1 });
          return true;
        }

        if (forumId) {
          render("agora", forumId, forumName || "", { no_history: 1 });
          return true;
        }

        if (view) {
          render(view, 0, "", { no_history: 1 });
          return true;
        }
      } catch (e) {}

      render("new", 0, "", { no_history: 1 });
      return true;
    }

    // Respond to Android back button / iOS swipe-back / browser back & forward
    window.addEventListener("popstate", () => {
      routeFromURL({ pop: 1 });
    });

    // -----------------------------
    // Initial view (supports deep links)
    // -----------------------------
    routeFromURL({ init: 1 });
  }

  window.IA_DISCUSS_ROUTER = { mount };

})();
