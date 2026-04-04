    mount.onclick = function (e) {
      const t = e.target;

      const a = t.closest && t.closest('a[href]');
      if (a) return;

      const modeBtn = t.closest && t.closest("[data-iad-feed-mode]");
      if (modeBtn) {
        e.preventDefault();
        e.stopPropagation();
        const nextMode = String(modeBtn.getAttribute("data-iad-feed-mode") || "loadmore");
        if (nextMode === paginationMode) return;
        paginationMode = (nextMode === 'pages') ? 'pages' : 'loadmore';
        try { localStorage.setItem(paginationStoreKey, paginationMode); } catch (ePM) {}
        currentPage = 1;
        resetFeedState(currentPage);
        setPaginationUI();
        loadNext({ append: false, page: currentPage });
        return;
      }

      const jumpToggle = t.closest && t.closest("[data-iad-feed-jump-toggle]");
      if (jumpToggle) {
        e.preventDefault();
        e.stopPropagation();
        const wrap = mount.querySelector('[data-iad-feed-jump]');
        const input = mount.querySelector('[data-iad-feed-jump-input]');
        if (wrap) {
          if (wrap.hasAttribute('hidden')) {
            wrap.removeAttribute('hidden');
            if (input) {
              input.value = '';
              setTimeout(() => { try { input.focus(); } catch (eJF) {} }, 0);
            }
          } else {
            wrap.setAttribute('hidden', '');
          }
        }
        return;
      }

      const navBtn = t.closest && t.closest("[data-iad-page-nav]");
      if (navBtn) {
        e.preventDefault();
        e.stopPropagation();
        const dir = String(navBtn.getAttribute('data-iad-page-nav') || '');
        if (dir === 'prev' && currentPage > 1) goToPage(currentPage - 1);
        if (dir === 'next' && currentPage < totalPages) goToPage(currentPage + 1);
        return;
      }

      const pageBtn = t.closest && t.closest("[data-iad-page]");
      if (pageBtn) {
        e.preventDefault();
        e.stopPropagation();
        const pageNum = parseInt(pageBtn.getAttribute('data-iad-page') || '1', 10) || 1;
        goToPage(pageNum);
        return;
      }

      const more = t.closest && t.closest("[data-iad-feed-more]");
      if (more) {
        e.preventDefault();
        e.stopPropagation();
        loadMoreClicks++;
        loadNext({ append: true, page: currentPage + 1 });
        return;
      }

      const copyBtn = t.closest && t.closest("[data-copy-topic-link]");
      if (copyBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = copyBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        const pid = card ? parseInt(card.getAttribute("data-first-post-id") || "0", 10) : 0;
        if (tid) {
          copyToClipboard(makeTopicUrl(tid, pid || 0)).then(() => {
            copyBtn.classList.add("is-pressed");
            setTimeout(() => copyBtn.classList.remove("is-pressed"), 450);
          });
        }
        return;
      }

      const shareBtn = t.closest && t.closest("[data-share-topic]");
      if (shareBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = shareBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        const pid = card ? parseInt(card.getAttribute("data-first-post-id") || "0", 10) : 0;
        if (!tid) return;
        openShareModal(tid, pid || 0);
        return;
      }

      const openComments = t.closest && t.closest("[data-open-topic-comments]");
      if (openComments) {
        e.preventDefault();
        e.stopPropagation();
        const card = openComments.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) {
          window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, open_reply: 1 } }));
        }
        return;
      }

      const lastReplyBtn = t.closest && t.closest("[data-open-topic-lastreply]");
      if (lastReplyBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = lastReplyBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) {
          window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, goto_last: 1 } }));
        }
        return;
      }

      const userBtn = t.closest && t.closest("[data-open-user]");
      if (userBtn) {
        e.preventDefault();
        e.stopPropagation();
        openConnectProfile({
          username: userBtn.getAttribute("data-username") || "",
          user_id: userBtn.getAttribute("data-user-id") || "0"
        });
        return;
      }

      const agoraBtn = t.closest && t.closest("[data-open-agora]");
      if (agoraBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = agoraBtn.closest && agoraBtn.closest("[data-topic-id]");
        const fid = parseInt(agoraBtn.getAttribute("data-forum-id") || (card ? (card.getAttribute("data-forum-id") || "0") : "0"), 10) || 0;
        const nm = (agoraBtn.getAttribute("data-forum-name") || (card ? (card.getAttribute("data-forum-name") || "") : "")) || "";

        try {
          const u = new URL(window.location.href);
          const curForum = parseInt(u.searchParams.get("iad_forum") || "0", 10) || 0;
          const curView  = String(u.searchParams.get("iad_view") || "").trim();
          if (curView === "agora" && curForum === fid) return;
        } catch (err) {}

        window.dispatchEvent(new CustomEvent("iad:open_agora", { detail: { forum_id: fid, forum_name: nm } }));
        return;
      }

      const linksBtn = t.closest && t.closest("[data-iad-open-links]");
      if (linksBtn) {
        e.preventDefault();
        e.stopPropagation();
        const raw = linksBtn.getAttribute("data-links-json") || linksBtn.getAttribute("data-links") || "[]";
        try {
          const urls = JSON.parse(raw);
          const card = linksBtn.closest && linksBtn.closest('[data-topic-id]');
          const tEl = card ? card.querySelector('.iad-title,[data-open-topic-title]') : null;
          const titleText = tEl ? (tEl.textContent || '').trim() : '';
          openLinksModal(urls, titleText);
        } catch (err) {}
        return;
      }

      const attBtn = t.closest && t.closest("[data-iad-open-attachments]");
      if (attBtn) {
        e.preventDefault();
        e.stopPropagation();
        const raw = attBtn.getAttribute("data-attachments-json") || "[]";
        try {
          const urls = JSON.parse(raw);
          const card = attBtn.closest && attBtn.closest("[data-topic-id]");
          const tEl = card ? card.querySelector(".iad-title,[data-open-topic-title]") : null;
          const titleText = tEl ? (tEl.textContent || '').trim() : '';
          openAttachmentsModal(urls, titleText);
        } catch (err) {}
        return;
      }

      const videoBtn = t.closest && t.closest("[data-iad-open-video]");
      if (videoBtn) {
        e.preventDefault();
        e.stopPropagation();
        const url = videoBtn.getAttribute("data-video-url") || "";
        if (url) {
          try {
            const card = videoBtn.closest && videoBtn.closest('[data-topic-id]');
            const tEl = card ? card.querySelector('.iad-title,[data-open-topic-title]') : null;
            const titleText = tEl ? (tEl.textContent || '').trim() : '';
            const meta = detectVideoMeta(url);
            if (meta) openVideoModal(meta, titleText);
          } catch (err) {}
        }
        return;
      }

      const quoteBtn = t.closest && t.closest("[data-quote-topic]");
      if (quoteBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = quoteBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, open_reply: 1 } }));
        return;
      }

      const replyBtn = t.closest && t.closest("[data-reply-topic]");
      if (replyBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = replyBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, open_reply: 1 } }));
        return;
      }

      const editBtn = t.closest && t.closest("[data-edit-topic]");
      if (editBtn) {
        e.preventDefault();
        e.stopPropagation();
        alert("Edit is wired into UI, but saving edits isn’t implemented yet.");
        return;
      }

      const openTitle = t.closest && t.closest("[data-open-topic-title],[data-open-topic-excerpt],[data-open-topic]");
      if (openTitle) {
        e.preventDefault();
        e.stopPropagation();
        const card = openTitle.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) {
          if (view === 'replies' || view === 'unread') {
            const pid = card ? parseInt(card.getAttribute('data-open-post-id') || '0', 10) : 0;
            window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, scroll_post_id: pid || 0 } }));
          } else {
            window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, scroll: "" } }));
          }
        }
      }
    };

    mount.onsubmit = function (e) {
      const form = e.target && e.target.closest ? e.target.closest('[data-iad-feed-jump-form]') : null;
      if (!form) return;
      e.preventDefault();
      e.stopPropagation();
      const input = mount.querySelector('[data-iad-feed-jump-input]');
      const nextPage = input ? parseInt(input.value || '0', 10) : 0;
      if (nextPage > 0) {
        goToPage(nextPage);
        const wrap = mount.querySelector('[data-iad-feed-jump]');
        if (wrap) wrap.setAttribute('hidden', '');
      }
    };

    renderShell();
    try {
      const sel = mount.querySelector('[data-iad-sort]');
      if (sel) {
        sel.value = orderKey || '';
        sel.addEventListener('change', function () {
          if (loading) return;
          orderKey = String(sel.value || '');
          try { localStorage.setItem(sortStoreKey, orderKey); } catch (eSS) {}
          currentPage = 1;
          resetFeedState(currentPage);
          setPaginationUI();
          loadNext({ append: false, page: currentPage });
        }, { passive: true });
      }
    } catch (eSort) {}
    setPaginationUI();
    loadNext({ append: false, page: currentPage });
  }
