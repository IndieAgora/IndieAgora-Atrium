    function buildPageTokens(page, pages) {
      const tokens = [];
      page = Math.max(1, parseInt(page || 1, 10) || 1);
      pages = Math.max(0, parseInt(pages || 0, 10) || 0);
      if (pages <= 0) return tokens;

      const push = (value) => {
        if (!tokens.length || tokens[tokens.length - 1] !== value) tokens.push(value);
      };

      if (pages <= 8) {
        for (let i = 1; i <= pages; i++) push(i);
        return tokens;
      }

      push(1);

      if (page <= 4) {
        push(2); push(3); push(4); push(5);
        push('dots');
        push(pages - 1);
        push(pages);
        return tokens;
      }

      if (page >= (pages - 3)) {
        push('dots');
        for (let i = Math.max(2, pages - 5); i <= pages; i++) push(i);
        return tokens;
      }

      push('dots');
      push(page - 1);
      push(page);
      push(page + 1);
      push(page + 2);
      push('dots');
      push(pages - 1);
      push(pages);
      return tokens;
    }

    function renderPagerMarkup() {
      if (paginationMode !== 'pages' || totalPages <= 1) return '';
      const tokens = buildPageTokens(currentPage, totalPages);
      const prevDisabled = currentPage <= 1 ? ' disabled aria-disabled="true"' : '';
      const nextDisabled = currentPage >= totalPages ? ' disabled aria-disabled="true"' : '';

      return `
        <div class="iad-pagination" aria-label="Pagination">
          <button type="button" class="iad-pagebtn iad-pagebtn-nav" data-iad-page-nav="prev"${prevDisabled} title="Previous page">
            ${ico("prev")}
          </button>
          <div class="iad-pagination-pages">
            ${tokens.map((token) => {
              if (token === 'dots') {
                return '<span class="iad-pagegap" aria-hidden="true">…</span>';
              }
              const num = parseInt(token || 0, 10) || 0;
              const active = num === currentPage;
              return `<button type="button" class="iad-pagebtn ${active ? 'is-active' : ''}" data-iad-page="${num}" aria-current="${active ? 'page' : 'false'}">${num}</button>`;
            }).join('')}
          </div>
          <button type="button" class="iad-pagebtn iad-pagebtn-nav" data-iad-page-nav="next"${nextDisabled} title="Next page">
            ${ico("next")}
          </button>
        </div>
      `;
    }

    function setPaginationUI() {
      const summary = mount.querySelector('[data-iad-feed-summary]');
      if (summary) {
        if (totalCount > 0) {
          const from = Math.min(totalCount, ((currentPage - 1) * pageSize) + 1);
          const to = Math.min(totalCount, currentPage * pageSize);
          summary.textContent = `${from}-${to} of ${totalCount}`;
        } else {
          summary.textContent = `0 results`;
        }
      }

      mount.querySelectorAll('[data-iad-feed-mode]').forEach((btn) => {
        const mode = String(btn.getAttribute('data-iad-feed-mode') || '');
        const active = mode === paginationMode;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      });

      const jumpToggle = mount.querySelector('[data-iad-feed-jump-toggle]');
      const jumpWrap = mount.querySelector('[data-iad-feed-jump]');
      if (jumpToggle) jumpToggle.hidden = !(paginationMode === 'pages' && totalPages > 1);
      if (jumpWrap && paginationMode !== 'pages') jumpWrap.setAttribute('hidden', '');

      const pagerHtml = renderPagerMarkup();
      const pagerTop = mount.querySelector('[data-iad-feed-pager-top]');
      const pagerBottom = mount.querySelector('[data-iad-feed-pager-bottom]');
      if (pagerTop) pagerTop.innerHTML = pagerHtml;
      if (pagerBottom) pagerBottom.innerHTML = pagerHtml;

      setMoreButton();
    }

    function resetFeedState(nextPage) {
      serverOffset = Math.max(0, ((nextPage || 1) - 1) * pageSize);
      hasMore = false;
      pendingLoad = false;
      didInitialDispatch = false;
      pagesLoaded = 0;
      if (paginationMode === 'pages') {
        loadMoreClicks = 0;
      }
      const list = mount.querySelector('.iad-feed-list');
      if (list) list.innerHTML = '';
      const moreWrap = mount.querySelector('.iad-feed-more');
      if (moreWrap) moreWrap.innerHTML = '';
      const jumpInput = mount.querySelector('[data-iad-feed-jump-input]');
      if (jumpInput) jumpInput.value = '';
    }

    function goToPage(pageNum) {
      if (loading) return;
      const nextPage = Math.max(1, parseInt(pageNum || 1, 10) || 1);
      const boundedPage = totalPages > 0 ? Math.min(nextPage, totalPages) : nextPage;
      currentPage = boundedPage;
      resetFeedState(currentPage);
      loadNext({ append: false, page: currentPage });
    }
