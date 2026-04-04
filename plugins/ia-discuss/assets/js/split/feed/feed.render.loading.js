    async function loadNext(opts) {
      opts = opts || {};
      const appendMode = !!opts.append;
      const requestedPage = Math.max(1, parseInt(opts.page || currentPage || 1, 10) || 1);

      if (loading) {
        pendingLoad = true;
        return;
      }
      loading = true;

      const moreBtn = mount.querySelector("[data-iad-feed-more]");
      if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = "Loading…"; }

      const data = await loadFeed(view, forumId, appendMode ? serverOffset : Math.max(0, (requestedPage - 1) * pageSize), orderKey, requestedPage);
      const items = Array.isArray(data.items) ? data.items : [];

      totalCount = Math.max(0, parseInt(data.total_count || 0, 10) || 0);
      totalPages = Math.max(0, parseInt(data.total_pages || 0, 10) || 0);
      currentPage = Math.max(1, parseInt(data.current_page || requestedPage || 1, 10) || 1);

      hasMore = !!data.has_more;
      serverOffset = (typeof data.next_offset === "number")
        ? data.next_offset
        : (appendMode ? (serverOffset + items.length) : (currentPage * pageSize));

      if (!mount.querySelector(".iad-feed-list")) renderShell();
      try {
        const sel = mount.querySelector('[data-iad-sort]');
        if (sel) sel.value = orderKey || '';
      } catch (eSort2) {}

      appendItems(items, view, { replace: !appendMode });
      pagesLoaded = appendMode ? (pagesLoaded + 1) : 1;
      setPaginationUI();

      loading = false;

      try {
        const countNow = (mount.querySelectorAll('[data-topic-id]') || []).length;
        window.dispatchEvent(new CustomEvent('iad:feed_page_appended', {
          detail: {
            view: view,
            forum_id: forumId || 0,
            order: orderKey || '',
            server_offset: serverOffset,
            pages_loaded: pagesLoaded,
            has_more: !!hasMore,
            item_count: countNow,
            mount: mount
          }
        }));
      } catch (ePg) {}

      if (!didInitialDispatch) {
        didInitialDispatch = true;
        try {
          window.dispatchEvent(new CustomEvent("iad:feed_loaded", {
            detail: {
              view: view,
              forum_id: forumId || 0,
              order: orderKey || '',
              server_offset: serverOffset,
              pages_loaded: pagesLoaded,
              item_count: (mount.querySelectorAll('[data-topic-id]') || []).length,
              mount: mount
            }
          }));
        } catch (e3) {}
      }

      if (pendingLoad && paginationMode === 'loadmore') {
        pendingLoad = false;
        setTimeout(() => loadNext({ append: true }), 0);
      } else {
        pendingLoad = false;
      }
    }
