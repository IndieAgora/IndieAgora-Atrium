/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.comments.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.comments = NS.ui.comments || {};

  let _videoId = '';
  let _page = 1;
  let _perPage = 30;
  let _total = null;
  let _loading = false;

  // threadId -> tree item
  const _threadTrees = Object.create(null);

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function icoCopy() {
    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
      '<path d="M8 8h12v12H8z" fill="none" stroke="currentColor" stroke-width="2"/>' +
      '<path d="M4 16H3a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1" fill="none" stroke="currentColor" stroke-width="2"/>' +
    '</svg>';
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

  function modal() {
    return NS.util.qs(".ia-stream-modal", document);
  }

  function commentsMount() {
    const M = modal();
    if (!M) return null;
    return NS.util.qs(".ia-stream-modal-comments", M);
  }

  function composerEls() {
    const M = modal();
    if (!M) return { wrap: null, reply: null, input: null, send: null };
    return {
      wrap: NS.util.qs('.ia-stream-modal-composer', M),
      input: NS.util.qs('.ia-stream-composer-input', M),
      send: NS.util.qs('.ia-stream-composer-send', M)
    };
  }

  function renderPlaceholder(msg) {
    const C = commentsMount();
    if (!C) return;
    C.innerHTML = '<div class="ia-stream-placeholder">' + esc(msg || "") + '</div>';
  }


  
  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

function showActionError(res, fallback) {
    try {
      const msg = (res && (res.error || res.message)) ? String(res.error || res.message) : String(fallback || 'Action failed');
      const code = res && res.code ? String(res.code) : '';
      let raw = '';
      if (res && res.raw !== undefined && res.raw !== null) {
        try {
          raw = (typeof res.raw === 'string') ? res.raw : JSON.stringify(res.raw, null, 2);
        } catch (e) {
          raw = String(res.raw);
        }
        raw = raw.slice(0, 2000);
      }
      const full = (code ? (code + ': ') : '') + msg + (raw ? ("\n\n" + raw) : '');

      // Neat in-page modal (no browser prompt/alert).
      const host = document.createElement('div');
      host.className = 'ia-stream-modal ia-stream-auth-modal';
      host.innerHTML = `
        <div class="ia-stream-modal__backdrop"></div>
        <div class="ia-stream-modal__sheet" role="dialog" aria-modal="true">
          <div class="ia-stream-modal__head">
            <div class="ia-stream-modal__title">Stream</div>
            <button type="button" class="ia-stream-modal__x" aria-label="Close">×</button>
          </div>
          <div class="ia-stream-modal__body">
            <div class="ia-stream-modal__error" style="white-space:pre-wrap;">${escapeHtml(full)}</div>
            <div class="ia-stream-modal__actions" style="margin-top:12px;">
              <button type="button" class="ia-stream-btn ia-stream-btn--primary">OK</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(host);

      function close() {
        try { host.remove(); } catch (e) {}
      }
      host.querySelector('.ia-stream-modal__backdrop')?.addEventListener('click', close);
      host.querySelector('.ia-stream-modal__x')?.addEventListener('click', close);
      host.querySelector('.ia-stream-btn')?.addEventListener('click', close);

      try { console.error('[IA Stream] action failed', res); } catch (e) {}
    } catch (e) {
      try { console.error('[IA Stream] action failed', res, e); } catch (e2) {}
    }
  }

  // Atrium uses state-based auth (phpBB canonical). The server often cannot
  // access a plaintext password at comment time, which the PeerTube password
  // grant needs to mint a per-user token. When Stream receives the explicit
  // missing_user_token code, prompt once for a password, mint, then retry.
  let _mintPrompted = false;
  let _mintInFlight = false;

  function authModal(opts) {
    opts = opts || {};
    return new Promise((resolve) => {
      const host = document.createElement('div');
      host.className = 'ia-stream-auth-modal';
      host.innerHTML =
        '<div class="ia-stream-auth-dialog" role="dialog" aria-modal="true">' +
          '<div class="ia-stream-auth-head">' +
            '<div class="ia-stream-auth-title">' + esc(opts.title || 'Enable Stream actions') + '</div>' +
            '<button type="button" class="ia-stream-auth-x" aria-label="Close">✕</button>' +
          '</div>' +
          '<div class="ia-stream-auth-body">' +
            '<div class="ia-stream-auth-msg">' + esc(opts.message || '') + '</div>' +
            '<label class="ia-stream-auth-label">Atrium password</label>' +
            '<input class="ia-stream-auth-input" type="password" autocomplete="current-password" />' +
            '<div class="ia-stream-auth-actions">' +
              '<button type="button" class="ia-stream-auth-btn ia-stream-auth-cancel">Cancel</button>' +
              '<button type="button" class="ia-stream-auth-btn ia-stream-auth-ok">Continue</button>' +
            '</div>' +
          '</div>' +
        '</div>';

      function cleanup(ret) {
        try { host.parentNode && host.parentNode.removeChild(host); } catch (e) {}
        resolve(ret);
      }

      document.body.appendChild(host);

      const input = host.querySelector('.ia-stream-auth-input');
      const okBtn = host.querySelector('.ia-stream-auth-ok');
      const cancelBtn = host.querySelector('.ia-stream-auth-cancel');
      const xBtn = host.querySelector('.ia-stream-auth-x');

      function onCancel() { cleanup({ ok: false }); }
      function onOk() {
        const pw = String((input && input.value) || '').trim();
        if (!pw) { if (input) input.focus(); return; }
        cleanup({ ok: true, password: pw });
      }

      if (cancelBtn) cancelBtn.addEventListener('click', onCancel);
      if (xBtn) xBtn.addEventListener('click', onCancel);
      if (okBtn) okBtn.addEventListener('click', onOk);
      host.addEventListener('click', (e) => {
        if (e.target === host) onCancel();
      });
      host.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') onCancel();
        if (e.key === 'Enter') onOk();
      });

      setTimeout(() => { try { input && input.focus(); } catch (e) {} }, 10);
    });
  }

  async function ensurePeerTubeUserToken() {
    if (_mintInFlight) return false;
    // Avoid pestering users repeatedly if they cancel.
    if (_mintPrompted) return false;

    _mintPrompted = true;

    const m = await authModal({
      title: 'Enable Stream actions',
      message: 'To comment in Stream, Atrium may need to create/link your PeerTube account and mint a token.'
    });
    if (!m || !m.ok) return false;
    const password = String(m.password || '').trim();
    if (!password) return false;

    _mintInFlight = true;
    const res = await NS.api.post('ia_stream_pt_mint_token', { password: password });
    _mintInFlight = false;

    if (!res || res.ok === false) {
      showActionError(res, 'Token mint failed');
      return false;
    }

    return true;
  }

  let _activeInlineReply = '';

  function cssEscape(s) {
    s = String(s || '');
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(s);
    // minimal fallback
    return s.replace(/[^a-zA-Z0-9_\-]/g, "\\$&");
  }

  function clearInlineReply() {
    if (!_activeInlineReply) return;
    const C = commentsMount();
    if (!C) { _activeInlineReply = ''; return; }
    const box = NS.util.qs('.ia-stream-inline-reply', C);
    if (box && box.parentNode) box.parentNode.removeChild(box);
    _activeInlineReply = '';
  }

  function openInlineReply(commentId, authorName) {
    commentId = String(commentId || '').trim();
    if (!commentId) return;

    const C = commentsMount();
    if (!C) return;

    // remove any existing inline reply UI
    clearInlineReply();

    const host = NS.util.qs('[data-ia-stream-comment="' + cssEscape(commentId) + '"]', C);
    if (!host) return;

    const wrap = document.createElement('div');
    wrap.className = 'ia-stream-inline-reply';
    wrap.setAttribute('data-video-id', _videoId);
    wrap.setAttribute('data-comment-id', commentId);
    wrap.innerHTML =
      '<div class="ia-stream-inline-reply-meta">Replying to ' + esc(authorName || 'comment') + '</div>' +
      '<textarea class="ia-stream-inline-reply-input" rows="2" placeholder="Write a reply…"></textarea>' +
      '<div class="ia-stream-inline-reply-actions">' +
        '<button type="button" class="ia-stream-inline-reply-cancel">Cancel</button>' +
        '<button type="button" class="ia-stream-inline-reply-send">Reply</button>' +
      '</div>';

    host.insertAdjacentElement('afterend', wrap);
    _activeInlineReply = commentId;

    const inp = NS.util.qs('.ia-stream-inline-reply-input', wrap);
    try { if (inp) inp.focus(); } catch (e) {}
  }

  function nodeHtml(node, depth) {
    depth = Number(depth || 0);
    const author = node && node.author ? node.author : {};
    const name = author && author.name ? author.name : 'User';
    const authorId = author && typeof author.id !== 'undefined' ? Number(author.id) : 0;
    const avatar = author && author.avatar ? author.avatar : '';
    const time = node && node.created_ago ? node.created_ago : '';
    const text = node && node.text ? node.text : '';
    const id = node && (node.id || node.comment_id) ? (node.id || node.comment_id) : '';

    const votes = node && node.votes ? node.votes : {};
    const up = votes && typeof votes.up !== 'undefined' ? Number(votes.up) : 0;
    const down = votes && typeof votes.down !== 'undefined' ? Number(votes.down) : 0;
    const my = votes && typeof votes.my !== 'undefined' ? Number(votes.my) : 0;

    const cfg = window.IA_STREAM_CFG || {};
    const ident = cfg && cfg.identity ? cfg.identity : {};
    const canModerate = !!(ident && ident.canModerate);
    const myPeerTubeUserId = ident && typeof ident.peertubeUserId !== 'undefined' ? Number(ident.peertubeUserId) : 0;
    const canDelete = !!(canModerate || (myPeerTubeUserId > 0 && authorId > 0 && myPeerTubeUserId === authorId));

    const pad = depth > 0 ? (' style="margin-left:' + Math.min(24, depth * 12) + 'px"') : '';
    return (
      '<div class="ia-stream-comment" data-ia-stream-comment="' + esc(String(id)) + '"' + pad + '>' +
        '<div class="ia-stream-comment-header">' +
          '<div class="ia-stream-comment-avatar" style="' + (avatar ? 'background-image:url(' + esc(avatar) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
          '<div class="ia-stream-comment-author">' + esc(name) + '</div>' +
          '<div class="ia-stream-comment-time">' + esc(time) + '</div>' +
        '</div>' +
        '<div class="ia-stream-comment-text">' + esc(text) + '</div>' +
        '<div class="ia-stream-comment-actions">' +
          '<button type="button" class="ia-stream-comment-vote ia-stream-comment-vote--up' + (my === 1 ? ' is-on' : '') + '" data-ia-stream-comment-vote="like" data-ia-stream-comment-id="' + esc(String(id)) + '" aria-label="Like">▲ <span class="ia-stream-vote-count" data-ia-stream-vote-up="' + esc(String(id)) + '">' + esc(String(up)) + '</span></button>' +
          '<button type="button" class="ia-stream-comment-vote ia-stream-comment-vote--down' + (my === -1 ? ' is-on' : '') + '" data-ia-stream-comment-vote="dislike" data-ia-stream-comment-id="' + esc(String(id)) + '" aria-label="Dislike">▼ <span class="ia-stream-vote-count" data-ia-stream-vote-down="' + esc(String(id)) + '">' + esc(String(down)) + '</span></button>' +
          '<button type="button" class="ia-stream-comment-reply" data-ia-stream-reply="' + esc(String(id)) + '" data-ia-stream-reply-author="' + esc(String(name)) + '">Reply</button>' +
          '<button type="button" class="ia-stream-comment-copy" data-ia-stream-copy-comment="' + esc(String(id)) + '" aria-label="Copy link">' + icoCopy() + '</button>' +
          (canDelete ? ('<button type="button" class="ia-stream-comment-del" data-ia-stream-comment-del="' + esc(String(id)) + '" aria-label="Delete">🗑</button>') : '') +
        '</div>' +
      '</div>'
    );
  }

  function threadHtml(thread) {
    const author = thread && thread.author ? thread.author : {};
    const name = author && author.name ? author.name : 'User';
    const authorId = author && typeof author.id !== 'undefined' ? Number(author.id) : 0;
    const avatar = author && author.avatar ? author.avatar : '';
    const time = thread && thread.created_ago ? thread.created_ago : '';
    const text = thread && thread.text ? thread.text : '';
    const threadId = thread && thread.id ? thread.id : '';
    const rootCommentId = thread && (thread.comment_id || thread.commentId) ? (thread.comment_id || thread.commentId) : '';
    const replies = thread && thread.replies_count ? parseInt(thread.replies_count, 10) : 0;

    const tree = threadId && _threadTrees[threadId] ? _threadTrees[threadId] : null;
    const children = tree && tree.root && Array.isArray(tree.root.children) ? tree.root.children : [];

    const rootNode = {
      id: rootCommentId,
      text,
      created_ago: time,
      author: { id: authorId, name, avatar },
      votes: thread && thread.votes ? thread.votes : undefined,
    };

    return (
      '<div class="ia-stream-thread" data-ia-stream-thread="' + esc(String(threadId)) + '">' +
        nodeHtml(rootNode, 0) +
        (replies > 0 ? (
          '<div class="ia-stream-thread-tools">' +
            '<button type="button" class="ia-stream-thread-toggle" data-ia-stream-thread-open="' + esc(String(threadId)) + '">' +
              (children.length ? 'Hide replies' : ('View replies (' + esc(String(replies)) + ')')) +
            '</button>' +
          '</div>'
        ) : '') +
        (children.length ? (
          '<div class="ia-stream-thread-children">' +
            children.map((ch) => renderNodeTree(ch, 1)).join('') +
          '</div>'
        ) : '') +
      '</div>'
    );
  }

  function renderThreads(items, append) {
    const C = commentsMount();
    if (!C) return;

    const html = (Array.isArray(items) ? items : []).map(threadHtml).join('');
    if (append) C.insertAdjacentHTML('beforeend', html);
    else C.innerHTML = html;
  }

  async function loadThreads(videoId, page) {
    if (_loading) return;
    _loading = true;

    const res = await NS.api.fetchComments({ video_id: videoId, page: page, per_page: _perPage });
    _loading = false;

    if (!res) return { ok: false, error: 'No response' };
    if (res.ok === false) return { ok: false, error: res.error || 'Comments error' };

    const items = Array.isArray(res.items) ? res.items : [];
    if (res.meta) {
      if (typeof res.meta.total === 'number') _total = res.meta.total;
      if (typeof res.meta.page === 'number') _page = res.meta.page;
      else _page = page;
      if (typeof res.meta.per_page === 'number') _perPage = res.meta.per_page;
    } else {
      _page = page;
    }

    return { ok: true, items };
  }

  NS.ui.comments.bindComposer = function (videoId) {
    _videoId = String(videoId || '').trim();
    clearInlineReply();
  };

  function resetStateForVideo(nextVideoId) {
    if (String(nextVideoId || '').trim() !== _videoId) {
      _videoId = String(nextVideoId || '').trim();
      _page = 1;
      _perPage = 30;
      _total = null;
      Object.keys(_threadTrees).forEach((k) => { delete _threadTrees[k]; });
      clearInlineReply();
    }
  }

  async function hydrateAllThreadTrees(videoId, threads) {
    const list = Array.isArray(threads) ? threads : [];
    const need = list.filter((t) => {
      const tid = t && t.id ? String(t.id) : '';
      const rc = t && t.replies_count ? parseInt(t.replies_count, 10) : 0;
      return tid && rc > 0 && !_threadTrees[tid];
    });

    if (!need.length) return;

    // Limit concurrency: avoid hammering PeerTube if a video has many threads.
    const limit = 3;
    let i = 0;

    async function worker() {
      while (i < need.length) {
        const idx = i++;
        const t = need[idx];
        const tid = t && t.id ? String(t.id) : '';
        if (!tid) continue;
        const res = await NS.api.fetchCommentThread({ video_id: videoId, thread_id: tid });
        if (res && res.ok !== false && res.item) {
          _threadTrees[tid] = res.item;
        }
      }
    }

    const workers = [];
    for (let w = 0; w < Math.min(limit, need.length); w++) workers.push(worker());
    await Promise.all(workers);
  }

  function renderNodeTree(node, depth) {
    const html = [];
    html.push(nodeHtml(node, depth));
    const kids = node && Array.isArray(node.children) ? node.children : [];
    for (const ch of kids) {
      html.push(renderNodeTree(ch, depth + 1));
    }
    return html.join('');
  }

  NS.ui.comments.load = async function (videoId) {
    resetStateForVideo(videoId);

    renderPlaceholder('Loading comments…');

    const out = await loadThreads(_videoId, 1);
    if (!out.ok) {
      renderPlaceholder(out.error || 'Comments error');
      return;
    }

    if (!out.items.length) {
      const note = (out && out.meta && out.meta.note) ? out.meta.note : '';
      renderPlaceholder(note ? ('No comments. ' + note) : 'No comments.');
      return;
    }

    // Ensure all reply trees from all users are visible.
    await hydrateAllThreadTrees(_videoId, out.items);

    renderThreads(out.items, false);

    // If the page URL includes a comment hash (#comment-...), scroll it into view.
    try {
      const h = String(window.location.hash || '');
      if (h.indexOf('#comment-') === 0) {
        const cid = decodeURIComponent(h.replace('#comment-', ''));
        if (cid) {
          const el = commentsMount().querySelector('[data-ia-stream-comment-node="' + cssEscape(cid) + '"]');
          if (el && el.scrollIntoView) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('ia-stream-highlight');
            setTimeout(function () { try { el.classList.remove('ia-stream-highlight'); } catch (e) {} }, 2000);
          }
        }
      }
    } catch (e) {}
    NS.ui.comments._bindOnce();
  };

  NS.ui.comments._bindOnce = function () {
    const C = commentsMount();
    if (!C || C.getAttribute('data-ia-stream-bound') === '1') return;
    C.setAttribute('data-ia-stream-bound', '1');

    NS.util.on(C, 'click', async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      // Copy link
      const copyBtn = t.closest ? t.closest('[data-ia-stream-copy-comment]') : null;
      if (copyBtn) {
        ev.preventDefault();
        const cid = copyBtn.getAttribute('data-ia-stream-copy-comment') || '';
        const base = (NS.state && NS.state.currentVideoUrl) ? String(NS.state.currentVideoUrl) : '';
        let link = '';
        try {
          const u = new URL(base || window.location.href);
          u.searchParams.set('tab', 'stream');
          u.searchParams.set('focus', 'comments');
          u.hash = 'comment-' + encodeURIComponent(cid);
          link = u.toString();
        } catch (e) {
          link = (base || window.location.href || '').replace(/#.*$/, '') + '#comment-' + encodeURIComponent(cid);
        }
        await copyText(link);
        return;
      }

      // Comment vote (local)
      const voteBtn = t.closest ? t.closest('[data-ia-stream-comment-vote]') : null;
      if (voteBtn) {
        ev.preventDefault();
        const cid = voteBtn.getAttribute('data-ia-stream-comment-id') || '';
        const action = voteBtn.getAttribute('data-ia-stream-comment-vote') || '';
        if (!cid || (action !== 'like' && action !== 'dislike')) return;

        // Toggle: if clicking the same active state, clear.
        const isOn = voteBtn.classList && voteBtn.classList.contains('is-on');
        const rating = isOn ? 'clear' : action;

        let res = await NS.api.rateComment({ comment_id: cid, rating: rating });
        if (!res || res.ok === false) {
          showActionError(res, 'Vote failed');
          return;
        }

        // Update counts + toggle state for both buttons.
        const upEl = C.querySelector('[data-ia-stream-vote-up="' + cssEscape(cid) + '"]');
        const downEl = C.querySelector('[data-ia-stream-vote-down="' + cssEscape(cid) + '"]');
        if (upEl && res.votes && typeof res.votes.up !== 'undefined') upEl.textContent = String(res.votes.up);
        if (downEl && res.votes && typeof res.votes.down !== 'undefined') downEl.textContent = String(res.votes.down);

        const upBtn = C.querySelector('[data-ia-stream-comment-id="' + cssEscape(cid) + '"][data-ia-stream-comment-vote="like"]');
        const downBtn = C.querySelector('[data-ia-stream-comment-id="' + cssEscape(cid) + '"][data-ia-stream-comment-vote="dislike"]');
        const my = res.votes && typeof res.votes.my !== 'undefined' ? Number(res.votes.my) : 0;
        if (upBtn) upBtn.classList.toggle('is-on', my === 1);
        if (downBtn) downBtn.classList.toggle('is-on', my === -1);
        return;
      }

      // Comment delete
      const delBtn = t.closest ? t.closest('[data-ia-stream-comment-del]') : null;
      if (delBtn) {
        ev.preventDefault();
        const cid = delBtn.getAttribute('data-ia-stream-comment-del') || '';
        const vid = _videoId || '';
        if (!cid || !vid) return;

        try { delBtn.disabled = true; } catch (e) {}
        let res = await NS.api.deleteComment({ video_id: vid, comment_id: cid });
        try { delBtn.disabled = false; } catch (e) {}

        if (!res || res.ok === false) {
          if (res && String(res.code || '') === 'missing_user_token') {
            const okTok = await ensurePeerTubeUserToken();
            if (okTok) {
              try { delBtn.disabled = true; } catch (e) {}
              res = await NS.api.deleteComment({ video_id: vid, comment_id: cid });
              try { delBtn.disabled = false; } catch (e) {}
            }
          }
        }

        if (!res || res.ok === false) {
          showActionError(res, 'Delete failed');
          return;
        }

        // Refresh comments to keep tree consistent.
        await NS.ui.comments.load(_videoId);
        return;
      }

      // Reply
      const replyBtn = t.closest ? t.closest('[data-ia-stream-reply]') : null;
      if (replyBtn) {
        ev.preventDefault();
        const id = replyBtn.getAttribute('data-ia-stream-reply') || '';
        const who = replyBtn.getAttribute('data-ia-stream-reply-author') || '';
        if (id) openInlineReply(id, who);
        return;
      }

      // Inline reply actions
      const box = t.closest ? t.closest('.ia-stream-inline-reply') : null;
      if (box) {
        const cancel = t.closest ? t.closest('.ia-stream-inline-reply-cancel') : null;
        if (cancel) {
          ev.preventDefault();
          clearInlineReply();
          return;
        }

        const send = t.closest ? t.closest('.ia-stream-inline-reply-send') : null;
        if (send) {
          ev.preventDefault();
          const vid = box.getAttribute('data-video-id') || _videoId;
          const cid = box.getAttribute('data-comment-id') || '';
          const inp = NS.util.qs('.ia-stream-inline-reply-input', box);
          const text = String(inp && inp.value ? inp.value : '').trim();
          if (!vid || !cid || !text) return;

          try { send.disabled = true; } catch (e) {}
          let res = await NS.api.replyToComment({ video_id: vid, comment_id: cid, text: text });
          try { send.disabled = false; } catch (e) {}

          if (!res || res.ok === false) {
            // If we have no per-user token yet, prompt once to mint then retry.
            if (res && String(res.code || '') === 'missing_user_token') {
              const okTok = await ensurePeerTubeUserToken();
              if (okTok) {
                try { send.disabled = true; } catch (e) {}
                res = await NS.api.replyToComment({ video_id: vid, comment_id: cid, text: text });
                try { send.disabled = false; } catch (e) {}
                if (res && res.ok !== false) {
                  clearInlineReply();
                  await NS.ui.comments.load(_videoId);
                  return;
                }
              }
            }
            showActionError(res, 'Reply failed');
            // fail -> keep UI, but refresh threads for visibility
            await NS.ui.comments.load(_videoId);
            return;
          }

          clearInlineReply();
          await NS.ui.comments.load(_videoId);
          return;
        }
      }

      // Toggle replies (load thread tree once)
      const openBtn = t.closest ? t.closest('[data-ia-stream-thread-open]') : null;
      if (openBtn) {
        ev.preventDefault();
        const threadId = openBtn.getAttribute('data-ia-stream-thread-open') || '';
        if (!threadId) return;

        const threadEl = openBtn.closest ? openBtn.closest('[data-ia-stream-thread]') : null;
        if (threadEl && threadEl.classList) {
          // If we already have a tree loaded, just collapse/expand in place.
          if (_threadTrees[threadId]) {
            const isCollapsed = threadEl.classList.contains('is-collapsed');
            if (isCollapsed) {
              threadEl.classList.remove('is-collapsed');
              openBtn.textContent = 'Hide replies';
            } else {
              threadEl.classList.add('is-collapsed');
              openBtn.textContent = 'Show replies';
            }
            return;
          }
        }

        // Fallback: tree not loaded yet (should be rare after hydrateAllThreadTrees)
        openBtn.textContent = 'Loading replies…';
        const res = await NS.api.fetchCommentThread({ video_id: _videoId, thread_id: threadId });
        if (!res || res.ok === false || !res.item) {
          openBtn.textContent = 'Show replies';
          return;
        }

        _threadTrees[threadId] = res.item;
        NS.ui.comments.load(_videoId);
        return;
      }
    });
  };

  NS.ui.comments.submit = async function () {
    const { input, send } = composerEls();
    if (!_videoId || !input) return;

    const text = String(input.value || '').trim();
    if (!text) return;

    try { if (send) send.disabled = true; } catch (e) {}

    let res = await NS.api.createCommentThread({ video_id: _videoId, text: text });

    try { if (send) send.disabled = false; } catch (e) {}

    if (!res || res.ok === false) {
      // If we have no per-user token yet, prompt once to mint then retry.
      if (res && String(res.code || '') === 'missing_user_token') {
        const okTok = await ensurePeerTubeUserToken();
        if (okTok) {
          try { if (send) send.disabled = true; } catch (e) {}
          res = await NS.api.createCommentThread({ video_id: _videoId, text: text });
          try { if (send) send.disabled = false; } catch (e) {}
          if (res && res.ok !== false) {
            input.value = '';
            clearInlineReply();
            await NS.ui.comments.load(_videoId);
            try { input.focus(); } catch (e) {}
            return;
          }
        }
      }
      showActionError(res, (res && res.error) ? res.error : "Comment failed");
      // then reload comments
      NS.ui.comments.load(_videoId);
      return;
    }

    input.value = '';
    clearInlineReply();
    await NS.ui.comments.load(_videoId);

    // keep focus
    try { input.focus(); } catch (e) {}
  };
})();
