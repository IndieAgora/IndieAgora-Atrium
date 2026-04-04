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

