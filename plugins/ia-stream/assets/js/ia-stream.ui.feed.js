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
  let _perPage = 12;
  let _total = null;
  let _browseItems = [];
  let _browseChannels = [];
  let _commentSearchSeq = 0;
  let _discoverState = {
    recent: { page: 1, perPage: 6, total: null, items: [] },
    trending: { page: 1, perPage: 6, total: null, items: [] },
    subscriptions: { page: 1, perPage: 12, total: null, items: [] }
  };

  function root() {
    return NS.util.qs("#ia-stream-shell");
  }

  function browseMount() {
    const R = root();
    return R ? NS.util.qs('[data-ia-stream-browse-feed]', R) : null;
  }

  function subscriptionsMount() {
    const R = root();
    return R ? NS.util.qs('[data-ia-stream-subscriptions-feed]', R) : null;
  }

  function searchMount() {
    const R = root();
    return R ? NS.util.qs('[data-ia-stream-search-feed]', R) : null;
  }

  function panelMount(kind) {
    if (kind === 'search') return searchMount();
    if (kind === 'subscriptions') return subscriptionsMount();
    return browseMount();
  }

  function discoverMount(name) {
    const R = root();
    return R ? NS.util.qs(name, R) : null;
  }

  function streamSiteTitle() {
    try {
      const raw = window.IA_STREAM && String(window.IA_STREAM.siteTitle || '').trim();
      if (raw) return raw;
    } catch (e) {}
    try {
      const brand = document.querySelector('#ia-atrium-shell .ia-atrium-brand');
      const txt = brand ? String(brand.textContent || '').trim() : '';
      if (txt) return txt;
    } catch (e2) {}
    return 'IndieAgora';
  }

  function streamSetMeta(name, value, attr) {
    try {
      if (!value) return;
      const sel = attr === 'property' ? 'meta[property="' + name + '"]' : 'meta[name="' + name + '"]';
      let el = document.head ? document.head.querySelector(sel) : null;
      if (!el && document.head) {
        el = document.createElement('meta');
        el.setAttribute(attr === 'property' ? 'property' : 'name', name);
        document.head.appendChild(el);
      }
      if (el) el.setAttribute('content', value);
    } catch (e) {}
  }

  function streamIsActiveSurface() {
    try {
      const shell = document.querySelector('#ia-atrium-shell');
      const active = shell ? String(shell.getAttribute('data-active-tab') || '').trim().toLowerCase() : '';
      if (active) return active === 'stream';
    } catch (e2) {}
    try {
      const url = new URL(window.location.href);
      const tab = String(url.searchParams.get('tab') || '').trim().toLowerCase();
      if (tab) return tab === 'stream';
    } catch (e) {}
    try {
      const panel = document.querySelector('#ia-atrium-shell .ia-panel[data-panel="stream"]');
      if (panel) return panel.classList.contains('active') || panel.getAttribute('aria-hidden') === 'false';
    } catch (e3) {}
    return false;
  }

  function applyStreamTitle(rawTitle) {
    try {
      if (!streamIsActiveSurface()) return;
      const site = streamSiteTitle();
      const clean = String(rawTitle || '').trim();
      const full = clean ? (site ? (clean + ' | ' + site) : clean) : site;
      if (!full) return;
      document.title = full;
      streamSetMeta('og:title', full, 'property');
      streamSetMeta('twitter:title', full, 'name');
      NS.state = NS.state || {};
      NS.state.currentPageTitle = clean || site;
    } catch (e) {}
  }

  function streamContextTitle() {
    try {
      const state = NS.state || {};
      if (state.currentVideoTitle) return String(state.currentVideoTitle);
      const tab = String(state.activeTab || 'discover');
      if (tab === 'search') {
        const q = String(state.query || '').trim();
        return q ? ('Search: ' + q) : 'Search';
      }
      if (tab === 'subscriptions') return 'Subscriptions';
      if (tab === 'browse') {
        const label = String(state.channelName || state.channelHandle || '').trim();
        return label || 'Browse videos';
      }
      return 'Discover';
    } catch (e) {}
    return 'Stream';
  }

  function refreshStreamTitle() {
    applyStreamTitle(streamContextTitle());
  }

  NS.refreshPageTitle = refreshStreamTitle;

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function fmtNum(n) {
    n = Number(n || 0);
    if (!isFinite(n) || n < 0) n = 0;
    if (n >= 1000000) return (Math.round(n / 100000) / 10) + "m";
    if (n >= 1000) return (Math.round(n / 100) / 10) + "k";
    return String(Math.round(n));
  }

  function streamPageUrl(videoId, commentId, replyId) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      u.searchParams.set('video', String(videoId || ''));
      u.searchParams.set('focus', 'comments');
      if (commentId) u.searchParams.set('stream_comment', String(commentId || ''));
      else u.searchParams.delete('stream_comment');
      if (replyId) u.searchParams.set('stream_reply', String(replyId || ''));
      else u.searchParams.delete('stream_reply');
      if (!commentId && !replyId) u.searchParams.delete('focus');
      u.hash = '';
      return u.toString();
    } catch (e) {
      return '';
    }
  }

  function channelPageUrl(handle, name) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      if (handle) u.searchParams.set('stream_channel', String(handle || ''));
      else u.searchParams.delete('stream_channel');
      if (name) u.searchParams.set('stream_channel_name', String(name || ''));
      else u.searchParams.delete('stream_channel_name');
      u.searchParams.delete('stream_q');
      u.searchParams.delete('stream_scope');
      u.searchParams.delete('stream_view');
      u.searchParams.delete('stream_subscriptions');
      u.hash = '';
      return u.toString();
    } catch (e) {
      return '';
    }
  }

  function subscriptionsPageUrl() {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      u.searchParams.set('stream_subscriptions', '1');
      u.searchParams.delete('stream_q');
      u.searchParams.delete('stream_scope');
      u.searchParams.delete('stream_view');
      u.searchParams.delete('stream_channel');
      u.searchParams.delete('stream_channel_name');
      u.hash = '';
      return u.toString();
    } catch (e) {
      return '';
    }
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

  function icon(name) {
    const common = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"';
    if (name === 'up') return '<svg ' + common + '><path d="M12 5l5 7H7l5-7Z"/><path d="M12 12v7"/></svg>';
    if (name === 'down') return '<svg ' + common + '><path d="M12 19l-5-7h10l-5 7Z"/><path d="M12 5v7"/></svg>';
    if (name === 'reply') return '<svg ' + common + '><path d="M8 9H5l4-4"/><path d="M5 9l4 4"/><path d="M5 9h7a6 6 0 0 1 6 6v1"/></svg>';
    if (name === 'link') return '<svg ' + common + '><path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 5"/><path d="M14 11a5 5 0 0 0-7.07 0L5.5 12.41a5 5 0 1 0 7.07 7.07L14 19"/></svg>';
    if (name === 'share') return '<svg ' + common + '><path d="M7 12v7a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-7"/><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/></svg>';
    return '';
  }

  function renderPlaceholder(target, msg) {
    const M = typeof target === 'string' ? discoverMount(target) : target;
    if (!M) return;
    M.innerHTML = '<div class="ia-stream-placeholder">' + esc(msg || '') + '</div>';
  }

  function cardHtml(v) {
    const id = v && v.id ? String(v.id) : '';
    const title = v && v.title ? v.title : '';
    const ago = v && v.published_ago ? v.published_ago : '';
    const excerpt = v && v.excerpt ? v.excerpt : '';
    const support = v && v.support ? v.support : '';
    const thumb = v && (v.thumbnail || v.preview) ? (v.thumbnail || v.preview) : '';
    const ch = (v && v.channel) ? v.channel : {};
    const chName = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : '';
    const chUrl = ch && ch.url ? ch.url : '';
    const counts = (v && v.counts) ? v.counts : {};
    const views = fmtNum(counts.views || 0);
    const likes = fmtNum(counts.likes || 0);
    const dislikes = fmtNum(counts.dislikes || 0);
    const comments = fmtNum(counts.comments || 0);
    const category = v && v.category ? String(v.category) : '';
    const tags = v && Array.isArray(v.tags) ? v.tags.slice(0, 3) : [];

    const media = thumb
      ? '<button type="button" class="ia-stream-thumb" data-open-video="' + esc(id) + '" aria-label="' + esc(title) + '">' +
          '<img src="' + esc(thumb) + '" alt="' + esc(title) + '" loading="lazy" />' +
        '</button>'
      : '';

    const chips = []
      .concat(category ? ['<span class="ia-stream-chip">' + esc(category) + '</span>'] : [])
      .concat(tags.map((tag) => '<span class="ia-stream-chip">#' + esc(tag) + '</span>'))
      .join('');

    return (
      '<article class="iad-card ia-stream-card" data-ia-stream-video-card="' + esc(id) + '">' +
        media +
        '<div class="ia-stream-card-bodywrap">' +
          '<div class="iad-card-meta">' +
            ((ch && (ch.handle || ch.name)) ? ('<a class="iad-agora-link" href="' + esc(channelPageUrl(ch.handle || ch.name, chName || ch.name || 'Channel')) + '" data-open-channel="' + esc(ch.handle || ch.name) + '" data-open-channel-name="' + esc(chName || ch.name || 'Channel') + '">' + esc(chName || 'Channel') + '</a>') : (chUrl ? ('<a class="iad-agora-link" href="' + esc(chUrl) + '" target="_blank" rel="noopener">' + esc(chName || 'Channel') + '</a>') : esc(chName || 'Channel'))) +
            (ago ? '<span> · ' + esc(ago) + '</span>' : '') +
          '</div>' +
          '<div class="iad-card-title" data-open-video="' + esc(id) + '">' + esc(title) + '</div>' +
          (excerpt ? ('<div class="iad-card-excerpt" data-open-video="' + esc(id) + '"><p>' + esc(excerpt) + '</p></div>') : '') +
          (chips ? ('<div class="ia-stream-chips">' + chips + '</div>') : '') +
          (support ? ('<div class="ia-stream-support"><span class="ia-stream-support-label">Support:</span> ' + esc(support) + '</div>') : '') +
          '<div class="iad-card-actions ia-stream-actions">' +
            '<button type="button" class="iad-iconbtn" data-ia-stream-rate="like" data-video-id="' + esc(id) + '" aria-label="Like">' + icon('up') + '<span class="iad-count" data-ia-stream-likes>' + esc(likes) + '</span></button>' +
            '<button type="button" class="iad-iconbtn" data-ia-stream-rate="dislike" data-video-id="' + esc(id) + '" aria-label="Dislike">' + icon('down') + '<span class="iad-count" data-ia-stream-dislikes>' + esc(dislikes) + '</span></button>' +
            '<button type="button" class="iad-iconbtn" data-open-comments="' + esc(id) + '" aria-label="Comments">' + icon('reply') + '<span class="iad-count" data-ia-stream-comments-count="' + esc(String(counts.comments || 0)) + '">' + esc(comments) + '</span></button>' +
            '<button type="button" class="iad-iconbtn" data-ia-stream-copy-video="' + esc(id) + '" data-ia-stream-copy-url="' + esc(streamPageUrl(id)) + '" aria-label="Copy link">' + icon('link') + '</button>' +
            '<button type="button" class="iad-iconbtn" data-ia-stream-share="' + esc(id) + '" data-ia-stream-share-url="' + esc(streamPageUrl(id)) + '" aria-label="Share to Connect">' + icon('share') + '</button>' +
            '<span class="ia-stream-views">Views: <span data-ia-stream-views>' + esc(views) + '</span></span>' +
          '</div>' +
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
    return '<div class="ia-stream-loadmore"><button type="button" class="ia-stream-loadmore-btn" data-ia-stream-loadmore>' + (_loading ? 'Loading…' : 'Load more') + '</button></div>';
  }

  function setButtonState(disabled) {
    const tab = String(NS.state.activeTab || '');
    const M = panelMount(tab === 'search' ? 'search' : (tab === 'subscriptions' ? 'subscriptions' : 'browse'));
    if (!M) return;
    const btn = NS.util.qs('[data-ia-stream-loadmore]', M);
    if (!btn) return;
    try { btn.disabled = !!disabled; } catch (e) {}
    btn.textContent = disabled ? 'Loading…' : 'Load more';
  }

  function localMatchText(v) {
    const parts = [];
    if (v && v.title) parts.push(v.title);
    if (v && v.excerpt) parts.push(v.excerpt);
    if (v && v.support) parts.push(v.support);
    if (v && v.category) parts.push(v.category);
    if (v && Array.isArray(v.tags)) parts.push(v.tags.join(' '));
    const ch = v && v.channel ? v.channel : {};
    if (ch && ch.display_name) parts.push(ch.display_name);
    if (ch && ch.name) parts.push(ch.name);
    return parts.join(' ').toLowerCase();
  }

  function filterItemsByScope(items, query, scope) {
    const q = String(query || '').trim().toLowerCase();
    if (!q) return Array.isArray(items) ? items : [];
    const list = Array.isArray(items) ? items : [];
    return list.filter((v) => {
      const ch = v && v.channel ? v.channel : {};
      const tags = Array.isArray(v && v.tags) ? v.tags.join(' ').toLowerCase() : '';
      const category = String((v && v.category) || '').toLowerCase();
      const userText = [ch.display_name || '', ch.name || ''].join(' ').toLowerCase();
      const videoText = [v && v.title || '', v && v.excerpt || '', v && v.support || ''].join(' ').toLowerCase();
      if (scope === 'videos') return videoText.indexOf(q) !== -1;
      if (scope === 'users') return userText.indexOf(q) !== -1;
      if (scope === 'tags') return tags.indexOf(q) !== -1;
      if (scope === 'categories') return category.indexOf(q) !== -1;
      return localMatchText(v).indexOf(q) !== -1;
    });
  }

  function renderBrowseItems(items, append, mountEl) {
    const M = mountEl || browseMount();
    if (!M) return;

    const lm = NS.util.qs('.ia-stream-loadmore', M);
    if (lm && lm.parentNode) lm.parentNode.removeChild(lm);

    const html = (Array.isArray(items) ? items : []).map(cardHtml).join('');
    if (append) M.insertAdjacentHTML('beforeend', html);
    else M.innerHTML = html;

    const tail = loadMoreHtml();
    if (tail) M.insertAdjacentHTML('beforeend', tail);
  }

  function renderResultMeta(text, kind) {
    const R = root();
    if (!R) return;
    const selector = kind === 'search' ? '[data-ia-stream-search-meta]' : (kind === 'subscriptions' ? '[data-ia-stream-subscriptions-meta]' : '[data-ia-stream-browse-meta]');
    const el = NS.util.qs(selector, R);
    if (el) el.textContent = String(text || '');
  }


  function syncSearchUrl() {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      if (String(NS.state.activeTab || '') === 'search' && String(NS.state.query || '').trim()) {
        u.searchParams.set('stream_view', 'search');
        u.searchParams.set('stream_q', String(NS.state.query || '').trim());
        u.searchParams.set('stream_scope', String(NS.state.scope || 'all'));
        u.searchParams.set('stream_sort', String(NS.state.sort || '-publishedAt'));
      } else {
        u.searchParams.delete('stream_view');
        u.searchParams.delete('stream_q');
        u.searchParams.delete('stream_scope');
        u.searchParams.delete('stream_sort');
      }
      if (String(NS.state.activeTab || '') === 'subscriptions') u.searchParams.set('stream_subscriptions', '1');
      else u.searchParams.delete('stream_subscriptions');
      if (String(NS.state.channelHandle || '').trim() && String(NS.state.activeTab || '') === 'browse') {
        u.searchParams.set('stream_channel', String(NS.state.channelHandle || ''));
        if (String(NS.state.channelName || '').trim()) u.searchParams.set('stream_channel_name', String(NS.state.channelName || ''));
      } else {
        u.searchParams.delete('stream_channel');
        u.searchParams.delete('stream_channel_name');
      }
      window.history.replaceState({}, '', u.toString());
    } catch (e) {}
  }

  function toggleSearchWrap(selector, html) {
    const R = root();
    const wrapSel = selector.replace('matches]', 'matches-wrap]');
    const wrap = R ? NS.util.qs(wrapSel, R) : null;
    const box = R ? NS.util.qs(selector, R) : null;
    const extras = R ? NS.util.qs('[data-ia-stream-search-extras]', R) : null;
    if (!wrap || !box || !extras) return;
    const out = String(html || '').trim();
    if (!out) {
      wrap.setAttribute('hidden', 'hidden');
      box.innerHTML = '';
    } else {
      wrap.removeAttribute('hidden');
      extras.removeAttribute('hidden');
      box.innerHTML = out;
    }

    const anyVisible = [
      '[data-ia-stream-user-matches-wrap]',
      '[data-ia-stream-channel-matches-wrap]',
      '[data-ia-stream-tag-matches-wrap]',
      '[data-ia-stream-comment-matches-wrap]'
    ].some((sel) => {
      const el = R ? NS.util.qs(sel, R) : null;
      return !!(el && !el.hasAttribute('hidden'));
    });
    if (!anyVisible) extras.setAttribute('hidden', 'hidden');
  }

  function clearSearchExtras() {
    [
      '[data-ia-stream-user-matches]',
      '[data-ia-stream-channel-matches]',
      '[data-ia-stream-tag-matches]',
      '[data-ia-stream-comment-matches]'
    ].forEach((sel) => toggleSearchWrap(sel, ''));
  }

  function uniqueByKey(rows, keyFn) {
    const seen = new Set();
    const out = [];
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const k = String(keyFn(row) || '');
      if (!k || seen.has(k)) return;
      seen.add(k);
      out.push(row);
    });
    return out;
  }

  function collectUserMatches(items, commentMatches) {
    const users = [];
    (Array.isArray(items) ? items : []).forEach((v) => {
      const ch = v && v.channel ? v.channel : {};
      const label = String(ch.display_name || ch.name || '').trim();
      if (!label) return;
      users.push({ id: 'channel:' + String(ch.id || label).toLowerCase(), label: label, secondary: 'Video uploader', avatar: String(ch.avatar || ''), url: String(ch.url || '') });
    });
    (Array.isArray(commentMatches) ? commentMatches : []).forEach((m) => {
      const label = String(m.author || '').trim();
      if (!label) return;
      users.push({ id: 'comment:' + label.toLowerCase(), label: label, secondary: 'Comment author', avatar: '', url: '' });
    });
    return uniqueByKey(users, (u) => u.id).slice(0, 8);
  }

  function renderUserMatches(items) {
    const html = (Array.isArray(items) ? items : []).map((u) =>
      '<a class="ia-stream-user-row" href="' + esc(u.url || '#') + '"' + (u.url ? ' target="_blank" rel="noopener"' : '') + '>' +
        (u.avatar ? '<img class="ia-stream-user-avatar" src="' + esc(u.avatar) + '" alt="" loading="lazy" />' : '<span class="ia-stream-user-avatar ia-stream-user-avatar--fallback">' + esc((u.label || '?').slice(0,1).toUpperCase()) + '</span>') +
        '<span class="ia-stream-user-meta">' +
          '<span class="ia-stream-user-name">' + esc(u.label || 'User') + '</span>' +
          (u.secondary ? '<span class="ia-stream-user-sub">' + esc(u.secondary) + '</span>' : '') +
        '</span>' +
      '</a>'
    ).join('');
    toggleSearchWrap('[data-ia-stream-user-matches]', html);
  }

  function renderTagMatches(items) {
    const tags = [];
    const q = String(NS.state.query || '').trim().toLowerCase();
    (Array.isArray(items) ? items : []).forEach((v) => {
      const vid = String(v && v.id || '');
      if (v && v.category) {
        const label = String(v.category).trim();
        if (label && (!q || label.toLowerCase().indexOf(q) !== -1)) tags.push({ type: 'Category', label: label, videoId: vid });
      }
      (Array.isArray(v && v.tags) ? v.tags : []).forEach((tag) => {
        const label = String(tag || '').trim();
        if (label && (!q || label.toLowerCase().indexOf(q) !== -1)) tags.push({ type: 'Tag', label: '#' + label, videoId: vid });
      });
    });
    const list = uniqueByKey(tags, (t) => t.type + ':' + t.label.toLowerCase()).slice(0, 10);
    const html = list.map((t) =>
      '<button type="button" class="ia-stream-tag-row" data-open-video="' + esc(t.videoId) + '">' +
        '<span class="ia-stream-tag-kind">' + esc(t.type) + '</span>' +
        '<span class="ia-stream-tag-label">' + esc(t.label) + '</span>' +
      '</button>'
    ).join('');
    toggleSearchWrap('[data-ia-stream-tag-matches]', html);
  }

  function showActionError(res, fallback) {
    const msg = (res && (res.error || res.message)) ? String(res.error || res.message) : String(fallback || 'Action failed');
    try { console.warn('IA Stream action error:', msg, res); } catch (e) {}
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

      function cleanup(v) { try { host.remove(); } catch (e) {} resolve(v); }
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
      host.addEventListener('keydown', (e) => { if (e.key === 'Escape') onCancel(); if (e.key === 'Enter') onOk(); });
      setTimeout(() => { try { input && input.focus(); } catch (e) {} }, 10);
    });
  }

  let _mintInFlight = false;
  let _mintPrompted = false;

  async function ensurePeerTubeUserToken() {
    if (_mintInFlight || _mintPrompted) return false;
    _mintPrompted = true;

    const m = await authModal({
      title: 'Enable Stream actions',
      message: 'To perform this action, Atrium may need to create or refresh your PeerTube token.'
    });
    if (!m || !m.ok) return false;

    const password = String(m.password || '').trim();
    if (!password) return false;

    _mintInFlight = true;
    const res = await NS.api.post('ia_stream_pt_mint_token', { password });
    _mintInFlight = false;
    _mintPrompted = false;

    if (!res || res.ok === false) {
      showActionError(res, 'Token mint failed');
      return false;
    }
    return true;
  }

  function getRecoverableTokenCode(res) {
    if (!res || typeof res !== 'object') return '';
    const candidates = [
      res.code, res.error, res.message,
      res.data && res.data.code,
      res.data && res.data.error,
      res.error && typeof res.error === 'object' ? res.error.code : '',
      res.error && typeof res.error === 'object' ? res.error.message : ''
    ];
    for (const raw of candidates) {
      const code = String(raw || '').trim();
      if (code === 'missing_user_token' || code === 'password_required') return code;
    }
    return '';
  }

  function isRecoverableTokenState(res) {
    return getRecoverableTokenCode(res) !== '';
  }

  function updateCardCounts(videoId, v) {
    videoId = String(videoId || '');
    if (!videoId) return;
    const M = root();
    if (!M) return;
    const cards = M.querySelectorAll('[data-ia-stream-video-card="' + CSS.escape(videoId) + '"]');
    cards.forEach((card) => {
      const counts = v && v.counts ? v.counts : {};
      const l = NS.util.qs('[data-ia-stream-likes]', card);
      if (l) l.textContent = fmtNum(counts.likes || 0);
      const d = NS.util.qs('[data-ia-stream-dislikes]', card);
      if (d) d.textContent = fmtNum(counts.dislikes || 0);
      const vw = NS.util.qs('[data-ia-stream-views]', card);
      if (vw) vw.textContent = fmtNum(counts.views || 0);
      const c = NS.util.qs('[data-ia-stream-comments-count]', card);
      if (c) c.textContent = fmtNum(counts.comments || 0);
    });
  }

  async function rate(videoId, rating) {
    videoId = String(videoId || '').trim();
    rating = String(rating || '').trim();
    if (!videoId || (rating !== 'like' && rating !== 'dislike')) return;

    let res = await NS.api.rateVideo({ id: videoId, rating });
    if (res && res.ok === false && isRecoverableTokenState(res)) {
      const ok = await ensurePeerTubeUserToken();
      if (!ok) return;
      res = await NS.api.rateVideo({ id: videoId, rating });
    }
    if (!res || res.ok === false) return showActionError(res, 'Rate failed');
    if (res.item) {
      updateCardCounts(videoId, res.item);
      try {
        if (NS.ui.video && typeof NS.ui.video.updateCounts === 'function') {
          NS.ui.video.updateCounts(videoId, res.item);
        }
      } catch (e) {}
    }
  }

  function discoverMoreButton(kind, disabled) {
    return '<div class="ia-stream-loadmore"><button type="button" class="ia-stream-loadmore-btn" data-ia-stream-discover-more="' + esc(kind) + '"' + (disabled ? ' disabled' : '') + '>' + (disabled ? 'Loading…' : 'Load more') + '</button></div>';
  }

  function updateDiscoverSection(target, kind, items, append, total) {
    const M = discoverMount(target);
    if (!M) return;
    const st = _discoverState[kind];
    if (!items.length && !append) {
      renderPlaceholder(target, kind === 'trending' ? 'No trending videos.' : 'No recent videos.');
      return;
    }
    const html = (Array.isArray(items) ? items : []).map(cardHtml).join('');
    if (append) M.insertAdjacentHTML('beforeend', html);
    else M.innerHTML = html;
    const old = NS.util.qs('.ia-stream-loadmore', M);
    if (old && old.parentNode) old.parentNode.removeChild(old);
    const loaded = Array.isArray(st.items) ? st.items.length : 0;
    if (total === null || total === undefined || loaded < total) M.insertAdjacentHTML('beforeend', discoverMoreButton(kind, false));
  }

  async function loadDiscoverStrip(target, opts, emptyText, kind, append) {
    kind = kind || 'recent';
    append = !!append;
    const st = _discoverState[kind] || { page: 1, perPage: 6, total: null, items: [] };
    const page = append ? (st.page + 1) : 1;
    if (!append) renderPlaceholder(target, 'Loading…');
    const req = Object.assign({}, opts || {}, { page: page, per_page: st.perPage });
    let res = null;
    try {
      res = await NS.api.fetchFeed(req);
    } catch (e) {
      res = { ok: false, error: (e && e.message) ? e.message : 'Feed error.' };
    }
    if (!res || res.ok === false) {
      renderPlaceholder(target, (res && res.error) || 'Feed error.');
      return [];
    }
    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length && !append) {
      renderPlaceholder(target, emptyText || 'No videos found.');
      return [];
    }
    st.page = page;
    st.total = res && res.meta && typeof res.meta.total === 'number' ? res.meta.total : st.total;
    st.items = append ? st.items.concat(items) : items.slice();
    _discoverState[kind] = st;
    updateDiscoverSection(target, kind, items, append, st.total);
    return st.items;
  }

  async function loadSubscriptions(append) {
    append = !!append;
    const target = subscriptionsMount();
    const st = _discoverState.subscriptions;
    const page = append ? (st.page + 1) : 1;
    if (!append) renderPlaceholder(target, 'Loading subscriptions…');
    let res = null;
    try {
      res = await NS.api.fetchFeed({ page: page, per_page: st.perPage, sort: String(NS.state.sort || '-publishedAt'), mode: 'subscriptions' });
    } catch (e) {
      res = { ok: false, error: (e && e.message) ? e.message : 'Subscriptions unavailable.' };
    }
    if (!res || res.ok === false) {
      renderPlaceholder(target, (res && res.error) || 'Subscriptions unavailable.');
      renderResultMeta('Subscriptions unavailable for this user.', 'subscriptions');
      return;
    }
    const items = Array.isArray(res.items) ? res.items : [];
    st.page = page;
    st.total = res && res.meta && typeof res.meta.total === 'number' ? res.meta.total : st.total;
    st.items = append ? st.items.concat(items) : items.slice();
    _discoverState.subscriptions = st;
    if (!st.items.length) renderPlaceholder(target, 'No subscription videos found.');
    else renderBrowseItems(items, append, target);
    const label = st.items.length ? (st.items.length + ' subscription video' + (st.items.length === 1 ? '' : 's')) : 'No subscription videos found';
    renderResultMeta(label, 'subscriptions');
    refreshStreamTitle();
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      if (String(NS.state.activeTab || '') === 'subscriptions') u.searchParams.set('stream_subscriptions', '1');
      else u.searchParams.delete('stream_subscriptions');
      if (String(NS.state.activeTab || '') !== 'browse') {
        u.searchParams.delete('stream_channel');
        u.searchParams.delete('stream_channel_name');
      }
      window.history.replaceState({}, '', u.toString());
    } catch (e) {}
  }

  async function loadDiscover() {
    try {
      await loadDiscoverStrip('[data-ia-stream-discover-recent]', { sort: '-publishedAt' }, 'No recent videos.', 'recent', false);
    } catch (e) {
      renderPlaceholder('[data-ia-stream-discover-recent]', 'Feed error.');
    }
    try {
      await loadDiscoverStrip('[data-ia-stream-discover-trending]', { sort: '-views' }, 'No trending videos.', 'trending', false);
    } catch (e) {
      renderPlaceholder('[data-ia-stream-discover-trending]', 'Feed error.');
    }
    try {
      if (NS.ui.channels && typeof NS.ui.channels.load === 'function') {
        await NS.ui.channels.load({ target: '[data-ia-stream-discover-channels]', per_page: 8 });
      }
    } catch (e) {
      renderPlaceholder('[data-ia-stream-discover-channels]', 'Channels error.');
    }
  }

  function showChannelMatches(items) {
    const list = Array.isArray(items) ? items : [];
    if (!list.length) {
      toggleSearchWrap('[data-ia-stream-channel-matches]', '');
      return;
    }
    if (NS.ui.channels && typeof NS.ui.channels.renderInto === 'function') {
      toggleSearchWrap('[data-ia-stream-channel-matches]', '<div class="ia-stream-placeholder">Loading…</div>');
      NS.ui.channels.renderInto('[data-ia-stream-channel-matches]', list);
      return;
    }
    toggleSearchWrap('[data-ia-stream-channel-matches]', '');
  }

  function collectCommentTreeMatches(node, q, video, matches) {
    if (!node || typeof node !== 'object') return;
    const txt = String(node && node.text || '').trim();
    const author = node && node.author ? String(node.author.display_name || node.author.name || 'Comment') : 'Comment';
    if (txt && txt.toLowerCase().indexOf(q) !== -1) {
      matches.push({
        videoId: String(video && video.id || ''),
        videoTitle: String(video && video.title || 'Video'),
        commentId: String(node && (node.id || node.comment_id) || ''),
        text: txt,
        author: author,
        kind: 'Reply'
      });
    }
    const children = Array.isArray(node && node.children) ? node.children : [];
    children.forEach((child) => collectCommentTreeMatches(child, q, video, matches));
  }

  async function searchCommentsForVideos(items, query) {
    const seq = ++_commentSearchSeq;
    const q = String(query || '').trim().toLowerCase();
    if (!q || q.length < 2 || !Array.isArray(items) || !items.length) {
      toggleSearchWrap('[data-ia-stream-comment-matches]', '');
      return [];
    }

    const picked = items.slice(0, 6);
    const matches = [];
    for (const v of picked) {
      if (!v || !v.id) continue;
      const res = await NS.api.fetchComments({ video_id: v.id, page: 1, per_page: 8 });
      if (seq !== _commentSearchSeq) return [];
      if (!res || res.ok === false) continue;
      const rows = Array.isArray(res.items) ? res.items : [];
      for (const row of rows) {
        const txt = String(row && row.text || '').trim();
        if (txt && txt.toLowerCase().indexOf(q) !== -1) {
          matches.push({
            videoId: String(v.id),
            videoTitle: String(v.title || 'Video'),
            commentId: String(row && (row.comment_id || row.commentId || row.id) || ''),
            threadId: String(row && row.id || ''),
            text: txt,
            author: row && row.author ? String(row.author.display_name || row.author.name || 'Comment') : 'Comment',
            kind: 'Comment'
          });
        }
        const threadId = String(row && row.id || '').trim();
        if (threadId && matches.length < 8) {
          const threadRes = await NS.api.fetchCommentThread({ video_id: v.id, thread_id: threadId });
          if (seq !== _commentSearchSeq) return [];
          if (threadRes && threadRes.ok !== false && threadRes.item && threadRes.item.root) {
            const children = Array.isArray(threadRes.item.root.children) ? threadRes.item.root.children : [];
            children.forEach((child) => collectCommentTreeMatches(child, q, v, matches));
          }
        }
        if (matches.length >= 8) break;
      }
      if (matches.length >= 8) break;
    }

    if (seq !== _commentSearchSeq) return [];
    const html = matches.slice(0, 8).map((m) =>
      '<button type="button" class="ia-stream-comment-search-row" data-open-video="' + esc(m.videoId) + '" data-open-comment="' + esc(m.commentId || '') + '" data-open-reply="' + esc(m.replyId || '') + '">' +
        '<div class="ia-stream-comment-search-video">' + esc(m.videoTitle) + '</div>' +
        '<div class="ia-stream-comment-search-author">' + esc(m.author) + (m.kind ? ' · ' + esc(m.kind) : '') + '</div>' +
        '<div class="ia-stream-comment-search-text">' + esc(m.text) + '</div>' +
      '</button>'
    ).join('');
    toggleSearchWrap('[data-ia-stream-comment-matches]', html);
    return matches.slice(0, 8);
  }

  async function loadBrowse(append, kind) {
    append = !!append;
    kind = kind === 'search' ? 'search' : 'browse';
    _loading = true;
    if (!append) {
      _page = 1;
      _perPage = 12;
      _total = null;
      if (kind === 'search') {
        _browseItems = [];
        renderPlaceholder(searchMount(), 'Searching…');
      } else {
        renderPlaceholder(browseMount(), 'Loading videos…');
      }
    }

    const page = append ? (_page + 1) : 1;
    const query = kind === 'search' ? String(NS.state.query || '') : '';
    const scope = kind === 'search' ? String(NS.state.scope || 'all') : 'all';
    const opts = {
      page: page,
      per_page: _perPage,
      search: query,
      sort: String(NS.state.sort || '-publishedAt')
    };
    if (kind === 'subscriptions') opts.mode = 'subscriptions';
    if (kind === 'browse' && String(NS.state.channelHandle || '').trim()) {
      opts.mode = 'channel';
      opts.channel_handle = String(NS.state.channelHandle || '').trim();
    }

    let res = null;
    try {
      res = await NS.api.fetchFeed(opts);
    } catch (e) {
      res = { ok: false, error: (e && e.message) ? e.message : 'Feed error.' };
    }
    _loading = false;

    if (!res || res.ok === false) {
      renderPlaceholder(panelMount(kind), (res && res.error) || 'Feed error.');
      renderResultMeta(kind === 'search' ? 'Unable to load search results.' : 'Unable to load videos.', kind);
      syncSearchUrl();
      return;
    }

    let items = Array.isArray(res.items) ? res.items : [];
    if (kind === 'search' && (scope === 'videos' || scope === 'users' || scope === 'tags' || scope === 'categories')) {
      items = filterItemsByScope(items, query, scope);
    }

    if (res.meta) {
      if (typeof res.meta.total === 'number') _total = res.meta.total;
      if (typeof res.meta.per_page === 'number') _perPage = res.meta.per_page;
      if (typeof res.meta.page === 'number') _page = res.meta.page;
      else _page = page;
    } else {
      _page = page;
    }

    const target = panelMount(kind);
    const renderedItems = append ? _browseItems.concat(items) : items.slice();
    if (!_browseItems.length && kind !== 'search') _browseItems = items.slice();
    if (kind === 'search') _browseItems = renderedItems.slice();

    if (!renderedItems.length) {
      renderPlaceholder(target, kind === 'search' ? 'No videos matched your search.' : 'No videos returned.');
    } else {
      renderBrowseItems(items, append, target);
    }

    const metaBits = [];
    if (kind === 'search' && query) {
      metaBits.push(renderedItems.length + ' video result' + (renderedItems.length === 1 ? '' : 's'));
      metaBits.push('for “' + query + '”');
    } else if (kind === 'subscriptions') {
      metaBits.push('Videos from your subscriptions');
    } else if (String(NS.state.channelHandle || '').trim()) {
      metaBits.push('Channel: ' + String(NS.state.channelName || NS.state.channelHandle || 'Channel'));
    } else {
      metaBits.push('Recently added videos');
    }
    renderResultMeta(metaBits.join(' '), kind);
    syncSearchUrl();
    refreshStreamTitle();

    if (kind === 'search' && !append) {
      if (query) {
        const chRes = await NS.api.fetchChannels({ page: 1, per_page: 8, search: query });
        _browseChannels = chRes && chRes.ok !== false && Array.isArray(chRes.items) ? chRes.items : [];
        if (scope === 'all' || scope === 'channels') showChannelMatches(_browseChannels);
        else showChannelMatches([]);
      } else {
        _browseChannels = [];
        showChannelMatches([]);
      }

      let commentMatches = [];
      if (query && (scope === 'all' || scope === 'comments' || scope === 'users')) {
        commentMatches = await searchCommentsForVideos(renderedItems, query);
      } else {
        await searchCommentsForVideos([], '');
      }

      if (query && (scope === 'all' || scope === 'users')) renderUserMatches(collectUserMatches(renderedItems, commentMatches));
      else renderUserMatches([]);

      if (query && (scope === 'all' || scope === 'tags' || scope === 'categories')) renderTagMatches(renderedItems);
      else renderTagMatches([]);
    } else if (kind !== 'search') {
      clearSearchExtras();
    }

    if (!hasMore()) {
      const wrap = target ? NS.util.qs('.ia-stream-loadmore', target) : null;
      if (wrap && wrap.parentNode) wrap.parentNode.removeChild(wrap);
    } else {
      setButtonState(false);
    }
  }

  function bindOnce() {
    if (_bound) return;
    const R = root();
    if (!R) return;
    _bound = true;

    NS.util.on(R, 'click', async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      const lm = (t.closest ? t.closest('[data-ia-stream-loadmore]') : null);
      if (lm) {
        ev.preventDefault();
        ev.stopPropagation();
        if (_loading || !hasMore()) return;
        setButtonState(true);
        const tab = String(NS.state.activeTab || '');
        if (tab === 'subscriptions') await loadSubscriptions(true);
        else await loadBrowse(true, tab === 'search' ? 'search' : 'browse');
        return;
      }

      const discMore = (t.closest ? t.closest('[data-ia-stream-discover-more]') : null);
      if (discMore) {
        ev.preventDefault();
        ev.stopPropagation();
        const kind = discMore.getAttribute('data-ia-stream-discover-more') || 'recent';
        if (kind === 'recent') await loadDiscoverStrip('[data-ia-stream-discover-recent]', { sort: '-publishedAt' }, 'No recent videos.', 'recent', true);
        else if (kind === 'trending') await loadDiscoverStrip('[data-ia-stream-discover-trending]', { sort: '-views' }, 'No trending videos.', 'trending', true);
        return;
      }

      const openBrowse = (t.closest ? t.closest('[data-ia-stream-open-browse]') : null);
      if (openBrowse) {
        ev.preventDefault();
        ev.stopPropagation();
        NS.state.channelHandle = '';
        NS.state.channelName = '';
        if (NS.ui.shell && typeof NS.ui.shell.setTab === 'function') NS.ui.shell.setTab('browse');
        return;
      }

      const rbtn = (t.closest ? t.closest('[data-ia-stream-rate]') : null);
      if (rbtn) {
        ev.preventDefault();
        ev.stopPropagation();
        await rate(rbtn.getAttribute('data-video-id') || '', rbtn.getAttribute('data-ia-stream-rate') || '');
        return;
      }

      const copyBtn = (t.closest ? t.closest('[data-ia-stream-copy-video]') : null);
      if (copyBtn) {
        ev.preventDefault();
        ev.stopPropagation();
        await copyText(copyBtn.getAttribute('data-ia-stream-copy-url') || '');
        return;
      }

      const shareBtn = (t.closest ? t.closest('[data-ia-stream-share]') : null);
      if (shareBtn) {
        ev.preventDefault();
        ev.stopPropagation();
        const vid = shareBtn.getAttribute('data-ia-stream-share') || '';
        const u = shareBtn.getAttribute('data-ia-stream-share-url') || '';
        try { window.dispatchEvent(new CustomEvent('ia:share_to_connect', { detail: { type: 'video', video_id: vid, url: u } })); } catch (e) {}
        return;
      }

      const cbtn = (t.closest ? t.closest('[data-open-comments]') : null);
      if (cbtn) {
        const id = cbtn.getAttribute('data-open-comments') || '';
        if (!id) return;
        ev.preventDefault();
        ev.stopPropagation();
        if (NS.ui.video && NS.ui.video.open) NS.ui.video.open(id, { focus: 'comments' });
        return;
      }

      const openChannel = (t.closest ? t.closest('[data-open-channel]') : null);
      if (openChannel) {
        const handle = openChannel.getAttribute('data-open-channel') || '';
        const name = openChannel.getAttribute('data-open-channel-name') || '';
        if (!handle) return;
        ev.preventDefault();
        ev.stopPropagation();
        NS.state.channelHandle = String(handle || '').trim();
        NS.state.channelName = String(name || '').trim();
        if (NS.ui.shell && typeof NS.ui.shell.setTab === 'function') NS.ui.shell.setTab('browse');
        return;
      }

      const commentRow = (t.closest ? t.closest('[data-open-comment]') : null);
      if (commentRow) {
        const id = commentRow.getAttribute('data-open-video') || '';
        const commentId = commentRow.getAttribute('data-open-comment') || '';
        const replyId = commentRow.getAttribute('data-open-reply') || '';
        if (!id) return;
        ev.preventDefault();
        ev.stopPropagation();
        if (NS.ui.video && NS.ui.video.open) NS.ui.video.open(id, { focus: 'comments', highlightCommentId: replyId || commentId, commentId: commentId, replyId: replyId });
        return;
      }

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

    try {
      window.addEventListener('ia:stream_video_counts', function (e) {
        const d = e && e.detail ? e.detail : {};
        const vid = String(d.id || '');
        const c = d.counts || {};
        if (!vid) return;
        updateCardCounts(vid, { counts: c });
      });
    } catch (e) {}

    NS.util.on(window, 'ia:stream:tab', function (ev) {
      refreshStreamTitle();
      const tab = ev && ev.detail ? ev.detail.tab : '';
      if (tab === 'discover') loadDiscover();
      if (tab === 'browse') loadBrowse(false, 'browse');
      if (tab === 'subscriptions') loadSubscriptions(false);
      if (tab === 'search') loadBrowse(false, 'search');
    });

    NS.util.on(window, 'ia:stream:search', function (ev) {
      const d = ev && ev.detail ? ev.detail : {};
      if (Object.prototype.hasOwnProperty.call(d, 'query')) NS.state.query = String(d.query || '').trim();
      if (Object.prototype.hasOwnProperty.call(d, 'scope')) NS.state.scope = String(d.scope || 'all');
      if (Object.prototype.hasOwnProperty.call(d, 'sort')) NS.state.sort = String(d.sort || '-publishedAt');
      NS.state.channelHandle = '';
      NS.state.channelName = '';
      NS.store.set('query', NS.state.query);
      NS.store.set('scope', NS.state.scope);
      NS.store.set('sort', NS.state.sort);
      if (String(NS.state.activeTab || '') !== 'browse' && String(NS.state.activeTab || '') !== 'search') {
        if (NS.ui.shell && typeof NS.ui.shell.setTab === 'function') NS.ui.shell.setTab('search');
        return;
      }
      loadBrowse(false, 'search');
    });
  }

  NS.ui.feed.load = async function (surface) {
    bindOnce();
    if (surface === 'browse') return loadBrowse(false, 'browse');
    if (surface === 'search') return loadBrowse(false, 'search');
    return loadDiscover();
  };
})();
