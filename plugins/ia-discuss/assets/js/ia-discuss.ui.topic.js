(function () {
  "use strict";

  const U = window.IA_DISCUSS_TOPIC_UTILS || {};
  const R = window.IA_DISCUSS_TOPIC_RENDER || {};
  const A = window.IA_DISCUSS_TOPIC_ACTIONS || {};

  const qs = U.qs;

  function fetchTopic(topicId, offset) {
    if (!window.IA_DISCUSS_API || typeof window.IA_DISCUSS_API.post !== "function") {
      return Promise.resolve({ success: false, data: { message: "IA_DISCUSS_API missing" } });
    }
    return window.IA_DISCUSS_API.post("ia_discuss_topic", {
      topic_id: topicId,
      offset: offset || 0
    });
  }

  function openConnectProfile(payload) {
    const p = payload || {};
    const username = (p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username, user_id, ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try { window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } })); } catch (e2) {}

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  function renderInto(root, topicId, opts) {
    opts = opts || {};
    const mount = root ? qs("[data-iad-view]", root) : null;
    if (!mount) return;

    mount.innerHTML = `<div class="iad-loading">Loading topic…</div>`;

    // Server returns pages of 25 posts.
    // UI reveals in smaller chunks for readability.
    const serverPageSize = 25;
    const uiInitialShow = 4; // OP + 3 replies
    const uiChunkShow = 8;   // reveal 8 more per click

    let fetchedCount = 0;    // total fetched from server
    let shownCount = 0;      // total rendered into DOM
    let cached = [];         // fetched posts cache
    let postsTotal = 0;      // total posts in topic (from server)
    let meta = {};           // topic meta snapshot for re-renders

    function apply(res, append) {
      if (!res || !res.success) {
        const msg = (res && res.data && res.data.message) ? res.data.message : "Failed to load topic";
        R.renderError(mount, msg);
        R.bindBack(mount);
        return;
      }

      const data = res.data || {};
      const payload = {
        topic_id: data.topic_id,
        topic_title: data.topic_title,
        forum_id: data.forum_id,
        forum_name: data.forum_name,
        topic_time: data.topic_time,
        last_post_time: data.last_post_time,
        posts: Array.isArray(data.posts) ? data.posts : [],
        has_more: !!data.has_more,
        posts_total: (data.posts_total != null) ? data.posts_total : 0
      };

      if (!append) {
        // snapshot meta for any later re-renders (e.g. goto last reply without server totals)
        meta = {
          topic_id: payload.topic_id,
          topic_title: payload.topic_title,
          forum_id: payload.forum_id,
          forum_name: payload.forum_name,
          topic_time: payload.topic_time,
          last_post_time: payload.last_post_time
        };

        cached = payload.posts || [];
        fetchedCount = cached.length;
        postsTotal = parseInt(payload.posts_total || "0", 10) || 0;

        shownCount = Math.min(cached.length, uiInitialShow);
        const firstSlice = cached.slice(0, shownCount);

        payload.posts = firstSlice;
        payload.shown_count = shownCount;
        payload.posts_total = postsTotal;
        payload.can_load_more = (cached.length > shownCount) || payload.has_more;

        mount.innerHTML = R.renderTopicHTML(payload);
        R.bindBack(mount);

        // bind quote/reply actions (modal)
        if (A.bindTopicActions) A.bindTopicActions(mount, topicId);

        try {
          if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.markRead === "function") {
            window.IA_DISCUSS_STATE.markRead(topicId);
          }
        } catch (e) {}
// If requested (e.g. feed reply icon), open the reply composer modal.
        try {
          if (opts.open_reply) {
            setTimeout(() => {
              const btn = qs("[data-iad-post-reply]", mount);
              if (btn) btn.click();
            }, 50);
          }
        } catch (e4) {}
        // Deep-link to a specific reply (iad_post) or scroll to a freshly submitted reply.
        // If the reply is not in the first rendered slice, reveal from cache and fetch further pages
        // until found (or the server runs out).
        try {
          const scrollPostId = parseInt(opts.scroll_post_id || "0", 10) || 0;
          const wantHi = !!opts.highlight_new;
          if (scrollPostId > 0) {
            setTimeout(() => {
              ensurePostVisible(scrollPostId, wantHi);
            }, 60);
          }
        } catch (e3) {}

        // Jump straight to the last reply (used by the feed "Last reply" button)
        try {
          if (opts.goto_last) {
            setTimeout(() => {
              if (typeof gotoLastReply === "function") gotoLastReply();
            }, 80);
          }
        } catch (e5) {}
      } else {
        const body = qs(".iad-modal-body", mount);
        if (!body) return;

        // Append additional posts (already computed by caller)
        const moreBtn = qs("[data-iad-more]", body);
        const existingCount = (body.querySelectorAll(".iad-post") || []).length;

        const tmp = document.createElement("div");
        tmp.innerHTML = (payload.posts || []).map((p, idx) => {
          // Default-collapse bodies for older replies (Reddit-style)
          if (idx + existingCount >= uiInitialShow) p._body_collapsed = true;
          return R.renderPostHTML(p, idx, existingCount, { body_collapsed: !!p._body_collapsed });
        }).join("");

        if (moreBtn) body.insertBefore(tmp, moreBtn);
        else body.appendChild(tmp);

        if (!payload.has_more && !payload.can_load_more && moreBtn) moreBtn.remove();
      }

      mount.querySelectorAll("[data-open-user]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          openConnectProfile({
            username: btn.getAttribute("data-username") || "",
            user_id: btn.getAttribute("data-user-id") || "0"
          });
        });
      });

      const openAgora = qs("[data-iad-topic-open-agora]", mount);
      if (openAgora) openAgora.addEventListener("click", () => {
        const fid = parseInt(openAgora.getAttribute("data-forum-id") || "0", 10) || 0;
        const nm  = openAgora.getAttribute("data-forum-name") || "";
        if (fid) window.dispatchEvent(new CustomEvent("iad:open_agora", { detail: { forum_id: fid, forum_name: nm } }));
      });

      function bindAttachmentsModal() {
        if (!U.openListModal) return;
        // Delegate once per mount
        if (mount.getAttribute("data-iad-attach-bound") === "1") return;
        mount.setAttribute("data-iad-attach-bound", "1");

        mount.addEventListener("click", (e) => {
          const t = e.target;
          const btn = t && t.closest ? t.closest("[data-iad-open-attachments]") : null;
          if (!btn) return;
          e.preventDefault();
          e.stopPropagation();

          const raw = btn.getAttribute("data-attachments-json") || "[]";
          let items = [];
          try { items = JSON.parse(raw) || []; } catch (err) { items = []; }
          const titleEl = mount.querySelector(".iad-modal-title");
          const tt = titleEl ? (titleEl.textContent || "").trim() : "";
          U.openListModal(tt ? `Attachments — ${tt}` : "Attachments", items);
        }, true);
      }

      function updateCount() {
        const el = qs("[data-iad-topic-count]", mount);
        if (!el) return;
        if (postsTotal > 0) el.textContent = `Showing ${Math.min(shownCount, postsTotal)} of ${postsTotal}`;
        else el.textContent = `Showing ${shownCount}`;
      }

      function appendPosts(postsToAdd, startIndex) {
        const body = qs(".iad-modal-body", mount);
        if (!body) return;
        const moreBtn = qs("[data-iad-more]", body);

        const tmp = document.createElement("div");
        tmp.innerHTML = (postsToAdd || []).map((p, idx) => {
          // Replies revealed later default to collapsed body (Reddit-style)
          p._body_collapsed = true;
          return R.renderPostHTML(p, idx, startIndex, { body_collapsed: true });
        }).join("");

        if (moreBtn) body.insertBefore(tmp, moreBtn);
        else body.appendChild(tmp);

        // Update shown count
        shownCount += (postsToAdd || []).length;
        updateCount();
        
      }

      async function ensurePostVisible(postId, doHighlight) {
        postId = parseInt(postId || "0", 10) || 0;
        if (!postId) return false;

        const maxSteps = 60; // safety cap
        let steps = 0;

        function applyScrollAndHighlight(el) {
          if (!el) return;
          try { el.scrollIntoView({ behavior: "smooth", block: "center" }); } catch (e) {}
          if (!doHighlight) return;
          // Prefer class highlight if CSS exists, otherwise fallback inline outline.
          try { el.classList.add("iad-highlight"); } catch (e) {}
          const prevOutline = el.style.outline;
          const prevBox = el.style.boxShadow;
          el.style.outline = "2px solid rgba(255,255,255,0.25)";
          el.style.boxShadow = "0 0 0 4px rgba(255,255,255,0.06)";
          setTimeout(() => {
            try { el.classList.remove("iad-highlight"); } catch (e) {}
            el.style.outline = prevOutline;
            el.style.boxShadow = prevBox;
          }, 2200);
        }

        while (steps < maxSteps) {
          const el = mount.querySelector(`[data-post-id="${postId}"]`);
          if (el) {
            applyScrollAndHighlight(el);
            return true;
          }

          // Reveal more from cached posts first
          if (cached && cached.length > shownCount) {
            const remaining = cached.length - shownCount;
            const take = Math.min(uiChunkShow, remaining);
            const slice = cached.slice(shownCount, shownCount + take);
            appendPosts(slice, shownCount);
            steps++;
            continue;
          }

          // Need to fetch another page from server
          if (!payload.has_more) break;

          try {
            const r = await fetchTopic(topicId, fetchedCount);
            if (!r || !r.success) break;
            const d = r.data || {};
            const newPosts = Array.isArray(d.posts) ? d.posts : [];
            if (!newPosts.length) {
              payload.has_more = false;
              break;
            }

            cached = (cached || []).concat(newPosts);
            fetchedCount = cached.length;
            payload.has_more = !!d.has_more;
            if (d.posts_total != null) {
              const t = parseInt(d.posts_total, 10) || 0;
              if (t > 0) postsTotal = t;
              updateCount();
            }
          } catch (e) {
            break;
          }

          steps++;
        }

        return false;
      }


      function bindBackTop() {
        const body = qs(".iad-modal-body", mount);
        const btn  = qs("[data-iad-back-top]", mount);
        if (!body || !btn) return;

        let last = body.scrollTop || 0;
        let shown = false;

        function setVisible(v) {
          if (v && !shown) { btn.removeAttribute("hidden"); shown = true; }
          else if (!v && shown) { btn.setAttribute("hidden", ""); shown = false; }
        }

        body.addEventListener("scroll", () => {
          const cur = body.scrollTop || 0;
          const goingUp = (cur < last - 12);
          last = cur;
          if (cur < 220) { setVisible(false); return; }
          if (goingUp) setVisible(true);
        }, { passive: true });

        btn.onclick = () => {
          try { body.scrollTo({ top: 0, behavior: "smooth" }); }
          catch (e) { body.scrollTop = 0; }
          setVisible(false);
        };
      }

      // Load more replies (reveal from cache first, then fetch next server page)
      const more = qs("[data-iad-more]", mount);
      if (more) {
        more.onclick = function () {
          const remainingCached = cached.length - shownCount;
          if (remainingCached > 0) {
            const take = Math.min(uiChunkShow, remainingCached);
            const slice = cached.slice(shownCount, shownCount + take);
            appendPosts(slice, shownCount);
            // If we've now shown everything we have and server says no more, remove button.
            if (shownCount >= cached.length && !payload.has_more) more.remove();
            return;
          }

          // Need to fetch another page
          if (!payload.has_more) {
            more.remove();
            return;
          }

          more.disabled = true;
          more.textContent = "Loading…";

          fetchTopic(topicId, fetchedCount).then((r) => {
            more.disabled = false;
            more.textContent = "Load more replies";
            if (!r || !r.success) return;

            const d = r.data || {};
            const newPosts = Array.isArray(d.posts) ? d.posts : [];
            if (!newPosts.length) {
              payload.has_more = false;
              more.remove();
              return;
            }

            // merge into cache
            cached = cached.concat(newPosts);
            fetchedCount = cached.length;
            payload.has_more = !!d.has_more;
            if (d.posts_total != null) postsTotal = parseInt(d.posts_total, 10) || postsTotal;

            const take = Math.min(uiChunkShow, newPosts.length);
            appendPosts(newPosts.slice(0, take), shownCount);
            if (!payload.has_more && shownCount >= cached.length) more.remove();
          }).catch(() => {
            more.disabled = false;
            more.textContent = "Load more replies";
            alert("Could not load more replies.");
          });
        };
      }

      
      async function gotoLastReply() {
        // Goal: behave like the copy-link deep-jump (no manual "Load more" clicking),
        // but target the last reply instead of a known post ID.
        //
        // We cannot trust `posts_total` on this build (it may be missing/0/wrong),
        // so we advance using the server's `has_more` flag until exhausted.

        // Prevent double-runs if the user clicks repeatedly.
        if (mount.getAttribute("data-iad-goto-last-running") === "1") return;
        mount.setAttribute("data-iad-goto-last-running", "1");

        const maxPages = 120; // safety cap
        let pages = 0;

        try {
          while (payload.has_more && pages < maxPages) {
            const r = await fetchTopic(topicId, fetchedCount);
            if (!r || !r.success) break;
            const d = r.data || {};
            const newPosts = Array.isArray(d.posts) ? d.posts : [];
            if (!newPosts.length) {
              payload.has_more = false;
              break;
            }

            cached = (cached || []).concat(newPosts);
            fetchedCount = cached.length;
            payload.has_more = !!d.has_more;
            pages++;
          }
        } catch (e) {
          // fall through to best-effort scroll/highlight
        }

        // Render everything we have so the last reply exists in the DOM.
        // For goto-last we want the topic "uncollapsed" so the last reply is fully visible.
        const all = (cached || []).map((p) => {
          p._body_collapsed = false;
          return p;
        });

        shownCount = all.length;

        const p2 = {
          topic_id: meta.topic_id || topicId,
          topic_title: meta.topic_title || "Topic",
          forum_id: meta.forum_id || 0,
          forum_name: meta.forum_name || "agora",
          topic_time: meta.topic_time || 0,
          last_post_time: meta.last_post_time || 0,
          posts: all,
          has_more: false,
          posts_total: postsTotal || 0,
          shown_count: shownCount,
          can_load_more: false
        };

        mount.innerHTML = R.renderTopicHTML(p2);
        R.bindBack(mount);
        if (A.bindTopicActions) A.bindTopicActions(mount, topicId);
        bindAttachmentsModal();
        bindBackTop();

        // Jump to the last rendered reply and highlight it.
        setTimeout(() => {
          const body = qs(".iad-modal-body", mount);
          const els = mount.querySelectorAll(".iad-post");
          const lastEl = els && els.length ? els[els.length - 1] : null;

          if (lastEl) {
            // Align to the *start* of the last reply (centering tall posts lands mid-body).
            try { lastEl.scrollIntoView({ behavior: "smooth", block: "start" }); } catch (e2) {}

            // If we're inside the modal body, nudge scroll so the element top is visible under any sticky header.
            setTimeout(() => {
              try {
                const sc = body || lastEl.closest(".iad-modal-body");
                if (!sc) return;
                const br = sc.getBoundingClientRect();
                const er = lastEl.getBoundingClientRect();
                const delta = (er.top - br.top) - 12;
                if (Math.abs(delta) > 2) sc.scrollTop += delta;
              } catch (e3) {}
            }, 80);

            // Brief highlight (class + inline fallback so it's visible even if CSS doesn't style it strongly).
            try { lastEl.classList.add("iad-highlight"); } catch (e4) {}
            const prevOutline = lastEl.style.outline;
            const prevBox = lastEl.style.boxShadow;
            lastEl.style.outline = "2px solid rgba(255,255,255,0.25)";
            lastEl.style.boxShadow = "0 0 0 4px rgba(255,255,255,0.06)";
            setTimeout(() => {
              try { lastEl.classList.remove("iad-highlight"); } catch (e5) {}
              lastEl.style.outline = prevOutline;
              lastEl.style.boxShadow = prevBox;
            }, 2200);
          } else if (body) {
            try { body.scrollTo({ top: body.scrollHeight, behavior: "smooth" }); }
            catch (e3) { body.scrollTop = body.scrollHeight; }
          }

          mount.removeAttribute("data-iad-goto-last-running");
        }, 60);
      }

// Go to last reply (fetch last page and scroll)
      const lastBtn = qs("[data-iad-goto-last]", mount);
      if (lastBtn) {
        lastBtn.onclick = function () {
          // delegate to the internal implementation so callers don't depend on DOM presence
          const prevDisabled = !!lastBtn.disabled;
          const prevText = lastBtn.textContent;
          lastBtn.disabled = true;
          lastBtn.textContent = "Loading…";
          try {
            gotoLastReply();
          } finally {
            // restore quickly; deeper loading state is handled inside gotoLastReply()
            setTimeout(() => {
              lastBtn.disabled = prevDisabled;
              lastBtn.textContent = prevText || "Last reply";
            }, 150);
          }
        };
      }

      updateCount();
      
      bindAttachmentsModal();
      bindBackTop();
    }

    fetchTopic(topicId, 0)
      .then((res) => apply(res, false))
      .catch(() => {
        R.renderError(mount, "Network error");
        R.bindBack(mount);
      });
  }

  window.IA_DISCUSS_UI_TOPIC = { renderInto };
})();
