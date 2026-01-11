/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.feed.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.feed = NS.ui.feed || {};

  let _bound = false;
  let _loading = false;
  let _page = 1;
  let _perPage = 10;
  let _total = null;

  function mount() {
    const R = NS.util.qs("#ia-stream-shell");
    if (!R) return null;
    return NS.util.qs('[data-panel="feed"] .ia-stream-feed', R);
  }

  function esc(s) {
    s = String(s ?? "");
    return s.replace(/[&<>"]|'/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  function fmtNum(n) {
    n = Number(n || 0);
    try { return n.toLocaleString(); } catch (e) { return String(n); }
  }

  // Stream modals should have a stable Atrium URL (not a direct PeerTube link).
  function streamPageUrl(videoId) {
    const id = String(videoId || '').trim();
    if (!id) return '';
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      u.searchParams.set('video', id);
      u.hash = '';
      return u.toString();
    } catch (e) {
      return (window.location.origin || '') + '/?tab=stream&video=' + encodeURIComponent(id);
    }
  }

  function ico(name) {
    // Minimal monochrome UI icons (kept inline to avoid asset coupling).
    const common = 'viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"';
    if (name === 'up')   return '<svg ' + common + '><path d="M12 5l7 8h-4v6H9v-6H5l7-8z" fill="currentColor"/></svg>';
    if (name === 'down') return '<svg ' + common + '><path d="M12 19l-7-8h4V5h6v6h4l-7 8z" fill="currentColor"/></svg>';
    if (name === 'reply') return '<svg ' + common + '><path d="M21 12a8 8 0 0 1-8 8H6l-4 3V8a8 8 0 0 1 8-8h3a8 8 0 0 1 8 8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
    if (name === 'link') return '<svg ' + common + '><path d="M10 13a5 5 0 0 1 0-7l1-1a5 5 0 0 1 7 7l-1 1" fill="none" stroke="currentColor" stroke-width="2"/><path d="M14 11a5 5 0 0 1 0 7l-1 1a5 5 0 0 1-7-7l1-1" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
    if (name === 'share') return '<svg ' + common + '><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3v12" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 8l5-5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
    if (name === 'copy') return '<svg ' + common + '><path d="M8 8h12v12H8z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 16H3a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
    return '';
  }

  async function copyText(text) {
    text = String(text || '');
    if (!text) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (e) {}

    try {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', 'readonly');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      return true;
    } catch (e) {
      return false;
    }
  }

  function renderPlaceholder(msg) {
    const M = mount();
    if (!M) return;
    M.innerHTML = '<div class="ia-stream-empty">' + esc(msg || '') + '</div>';
  }

  function cardHtml(v) {
    const id = v && v.id ? String(v.id) : '';
    const title = v && v.title ? v.title : '';
    const ago = v && v.published_ago ? v.published_ago : '';
    const embed = v && v.embed_url ? v.embed_url : '';
    const url = v && v.url ? v.url : '';
    const excerpt = v && v.excerpt ? v.excerpt : '';
    const support = v && v.support ? v.support : '';

    const ch = (v && v.channel) ? v.channel : {};
    const chName = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : '';
    const chUrl = ch && ch.url ? ch.url : '';

    const counts = (v && v.counts) ? v.counts : {};
    const viewsN = Number(counts.views || 0);
    const likesN = Number(counts.likes || 0);
    const dislikesN = Number(counts.dislikes || 0);
    const commentsN = (counts && typeof counts.comments !== 'undefined') ? Number(counts.comments || 0) : 0;

    const views = fmtNum(viewsN);
    const likes = fmtNum(likesN);
    const dislikes = fmtNum(dislikesN);
    const comments = fmtNum(commentsN);

    const player = embed
      ? '<div class="ia-stream-player"><iframe loading="lazy" src="' + esc(embed) + '" title="' + esc(title) + '" allow="fullscreen; autoplay; encrypted-media" allowfullscreen></iframe></div>'
      : '';

    const metaLeft =
      '<span class="iad-sub">' + (chName ? esc(chName) : 'Channel') + '</span>' +
      (ago ? '<span> · ' + esc(ago) + '</span>' : '');

    return (
      '<article class="iad-card ia-stream-card" data-ia-stream-video-card="' + esc(id) + '">' +
        '<div class="iad-card-meta">' +
          (chUrl ? ('<a class="iad-agora-link" href="' + esc(chUrl) + '" target="_blank" rel="noopener">' + metaLeft + '</a>') : metaLeft) +
        '</div>' +

        '<div class="iad-card-title" data-open-video="' + esc(id) + '">' + esc(title) + '</div>' +

        (excerpt ? ('<div class="iad-card-excerpt" data-open-video="' + esc(id) + '"><p>' + esc(excerpt) + '</p></div>') : '') +

        (player ? ('<div class="ia-stream-embed">' + player + '</div>') : '') +

        (support ? ('<div class="ia-stream-support"><span class="ia-stream-support-label">Support:</span> ' + esc(support) + '</div>') : '') +

        '<div class="iad-card-actions ia-stream-actions">' +

          '<button type="button" class="iad-iconbtn" data-ia-stream-rate="like" data-video-id="' + esc(id) + '" aria-label="Like">' +
            ico('up') + '<span class="iad-count" data-ia-stream-likes>' + esc(likes) + '</span>' +
          '</button>' +

          '<button type="button" class="iad-iconbtn" data-ia-stream-rate="dislike" data-video-id="' + esc(id) + '" aria-label="Dislike">' +
            ico('down') + '<span class="iad-count" data-ia-stream-dislikes>' + esc(dislikes) + '</span>' +
          '</button>' +

          '<button type="button" class="iad-iconbtn" data-open-comments="' + esc(id) + '" aria-label="Replies">' +
            ico('reply') + '<span class="iad-count" data-ia-stream-comments-count="' + esc(String(commentsN)) + '">' + esc(comments) + '</span>' +
          '</button>' +

          '<button type="button" class="iad-iconbtn" data-ia-stream-copy-video="' + esc(id) + '" data-ia-stream-copy-url="' + esc(streamPageUrl(id)) + '" aria-label="Copy link">' +
            ico('link') +
          '</button>' +

          '<button type="button" class="iad-iconbtn" data-ia-stream-share="' + esc(id) + '" data-ia-stream-share-url="' + esc(streamPageUrl(id)) + '" aria-label="Share to Connect">' +
            ico('share') +
          '</button>' +

          '<span class="ia-stream-views">Views: <span data-ia-stream-views>' + esc(views) + '</span></span>' +
        '</div>' +

      '</article>'
    );
  }

  function hasMore() {
    if (_total === null || _total === undefined) return true;
    const loaded = _page * _perPage;
    return loaded < _total;
  }

  function loadMoreHtml() {
    if (!hasMore()) return '';
    return (
      '<div class="ia-stream-loadmore">' +
        '<button type="button" class="ia-stream-loadmore-btn" data-ia-stream-loadmore>' +
          (_loading ? 'Loading…' : 'Load more') +
        '</button>' +
      '</div>'
    );
  }

  function setButtonState(disabled) {
    const M = mount();
    if (!M) return;
    const btn = NS.util.qs('[data-ia-stream-loadmore]', M);
    if (!btn) return;
    try { btn.disabled = !!disabled; } catch (e) {}
    btn.textContent = disabled ? 'Loading…' : 'Load more';
  }

  function renderItems(items, append) {
    const M = mount();
    if (!M) return;

    const lm = NS.util.qs('.ia-stream-loadmore', M);
    if (lm && lm.parentNode) lm.parentNode.removeChild(lm);

    const html = (Array.isArray(items) ? items : []).map(cardHtml).join('');
    if (append) M.insertAdjacentHTML('beforeend', html);
    else M.innerHTML = html;

    const tail = loadMoreHtml();
    if (tail) M.insertAdjacentHTML('beforeend', tail);
  }

  function showActionError(res, fallback) {
    const msg = (res && (res.error || res.message)) ? String(res.error || res.message) : String(fallback || 'Action failed');
    try { console.warn('IA Stream action error:', msg, res); } catch (e) {}
    // keep UI quiet; Stream already has modal messaging for mint.
  }

  function authModal(opts) {
    opts = opts || {};
    return new Promise((resolve) => {
      const host = document.createElement('div');
      host.className = 'ia-stream-auth-modal';
      host.innerHTML =
        '<div class="ia-stream-auth-dialog" role="dialog" aria-modal="true" aria-label="Auth">' +
          '<div class="ia-stream-auth-head">' +
            '<div class="ia-stream-auth-title">' + esc(opts.title || 'Enable Stream actions') + '</div>' +
            '<button type="button" class="ia-stream-auth-x" aria-label="Close">✕</button>' +
          '</div>' +
          '<div class="ia-stream-auth-body">' +
            '<div class="ia-stream-auth-msg">' + esc(opts.message || 'Please confirm your password to enable this action.') + '</div>' +
            '<label class="ia-stream-auth-label">Password</label>' +
            '<input class="ia-stream-auth-input" type="password" autocomplete="current-password" />' +
            '<div class="ia-stream-auth-actions">' +
              '<button type="button" class="ia-stream-auth-btn ia-stream-auth-cancel">Cancel</button>' +
              '<button type="button" class="ia-stream-auth-btn ia-stream-auth-ok">Confirm</button>' +
            '</div>' +
          '</div>' +
        '</div>';

      document.body.appendChild(host);
      const input = NS.util.qs('.ia-stream-auth-input', host);
      const okBtn = NS.util.qs('.ia-stream-auth-ok', host);
      const cancelBtn = NS.util.qs('.ia-stream-auth-cancel', host);
      const xBtn = NS.util.qs('.ia-stream-auth-x', host);

      function cleanup(v) {
        try { host.remove(); } catch (e) {}
        resolve(v);
      }

      function onCancel() { cleanup({ ok: false }); }
      function onOk() {
        const pw = String((input && input.value) || '').trim();
        if (!pw) { try { input && input.focus(); } catch (e) {} return; }
        cleanup({ ok: true, password: pw });
      }

      if (cancelBtn) cancelBtn.addEventListener('click', onCancel);
      if (xBtn) xBtn.addEventListener('click', onCancel);
      if (okBtn) okBtn.addEventListener('click', onOk);
      host.addEventListener('click', (e) => { if (e.target === host) onCancel(); });
      host.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') onCancel();
        if (e.key === 'Enter') onOk();
      });

      setTimeout(() => { try { input && input.focus(); } catch (e) {} }, 10);
    });
  }

  let _mintInFlight = false;
  let _mintPrompted = false;

  async function ensurePeerTubeUserToken() {
    if (_mintInFlight) return false;
    if (_mintPrompted) return false;
    _mintPrompted = true;

    const m = await authModal({
      title: 'Enable Stream actions',
      message: 'To perform this action, Atrium may need to create/link your PeerTube account and mint a token.'
    });
    if (!m || !m.ok) return false;

    const password = String(m.password || '').trim();
    if (!password) return false;

    _mintInFlight = true;
    const res = await NS.api.post('ia_stream_pt_mint_token', { password });
    _mintInFlight = false;

    if (!res || res.ok === false) {
      showActionError(res, 'Token mint failed');
      return false;
    }

    return true;
  }

  function updateCardCounts(videoId, v) {
    videoId = String(videoId || '');
    if (!videoId) return;

    const M = mount();
    if (!M) return;

    const card = NS.util.qs('[data-ia-stream-video-card="' + videoId.replace(/"/g, '&quot;') + '"]', M);
    if (!card) return;

    const counts = v && v.counts ? v.counts : {};
    const likes = fmtNum(counts.likes || 0);
    const dislikes = fmtNum(counts.dislikes || 0);
    const views = fmtNum(counts.views || 0);
    const comments = fmtNum(counts.comments || 0);

    const l = NS.util.qs('[data-ia-stream-likes]', card);
    if (l) l.textContent = likes;
    const d = NS.util.qs('[data-ia-stream-dislikes]', card);
    if (d) d.textContent = dislikes;
    const vw = NS.util.qs('[data-ia-stream-views]', card);
    if (vw) vw.textContent = views;
    const c = NS.util.qs('[data-ia-stream-comments-count]', card);
    if (c) c.textContent = comments;
  }

  async function rate(videoId, rating) {
    videoId = String(videoId || '').trim();
    rating = String(rating || '').trim();
    if (!videoId) return;
    if (rating !== 'like' && rating !== 'dislike') return;

    let res = await NS.api.rateVideo({ id: videoId, rating });
    if (res && res.ok === false && res.code === 'missing_user_token') {
      const ok = await ensurePeerTubeUserToken();
      if (!ok) return;
      res = await NS.api.rateVideo({ id: videoId, rating });
    }

    if (!res || res.ok === false) {
      showActionError(res, 'Rate failed');
      return;
    }

    if (res.item) {
      updateCardCounts(videoId, res.item);
      // If the fullscreen modal is open, let it update too.
      try {
        if (NS.ui.video && typeof NS.ui.video.updateCounts === 'function') {
          NS.ui.video.updateCounts(videoId, res.item);
        }
      } catch (e) {}
    }
  }

  function bindOnce() {
    if (_bound) return;
    const M = mount();
    if (!M) return;
    _bound = true;

    NS.util.on(M, 'click', async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      // Load more
      const lm = (t.closest ? t.closest('[data-ia-stream-loadmore]') : null);
      if (lm) {
        ev.preventDefault();
        ev.stopPropagation();
        if (_loading) return;
        if (!hasMore()) return;

        _loading = true;
        setButtonState(true);

        const next = _page + 1;
        const res = await NS.api.fetchFeed({ page: next, per_page: _perPage });

        _loading = false;

        if (!res || res.ok === false) {
          setButtonState(false);
          return;
        }

        const items = Array.isArray(res.items) ? res.items : [];
        if (res.meta) {
          if (typeof res.meta.total === 'number') _total = res.meta.total;
          if (typeof res.meta.per_page === 'number') _perPage = res.meta.per_page;
          if (typeof res.meta.page === 'number') _page = res.meta.page;
          else _page = next;
        } else {
          _page = next;
        }

        if (items.length) renderItems(items, true);

        if (!hasMore()) {
          const wrap = NS.util.qs('.ia-stream-loadmore', M);
          if (wrap && wrap.parentNode) wrap.parentNode.removeChild(wrap);
        } else {
          setButtonState(false);
        }

        return;
      }

      // Like/dislike
      const rbtn = (t.closest ? t.closest('[data-ia-stream-rate]') : null);
      if (rbtn) {
        ev.preventDefault();
        ev.stopPropagation();
        const rating = rbtn.getAttribute('data-ia-stream-rate') || '';
        const vid = rbtn.getAttribute('data-video-id') || '';
        await rate(vid, rating);
        return;
      }

      // Copy video link
      const copyBtn = (t.closest ? t.closest('[data-ia-stream-copy-video]') : null);
      if (copyBtn) {
        ev.preventDefault();
        ev.stopPropagation();
        const u = copyBtn.getAttribute('data-ia-stream-copy-url') || '';
        await copyText(u);
        return;
      }

      // Share to Connect
      const shareBtn = (t.closest ? t.closest('[data-ia-stream-share]') : null);
      if (shareBtn) {
        ev.preventDefault();
        ev.stopPropagation();
        const vid = shareBtn.getAttribute('data-ia-stream-share') || '';
        const u = shareBtn.getAttribute('data-ia-stream-share-url') || '';
        try { window.dispatchEvent(new CustomEvent('ia:share_to_connect', { detail: { type: 'video', video_id: vid, url: u } })); } catch (e) {}
        return;
      }

      // Open comments
      const cbtn = (t.closest ? t.closest('[data-open-comments]') : null);
      if (cbtn) {
        const id = cbtn.getAttribute('data-open-comments') || '';
        if (!id) return;
        ev.preventDefault();
        ev.stopPropagation();
        if (NS.ui.video && NS.ui.video.open) NS.ui.video.open(id, { focus: 'comments' });
        return;
      }

      // Open video (title/excerpt)
      const vbtn = (t.closest ? t.closest('[data-open-video]') : null);
      if (vbtn) {
        const id = vbtn.getAttribute('data-open-video') || '';
        if (!id) return;
        ev.preventDefault();
        ev.stopPropagation();
        if (NS.ui.video && NS.ui.video.open) NS.ui.video.open(id);
        return;
      }
    });

    // Keep counts in sync with modal actions
    try {
      window.addEventListener('ia:stream_video_counts', function (e) {
        const d = e && e.detail ? e.detail : {};
        const vid = String(d.id || '');
        const c = d.counts || {};
        if (!vid) return;
        const cards = M.querySelectorAll('[data-ia-stream-video-card="' + CSS.escape(vid) + '"]');
        cards.forEach((card) => {
          const likeEl = card.querySelector('[data-ia-stream-likes]');
          const disEl = card.querySelector('[data-ia-stream-dislikes]');
          const viewEl = card.querySelector('[data-ia-stream-views]');
          const comEl = card.querySelector('[data-ia-stream-comments-count]');
          if (likeEl && typeof c.likes !== 'undefined') likeEl.textContent = fmtNum(c.likes || 0);
          if (disEl && typeof c.dislikes !== 'undefined') disEl.textContent = fmtNum(c.dislikes || 0);
          if (viewEl && typeof c.views !== 'undefined') viewEl.textContent = fmtNum(c.views || 0);
          if (comEl && typeof c.comments !== 'undefined') comEl.textContent = fmtNum(c.comments || 0);
        });
      });
    } catch (e) {}
  }

  NS.ui.feed.load = async function () {
    _loading = true;
    _page = 1;
    _perPage = 10;
    _total = null;

    renderPlaceholder('Loading video feed…');

    const res = await NS.api.fetchFeed({ page: _page, per_page: _perPage });
    _loading = false;

    if (!res) {
      renderPlaceholder('No response (network).');
      return;
    }

    if (res.ok === false) {
      renderPlaceholder(res.error || 'Feed error.');
      return;
    }

    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      const note = res.meta && res.meta.note ? res.meta.note : '';
      renderPlaceholder(note ? ('No videos. ' + note) : 'No videos returned.');
      return;
    }

    if (res.meta) {
      if (typeof res.meta.total === 'number') _total = res.meta.total;
      if (typeof res.meta.per_page === 'number') _perPage = res.meta.per_page;
      if (typeof res.meta.page === 'number') _page = res.meta.page;
    }

    renderItems(items, false);
    bindOnce();

    if (!hasMore()) {
      const M = mount();
      const wrap = M ? NS.util.qs('.ia-stream-loadmore', M) : null;
      if (wrap && wrap.parentNode) wrap.parentNode.removeChild(wrap);
    }
  };
})();
