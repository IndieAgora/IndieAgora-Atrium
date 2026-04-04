
  function iaRelModal(opts){
    opts = opts || {};
    try{ const ex=document.querySelector('.iad-relmodal'); if(ex) ex.remove(); }catch(_){}
    const wrap=document.createElement('div');
    wrap.className='iad-relmodal';
    wrap.innerHTML =
      '<div class="iad-relmodal-backdrop" data-iad-relmodal-close></div>' +
      '<div class="iad-relmodal-card" role="dialog" aria-modal="true">' +
        '<div class="iad-relmodal-title">'+(opts.title||'')+'</div>' +
        '<div class="iad-relmodal-body">'+(opts.body||'')+'</div>' +
        '<div class="iad-relmodal-actions"></div>' +
      '</div>';
    const acts=wrap.querySelector('.iad-relmodal-actions');
    (opts.actions||[{label:'Close'}]).forEach(a=>{
      const b=document.createElement('button');
      b.type='button';
      b.className='iad-relmodal-btn'+(a.primary?' is-primary':'');
      b.textContent=a.label||'OK';
      b.addEventListener('click', async (ev)=>{
        ev.preventDefault(); ev.stopPropagation();
        if (a.onClick){ try{ await a.onClick(); }catch(_){ } }
        if (a.stay) return;
        try{ wrap.remove(); }catch(_){}
      });
      acts.appendChild(b);
    });
    wrap.addEventListener('click',(ev)=>{
      const c=ev.target && ev.target.closest ? ev.target.closest('[data-iad-relmodal-close]') : null;
      if(c){ try{ wrap.remove(); }catch(_){ } }
    }, true);
    document.body.appendChild(wrap);
    return wrap;
  }

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
        posts_total: (data.posts_total != null) ? data.posts_total : 0,
        notify_enabled: (data.notify_enabled != null) ? data.notify_enabled : 0,
        viewer: data.viewer || {}
      };

      if (!append) {
        // snapshot meta for any later re-renders (e.g. goto last reply)
        // IMPORTANT: keep viewer + notify state so header controls (email toggle etc.) don't vanish on re-render.
        meta = {
          topic_id: payload.topic_id,
          topic_title: payload.topic_title,
          forum_id: payload.forum_id,
          forum_name: payload.forum_name,
          topic_time: payload.topic_time,
          last_post_time: payload.last_post_time,
          notify_enabled: (payload.notify_enabled != null) ? payload.notify_enabled : 0,
          viewer: payload.viewer || {}
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
        try {
          window.dispatchEvent(new CustomEvent('iad:topic_loaded', {
            detail: {
              topic_id: payload.topic_id || topicId,
              topic_title: payload.topic_title || 'Topic',
              forum_id: payload.forum_id || 0,
              forum_name: payload.forum_name || ''
            }
          }));
        } catch (eTitle) {}
        R.bindBack(mount);

        // bind quote/reply actions (modal)
        if (A.bindTopicActions) A.bindTopicActions(mount, topicId);

        // topic-level email toggle
        try {
          const chk = mount.querySelector('[data-iad-topic-notify]');
          if (chk) {
            chk.addEventListener('change', () => {
              window.IA_DISCUSS_API.post('ia_discuss_topic_notify_set', {
                topic_id: topicId,
                enabled: chk.checked ? 1 : 0
              }).then(() => {}).catch(() => {});
            });
          }
        } catch (e) {}

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
        const btn  = qs("[data-iad-back-top]", mount);
        if (!btn) return;

        function resolveScroller() {
          function isGoodScroller(el) {
            if (!el) return false;
            try {
              const sh = el.scrollHeight || 0;
              const ch = el.clientHeight || 0;
              if (sh <= ch + 4) return false;
              if (el === document.scrollingElement || el === document.documentElement || el === document.body) return true;
              const cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
              const oy = cs ? String(cs.overflowY || "") : "";
              if (oy === "auto" || oy === "scroll" || oy === "overlay") return true;
              const before = el.scrollTop || 0;
              el.scrollTop = before + 1;
              const moved = (el.scrollTop || 0) !== before;
              el.scrollTop = before;
              return moved;
            } catch (e) {
              return false;
            }
          }

          const panelBody = mount.closest ? mount.closest(".ia-atrium-panel-body, .ia-atrium-panel__body, .ia-panel-body, .ia-panel-scroll, .ia-panel") : null;
          const cands = [
            qs(".iad-modal-sheet", mount),
            qs(".iad-modal-body", mount),
            qs(".iad-topic-modal", mount),
            panelBody,
            mount,
            document.scrollingElement,
            document.documentElement,
            document.body
          ].filter(Boolean);

          for (const el of cands) {
            if (isGoodScroller(el)) return el;
          }
          return document.scrollingElement || document.documentElement || document.body;
        }

        let scroller = resolveScroller();
        let last = (scroller && scroller.scrollTop) ? scroller.scrollTop : 0;
        let shown = false;

        function getTop() {
          try {
            if (scroller === document.scrollingElement || scroller === document.documentElement || scroller === document.body) {
              return (document.scrollingElement && document.scrollingElement.scrollTop) ? document.scrollingElement.scrollTop : (window.pageYOffset || 0);
            }
            return scroller.scrollTop || 0;
          } catch (e) {
            return 0;
          }
        }

        function scrollToTop() {
          try {
            if (scroller === document.scrollingElement || scroller === document.documentElement || scroller === document.body) {
              window.scrollTo({ top: 0, behavior: "smooth" });
            } else {
              scroller.scrollTo({ top: 0, behavior: "smooth" });
            }
          } catch (e) {
            try {
              if (scroller === document.scrollingElement || scroller === document.documentElement || scroller === document.body) window.scrollTo(0, 0);
              else scroller.scrollTop = 0;
            } catch (e2) {}
          }
        }

        function setVisible(v) {
          if (v && !shown) { btn.removeAttribute("hidden"); shown = true; }
          else if (!v && shown) { btn.setAttribute("hidden", ""); shown = false; }
        }

        function onScroll() {
          // Re-resolve if the scroll container changes after a route re-render.
          const curScroller = resolveScroller();
          if (curScroller && curScroller !== scroller) {
            scroller = curScroller;
            last = getTop();
          }

          const cur = getTop();
          const goingUp = (cur < last - 12);
          last = cur;
          if (cur < 220) { setVisible(false); return; }
          if (goingUp) setVisible(true);
        }

        // Bind to both the resolved scroller (if it's an element) and window (if document scroll).
        if (scroller && scroller !== document.scrollingElement && scroller !== document.documentElement && scroller !== document.body) {
          scroller.addEventListener("scroll", onScroll, { passive: true });
        } else {
          window.addEventListener("scroll", onScroll, { passive: true });
        }

        btn.onclick = () => {
          scrollToTop();
          setVisible(false);
        };
      }


      function bindTopicTopbarAutoHide() {
        try {
          if (mount && typeof mount.__iadTopicTopbarTeardown === 'function') {
            try { mount.__iadTopicTopbarTeardown(); } catch (e) {}
            mount.__iadTopicTopbarTeardown = null;
          }
        } catch (e) {}

        function resolveScroller() {
          function isGoodScroller(el) {
            if (!el) return false;
            try {
              const sh = el.scrollHeight || 0;
              const ch = el.clientHeight || 0;
              if (sh <= ch + 4) return false;
              if (el === document.scrollingElement || el === document.documentElement || el === document.body) return true;
              const cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
              const oy = cs ? String(cs.overflowY || '') : '';
              if (oy === 'auto' || oy === 'scroll' || oy === 'overlay') return true;
              const before = el.scrollTop || 0;
              el.scrollTop = before + 1;
              const moved = (el.scrollTop || 0) !== before;
              el.scrollTop = before;
              return moved;
            } catch (e) {
              return false;
            }
          }

          const panelBody = mount && mount.closest
            ? mount.closest('.ia-atrium-panel-body, .ia-atrium-panel__body, .ia-panel-body, .ia-panel-scroll, .ia-panel')
            : null;

          const cands = [
            qs('.iad-modal-body', mount),
            qs('.iad-modal-sheet', mount),
            qs('.iad-topic-modal', mount),
            panelBody,
            mount,
            document.scrollingElement,
            document.documentElement,
            document.body
          ].filter(Boolean);

          for (const el of cands) {
            if (isGoodScroller(el)) return el;
          }
          return document.scrollingElement || document.documentElement || document.body;
        }

        function resolveAtriumTopbar() {
          const shell = document.querySelector('#ia-atrium-shell');
          if (!shell) return null;

          const brand = shell.querySelector('.ia-atrium-brand');
          const cands = [
            brand && brand.closest('.ia-atrium-topbar, .ia-atrium-header, .ia-topbar, .ia-header, header'),
            shell.querySelector('.ia-atrium-topbar, .ia-atrium-header, .ia-topbar, .ia-header, header'),
            brand ? brand.parentElement : null,
            shell.firstElementChild || null
          ].filter(Boolean);

          for (const el of cands) {
            if (!el || el === mount || (mount && mount.contains && mount.contains(el))) continue;
            return el;
          }
          return null;
        }

        const scroller = resolveScroller();
        const topbar = resolveAtriumTopbar();
        if (!scroller || !topbar) return;

        const prev = {
          transform: topbar.style.transform || '',
          transition: topbar.style.transition || '',
          willChange: topbar.style.willChange || '',
          opacity: topbar.style.opacity || ''
        };

        topbar.style.transition = topbar.style.transition || 'transform 180ms ease, opacity 180ms ease';
        topbar.style.willChange = 'transform, opacity';

        let hidden = false;
        let last = 0;

        function getTop() {
          try {
            if (scroller === document.scrollingElement || scroller === document.documentElement || scroller === document.body) {
              return (document.scrollingElement && document.scrollingElement.scrollTop) ? document.scrollingElement.scrollTop : (window.pageYOffset || 0);
            }
            return scroller.scrollTop || 0;
          } catch (e) {
            return 0;
          }
        }

        function setHidden(next) {
          if (next === hidden) return;
          hidden = !!next;
          const modal = qs('.iad-topic-modal', mount);
          if (hidden) {
            topbar.style.transform = 'translateY(calc(-100% - 8px))';
            topbar.style.opacity = '0.01';
            try { if (modal) modal.classList.add('is-topbar-hidden'); } catch (e) {}
          } else {
            topbar.style.transform = prev.transform || 'translateY(0)';
            topbar.style.opacity = prev.opacity || '1';
            try { if (modal) modal.classList.remove('is-topbar-hidden'); } catch (e) {}
          }
        }

        function onScroll() {
          const modal = qs('.iad-topic-modal', mount);
          if (!modal || !document.body.contains(modal)) {
            cleanup();
            return;
          }

          const cur = getTop();
          if (cur < 48) {
            last = cur;
            setHidden(false);
            return;
          }

          if (cur > last + 10) setHidden(true);
          else if (cur < last - 10) setHidden(false);
          last = cur;
        }

        function onTopicClose() {
          cleanup();
        }

        function onTabChanged(ev) {
          const tab = ev && ev.detail ? String(ev.detail.tab || '') : '';
          if (tab !== 'discuss') cleanup();
        }

        function cleanup() {
          try {
            if (scroller && scroller !== document.scrollingElement && scroller !== document.documentElement && scroller !== document.body) {
              scroller.removeEventListener('scroll', onScroll, { passive: true });
            } else {
              window.removeEventListener('scroll', onScroll, { passive: true });
            }
          } catch (e) {}
          window.removeEventListener('iad:topic_close', onTopicClose);
          window.removeEventListener('ia_atrium:tabChanged', onTabChanged);
          topbar.style.transform = prev.transform;
          topbar.style.transition = prev.transition;
          topbar.style.willChange = prev.willChange;
          topbar.style.opacity = prev.opacity;
          try {
            const modal = qs('.iad-topic-modal', mount);
            if (modal) modal.classList.remove('is-topbar-hidden');
          } catch (e) {}
          try { if (mount) mount.__iadTopicTopbarTeardown = null; } catch (e) {}
        }

        if (scroller && scroller !== document.scrollingElement && scroller !== document.documentElement && scroller !== document.body) {
          scroller.addEventListener('scroll', onScroll, { passive: true });
        } else {
          window.addEventListener('scroll', onScroll, { passive: true });
        }
        window.addEventListener('iad:topic_close', onTopicClose);
        window.addEventListener('ia_atrium:tabChanged', onTabChanged);

        last = getTop();
        onScroll();
        try { if (mount) mount.__iadTopicTopbarTeardown = cleanup; } catch (e) {}
      }

      function bindTopicNav() {
        const prevBtn = qs('[data-iad-topic-prev]', mount);
        const nextBtn = qs('[data-iad-topic-next]', mount);
        const topBtn  = qs('[data-iad-topic-top]', mount);
        if (!prevBtn && !nextBtn && !topBtn) return;

        function resolveScroller() {
          // Robust scroller resolver:
          // - Topic view can scroll in different containers depending on whether it's in the modal,
          //   the page, or inside an Atrium panel.
          // - Don't default to the first candidate (it might exist but not be the active scroller).
          function isGoodScroller(el) {
            if (!el) return false;
            try {
              const sh = el.scrollHeight || 0;
              const ch = el.clientHeight || 0;
              if (sh <= ch + 4) return false;
              if (el === document.scrollingElement || el === document.documentElement || el === document.body) return true;
              const cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
              const oy = cs ? String(cs.overflowY || '') : '';
              // Some layouts rely on a parent scroller even when overflowY is "visible".
              // Treat visible as a soft-signal; we'll still accept it if it can actually scroll.
              if (oy === 'auto' || oy === 'scroll' || oy === 'overlay') return true;
              // If scrollTop moves and content is taller, it's effectively a scroller.
              const before = el.scrollTop || 0;
              el.scrollTop = before + 1;
              const moved = (el.scrollTop || 0) !== before;
              el.scrollTop = before;
              return moved;
            } catch (e) {
              return false;
            }
          }

          // 1) Prefer the closest real scroller to the nav button.
          const start = topBtn || mount;
          if (start && start.parentElement) {
            let cur = start.parentElement;
            for (let i = 0; i < 18 && cur; i++) {
              if (isGoodScroller(cur)) return cur;
              cur = cur.parentElement;
            }
          }

          // 2) Common known containers.
          const panelBody = mount && mount.closest
            ? mount.closest('.ia-atrium-panel-body, .ia-atrium-panel__body, .ia-panel-body, .ia-panel-scroll, .ia-panel')
            : null;

          const cands = [
            qs('.iad-modal-body', mount),
            qs('.iad-modal-sheet', mount),
            qs('.iad-topic-modal', mount),
            panelBody,
            mount,
            document.scrollingElement,
            document.documentElement,
            document.body
          ].filter(Boolean);

          for (const el of cands) {
            if (isGoodScroller(el)) return el;
          }

          // 3) Last resort: document.
          return document.scrollingElement || document.documentElement || document.body;
        }

        function navFromState() {
          try {
            const s = (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.get === 'function') ? window.IA_DISCUSS_STATE.get() : {};
            return (s && s.topic_nav) ? s.topic_nav : null;
          } catch (e) { return null; }
        }

        function computeIdsAndIndex(nav) {
          const ids = (nav && Array.isArray(nav.ids)) ? nav.ids.map((n) => parseInt(n, 10) || 0).filter((n) => n > 0) : [];
          const cur = parseInt(topicId || '0', 10) || 0;
          const idx = ids.length ? ids.indexOf(cur) : -1;
          return { ids, cur, idx };
        }

        function tabForView(v) {
          v = String(v || '');
          if (v === 'noreplies') return 'no_replies';
          // unread is a client-side filter over new_posts
          return 'new_posts';
        }

        function syncButtons() {
          const nav = navFromState();
          const { ids, idx } = computeIdsAndIndex(nav);
          const prevId = (idx > 0) ? ids[idx - 1] : 0;
          const nextId = (idx >= 0 && idx < ids.length - 1) ? ids[idx + 1] : 0;
          if (prevBtn) prevBtn.disabled = !prevId && !(nav && nav.view === 'random' && idx > 0);
          if (nextBtn) nextBtn.disabled = !nextId && !(nav && (nav.view === 'random' || nav.view === 'agora' || nav.view === 'new' || nav.view === 'unread' || nav.view === 'noreplies'));
        }

        syncButtons();

        // Back to top (topic view)
        if (topBtn) {
          const sc = resolveScroller();
          const syncTop = () => {
            const cur = sc ? (sc.scrollTop || 0) : 0;
            topBtn.disabled = (cur < 8);
          };
          try { (sc || window).addEventListener('scroll', syncTop, { passive: true }); } catch (e) {}
          syncTop();

          topBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const scc = resolveScroller();
            if (!scc) return;
            try { scc.scrollTop = 0; } catch (_) {}
            try {
              if (typeof scc.scrollTo === 'function') {
                try { scc.scrollTo({ top: 0, left: 0, behavior: 'smooth' }); }
                catch (e1) { scc.scrollTo(0, 0); }
              }
            } catch (_) {}
            try { requestAnimationFrame(() => { try { scc.scrollTop = 0; } catch (_) {} }); } catch (_) {}
            syncTop();
          };
        }

        async function ensureNextTopic(nav) {
          nav = nav || navFromState();
          if (!nav) return 0;

          const { ids, idx } = computeIdsAndIndex(nav);
          if (idx >= 0 && idx < ids.length - 1) return ids[idx + 1];

          // At end of list: extend based on context.
          // 1) Random: fetch another random topic, append, open.
          if (nav.view === 'random') {
            let tid = 0;
            try {
              const baseView = String(nav.base_view || 'new');
              const forumId = parseInt(nav.forum_id || '0', 10) || 0;
              const res = await window.IA_DISCUSS_API.post('ia_discuss_random_topic', {
                tab: tabForView(baseView),
                forum_id: forumId || 0,
                q: ''
              });
              if (res && res.success && res.data && res.data.topic_id) tid = parseInt(res.data.topic_id, 10) || 0;
            } catch (e) {}
            if (!tid) return 0;

            try {
              const nextIds = ids.slice(0);
              if (!nextIds.includes(tid)) nextIds.push(tid);
              if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
                window.IA_DISCUSS_STATE.set({ topic_nav: Object.assign({}, nav, { ids: nextIds, ts: Date.now() }) });
              }
            } catch (e2) {}
            return tid;
          }

          // 2) Agora: advance to next agora and open its first topic.
          if (nav.view === 'agora') {
            let nextForum = 0;
            let nextForumName = '';
            try {
              const s = (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.get === 'function') ? window.IA_DISCUSS_STATE.get() : {};
              const ag = s && s.agora_list ? s.agora_list : null;
              const list = ag && Array.isArray(ag.ids) ? ag.ids.map((n) => parseInt(n, 10) || 0).filter((n) => n > 0) : [];
              const names = ag && ag.names ? ag.names : {};
              const curForum = parseInt(nav.forum_id || '0', 10) || 0;
              const at = list.indexOf(curForum);
              if (list.length) {
                nextForum = list[(at >= 0 ? (at + 1) : 0) % list.length] || 0;
                nextForumName = names[String(nextForum)] || '';
              }
            } catch (eAg) {}
            if (!nextForum) return 0;

            // Load first page of that forum's topics to seed nav.ids.
            try {
              const res = await window.IA_DISCUSS_API.post('ia_discuss_feed', {
                tab: 'new_posts',
                offset: 0,
                forum_id: nextForum
              });
              if (res && res.success && res.data && Array.isArray(res.data.items) && res.data.items.length) {
                const newIds = res.data.items.map((it) => parseInt(it.topic_id || 0, 10) || 0).filter((n) => n > 0);
                const first = newIds[0] || 0;
                if (first) {
                  if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
                    window.IA_DISCUSS_STATE.set({
                      topic_nav: {
                        view: 'agora',
                        forum_id: nextForum,
                        forum_name: String(nextForumName || ''),
                        ids: newIds,
                        server_offset: (typeof res.data.next_offset === 'number') ? res.data.next_offset : newIds.length,
                        has_more: !!res.data.has_more,
                        ts: Date.now()
                      }
                    });
                  }
                  // Also push the agora name into the URL cache so the Agora header can show it on back.
                  try {
                    if (nextForumName && window.history && window.history.replaceState) {
                      const u = new URL(window.location.href);
                      u.searchParams.set('iad_forum_name', String(nextForumName));
                      window.history.replaceState(window.history.state || {}, '', u.toString());
                    }
                  } catch (eUrl) {}
                  return first;
                }
              }
            } catch (eFeed) {}
            return 0;
          }

          // 3) Feed lists (new/unread/no replies): fetch the next page and append.
          const view = String(nav.view || 'new');
          const tab = tabForView(view);
          const forumId = parseInt(nav.forum_id || '0', 10) || 0;

          const offset = parseInt(nav.server_offset || '0', 10) || 0;
          const hasMore = !!nav.has_more;

          // If we don't know if there is more, still try once if offset is > 0.
          if (!hasMore && offset <= 0) return 0;

          try {
            const res = await window.IA_DISCUSS_API.post('ia_discuss_feed', {
              tab: tab,
              offset: offset || 0,
              forum_id: forumId || 0
            });
            if (res && res.success && res.data && Array.isArray(res.data.items) && res.data.items.length) {
              const added = res.data.items.map((it) => parseInt(it.topic_id || 0, 10) || 0).filter((n) => n > 0);
              const merged = ids.slice(0);
              added.forEach((id) => { if (id && !merged.includes(id)) merged.push(id); });

              const nextOffset = (typeof res.data.next_offset === 'number') ? res.data.next_offset : (offset + added.length);
              const nextHasMore = !!res.data.has_more || (added.length >= 20);

              if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
                window.IA_DISCUSS_STATE.set({
                  topic_nav: Object.assign({}, nav, {
                    ids: merged,
                    server_offset: nextOffset,
                    has_more: nextHasMore,
                    ts: Date.now()
                  })
                });
              }

              const { idx: idx2 } = computeIdsAndIndex({ ids: merged });
              if (idx2 >= 0 && idx2 < merged.length - 1) return merged[idx2 + 1];
              // Fallback: open first newly added
              return added[0] || 0;
            } else {
              // Mark no more
              if (window.IA_DISCUSS_STATE && typeof window.IA_DISCUSS_STATE.set === 'function') {
                window.IA_DISCUSS_STATE.set({ topic_nav: Object.assign({}, nav, { has_more: false, ts: Date.now() }) });
              }
            }
          } catch (ePg) {}

          return 0;
        }

        function prevTopicId(nav) {
          nav = nav || navFromState();
          const { ids, idx } = computeIdsAndIndex(nav);
          if (idx > 0) return ids[idx - 1];
          return 0;
        }

        if (prevBtn) {
          prevBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const nav = navFromState();
            const pid = prevTopicId(nav);
            if (pid) {
              window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: pid } }));
              return;
            }
            // In random mode, if state is missing, fall back to browser back.
            try { window.history.back(); } catch (e2) {}
          };
        }

        if (nextBtn) {
          nextBtn.onclick = async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const nav = navFromState();
            const { ids, idx } = computeIdsAndIndex(nav);
            const nextId = (idx >= 0 && idx < ids.length - 1) ? ids[idx + 1] : 0;

            let nid = nextId;
            if (!nid) {
              // Extend list based on context (random/agora/feed).
              nid = await ensureNextTopic(nav);
            }

            if (!nid) return;
            window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: nid } }));
          };
        }

        // If topic_nav changes while topic is open (e.g. list extension), resync buttons.
        try {
          window.addEventListener('storage', (e) => {
            if (e && e.key && String(e.key).indexOf('ia_discuss_state_v1') !== -1) syncButtons();
          });
        } catch (e) {}
      }

      // Load more replies (reveal from cache first, then fetch next server page)

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
          can_load_more: false,
          notify_enabled: (meta.notify_enabled != null) ? meta.notify_enabled : 0,
          viewer: meta.viewer || {}
        };

        mount.innerHTML = R.renderTopicHTML(p2);
        try {
          window.dispatchEvent(new CustomEvent('iad:topic_loaded', {
            detail: {
              topic_id: p2.topic_id || topicId,
              topic_title: p2.topic_title || 'Topic',
              forum_id: p2.forum_id || 0,
              forum_name: p2.forum_name || ''
            }
          }));
        } catch (eTitle2) {}
        R.bindBack(mount);
        if (A.bindTopicActions) A.bindTopicActions(mount, topicId);

        // Re-bind topic-level email toggle after full re-render (goto-last).
        try {
          const chk = mount.querySelector('[data-iad-topic-notify]');
          if (chk) {
            chk.addEventListener('change', () => {
              window.IA_DISCUSS_API.post('ia_discuss_topic_notify_set', {
                topic_id: topicId,
                enabled: chk.checked ? 1 : 0
              }).then(() => {}).catch(() => {});
            });
          }
        } catch (e) {}

        bindAttachmentsModal();
        bindBackTop();
        bindTopicNav();

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
      bindTopicNav();
      bindTopicTopbarAutoHide();
    }

    fetchTopic(topicId, 0)
      .then((res) => apply(res, false))
      .catch(() => {
        R.renderError(mount, "Network error");
        R.bindBack(mount);
      });
  }

  
  // -------------------------------------------------
  // Cross-platform follow/block buttons (next to usernames)
  // -------------------------------------------------
  (function bindRelButtons(){
    if (document.documentElement.getAttribute('data-iad-relbound') === '1') return;
    document.documentElement.setAttribute('data-iad-relbound','1');

    async function relStatus(target){
      try{
        return await window.IA_DISCUSS_API.post('ia_user_rel_status', { target_phpbb: target });
      }catch(e){ return null; }
    }

    function setBtnState(btn, on){
      if (!btn) return;
      if (on) btn.classList.add('is-on'); else btn.classList.remove('is-on');
    }

    document.addEventListener('click', async (e)=>{
      const f = e.target && e.target.closest ? e.target.closest('[data-iad-follow-user]') : null;
      const b = e.target && e.target.closest ? e.target.closest('[data-iad-block-user]') : null;
      if (!f && !b) return;
      if (!window.IA_DISCUSS_API) return;
      e.preventDefault();
      e.stopPropagation();

      const target = parseInt((f||b).getAttribute('data-user-id')||'0',10)||0;
      if (!target) return;

      if (f){
        const res = await window.IA_DISCUSS_API.post('ia_user_follow_toggle', { target_phpbb: target });
        if (res && !res.ok && (res.status === 403 || (res.data && (res.data.message==='Blocked' || res.data.message==='blocked')))){
          iaRelModal({ title:'You are blocked', body:'You can\'t follow or interact with this user right now because a block is active.' });
          return;
        }
        setBtnState(f, !!(res && res.ok && res.data && res.data.following));
        return;
      }
      if (b){
        const isOn = b.classList.contains('is-on');
        iaRelModal({
          title: (isOn ? 'Unblock user?' : 'Block user?'),
          body: (isOn ? 'Are you sure you want to unblock this user?' : 'Are you sure you want to block this user? You won\'t be able to see or interact with each other until unblocked.'),
          actions: [
            {label:'Cancel'},
            {label:(isOn ? 'Unblock' : 'Block'), primary:true, onClick: async ()=>{
              const res = await window.IA_DISCUSS_API.post('ia_user_block_toggle', { target_phpbb: target });
              setBtnState(b, !!(res && res.ok && res.data && res.data.blocked_by_me));
              const st = await window.IA_DISCUSS_API.post('ia_user_rel_status', { target_phpbb: target });
              if (st && st.ok && st.data && st.data.blocked_any){
                if (st.data.blocked_by_me){
                  iaRelModal({ title:'User blocked', body:'This user is blocked. You can unblock them to interact again.', actions:[{label:'Close'},{label:'Unblock', primary:true, onClick: async ()=>{ await window.IA_DISCUSS_API.post('ia_user_block_toggle', { target_phpbb: target }); setBtnState(b,false); }}] });
                } else {
                  iaRelModal({ title:'You are blocked', body:'You can\'t interact with this user right now. Replies are disabled while a block is active.' });
                }
              }
            }}
          ]
        });
        return;
      }
    }, true);

    // Lazy init of states when posts are rendered
    document.addEventListener('ia_discuss_topic_rendered', async (e)=>{
      const mount = e && e.detail ? e.detail.mount : null;
      const root = mount || document;
      const btns = root.querySelectorAll ? root.querySelectorAll('[data-iad-follow-user], [data-iad-block-user]') : [];
      if (!btns || !btns.length) return;
      const seen = {};
      for (const btn of btns){
        const id = parseInt(btn.getAttribute('data-user-id')||'0',10)||0;
        if (!id || seen[id]) continue;
        seen[id]=1;
        const st = await relStatus(id);
        if (!st || !st.ok) continue;
        if (st.data && st.data.blocked_any && !st.data.blocked_by_me && !window.__iadBlockedModalShown){
          window.__iadBlockedModalShown = true;
          iaRelModal({ title:'You are blocked', body:'You can\'t interact with this user right now. Replies and messaging are disabled while a block is active.' });
        }
        root.querySelectorAll(`[data-iad-follow-user][data-user-id="${id}"]`).forEach((x)=>setBtnState(x, !!st.data.following));
        root.querySelectorAll(`[data-iad-block-user][data-user-id="${id}"]`).forEach((x)=>setBtnState(x, !!st.data.blocked_by_me));
      }
    });
  })();


window.IA_DISCUSS_UI_TOPIC = { renderInto };
})();
