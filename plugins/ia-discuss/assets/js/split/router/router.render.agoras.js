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
