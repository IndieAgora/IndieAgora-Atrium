/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.video.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.video = NS.ui.video || {};

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function fmtNum(n) {
    n = Number(n || 0);
    try { return n.toLocaleString(); } catch (e) { return String(n); }
  }

  function ico(name) {
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

  function authModal(opts) {
    opts = opts || {};
    const title = String(opts.title || 'Enable Stream actions');
    const message = String(opts.message || 'Enter your Atrium password so Stream can mint your PeerTube token.');

    return new Promise((resolve) => {
      const host = document.createElement('div');
      host.className = 'ia-stream-auth-modal';
      host.innerHTML =
        '<div class="ia-stream-auth-dialog" role="dialog" aria-modal="true">' +
          '<div class="ia-stream-auth-head">' +
            '<div class="ia-stream-auth-title">' + esc(title) + '</div>' +
            '<button type="button" class="ia-stream-auth-x" aria-label="Close">✕</button>' +
          '</div>' +
          '<div class="ia-stream-auth-body">' +
            '<div class="ia-stream-auth-msg">' + esc(message) + '</div>' +
            '<label class="ia-stream-auth-label">Password</label>' +
            '<input class="ia-stream-auth-input" type="password" autocomplete="current-password" />' +
            '<div class="ia-stream-auth-actions">' +
              '<button type="button" class="ia-stream-auth-btn ia-stream-auth-cancel">Cancel</button>' +
              '<button type="button" class="ia-stream-auth-btn ia-stream-auth-ok">Continue</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(host);

      const input = host.querySelector('.ia-stream-auth-input');
      const cancelBtn = host.querySelector('.ia-stream-auth-cancel');
      const okBtn = host.querySelector('.ia-stream-auth-ok');
      const xBtn = host.querySelector('.ia-stream-auth-x');

      function cleanup(result) {
        try { document.body.removeChild(host); } catch (e) {}
        resolve(result);
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

  let _mintPrompted = false;
  let _mintInFlight = false;

  async function ensurePeerTubeUserToken() {
    if (_mintInFlight) return false;
    if (_mintPrompted) return false;
    _mintPrompted = true;

    const m = await authModal({
      title: 'Enable Stream actions',
      message: 'To like/dislike or comment in Stream, Atrium may need to create/link your PeerTube account and mint a token.'
    });
    if (!m || !m.ok) return false;
    const password = String(m.password || '').trim();
    if (!password) return false;

    _mintInFlight = true;
    const res = await NS.api.post('ia_stream_pt_mint_token', { password: password });
    _mintInFlight = false;

    if (!res || res.ok === false) return false;
    return true;
  }

  function root() {
    return NS.util.qs("#ia-stream-shell");
  }

  function ensureModal() {
    const R = root();
    if (!R) return null;

    let M = NS.util.qs(".ia-stream-modal", document);
    if (M) return M;

    M = document.createElement("div");
    M.className = "ia-stream-modal";
    M.setAttribute("hidden", "hidden");
    M.innerHTML =
      '<div class="ia-stream-modal-dialog" role="dialog" aria-modal="true" aria-label="Stream video">' +
        '<div class="ia-stream-modal-header">' +
          '<div class="ia-stream-modal-title">Loading…</div>' +
          '<button type="button" class="ia-stream-modal-close" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="ia-stream-modal-body">' +
          '<div class="ia-stream-modal-layout">' +
            '<div class="ia-stream-modal-left">' +
              '<div class="ia-stream-modal-video"></div>' +
              '<div class="ia-stream-modal-meta"></div>' +
            '</div>' +
            '<div class="ia-stream-modal-right">' +
              '<div class="ia-stream-modal-composer" role="region" aria-label="Comment composer">' +
                '<textarea class="ia-stream-composer-input" rows="2" placeholder="Add a comment…"></textarea>' +
                '<button type="button" class="ia-stream-composer-send">Send</button>' +
              '</div>' +
              '<div class="ia-stream-modal-comments"></div>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(M);

    // close handlers
    const closeBtn = NS.util.qs(".ia-stream-modal-close", M);
    NS.util.on(closeBtn, "click", closeModal);

    NS.util.on(M, "click", function (ev) {
      // click backdrop closes
      if (ev && ev.target === M) closeModal();
    });

    NS.util.on(document, "keydown", function (ev) {
      if (!isOpen()) return;
      if (ev && ev.key === "Escape") closeModal();
    });

    // composer handlers (top-level comments only)
    const sendBtn = NS.util.qs('.ia-stream-composer-send', M);
    const input = NS.util.qs('.ia-stream-composer-input', M);
    NS.util.on(sendBtn, 'click', function (e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      if (NS.ui.comments && NS.ui.comments.submit) NS.ui.comments.submit();
    });
    NS.util.on(input, 'keydown', function (e) {
      // Enter sends; Shift+Enter inserts newline
      if (!e) return;
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (NS.ui.comments && NS.ui.comments.submit) NS.ui.comments.submit();
      }
    });

    // Action delegation inside modal (rate/copy/share)
    NS.util.on(M, 'click', async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      const rateBtn = t.closest ? t.closest('[data-ia-stream-rate]') : null;
      if (rateBtn) {
        ev.preventDefault();
        const rating = rateBtn.getAttribute('data-ia-stream-rate') || '';
        const vid = M.getAttribute('data-ia-stream-video') || '';
        if (!vid || (rating !== 'like' && rating !== 'dislike')) return;

        let out = await NS.api.rateVideo({ id: vid, rating: rating });
        if (out && out.ok === false && String(out.code || '') === 'missing_user_token') {
          const okTok = await ensurePeerTubeUserToken();
          if (okTok) out = await NS.api.rateVideo({ id: vid, rating: rating });
        }

        if (out && out.ok !== false && out.item && out.item.counts) {
          const counts = out.item.counts;
          // Update modal counts
          const like = NS.util.qs('[data-ia-stream-like-count]', M);
          const dislike = NS.util.qs('[data-ia-stream-dislike-count]', M);
          if (like) like.textContent = fmtNum(counts.likes || 0);
          if (dislike) dislike.textContent = fmtNum(counts.dislikes || 0);

          // Broadcast to feed cards
          try {
            window.dispatchEvent(new CustomEvent('ia:stream_video_counts', {
              detail: { video_id: vid, counts: counts }
            }));
          } catch (e) {}
        }

        return;
      }

      const copyBtn = t.closest ? t.closest('[data-ia-stream-copy]') : null;
      if (copyBtn) {
        ev.preventDefault();
        const url = copyBtn.getAttribute('data-ia-stream-copy') || '';
        await copyText(url);
        return;
      }

      const shareBtn = t.closest ? t.closest('[data-ia-stream-share]') : null;
      if (shareBtn) {
        ev.preventDefault();
        const vid = M.getAttribute('data-ia-stream-video') || '';
        const url = shareBtn.getAttribute('data-ia-stream-share') || '';
        try {
          window.dispatchEvent(new CustomEvent('ia:share_to_connect', {
            detail: { kind: 'video', video_id: vid, url: url }
          }));
        } catch (e) {}
        return;
      }
    });

    // Action buttons (delegated)
    NS.util.on(M, 'click', async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      // Copy video link
      const copyBtn = t.closest ? t.closest('[data-ia-stream-copy]') : null;
      if (copyBtn) {
        ev.preventDefault();
        const url = copyBtn.getAttribute('data-ia-stream-copy') || '';
        await copyText(url);
        return;
      }

      // Share to Connect (signal only)
      const shareBtn = t.closest ? t.closest('[data-ia-stream-share]') : null;
      if (shareBtn) {
        ev.preventDefault();
        const vid = shareBtn.getAttribute('data-ia-stream-video') || M.getAttribute('data-ia-stream-video') || '';
        const url = shareBtn.getAttribute('data-ia-stream-share') || '';
        try {
          window.dispatchEvent(new CustomEvent('ia:share_to_connect', { detail: { type: 'video', video_id: vid, url: url } }));
        } catch (e) {}
        return;
      }

      // Focus comments
      const focusBtn = t.closest ? t.closest('[data-ia-stream-focus-comments]') : null;
      if (focusBtn) {
        ev.preventDefault();
        const C = NS.util.qs('.ia-stream-modal-comments', M);
        if (C && C.scrollIntoView) C.scrollIntoView({ block: 'start', behavior: 'smooth' });
        const inp = NS.util.qs('.ia-stream-composer-input', M);
        try { inp && inp.focus && inp.focus(); } catch (e) {}
        return;
      }

      // Rating
      const rateBtn = t.closest ? t.closest('[data-ia-stream-rate]') : null;
      if (rateBtn) {
        ev.preventDefault();
        const vid = rateBtn.getAttribute('data-ia-stream-video') || M.getAttribute('data-ia-stream-video') || '';
        const rating = rateBtn.getAttribute('data-ia-stream-rate') || '';
        if (!vid || (rating !== 'like' && rating !== 'dislike')) return;

        let res = await NS.api.rateVideo({ id: vid, rating: rating });
        if (res && res.ok === false && String(res.code || '') === 'missing_user_token') {
          const okTok = await ensurePeerTubeUserToken();
          if (okTok) res = await NS.api.rateVideo({ id: vid, rating: rating });
        }

        if (res && res.ok !== false && res.item && res.item.counts) {
          const counts = res.item.counts;
          const likeEl = NS.util.qs('[data-ia-stream-like-count]', M);
          const disEl = NS.util.qs('[data-ia-stream-dislike-count]', M);
          if (likeEl) likeEl.textContent = fmtNum(counts.likes || 0);
          if (disEl) disEl.textContent = fmtNum(counts.dislikes || 0);
          try {
            window.dispatchEvent(new CustomEvent('ia:stream_video_counts', { detail: { id: vid, counts: counts } }));
          } catch (e) {}
        }
        return;
      }
    });

    return M;
  }

  function isOpen() {
    const M = NS.util.qs(".ia-stream-modal", document);
    return !!(M && !M.hasAttribute("hidden"));
  }

  function closeModal() {
    const M = NS.util.qs(".ia-stream-modal", document);
    if (!M) return;

    M.setAttribute("hidden", "hidden");

    // stop playback by clearing iframe
    const V = NS.util.qs(".ia-stream-modal-video", M);
    if (V) V.innerHTML = "";

    // clear comments/meta
    const C = NS.util.qs(".ia-stream-modal-comments", M);
    if (C) C.innerHTML = "";
    const META = NS.util.qs(".ia-stream-modal-meta", M);
    if (META) META.innerHTML = "";

    const inp = NS.util.qs(".ia-stream-composer-input", M);
    if (inp) inp.value = "";

    const T = NS.util.qs(".ia-stream-modal-title", M);
    if (T) T.textContent = "";
  }

  function openModalShell(title) {
    const M = ensureModal();
    if (!M) return null;

    const T = NS.util.qs(".ia-stream-modal-title", M);
    if (T) T.textContent = title || "Loading…";

    const V = NS.util.qs(".ia-stream-modal-video", M);
    if (V) V.innerHTML = '<div class="ia-stream-player"><div class="ia-stream-player-overlay">Loading…</div></div>';

    const META = NS.util.qs(".ia-stream-modal-meta", M);
    if (META) META.innerHTML = "";

    const C = NS.util.qs(".ia-stream-modal-comments", M);
    if (C) C.innerHTML = '<div class="ia-stream-placeholder">Loading comments…</div>';

    const inp = NS.util.qs('.ia-stream-composer-input', M);
    if (inp) {
      inp.value = '';
      inp.placeholder = 'Add a comment…';
    }

    M.removeAttribute("hidden");
    return M;
  }

  function renderVideoIntoModal(M, v) {
    const title = v && v.title ? v.title : "Video";
    const embed = v && v.embed_url ? v.embed_url : "";
    const url = v && v.url ? v.url : "";
    const excerpt = v && v.excerpt ? v.excerpt : "";
    const support = v && v.support ? v.support : "";
    const ago = v && v.published_ago ? v.published_ago : "";
    const ch = v && v.channel ? v.channel : {};
    const chName = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : "";
    const counts = v && v.counts ? v.counts : (v && v.counts === 0 ? v.counts : (v && v.counts));
    const likes = counts && typeof counts.likes === 'number' ? counts.likes : (v && v.counts && v.counts.likes) ? v.counts.likes : (v && v.counts?.likes);
    const dislikes = counts && typeof counts.dislikes === 'number' ? counts.dislikes : (v && v.counts && v.counts.dislikes) ? v.counts.dislikes : (v && v.counts?.dislikes);
    const comments = counts && typeof counts.comments === 'number' ? counts.comments : (v && v.counts && v.counts.comments) ? v.counts.comments : (v && v.counts?.comments);
    const views = counts && typeof counts.views === 'number' ? counts.views : (v && v.counts && v.counts.views) ? v.counts.views : (v && v.counts?.views);

    const T = NS.util.qs(".ia-stream-modal-title", M);
    if (T) T.textContent = title;

    // Track current video URL for copy links.
    // Use an Atrium URL that re-opens this modal (not the direct PeerTube link).
    try {
      NS.state = NS.state || {};
      const id = v && v.id ? String(v.id) : '';
      let local = '';
      try {
        const u = new URL(window.location.href);
        u.searchParams.set('tab', 'stream');
        u.searchParams.set('video', id);
        u.hash = '';
        local = u.toString();
      } catch (e2) {
        local = (window.location.origin || '') + '/?tab=stream&video=' + encodeURIComponent(id);
      }
      NS.state.currentVideoUrl = local;
      NS.state.currentVideoPeerTubeUrl = url || '';
      NS.state.currentVideoId = id;
    } catch (e) {}

    M.setAttribute('data-ia-stream-video', (v && v.id) ? String(v.id) : '');

    const V = NS.util.qs(".ia-stream-modal-video", M);
    if (V) {
      if (embed) {
        V.innerHTML =
          '<iframe loading="lazy" src="' + esc(embed) + '" allow="fullscreen; autoplay; encrypted-media" allowfullscreen></iframe>';
      } else if (url) {
        V.innerHTML =
          '<div class="ia-stream-player"><a href="' + esc(url) + '" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;height:100%;color:white;text-decoration:none">Open on PeerTube</a></div>';
      } else {
        V.innerHTML =
          '<div class="ia-stream-player"><div class="ia-stream-player-overlay">No embed URL</div></div>';
      }
    }

    const META = NS.util.qs(".ia-stream-modal-meta", M);
    if (META) {
      META.innerHTML =
        '<div class="iad-card-meta">' +
          (chName ? '<span class="iad-sub">' + esc(chName) + '</span>' : '<span class="iad-sub">Unknown channel</span>') +
          (ago ? ' • ' + esc(ago) : '') +
        '</div>' +
        '<div class="iad-card-title" style="margin-top:6px">' + esc(title) + '</div>' +
        '<div class="iad-card-actions ia-stream-actions">' +
          '<button type="button" class="iad-iconbtn" data-ia-stream-rate="like" data-ia-stream-video="' + esc(String((v && v.id) ? v.id : '')) + '" aria-label="Like">' + ico('up') + '<span class="iad-iconbtn-count" data-ia-stream-like-count>' + fmtNum(likes || 0) + '</span></button>' +
          '<button type="button" class="iad-iconbtn" data-ia-stream-rate="dislike" data-ia-stream-video="' + esc(String((v && v.id) ? v.id : '')) + '" aria-label="Dislike">' + ico('down') + '<span class="iad-iconbtn-count" data-ia-stream-dislike-count>' + fmtNum(dislikes || 0) + '</span></button>' +
          '<button type="button" class="iad-iconbtn" data-ia-stream-focus-comments aria-label="Comments">' + ico('reply') + '<span class="iad-iconbtn-count">' + fmtNum(comments || 0) + '</span></button>' +
          (url ? '<button type="button" class="iad-iconbtn" data-ia-stream-copy="' + esc(url) + '" aria-label="Copy link">' + ico('link') + '</button>' : '') +
          (url ? '<button type="button" class="iad-iconbtn" data-ia-stream-share="' + esc(url) + '" aria-label="Share to Connect">' + ico('share') + '</button>' : '') +
          '<span class="iad-spacer"></span>' +
          '<span class="ia-stream-views">Views: ' + fmtNum(views || 0) + '</span>' +
        '</div>' +
        (support ? '<div class="ia-stream-support"><div class="ia-stream-support-label">Support</div><div class="ia-stream-support-text">' + esc(support) + '</div></div>' : '') +
        (excerpt ? '<div class="iad-card-excerpt" style="margin-top:10px"><p>' + esc(excerpt) + '</p></div>' : '');
    }
  }

  NS.ui.video.open = async function (videoId, opts) {
    videoId = (videoId === null || videoId === undefined) ? "" : String(videoId).trim();
    if (!videoId) return;

    const M = openModalShell("Loading…");
    if (!M) return;

    // Fetch full video details (PeerTube-backed via PHP module)
    const res = await NS.api.fetchVideo({ id: videoId });

    if (!res || res.ok === false || !res.item) {
      const C = NS.util.qs(".ia-stream-modal-comments", M);
      if (C) C.innerHTML = '<div class="ia-stream-placeholder">' + esc((res && res.error) ? res.error : "Video load failed") + '</div>';
      const V = NS.util.qs(".ia-stream-modal-video", M);
      if (V) V.innerHTML = '<div class="ia-stream-player"><div class="ia-stream-player-overlay">Failed to load</div></div>';
      return;
    }

    renderVideoIntoModal(M, res.item);

    // Load comments after video renders
    if (NS.ui.comments && NS.ui.comments.load) {
      NS.ui.comments.load(videoId);
    }

    // Wire composer after open
    if (NS.ui.comments && NS.ui.comments.bindComposer) {
      NS.ui.comments.bindComposer(videoId);
    }

    if (opts && opts.focus === 'comments') {
      const body = NS.util.qs('.ia-stream-modal-body', M);
      if (body) body.scrollTop = body.scrollHeight;
      const inp = NS.util.qs('.ia-stream-composer-input', M);
      if (inp && inp.focus) inp.focus();
    }
  };

  NS.ui.video.close = closeModal;
})();
