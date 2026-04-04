(function () {
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

  function ensureLinksModal() {
    let m = document.querySelector("[data-iad-linksmodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-linksmodal" data-iad-linksmodal hidden>
        <div class="iad-linksmodal-backdrop" data-iad-linksmodal-close></div>
        <div class="iad-linksmodal-sheet" role="dialog" aria-modal="true" aria-label="Links">
          <div class="iad-linksmodal-top">
            <div class="iad-linksmodal-title" data-iad-linksmodal-title>Links</div>
            <button class="iad-x" type="button" data-iad-linksmodal-close aria-label="Close">×</button>
          </div>
          <div class="iad-linksmodal-body" data-iad-linksmodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-linksmodal]");

    m.querySelectorAll("[data-iad-linksmodal-close]").forEach((x) => {
      x.addEventListener("click", () => m.setAttribute("hidden", ""));
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        m.setAttribute("hidden", "");
      }
    });

    return m;
  }

  function openLinksModal(urls, topicTitle) {
    const m = ensureLinksModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    const safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Links — ${topicTitle}` : "Links";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No links found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            const label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(label)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
  }

  // -----------------------------
  // Video viewer (Atrium-styled)
  // -----------------------------
  function lockPageScroll(lock) {
    const cls = "iad-modal-open";
    if (lock) document.documentElement.classList.add(cls);
    else document.documentElement.classList.remove(cls);
  }

  
  function openAttachmentsModal(urls, topicTitle) {
    const m = ensureLinksModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    const safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Attachments — ${topicTitle}` : "Attachments";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No attachments found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            const label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(label)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
    lockPageScroll(true);
  }

function ensureVideoModal() {
    let m = document.querySelector("[data-iad-videomodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-videomodal" data-iad-videomodal hidden>
        <div class="iad-videomodal-backdrop" data-iad-videomodal-close></div>

        <div class="iad-videomodal-sheet" role="dialog" aria-modal="true" aria-label="Video viewer">
          <div class="iad-videomodal-top">
            <div class="iad-videomodal-title" data-iad-videomodal-title>Video</div>
            <div class="iad-videomodal-actions">
              <a class="iad-videomodal-open" data-iad-videomodal-open target="_blank" rel="noopener noreferrer">Open ↗</a>
              <button class="iad-x" type="button" data-iad-videomodal-close aria-label="Close">×</button>
            </div>
          </div>

          <div class="iad-videomodal-body" data-iad-videomodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-videomodal]");

    m.querySelectorAll("[data-iad-videomodal-close]").forEach((x) => {
      x.addEventListener("click", () => closeVideoModal());
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        closeVideoModal();
      }
    });

    return m;
  }

  function closeVideoModal() {
    const m = document.querySelector("[data-iad-videomodal]");
    if (!m) return;

    const body = m.querySelector("[data-iad-videomodal-body]");
    if (body) body.innerHTML = ""; // stop playback
    m.setAttribute("hidden", "");
    lockPageScroll(false);
  }


  function decodeHtmlUrl(url) {
    const YT = window.IA_DISCUSS_YOUTUBE || {};
    if (YT.decodeHtmlUrl) return YT.decodeHtmlUrl(url);
    const raw = String(url || "").trim();
    if (!raw) return "";
    return raw
      .replace(/&amp;/gi, '&')
      .replace(/&#038;/gi, '&')
      .replace(/&#x26;/gi, '&');
  }

  function parseYouTubeMeta(url) {
    const YT = window.IA_DISCUSS_YOUTUBE || {};
    if (YT.parseYouTubeMeta) return YT.parseYouTubeMeta(url);
    return null;
  }

  function parsePeerTubeUuid(url) {
    try {
      const u = new URL(decodeHtmlUrl(url));
      const m = u.pathname.match(/\/videos\/watch\/([^\/\?\#]+)/i);
      if (m && m[1]) return m[1];
      return null;
    } catch (e) {
      return null;
    }
  }

  function buildVideoMeta(videoUrl) {
    const url = String(videoUrl || "").trim();
    if (!url) return null;

    const yt = parseYouTubeMeta(url);
    if (yt && (yt.id || yt.isPlaylist)) {
      const YT = window.IA_DISCUSS_YOUTUBE || {};
      const embedUrl = YT.buildEmbed ? YT.buildEmbed(yt) : "";
      const thumbUrl = YT.thumbUrl ? YT.thumbUrl(yt) : "";
      return {
        kind: yt.isPlaylist ? "youtube-playlist" : "youtube",
        url,
        isShort: !!yt.isShort,
        isPlaylist: !!yt.isPlaylist,
        embedUrl,
        thumbUrl,
        openUrl: YT.buildOpenUrl ? YT.buildOpenUrl(yt) : url
      };
    }

    const uuid = parsePeerTubeUuid(url);
    if (uuid) {
      try {
        const u = new URL(decodeHtmlUrl(url));
        const origin = u.origin;
        return {
          kind: "peertube",
          url,
          embedUrl: origin + "/videos/embed/" + encodeURIComponent(uuid),
          thumbUrl: origin + "/lazy-static/previews/" + encodeURIComponent(uuid) + ".jpg"
        };
      } catch (e) {}
    }

    const lu = url.toLowerCase();
    if (/\.(mp4|webm|mov)(\?|$)/i.test(lu)) {
      return { kind: "file", url, embedUrl: url, thumbUrl: "" };
    }

    return { kind: "iframe", url, embedUrl: url, thumbUrl: "" };
  }

  // Back-compat alias (older handler name)
  function detectVideoMeta(videoUrl) {
    return buildVideoMeta(videoUrl);
  }


  function openVideoModal(meta, titleText) {
    if (!meta) return;
    if (meta.kind === "youtube-playlist") {
      const targetUrl = meta.openUrl || meta.url || "";
      if (targetUrl) window.open(targetUrl, "_blank", "noopener,noreferrer");
      return;
    }

    const m = ensureVideoModal();
    const title = m.querySelector("[data-iad-videomodal-title]");
    const body = m.querySelector("[data-iad-videomodal-body]");
    const openLink = m.querySelector("[data-iad-videomodal-open]");

    title.textContent = titleText ? titleText : "Video";
    if (openLink) openLink.href = meta.url;

    if (meta.kind === "file") {
      body.innerHTML = `
        <div class="iad-video-stage">
          <div class="iad-video-frame${meta.isShort ? ' is-vertical' : ''}">
            <video class="iad-video-el" controls playsinline>
              <source src="${esc(meta.embedUrl)}" />
            </video>
          </div>
        </div>
      `;
    } else {
      body.innerHTML = `
        <div class="iad-video-stage">
          <div class="iad-video-frame${meta.isShort ? ' is-vertical' : ''}">
            <iframe
              class="iad-video-iframe"
              src="${esc(meta.embedUrl)}"
              title="Video"
              frameborder="0"
              allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen"
              allowfullscreen
              referrerpolicy="origin-when-cross-origin"></iframe>
          </div>
        </div>
      `;
    }

    m.removeAttribute("hidden");
    lockPageScroll(true);
  }

  // -----------------------------
  // Attachment media helpers (ADDED)
  // -----------------------------
  function isImageAtt(a) {
    const mime = String((a && a.mime) || "").toLowerCase();
    const url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("image/")) return true;
    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(url);
  }

  function isVideoAtt(a) {
    const mime = String((a && a.mime) || "").toLowerCase();
    const url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("video/")) return true;
    return /\.(mp4|webm|mov|m4v|ogg)(\?|#|$)/i.test(url);
  }

  function isAudioAtt(a) {
    const mime = String((a && a.mime) || "").toLowerCase();
    const url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("audio/")) return true;
    return /\.(mp3|m4a|aac|wav|wave|flac|oga|ogg|opus|weba)(\?|#|$)/i.test(url);
  }

  function agoraPlayerHTML(att) {
    const src = att && att.url ? String(att.url) : "";
    if (!src) return "";
    const filename = att && att.filename ? String(att.filename) : "audio";
    const logo = (window.IA_DISCUSS && IA_DISCUSS.assets && IA_DISCUSS.assets.agoraPlayerLogo)
      ? String(IA_DISCUSS.assets.agoraPlayerLogo)
      : "";
    return `
      <div class="iad-att-media">
        <div class="iad-audio-player" data-audio-src="${esc(src)}" data-audio-title="${esc(filename)}">
          <div class="iad-ap-head">
            ${logo ? `<img class="iad-ap-logo" src="${esc(logo)}" alt="" />` : ""}
            <div class="iad-ap-brand">Agora Player</div>
            <div class="iad-ap-file">${esc(filename)}</div>
          </div>
          <div class="iad-ap-main">
            <button type="button" class="iad-ap-play" data-ap-play aria-label="Play/Pause">▶</button>
            <div class="iad-ap-wave" data-ap-wave aria-hidden="true"></div>
            <div class="iad-ap-time"><span data-ap-cur>0:00</span><span class="iad-ap-sep">/</span><span data-ap-dur>0:00</span></div>
          </div>
          <input class="iad-ap-seek" data-ap-seek type="range" min="0" max="100" value="0" step="0.1" aria-label="Seek" />
          <audio class="iad-ap-audio" preload="metadata" src="${esc(src)}"></audio>
        </div>
      </div>
    `;
  }

  function attachmentInlineMediaHTML(item) {
    // Show uploaded media inline WITHOUT inserting into body:
    // - first video (if present)
    // - first audio (if present)
    // - then first image (if present)
    const atts = (item && item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    let firstVideo = null;
    let firstAudio = null;
    let firstImage = null;

    for (const a of atts) {
      if (!firstVideo && isVideoAtt(a)) firstVideo = a;
      if (!firstAudio && isAudioAtt(a)) firstAudio = a;
      if (!firstImage && isImageAtt(a)) firstImage = a;
      if (firstVideo && firstImage) break;
    }

    if (!firstVideo && !firstAudio && !firstImage) return "";

    const parts = [];
    if (firstVideo && firstVideo.url) {
      parts.push(`
        <div class="iad-att-media">
          <video class="iad-att-video" controls playsinline preload="none">
            <source src="${esc(String(firstVideo.url))}" />
          </video>
        </div>
      `);
    }
    if (firstAudio && firstAudio.url) {
      parts.push(agoraPlayerHTML(firstAudio));
    }
    if (firstImage && firstImage.url) {
      parts.push(`
        <div class="iad-att-media">
          <img class="iad-att-img" src="${esc(String(firstImage.url))}" alt="" loading="lazy" decoding="async" />
        </div>
      `);
    }

    return parts.join("");
  }

  // -----------------------------
  // Media UI (New feed only)
  // -----------------------------

  function mediaBlockHTML(item, view) {
    const media = (item && item.media) ? item.media : {};
    const urls = Array.isArray(media.urls) ? media.urls.filter(Boolean).map(String) : [];

    const videoUrl = media.video_url ? String(media.video_url) : "";
    const videoMeta = videoUrl ? buildVideoMeta(videoUrl) : null;

    const linkUrls = urls.filter((u) => u && u !== videoUrl);

    // In non-"new" feed views, we still show the first detected video (if any),
    // but keep the link strip/modal reserved for the main New feed.
    if (view !== "new") {
      if (!videoMeta) return "";
      const thumb = videoMeta && videoMeta.thumbUrl ? videoMeta.thumbUrl : "";
      const host = (function () {
        try { return new URL(videoMeta.url).hostname.replace(/^www\./, ""); }
        catch (e) { return ""; }
      })();
      return `
        <div class="iad-mediawrap">
          <div class="iad-media-row">
            <button
              type="button"
              class="iad-vthumb is-compact${videoMeta && videoMeta.isShort ? ' is-vertical' : ''}"
              data-iad-open-video
              data-video-url="${esc(videoMeta.url)}"
              aria-label="${videoMeta && videoMeta.isPlaylist ? 'Open playlist on YouTube' : 'Open video'}">
              <div class="iad-vthumb-inner">
                ${thumb
                  ? `<img class="iad-vthumb-img" src="${esc(thumb)}" alt="" loading="lazy" />`
                  : `<div class="iad-vthumb-fallback"></div>`
                }
                <div class="iad-vthumb-overlay">
                  <span class="iad-vthumb-play">▶</span>
                </div>
              </div>
            </button>
            <div class="iad-media-meta">
              <div class="iad-media-line"><span class="iad-media-tag">${videoMeta && videoMeta.isPlaylist ? 'playlist' : 'video'}</span><span class="iad-media-host">${esc(host || (videoMeta && videoMeta.isPlaylist ? "youtube.com" : "video"))}</span></div>
            </div>
          </div>
        </div>
      `;
    }

    if (!videoMeta && (!linkUrls || !linkUrls.length)) return "";

    const thumb = videoMeta && videoMeta.thumbUrl ? videoMeta.thumbUrl : "";

    const host = (function () {
      try { return new URL(videoMeta ? videoMeta.url : linkUrls[0]).hostname.replace(/^www\./, ""); }
      catch (e) { return ""; }
    })();

    function linkLabel(u) {
      try {
        const U = new URL(u);
        const h = U.hostname.replace(/^www\./, "");
        const p = (U.pathname && U.pathname !== "/") ? U.pathname.replace(/\/$/, "") : "";
        return (p && p.length <= 18) ? (h + p) : h;
      } catch (e) {
        return String(u || "link").slice(0, 24);
      }
    }

    const linkCount = linkUrls.length;

    return `
      <div class="iad-mediawrap">
        <div class="iad-media-row">
          ${videoMeta ? `
              <button
                type="button"
                class="iad-vthumb is-compact${videoMeta && videoMeta.isShort ? ' is-vertical' : ''}"
                data-iad-open-video
                data-video-url="${esc(videoMeta.url)}"
                aria-label="${videoMeta && videoMeta.isPlaylist ? 'Open playlist on YouTube' : 'Open video'}">
                <div class="iad-vthumb-inner">
                  ${thumb
                    ? `<img class="iad-vthumb-img" src="${esc(thumb)}" alt="" loading="lazy" />`
                    : `<div class="iad-vthumb-fallback"></div>`
                  }
                  <div class="iad-vthumb-overlay">
                    <span class="iad-vthumb-play">▶</span>
                  </div>
                </div>
              </button>
            ` : ""}

          <div class="iad-media-meta">
            ${videoMeta ? `<div class="iad-media-line"><span class="iad-media-tag">${videoMeta && videoMeta.isPlaylist ? 'playlist' : 'video'}</span><span class="iad-media-host">${esc(host || (videoMeta && videoMeta.isPlaylist ? "youtube.com" : "video"))}</span></div>` : ""}
            ${linkCount ? `
              <div class="iad-mediastrip">
                <button
                  type="button"
                  class="iad-pill is-muted"
                  data-iad-open-links
                  data-links-json="${esc(JSON.stringify(linkUrls))}">
                  Links (${linkCount})
                </button>
              </div>
            ` : ""}
          </div>
        </div>
      </div>
    `;
  }

  function attachmentPillsHTML(item) {
    const atts = (item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    const urls = atts.map((a) => (a && a.url ? String(a.url) : "")).filter(Boolean);
    const count = urls.length;

    // Single pill that opens a modal listing all attachments.
    return `
      <div class="iad-attachrow">
        <button
          type="button"
          class="iad-attachpill"
          data-iad-open-attachments
          data-attachments-json="${esc(JSON.stringify(urls))}">
          Attachments (${count})
        </button>
      </div>
    `;
  }


  function feedCard(item, view) {
    // Compatibility: some routes may still pass legacy view keys (e.g. "unread")
    // but should behave like Replies.
    const showLast = (view === 'replies' || view === 'unread');

    const username = showLast
      ? (item.last_poster_username || ("user#" + (item.last_poster_id || 0)))
      : (item.topic_poster_username || ("user#" + (item.topic_poster_id || 0)));

    const author = showLast
      ? (item.last_poster_display || item.last_poster_username || ("user#" + (item.last_poster_id || 0)))
      : (item.topic_poster_display || item.topic_poster_username || ("user#" + (item.topic_poster_id || 0)));

    const authorId = showLast
      ? (parseInt(item.last_poster_id || "0", 10) || 0)
      : (parseInt(item.topic_poster_id || "0", 10) || 0);

    const authorAvatar = showLast
      ? (item.last_poster_avatar_url || "")
      : (item.topic_poster_avatar_url || "");
    const me = currentUserId();

    const ago = timeAgo(item.last_post_time || item.topic_time);

    const forumId = parseInt(item.forum_id || "0", 10) || 0;
    const forumName = item.forum_name || "agora";

    const canEdit = !!(me && !showLast && authorId && me === authorId);

    // Read/unread glow
    let readClass = '';
    try {
      const isRead = (STATE && typeof STATE.isRead === 'function') ? !!STATE.isRead(item.topic_id) : false;
      readClass = isRead ? ' is-read' : ' is-unread';
    } catch (eR) {}

    const openPostId = showLast
      ? (parseInt(item.last_post_id || "0", 10) || parseInt(item.first_post_id || "0", 10) || 0)
      : (parseInt(item.first_post_id || "0", 10) || 0);

    // ✅ ADDED: inline uploaded media (video first, then image)
    // This does NOT replace your link-based media block; it only renders attachments.
    const inlineAttMedia = attachmentInlineMediaHTML(item);

    return `
      <article class="iad-card${readClass}"
        data-topic-id="${item.topic_id}"
        data-first-post-id="${esc(String(item.first_post_id || 0))}"
        data-last-post-id="${esc(String(item.last_post_id || 0))}"
        data-open-post-id="${esc(String(openPostId || 0))}"
        data-forum-id="${forumId}"
        data-forum-name="${esc(forumName)}"
        data-author-id="${esc(String(authorId))}">

        <div class="iad-card-main">
          <div class="iad-card-meta">
            ${authorAvatar ? `<img class="iad-uava" src="${esc(authorAvatar)}" alt="" />` : ""}
            <button
              type="button"
              class="iad-sub iad-agora-link"
              data-open-agora
              data-forum-id="${forumId}"
              data-forum-name="${esc(forumName)}"
              aria-label="Open agora ${esc(forumName)}"
              title="Open agora">
              agora/${esc(forumName)}
            </button>

            <span class="iad-dotsep">•</span>

            <button
              type="button"
              class="iad-user-link"
              data-open-user
              data-username="${esc(username)}"
              data-user-id="${esc(String(authorId))}"
              aria-label="Open profile ${esc(author)}"
              title="Open profile">
              ${esc(author)}
            </button>

            <span class="iad-dotsep">•</span>
            <span class="iad-time">${esc(ago)}</span>
          </div>

          <h3 class="iad-card-title" data-open-topic-title>${esc(item.topic_title || "")}</h3>

          ${inlineAttMedia ? `<div class="iad-attwrap">${inlineAttMedia}</div>` : ""}

          <div class="iad-card-excerpt" data-open-topic-excerpt>${item.excerpt_html || ""}</div>

          ${mediaBlockHTML(item, view)}

          ${attachmentPillsHTML(item)}

          <div class="iad-card-actions">
            <!-- Reply/comments icon (scrolls to comments) -->
            <button type="button" class="iad-iconbtn" data-open-topic-comments title="Open comments" aria-label="Open comments">
              ${ico("reply")}
            </button>

            <!-- Copy link -->
            <button type="button" class="iad-iconbtn" data-copy-topic-link title="Copy link" aria-label="Copy link">
              ${ico("link")}
            </button>

            <!-- Share to Connect -->
            <button type="button" class="iad-iconbtn" data-share-topic title="Share to Connect" aria-label="Share to Connect">
              ${ico("share")}
            </button>

            <!-- Last reply -->
            <button type="button" class="iad-pill is-muted" data-open-topic-lastreply title="Last reply" aria-label="Last reply">
              ${ico("last")} <span>Last reply</span>
            </button>

            ${canEdit ? `
              <button type="button" class="iad-iconbtn" data-edit-topic title="Edit (coming soon)" aria-label="Edit">
                ${ico("edit")}
              </button>
            ` : ""}

            <span class="iad-muted">${esc(String(item.views || 0))} views</span>
          </div>
        </div>
      </article>
    `;
  }


  async function loadFeed(view, forumId, offset, orderKey, page) {
    let tab = "new_posts";
    if (view === "noreplies") tab = "no_replies";
    if (view === "replies" || view === "unread") tab = "latest_replies";
    if (view === "mytopics") tab = "my_topics";
    if (view === "myreplies") tab = "my_replies";
    if (view === "myhistory") tab = "my_history";

    const payload = {
      tab,
      offset: offset || 0,
      forum_id: forumId || 0,
      order: orderKey || ''
    };

    if (page && parseInt(page, 10) > 0) {
      payload.page = parseInt(page, 10);
    }

    const res = await API.post("ia_discuss_feed", payload);

    if (!res || !res.success) {
      return {
        items: [],
        has_more: false,
        next_offset: (offset || 0),
        total_count: 0,
        total_pages: 0,
        current_page: page || 1,
        error: true
      };
    }
    return res.data || {};
  }

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

  function renderFeed(root, view, forumId) {
    const mount = root ? root.querySelector("[data-iad-view]") : null;
    renderFeedInto(mount, view, forumId);
  }

  window.IA_DISCUSS_UI_FEED = { renderFeed, renderFeedInto };

})();
