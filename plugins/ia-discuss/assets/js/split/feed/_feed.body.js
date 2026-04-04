  "use strict";
  const CORE = window.IA_DISCUSS_CORE || {};
  const esc = CORE.esc || function (s) {
    return String(s || "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  };
  const timeAgo = CORE.timeAgo || function () { return ""; };

  const API = window.IA_DISCUSS_API;
  const STATE = window.IA_DISCUSS_STATE;

  // Share to Connect modal (select wall + optional comment)
  function ensureShareModal(){
    let modal = document.querySelector('[data-iad-share-modal]');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.setAttribute('data-iad-share-modal','1');
    modal.className = 'iad-modal iad-share-modal';
    modal.setAttribute('hidden','');
    modal.innerHTML = (
      '<div class="iad-modal-backdrop" data-iad-share-close></div>' +
      '<div class="iad-modal-sheet" role="dialog" aria-modal="true" aria-label="Share to Connect">' +
        '<div class="iad-modal-top">' +
          '<div class="iad-modal-title">Share to Connect</div>' +
          '<div style="margin-left:auto"></div>' +
          '<button type="button" class="iad-x" data-iad-share-close aria-label="Close">×</button>' +
        '</div>' +
        '<div class="iad-modal-body">' +
          '<div class="iad-share-hint" data-iad-share-msg></div>' +
          '<label class="iad-share-label">Add a comment</label>' +
          '<textarea class="iad-share-comment" rows="3" data-iad-share-comment placeholder="Say something about this..."></textarea>' +
          '<label class="iad-share-label">Choose a wall</label>' +
          '<input type="text" class="iad-share-search" data-iad-share-search placeholder="Search users..." autocomplete="off" />' +
          '<div class="iad-share-results" data-iad-share-results></div>' +
          '<div class="iad-share-picked" data-iad-share-picked></div>' +
          '<label class="iad-share-self"><input type="checkbox" data-iad-share-self checked /> Also share to my wall</label>' +
        '</div>' +
        '<div class="iad-modal-body" style="padding-top:0">' +
          '<div style="display:flex;gap:10px;justify-content:flex-end">' +
            '<button type="button" class="iad-btn" data-iad-share-cancel>Cancel</button>' +
            '<button type="button" class="iad-btn iad-btn-primary" data-iad-share-send>Share</button>' +
          '</div>' +
        '</div>' +
      '</div>'
    );

    document.body.appendChild(modal);

    modal.addEventListener('click', (e)=>{
      const t = e.target;
      if (t && t.closest && t.closest('[data-iad-share-close],[data-iad-share-cancel]')) {
        e.preventDefault();
        closeShareModal();
        return;
      }
      const pick = t && t.closest && t.closest('[data-iad-share-pick]');
      if (pick) {
        e.preventDefault();
        togglePicked(pick);
        return;
      }
      const unpick = t && t.closest && t.closest('[data-iad-share-unpick]');
      if (unpick) {
        e.preventDefault();
        const wrap = unpick.closest('[data-iad-share-picked-item]');
        if (wrap) wrap.remove();
        return;
      }
    });

    document.addEventListener('keydown', (e)=>{
      if (!isShareModalOpen()) return;
      if (e.key === 'Escape') closeShareModal();
    });

    let tmr = null;
    const search = modal.querySelector('[data-iad-share-search]');
    if (search) {
      search.addEventListener('input', ()=>{
        clearTimeout(tmr);
        tmr = setTimeout(()=> runShareSearch(), 150);
      });
    }

    const send = modal.querySelector('[data-iad-share-send]');
    if (send) send.addEventListener('click', ()=> submitShare());

    return modal;
  }

  function isShareModalOpen(){
    const m = document.querySelector('[data-iad-share-modal]');
    return !!(m && !m.hasAttribute('hidden') && m.classList.contains('is-open'));
  }

  function closeShareModal(){
    const m = document.querySelector('[data-iad-share-modal]');
    if (!m) return;
    m.classList.remove('is-open');
    m.setAttribute('hidden','');
  }

  function openShareModal(topicId, postId){
    const m = ensureShareModal();
    m.__shareTopicId = parseInt(topicId||0,10)||0;
    m.__sharePostId  = parseInt(postId||0,10)||0;
    const msg = m.querySelector('[data-iad-share-msg]');
    if (msg) msg.textContent = '';
    const res = m.querySelector('[data-iad-share-results]');
    if (res) res.innerHTML = '';
    const picked = m.querySelector('[data-iad-share-picked]');
    if (picked) picked.innerHTML = '';
    const q = m.querySelector('[data-iad-share-search]');
    if (q) q.value = '';
    const c = m.querySelector('[data-iad-share-comment]');
    if (c) c.value = '';
    const self = m.querySelector('[data-iad-share-self]');
    if (self) self.checked = true;

    m.removeAttribute('hidden');
    m.classList.add('is-open');
    try { setTimeout(()=>{ q && q.focus(); }, 0); } catch (e) {}
  }

  function togglePicked(row){
    const m = document.querySelector('[data-iad-share-modal]');
    if (!m) return;
    const picked = m.querySelector('[data-iad-share-picked]');
    if (!picked) return;
    const wp = row.getAttribute('data-wp') || '0';
    const phpbb = row.getAttribute('data-phpbb') || '0';
    const display = row.getAttribute('data-display') || row.getAttribute('data-username') || 'User';
    const key = wp + ':' + phpbb;
    const existing = Array.from(picked.querySelectorAll('[data-iad-share-picked-item]')).find(el=>String(el.getAttribute('data-key')||'')===key);
    if (existing) { existing.remove(); return; }

    const html = '<div class="iad-share-picked-item" data-iad-share-picked-item data-key="' + esc(key) + '" data-wp="' + esc(wp) + '" data-phpbb="' + esc(phpbb) + '">' +
      '<span>' + esc(display) + '</span>' +
      '<button type="button" class="iad-share-unpick" data-iad-share-unpick aria-label="Remove">×</button>' +
    '</div>';
    picked.insertAdjacentHTML('beforeend', html);
  }

  function runShareSearch(){
    const m = document.querySelector('[data-iad-share-modal]');
    if (!m) return;
    const qEl = m.querySelector('[data-iad-share-search]');
    const out = m.querySelector('[data-iad-share-results]');
    const msg = m.querySelector('[data-iad-share-msg]');
    const q = (qEl ? String(qEl.value||'') : '').trim();
    if (!out) return;

    // Use Connect's existing user search endpoint + nonce.
    // In Atrium, IA_CONNECT is localized by ia-connect.
    const c = (window.IA_CONNECT && typeof IA_CONNECT === 'object') ? IA_CONNECT : null;
    const nonce = c && c.nonces ? String(c.nonces.user_search||'') : '';
    if (!nonce) {
      out.innerHTML = '';
      if (msg) msg.textContent = 'Connect user search is not available.';
      return;
    }
    if (q.length < 2) { out.innerHTML = ''; return; }

    const fd = new FormData();
    fd.append('action', 'ia_connect_user_search');
    fd.append('nonce', nonce);
    fd.append('q', q);

    fetch((c && c.ajaxUrl) ? c.ajaxUrl : (window.IA_DISCUSS ? IA_DISCUSS.ajaxUrl : ''), { method:'POST', credentials:'same-origin', body: fd })
      .then(r=>r.text().then(t=>{ try { return JSON.parse(t); } catch(e){ return { success:false, data:{ message:'Bad JSON' } }; } }))
      .then(res=>{
        if (!res || !res.success || !res.data) throw new Error((res && res.data && res.data.message) ? res.data.message : 'Search failed');
        const rows = (res.data.results || []);
        if (!rows.length) { out.innerHTML = '<div class="iad-share-empty">No results</div>'; return; }
        out.innerHTML = rows.map(u=>{
          return '<button type="button" class="iad-share-row" data-iad-share-pick data-wp="' + esc(u.wp_user_id||0) + '" data-phpbb="' + esc(u.phpbb_user_id||0) + '" data-username="' + esc(u.username||'') + '" data-display="' + esc(u.display||u.username||'User') + '">' +
            '<img class="iad-share-ava" src="' + esc(u.avatarUrl||'') + '" alt="" />' +
            '<span class="iad-share-name">' + esc(u.display||u.username||'User') + '</span>' +
          '</button>';
        }).join('');
      })
      .catch(()=>{
        out.innerHTML = '<div class="iad-share-empty">Search failed</div>';
      });
  }

  function submitShare(){
    const m = document.querySelector('[data-iad-share-modal]');
    if (!m) return;
    const topicId = parseInt(m.__shareTopicId||0,10)||0;
    const postId  = parseInt(m.__sharePostId||0,10)||0;
    if (!topicId) return;

    const send = m.querySelector('[data-iad-share-send]');
    const msg  = m.querySelector('[data-iad-share-msg]');
    const picked = m.querySelector('[data-iad-share-picked]');
    const self = m.querySelector('[data-iad-share-self]');
    const commentEl = m.querySelector('[data-iad-share-comment]');

    const items = picked ? Array.from(picked.querySelectorAll('[data-iad-share-picked-item]')) : [];
    const wpIds = items.map(el=>String(el.getAttribute('data-wp')||'0')).filter(v=>parseInt(v,10)>0);
    const phpIds = items.map(el=>String(el.getAttribute('data-phpbb')||'0')).filter(v=>parseInt(v,10)>0);
    const shareToSelf = (self && self.checked) ? 1 : 0;
    const comment = commentEl ? String(commentEl.value||'') : '';

    if (send) send.disabled = true;
    if (msg) msg.textContent = '';

    API.post('ia_discuss_share_to_connect', {
      topic_id: topicId,
      post_id: postId,
      wall_wp_ids: wpIds.join(','),
      wall_phpbb_ids: phpIds.join(','),
      share_to_self: String(shareToSelf),
      comment: comment
    })
      .then((res)=>{
        const ok = res && res.success && res.data && res.data.connect_post_id;
        if (!ok) throw new Error((res && res.data && res.data.message) ? res.data.message : 'Share failed');

        const connectPostId = parseInt(res.data.connect_post_id, 10) || 0;
        const href = (()=>{
          try {
            const u = new URL(window.location.href);
            u.searchParams.set('tab','connect');
            u.searchParams.set('ia_post', String(connectPostId));
            return u.toString();
          } catch (e) { return ''; }
        })();
        if (msg) msg.innerHTML = href ? ('Shared. <a href="' + esc(href) + '">View in Connect</a>') : 'Shared.';
        // Keep modal open briefly so user sees confirmation.
        setTimeout(()=>{ closeShareModal(); }, 700);
      })
      .catch(()=>{
        if (msg) msg.textContent = 'Request failed.';
      })
      .finally(()=>{
        if (send) send.disabled = false;
      });
  }

  // Expose share modal opener so topic view can reuse it without duplicating UI.
  try {
    window.IA_DISCUSS_SHARE = window.IA_DISCUSS_SHARE || {};
    window.IA_DISCUSS_SHARE.openShareModal = openShareModal;
  } catch (e) {}

  function currentUserId() {
    // If your localized IA_DISCUSS includes userId later, we’ll pick it up.
    // Otherwise edit buttons just won’t show.
    try {
      const v = (window.IA_DISCUSS && (IA_DISCUSS.userId || IA_DISCUSS.user_id || IA_DISCUSS.wpUserId)) || 0;
      return parseInt(v, 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  function makeTopicUrl(topicId, postId) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set("iad_topic", String(topicId));
      if (postId) u.searchParams.set("iad_post", String(postId));
      else u.searchParams.delete("iad_post");
      return u.toString();
    } catch (e) {
      const base = String(window.location.origin || "") + String(window.location.pathname || "");
      let out = base + "?iad_topic=" + encodeURIComponent(String(topicId || ""));
      if (postId) out += "&iad_post=" + encodeURIComponent(String(postId || ""));
      return out;
    }
  }

  async function copyToClipboard(text) {
    const t = String(text || "");
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(t);
        return true;
      }
    } catch (e) {}
    try {
      const ta = document.createElement("textarea");
      ta.value = t;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.top = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
      return true;
    } catch (e) {}
    return false;
  }

  function openConnectProfile(payload) {
    const p = payload || {};
    const username = (p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username,
        user_id,
        ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try {
      window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } }));
    } catch (e) {}

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // -----------------------------
  // Icons (inline SVG)
  // -----------------------------
  function ico(name) {
    const common = `width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"`;
    if (name === "reply") return `<svg ${common}><path d="M9 17l-4-4 4-4"/><path d="M20 19v-2a4 4 0 0 0-4-4H5"/></svg>`;
    if (name === "link")  return `<svg ${common}><path d="M10 13a5 5 0 0 1 0-7l1-1a5 5 0 0 1 7 7l-1 1"/><path d="M14 11a5 5 0 0 1 0 7l-1 1a5 5 0 0 1-7-7l1-1"/></svg>`;
    if (name === "share") return `<svg ${common}><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v13"/></svg>`;
    if (name === "last")  return `<svg ${common}><path d="M12 3v14"/><path d="M7 12l5 5 5-5"/><path d="M5 21h14"/></svg>`;
    if (name === "edit")  return `<svg ${common}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>`;
    if (name === "prev")  return `<svg ${common}><path d="m15 18-6-6 6-6"/></svg>`;
    if (name === "next")  return `<svg ${common}><path d="m9 18 6-6-6-6"/></svg>`;
    if (name === "pages") return `<svg ${common}><rect x="3" y="5" width="6" height="6" rx="1"/><rect x="15" y="5" width="6" height="6" rx="1"/><rect x="3" y="13" width="6" height="6" rx="1"/><rect x="15" y="13" width="6" height="6" rx="1"/></svg>`;
    if (name === "stream") return `<svg ${common}><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h10"/></svg>`;
    if (name === "jump")  return `<svg ${common}><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/><path d="M5 6v12"/></svg>`;
    if (name === "sort")  return `<svg ${common}><path d="M7 6h10"/><path d="M5 12h14"/><path d="M9 18h6"/></svg>`;
    if (name === "dots")  return `<svg ${common}><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>`;
    return "";
  }

  // -----------------------------
  // Links modal (New feed only)
  // -----------------------------
