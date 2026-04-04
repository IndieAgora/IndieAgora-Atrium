  function renderFeedInto(mount, view, forumId) {
    if (!mount) return;

    const pageSize = 20;
    let serverOffset = 0;
    let hasMore = false;
    let loading = false;
    let pendingLoad = false;
    let didInitialDispatch = false;
    let pagesLoaded = 0;
    let loadMoreClicks = 0;
    let totalCount = 0;
    let totalPages = 0;
    let currentPage = 1;

    let orderKey = "";
    const sortStoreKey = "ia_discuss_sort_" + String(view || "") + "_" + String(forumId || 0);
    try { orderKey = localStorage.getItem(sortStoreKey) || ""; } catch (eS) { orderKey = ""; }

    let paginationMode = "loadmore";
    const paginationStoreKey = "ia_discuss_pagination_" + String(view || "") + "_" + String(forumId || 0);
    try {
      const savedMode = String(localStorage.getItem(paginationStoreKey) || "").trim().toLowerCase();
      paginationMode = (savedMode === "pages") ? "pages" : "loadmore";
    } catch (eP) { paginationMode = "loadmore"; }

    function renderShell() {
      mount.innerHTML = `
        <div class="iad-feed">
          <div class="iad-feed-toolbar">
            <div class="iad-feed-toolbar-left">
              <div class="iad-feed-controls">
                <div class="iad-feed-control-group iad-feed-mode-toggle" role="group" aria-label="Pagination mode">
                  <button type="button" class="iad-iconbtn iad-feed-mode-btn" data-iad-feed-mode="loadmore" aria-pressed="false" aria-label="Continuous scroll with load more" title="Continuous scroll with load more">
                    ${ico("stream")}
                    <span class="iad-screen-reader-text">Load more mode</span>
                  </button>
                  <button type="button" class="iad-iconbtn iad-feed-mode-btn" data-iad-feed-mode="pages" aria-pressed="false" aria-label="Numbered pagination" title="Numbered pagination">
                    ${ico("pages")}
                    <span class="iad-screen-reader-text">Pages mode</span>
                  </button>
                </div>
                <div class="iad-feed-control-group iad-feed-sort-group">
                  <select class="iad-select" data-iad-sort id="iad-sort-${String(view||'')}-${String(forumId||0)}" aria-label="Sort topics" title="Sort topics">
                  <option value="">Most recent</option>
                  <option value="oldest">Oldest first</option>
                  <option value="most_replies">Most replies</option>
                  <option value="least_replies">Least replies</option>
                  ${(forumId && parseInt(forumId,10)>0) ? '<option value="created">Date created</option>' : ''}
                  </select>
                </div>
              </div>
            </div>
            <div class="iad-feed-toolbar-center">
              <div class="iad-feed-pager iad-feed-pager--top" data-iad-feed-pager-top></div>
            </div>
            <div class="iad-feed-toolbar-right">
              <div class="iad-feed-summary" data-iad-feed-summary></div>
              <button type="button" class="iad-iconbtn iad-feed-jump-toggle" data-iad-feed-jump-toggle aria-label="Jump to page" title="Jump to page">
                ${ico("jump")}
                <span class="iad-screen-reader-text">Jump to</span>
              </button>
            </div>
          </div>
          <div class="iad-feed-jump" data-iad-feed-jump hidden>
            <form class="iad-feed-jump-form" data-iad-feed-jump-form>
              <label class="iad-screen-reader-text" for="iad-jump-${String(view||'')}-${String(forumId||0)}">Page number</label>
              <input type="number" min="1" step="1" class="iad-input iad-feed-jump-input" id="iad-jump-${String(view||'')}-${String(forumId||0)}" data-iad-feed-jump-input placeholder="Page number" inputmode="numeric" />
              <button type="submit" class="iad-btn iad-feed-jump-go">Go</button>
            </form>
          </div>
          <div class="iad-feed-list"></div>
          <div class="iad-feed-more"></div>
          <div class="iad-feed-pager iad-feed-pager--bottom" data-iad-feed-pager-bottom></div>
        </div>
      `;
    }

    try {
      mount.__iadFeedCtl = {
        loadMore: () => {
          if (paginationMode === 'pages') return;
          loadNext({ append: true });
        },
        goToPage: (pageNum) => goToPage(pageNum),
        getState: () => ({
          view: view,
          forum_id: forumId || 0,
          order: orderKey || '',
          server_offset: serverOffset,
          pages_loaded: pagesLoaded,
          load_more_clicks: loadMoreClicks,
          has_more: !!hasMore,
          item_count: (mount.querySelectorAll('[data-topic-id]') || []).length,
          pagination_mode: paginationMode,
          current_page: currentPage,
          total_pages: totalPages,
          total_count: totalCount
        })
      };
    } catch (eCtl) {}

    function setMoreButton() {
      const moreWrap = mount.querySelector(".iad-feed-more");
      if (!moreWrap) return;
      if (paginationMode !== 'loadmore' || !hasMore) {
        moreWrap.innerHTML = "";
        return;
      }
      moreWrap.innerHTML = `<button type="button" class="iad-more" data-iad-feed-more>Load more</button>`;
    }

    function appendItems(items, feedView, opts) {
      const list = mount.querySelector(".iad-feed-list");
      if (!list) return;

      opts = opts || {};
      if (feedView === "unread") {
        items = items.filter((it) => !STATE.isRead(it.topic_id));
      }

      if (opts.replace) {
        list.innerHTML = "";
      }

      if (!items.length && !list.children.length) {
        list.innerHTML = `<div class="iad-empty">Nothing here yet.</div>`;
        return;
      }

      if (list.querySelector(".iad-empty")) list.innerHTML = "";
      list.insertAdjacentHTML("beforeend", items.map((it) => feedCard(it, feedView)).join(""));
    }
