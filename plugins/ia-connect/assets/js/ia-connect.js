(function(){
  'use strict';

  const C = window.IA_CONNECT || {};
  const ajaxUrl = C.ajaxUrl || (window.ajaxurl || '/wp-admin/admin-ajax.php');
  const nonces = (C.nonces || {});
  const ME_ID = parseInt((C.me && C.me.id) ? C.me.id : 0, 10) || 0;
  const IS_ADMIN = !!(C.me && C.me.is_admin);
  const BLANK_AVA = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

  
  // Open profile requested by another subsystem (e.g., Discuss).
  // We intentionally navigate with query params so the server-side profile resolver + privacy checks remain authoritative.
  window.addEventListener('ia:open_profile', (ev) => {
    try {
      const d = (ev && ev.detail) ? ev.detail : {};
      const username = (d.username != null && String(d.username).trim() !== '') ? String(d.username).trim() : '';
      const userId = (d.user_id != null) ? parseInt(d.user_id, 10) || 0 : 0;

      const url = new URL(window.location.href);
      url.searchParams.set('tab', 'connect');
      if (username) url.searchParams.set('ia_profile_name', username);
      if (userId) url.searchParams.set('ia_profile', String(userId));

      // Avoid redundant reload if we're already at the same target.
      const cur = new URL(window.location.href);
      const same =
        cur.searchParams.get('tab') === 'connect' &&
        (username ? (cur.searchParams.get('ia_profile_name') === username) : true) &&
        (userId ? (cur.searchParams.get('ia_profile') === String(userId)) : true);

      if (!same) window.location.href = url.toString();
    } catch (e) {}
  });

const qs = (root, sel) => (root || document).querySelector(sel);
  const qsa = (root, sel) => Array.from((root || document).querySelectorAll(sel));

  function postForm(action, data, files){
    return new Promise((resolve,reject)=>{
      const fd = new FormData();
      fd.append('action', action);
      Object.keys(data||{}).forEach(k=>{
        if (data[k] !== undefined && data[k] !== null) fd.append(k, data[k]);
      });
      if (files && files.length){
        for (let i=0;i<files.length;i++) fd.append('files[]', files[i]);
      }
      const xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.onreadystatechange = ()=>{
        if (xhr.readyState !== 4) return;
        let json = null;
        try{ json = JSON.parse(xhr.responseText || '{}'); }catch(e){}
        if (xhr.status >= 200 && xhr.status < 300 && json){
          resolve(json);
        } else {
          reject(json || {success:false, data:{message:'Request failed'}});
        }
      };
      xhr.send(fd);
    });
  }

  // Compatibility: some Atrium modules bind to buttons and call a global postForm helper.
  // Provide it only if it doesn't already exist.
  try{
    if (typeof window.postForm !== 'function') window.postForm = postForm;
  }catch(_){ }

  function esc(s){
    return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]));
  }

  function linkMentions(text){
    const t = String(text||'');
    return t.replace(/(^|\s)@([a-zA-Z0-9_\-\.]{2,40})/g, (m,sp,u)=>{
      const url = '?tab=connect&ia_profile_name=' + encodeURIComponent(u);
      return sp + '<a class="iac-mention" href="' + url + '">@' + esc(u) + '</a>';
    });
  }

  // Extract URLs from plain text (best-effort). Used for rich previews/embeds.
  function extractUrls(text){
    const t = String(text||'');
    const m = t.match(/https?:\/\/[^\s<>()\[\]"']+/gi);
    return m ? m.map(s=>s.replace(/[\]\).,;!?]+$/,'')).filter(Boolean) : [];
  }

  function parseYouTubeId(u){
    try{
      const url = new URL(u);
      const host = (url.hostname||'').toLowerCase();
      if (host === 'youtu.be'){
        const id = (url.pathname||'').replace(/^\//,'').split('/')[0];
        return id || '';
      }
      if (host.endsWith('youtube.com') || host.endsWith('youtube-nocookie.com')){
        if (url.pathname.startsWith('/watch')){
          return url.searchParams.get('v') || '';
        }
        const m1 = url.pathname.match(/^\/shorts\/([^/?#]+)/i);
        if (m1) return m1[1];
        const m2 = url.pathname.match(/^\/embed\/([^/?#]+)/i);
        if (m2) return m2[1];
        const m3 = url.pathname.match(/^\/live\/([^/?#]+)/i);
        if (m3) return m3[1];
      }
    }catch(_){ }
    return '';
  }


  function iaConnectSiteTitle(){
    try {
      const raw = String(document.documentElement.getAttribute('data-iac-site-title') || '').trim();
      if (raw) return raw;
    } catch (e) {}
    try {
      const localized = window.IA_CONNECT && String(window.IA_CONNECT.siteTitle || '').trim();
      if (localized) return localized;
    } catch (e2) {}
    try {
      const brand = document.querySelector('#ia-atrium-shell .ia-atrium-brand');
      const text = brand ? String(brand.textContent || '').trim() : '';
      if (text) return text;
    } catch (e3) {}
    return 'IndieAgora';
  }

  function iaConnectSetMeta(name, value, attr){
    try{
      if (!value) return;
      const sel = attr === 'property' ? 'meta[property="' + name + '"]' : 'meta[name="' + name + '"]';
      let el = document.head ? document.head.querySelector(sel) : null;
      if (!el && document.head){
        el = document.createElement('meta');
        el.setAttribute(attr === 'property' ? 'property' : 'name', name);
        document.head.appendChild(el);
      }
      if (el) el.setAttribute('content', value);
    }catch(_){ }
  }

  function iaConnectIsActiveSurface(){
    try{
      const shell = document.querySelector('#ia-atrium-shell');
      const active = shell ? String(shell.getAttribute('data-active-tab') || '').trim().toLowerCase() : '';
      if (active) return active === 'connect';
    }catch(_2){ }
    try{
      const url = new URL(location.href);
      const tab = String(url.searchParams.get('tab') || '').trim().toLowerCase();
      if (tab) return tab === 'connect';
    }catch(_){ }
    try{
      const panel = document.querySelector('#ia-atrium-shell .ia-panel[data-panel="connect"]');
      if (panel) return panel.classList.contains('active') || panel.getAttribute('aria-hidden') === 'false';
    }catch(_3){ }
    return false;
  }

  function iaConnectApplyPageTitle(rawTitle){
    try{
      if (!iaConnectIsActiveSurface()) return;
      const site = iaConnectSiteTitle();
      const clean = String(rawTitle || '').trim();
      const full = clean ? (site ? (clean + ' | ' + site) : clean) : site;
      if (!full) return;
      document.title = full;
      try{ document.documentElement.setAttribute('data-iac-site-title', site || ''); }catch(_){ }
      iaConnectSetMeta('og:title', full, 'property');
      iaConnectSetMeta('twitter:title', full, 'name');
    }catch(_){ }
  }

  function iaConnectResolveClientTitle(root, postModal){
    try{
      const url = new URL(location.href);
      const pid = parseInt(url.searchParams.get('ia_post') || '0', 10) || 0;
      const profileParam = String(url.searchParams.get('ia_profile_name') || '').trim();

      if (pid > 0){
        let modalPostId = 0;
        try {
          modalPostId = parseInt((postModal && postModal.getAttribute('data-iac-open-post-id')) || '0', 10) || 0;
        } catch (_modalIdErr) {}
        if (modalPostId && modalPostId === pid){
          const selectors = [
            '.iac-card-title',
            '.iac-discuss-sharelink strong',
            '.iac-discuss-sharelink',
            '.iac-discuss-embed .iad-modal-title',
            '.iac-card-text'
          ];
          for (let i = 0; i < selectors.length; i++){
            const el = postModal ? postModal.querySelector(selectors[i]) : null;
            const text = el ? String(el.textContent || '').trim() : '';
            if (text) return (selectors[i] === '.iac-card-text' && text.length > 80) ? (text.slice(0, 80).trim() + '…') : text;
          }
        }
        return 'Connect';
      }

      const subEl = root ? root.querySelector('.iac-sub') : null;
      const sub = subEl ? String(subEl.textContent || '').trim().replace(/^@/, '') : '';
      const nameEl = root ? root.querySelector('.iac-name') : null;
      const name = nameEl ? String(nameEl.textContent || '').trim() : '';
      if (profileParam){
        if (sub && sub.toLowerCase() === profileParam.toLowerCase() && name) return name;
        return 'Connect';
      }
      if (name) return name;
      return 'Connect';
    }catch(_){ }
    return 'Connect';
  }

  function iaConnectRefreshPageTitle(root, postModal){
    const run = ()=> iaConnectApplyPageTitle(iaConnectResolveClientTitle(root, postModal));
    try{ window.requestAnimationFrame(run); }catch(_){ setTimeout(run, 0); }
    setTimeout(run, 60);
    setTimeout(run, 220);
  }

  function iaConnectInstallTitleObservers(root, postModal){
    let timer = 0;
    const queue = ()=>{
      clearTimeout(timer);
      timer = setTimeout(()=> iaConnectRefreshPageTitle(root, postModal), 20);
    };

    try{
      if (!window.__iacTitleHistoryWrapped){
        window.__iacTitleHistoryWrapped = 1;
        const origPush = history.pushState;
        const origReplace = history.replaceState;
        history.pushState = function(){
          const out = origPush.apply(this, arguments);
          queue();
          return out;
        };
        history.replaceState = function(){
          const out = origReplace.apply(this, arguments);
          queue();
          return out;
        };
        window.addEventListener('popstate', queue);
      }
    }catch(_){ }

    try{
      if (root && !root.__iacTitleObserver){
        const mo = new MutationObserver(queue);
        mo.observe(root, {subtree:true, childList:true, characterData:true});
        root.__iacTitleObserver = mo;
      }
    }catch(_){ }

    try{
      if (postModal && !postModal.__iacTitleObserver){
        const mo2 = new MutationObserver(queue);
        mo2.observe(postModal, {subtree:true, childList:true, characterData:true, attributes:true});
        postModal.__iacTitleObserver = mo2;
      }
    }catch(_){ }

    queue();
    setTimeout(queue, 120);
    setTimeout(queue, 400);
  }


  function parseVimeoId(u){
    try{
      const url = new URL(u);
      const host = (url.hostname||'').toLowerCase();
      if (!host.endsWith('vimeo.com')) return '';
      const m = (url.pathname||'').match(/\/(\d{6,})/);
      return m ? m[1] : '';
    }catch(_){ }
    return '';
  }

  function parsePeerTubeEmbed(u){
    // Support common PeerTube patterns:
    //   /videos/watch/<uuid>
    //   /w/<shortId>
    //   /videos/embed/<id>
    try{
      const url = new URL(u);
      const path = url.pathname || '';
      if (/\/videos\/embed\//i.test(path)){
        return url.origin + path;
      }
      const m1 = path.match(/\/videos\/watch\/([^/?#]+)/i);
      if (m1) return url.origin + '/videos/embed/' + m1[1];
      const m2 = path.match(/\/w\/([^/?#]+)/i);
      if (m2) return url.origin + '/videos/embed/' + m2[1];
    }catch(_){ }
    return '';
  }

  function renderVideoEmbedsFromText(text){
    const urls = extractUrls(text);
    if (!urls.length) return '';
    const embeds = [];
    const seen = new Set();

    for (let i=0;i<urls.length && embeds.length<3;i++){
      const u = urls[i];
      if (seen.has(u)) continue;
      seen.add(u);

      const yt = parseYouTubeId(u);
      if (yt){
        const src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(yt);
        embeds.push(
          '<div class="iac-video-embed" data-iac-embed>' +
            '<iframe src="' + esc(src) + '" loading="lazy" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>' +
          '</div>'
        );
        continue;
      }

      const vm = parseVimeoId(u);
      if (vm){
        const src = 'https://player.vimeo.com/video/' + encodeURIComponent(vm);
        embeds.push(
          '<div class="iac-video-embed" data-iac-embed>' +
            '<iframe src="' + esc(src) + '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>' +
          '</div>'
        );
        continue;
      }

      const pt = parsePeerTubeEmbed(u);
      if (pt){
        embeds.push(
          '<div class="iac-video-embed" data-iac-embed>' +
            '<iframe src="' + esc(pt) + '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>' +
          '</div>'
        );
        continue;
      }

      // Direct video file link in text.
      if (/\.(mp4|webm|ogg)(\?|#|$)/i.test(u)){
        embeds.push(
          '<div class="iac-video-embed is-file" data-iac-embed>' +
            '<video src="' + esc(u) + '" controls playsinline preload="metadata"></video>' +
          '</div>'
        );
        continue;
      }
    }

    return embeds.length ? ('<div class="iac-embeds">' + embeds.join('') + '</div>') : '';
  }

  function escapeRegExp(s){
    return String(s||'').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function isEmbeddableVideoUrl(u){
    if (!u) return false;
    if (parseYouTubeId(u)) return true;
    if (parseVimeoId(u)) return true;
    if (parsePeerTubeEmbed(u)) return true;
    if (/\.(mp4|webm|ogg)(\?|#|$)/i.test(u)) return true;
    return false;
  }

  function stripEmbeddableVideoUrls(text){
    let t = String(text||'');
    const urls = extractUrls(t);
    if (!urls.length) return t;
    const seen = new Set();
    urls.forEach(u=>{
      if (!u || seen.has(u)) return;
      seen.add(u);
      if (!isEmbeddableVideoUrl(u)) return;
      const re = new RegExp('(?:^|\\s)'+ escapeRegExp(u) + '(?=\\s|$)', 'g');
      t = t.replace(re, (m)=>{
        // Preserve any leading whitespace captured by (?:^|\s)
        return m[0] === ' ' || m[0] === '\n' || m[0] === '\t' ? m[0] : '';
      });
      // Also remove any remaining direct occurrences (e.g. punctuation-adjacent)
      t = t.replace(new RegExp(escapeRegExp(u), 'g'), '');
    });
    // Tidy whitespace/newlines after removals.
    t = t.replace(/[ \t]+\n/g, '\n');
    t = t.replace(/\n{3,}/g, '\n\n');
    return t.trim();
  }


  function renderLinkCard(u){
    try{
      const url = new URL(String(u||''));
      const host = String(url.hostname || '').replace(/^www\./i,'') || String(u||'');
      const path = decodeURIComponent(String(url.pathname || '/')).replace(/\/+$/,'') || '/';
      const query = String(url.search || '').trim();
      let title = path === '/' ? host : path.replace(/^\//,'').replace(/[\-_]+/g,' ').replace(/\//g,' / ').trim();
      if (!title) title = host;
      if (query) title += ' ' + query;
      title = title.replace(/\s+/g,' ').trim();
      if (title.length > 92) title = title.slice(0, 89).trim() + '…';
      return (
        '<a class="iac-linkcard" href="' + esc(url.toString()) + '" target="_blank" rel="noopener noreferrer nofollow">' +
          '<span class="iac-linkcard-host">' + esc(host) + '</span>' +
          '<span class="iac-linkcard-title">' + esc(title) + '</span>' +
          '<span class="iac-linkcard-url">' + esc(url.toString()) + '</span>' +
        '</a>'
      );
    }catch(_){ }
    return '';
  }

  function renderLinkCardsFromText(text){
    const urls = extractUrls(text);
    if (!urls.length) return '';
    const seen = new Set();
    const cards = [];
    for (let i=0;i<urls.length && cards.length<3;i++){
      const u = urls[i];
      if (!u || seen.has(u)) continue;
      seen.add(u);
      if (isEmbeddableVideoUrl(u)) continue;
      const card = renderLinkCard(u);
      if (card) cards.push(card);
    }
    return cards.length ? ('<div class="iac-linkcards">' + cards.join('') + '</div>') : '';
  }

  function profileUrlFrom(u){
    const url = new URL(location.href);
    url.searchParams.set('tab','connect');
    if (u && u.phpbb_user_id) url.searchParams.set('ia_profile', String(u.phpbb_user_id));
    if (u && u.username) url.searchParams.set('ia_profile_name', String(u.username));
    return url.toString();
  }

  function userLinkHtml(label, phpbbId, username, extraClass){
    const pid = parseInt(phpbbId||0,10)||0;
    const uname = String(username||'');
    const cls = extraClass ? (' ' + String(extraClass)) : '';
    if (!pid && !uname) return '<span class="iac-user-name">' + esc(label||'User') + '</span>';
    return '<button type="button" class="iac-userlink' + cls + '" data-iac-userlink data-phpbb="' + esc(pid) + '" data-username="' + esc(uname) + '">' + esc(label||'User') + '</button>';
  }

  function hydrateMentions(_mount){
    // Placeholder for future mention interactions.
  }

  // Small modal-style toast popup (non-blocking)
  function showToast(message, ms){
    const ttl = typeof ms === 'number' ? ms : 2200;
    let wrap = document.querySelector('.iac-toast-wrap');
    if (!wrap){
      wrap = document.createElement('div');
      wrap.className = 'iac-toast-wrap';
      // Inline fallback styling (helps if cached CSS prevents new rules applying)
      wrap.style.position = 'fixed';
      wrap.style.left = '50%';
      wrap.style.bottom = '18px';
      wrap.style.transform = 'translateX(-50%)';
      // Must sit above all Atrium/modals.
      wrap.style.zIndex = '999999';
      wrap.style.display = 'flex';
      wrap.style.flexDirection = 'column';
      wrap.style.gap = '10px';
      wrap.style.pointerEvents = 'none';
      document.body.appendChild(wrap);
    }
    const t = document.createElement('div');
    t.className = 'iac-toast';
    t.style.pointerEvents = 'auto';
    t.style.minWidth = 'min(420px,92vw)';
    t.style.background = 'rgba(0,0,0,.88)';
    t.style.border = '1px solid rgba(255,255,255,.12)';
    t.style.borderRadius = '14px';
    t.style.padding = '10px 12px';
    t.style.boxShadow = '0 18px 50px rgba(0,0,0,.55)';
    t.style.display = 'flex';
    t.style.alignItems = 'center';
    t.style.gap = '10px';
    t.innerHTML = '<div class="iac-toast-txt"></div><button type="button" class="iac-toast-x" aria-label="Close">×</button>';
    t.querySelector('.iac-toast-txt').textContent = String(message||'');
    t.querySelector('.iac-toast-txt').style.color = '#fff';
    t.querySelector('.iac-toast-txt').style.fontSize = '14px';
    t.querySelector('.iac-toast-txt').style.lineHeight = '1.3';
    const x = t.querySelector('.iac-toast-x');
    x.style.background = 'rgba(255,255,255,.08)';
    x.style.border = '1px solid rgba(255,255,255,.12)';
    x.style.color = '#fff';
    x.style.borderRadius = '10px';
    x.style.padding = '6px 10px';
    x.style.cursor = 'pointer';
    x.style.fontSize = '14px';
    const kill = ()=>{ if (t && t.parentNode) t.parentNode.removeChild(t); };
    t.querySelector('.iac-toast-x').addEventListener('click', (e)=>{ e.preventDefault(); kill(); });
    wrap.appendChild(t);
    window.setTimeout(kill, ttl);
  }

  // Confirm modal (non-blocking replacement for window.confirm)
  let __iacConfirmResolve = null;
  function openConfirm(message, title, okLabel, cancelLabel){
    const modal = qs(document, '[data-iac-confirm]');
    if (!modal){
      return Promise.resolve(!!window.confirm(message || 'Are you sure?'));
    }
    const titleEl = qs(modal, '#iac-confirm-title');
    const msgEl = qs(modal, '[data-iac-confirm-msg]');
    const okBtn = qs(modal, '[data-iac-confirm-ok]');
    const cancelBtns = qsa(modal, '[data-iac-confirm-cancel]');

    if (titleEl) titleEl.textContent = title || 'Confirm';
    if (msgEl) msgEl.textContent = message || 'Are you sure?';
    if (okBtn) okBtn.textContent = okLabel || 'OK';
    cancelBtns.forEach(b=>{
      if (b && b.classList && b.classList.contains('iac-confirm-btn')) b.textContent = cancelLabel || 'Cancel';
    });

    modal.hidden = false;
    // Focus OK for keyboard flow.
    try{ okBtn && okBtn.focus && okBtn.focus(); }catch(_){ }

    return new Promise((resolve)=>{
      __iacConfirmResolve = resolve;

      const close = (val)=>{
        if (!modal.hidden) modal.hidden = true;
        __iacConfirmResolve = null;
        cleanup();
        resolve(!!val);
      };

      const onOk = (e)=>{ e.preventDefault(); close(true); };
      const onCancel = (e)=>{ e.preventDefault(); close(false); };
      const onKey = (e)=>{ if (e.key === 'Escape') close(false); };

      const cleanup = ()=>{
        okBtn && okBtn.removeEventListener('click', onOk);
        cancelBtns.forEach(b=>b && b.removeEventListener('click', onCancel));
        document.removeEventListener('keydown', onKey);
      };

      okBtn && okBtn.addEventListener('click', onOk);
      cancelBtns.forEach(b=>b && b.addEventListener('click', onCancel));
      document.addEventListener('keydown', onKey);
    });
  }

  function mountInlineEditor(container, fields, onSave){
    // fields: [{type:'input'|'textarea', value:'', placeholder:'', key:'title'|'body'}]
    if (!container) return null;
    if (container.querySelector('.iac-inline-editor')) return null;

    const wrap = document.createElement('div');
    wrap.className = 'iac-inline-editor';

    const inputs = {};
    fields.forEach(f=>{
      let el = null;
      if (f.type === 'input'){
        el = document.createElement('input');
        el.type = 'text';
      } else {
        el = document.createElement('textarea');
        el.rows = 2;
      }
      el.value = String(f.value||'');
      if (f.placeholder) el.placeholder = String(f.placeholder);
      el.setAttribute('data-iac-inline-key', String(f.key||''));
      wrap.appendChild(el);
      inputs[String(f.key||'')] = el;
    });

    const actions = document.createElement('div');
    actions.className = 'iac-inline-actions';
    actions.innerHTML =
      '<button type="button" class="iac-inline-cancel">Cancel</button>' +
      '<button type="button" class="iac-inline-save is-primary">Save</button>';
    wrap.appendChild(actions);

    const btnSave = actions.querySelector('.iac-inline-save');
    const btnCancel = actions.querySelector('.iac-inline-cancel');

    const cleanup = ()=>{ try{ wrap.remove(); }catch(_){} };
    const cancel = ()=>{ cleanup(); };
    const save = async ()=>{
      const payload = {};
      Object.keys(inputs).forEach(k=> payload[k] = String(inputs[k].value||'').trim());
      btnSave && (btnSave.disabled = true);
      try{
        await onSave(payload);
        cleanup();
      }finally{
        btnSave && (btnSave.disabled = false);
      }
    };

    btnCancel && btnCancel.addEventListener('click', (e)=>{ e.preventDefault(); cancel(); });
    btnSave && btnSave.addEventListener('click', (e)=>{ e.preventDefault(); save(); });

    // Insert directly after the container's existing text/title block.
    container.appendChild(wrap);
    // Focus first input
    const first = wrap.querySelector('input,textarea');
    try{ first && first.focus && first.focus(); }catch(_){ }
    return wrap;
  }

  function mount(){
    const root = qs(document, '.iac-profile');
    if (!root) return;

    const viewer = qs(document, '[data-iac-viewer]');
    const viewerBody = qs(document, '[data-iac-viewer-body]');

    // Post modal
    const postModal = qs(document, '[data-iac-post-modal]');
    iaConnectInstallTitleObservers(root, postModal);
    const postBody  = qs(document, '[data-iac-post-body]');
    const postComms = qs(document, '[data-iac-post-comments]');
    const postCommentInput = qs(document, '[data-iac-post-comment]');
    const postCommentSend  = qs(document, '[data-iac-post-comment-send]');
    const postCopyBtn = qs(document, '[data-iac-post-copy]');
    const postShareBtn = qs(document, '[data-iac-post-share]');
    const postFollowBtn = qs(document, '[data-iac-post-follow]');
	    const postEditBtn = qs(document, '[data-iac-post-edit]');
	    const postDeleteBtn = qs(document, '[data-iac-post-delete]');
    const postJumpCommentsBtn = qs(document, '[data-iac-post-jump-comments]');
    const postCommentCountEl = qs(document, '[data-iac-post-comment-count]');

    // Share modal
    const shareModal = qs(document, '[data-iac-share-modal]');
    const shareSearch = qs(document, '[data-iac-share-search]');
    const shareResults = qs(document, '[data-iac-share-results]');
    const sharePicked = qs(document, '[data-iac-share-picked]');
    const shareSend = qs(document, '[data-iac-share-send]');
    const shareSelf = qs(document, '[data-iac-share-self]');

    let currentOpenPostId = 0;
    let currentOpenPostFollowing = false;
	    let currentOpenPostCommentCount = 0;
	    let currentOpenPostCanEdit = false;
    let shareForPostId = 0;
    let sharePickedUsers = [];
    let lastUrlBeforeModal = '';

    function openViewer(payload){
      if (!viewer || !viewerBody) return;
      viewerBody.innerHTML = '';
      const src = String(payload || '');

      // Try infer type
      const isVideo = /\.(mp4|webm|ogg)(\?|#|$)/i.test(src);
      const isImage = /\.(png|jpe?g|gif|webp|avif)(\?|#|$)/i.test(src);
      const isPdf   = /\.(pdf)(\?|#|$)/i.test(src);

      if (isVideo){
        const v = document.createElement('video');
        v.src = src;
        v.controls = true;
        v.playsInline = true;
        viewerBody.appendChild(v);
      } else if (isPdf){
        const iframe = document.createElement('iframe');
        iframe.src = src;
        viewerBody.appendChild(iframe);
      } else if (isImage || src){
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        viewerBody.appendChild(img);
      }

      viewer.hidden = false;
    }

    function setModalCommentCount(n){
      currentOpenPostCommentCount = parseInt(n||0,10)||0;
      if (postCommentCountEl) postCommentCountEl.textContent = String(currentOpenPostCommentCount);
    }

    function setModalFollowState(following){
      currentOpenPostFollowing = !!following;
      if (postFollowBtn){
        postFollowBtn.setAttribute('aria-label', currentOpenPostFollowing ? 'Unfollow' : 'Follow');
        postFollowBtn.setAttribute('title', currentOpenPostFollowing ? 'Unfollow' : 'Follow');
        postFollowBtn.setAttribute('data-following', currentOpenPostFollowing ? '1' : '0');
        postFollowBtn.classList.toggle('is-following', currentOpenPostFollowing);
      }
    }

    function closeViewer(){
      if (!viewer || !viewerBody) return;
      viewer.hidden = true;
      viewerBody.innerHTML = '';
    }

	    // Fullscreen post modal composer layout helpers (iOS-friendly).
	    // iOS Safari can flicker/vanish with position:sticky inside overflow scroll. We keep the composer fixed
	    // and measure its height to pad the scroll area so the last comment is always visible.
	    let modalComposerCleanup = null;
	    function setupModalComposerLayout(){
	      if (!postModal) return;
	      const compose = postModal.querySelector('.iac-modal-compose');
	      const sheet = postModal.querySelector('.iac-modal-sheet');
	      const comms = postModal.querySelector('.iac-modal-comments');
	      if (!compose) return;
	      const root = postModal;
      const EXTRA_PAD = 260;
      const MIN_PAD = 320;
	      let raf = 0;
	      const applyNow = ()=>{
	        raf = 0;
	        try{
	          const h = compose.offsetHeight || 0;
	          // Always set a value so CSS calc() never becomes invalid.
	          root.style.setProperty('--iac-compose-h', String(Math.max(0, h)) + 'px');
	          // Also hard-apply padding so browser differences in scroll containers don't hide the last comment.
          const padFixed = Math.max(Math.max(0, h) + EXTRA_PAD, MIN_PAD);
          if (sheet) sheet.style.paddingBottom = String(padFixed) + 'px';
          if (comms) comms.style.paddingBottom = String(padFixed) + 'px';
	        }catch(_){ }
	        try{
	          let bot = 0;
	          const vv = window.visualViewport;
	          if (vv){
	            // Distance from layout viewport bottom to visual viewport bottom.
	            bot = Math.max(0, (window.innerHeight - vv.height - vv.offsetTop));
	          }
	          root.style.setProperty('--iac-vvbot', String(bot) + 'px');
	          // Keep fixed composer above the keyboard/visualViewport and keep scroll padding in sync.
	          try{
	            const h2 = compose.offsetHeight || 0;
	            const pad2Fixed = Math.max(Math.max(0, h2) + Math.max(0, bot) + EXTRA_PAD, MIN_PAD);
				    if (sheet) sheet.style.paddingBottom = String(pad2Fixed) + 'px';
				    if (comms) comms.style.paddingBottom = String(pad2Fixed) + 'px';
	          }catch(_2){ }
	        }catch(_){ }
	      };
	      const schedule = ()=>{ if (raf) return; raf = window.requestAnimationFrame(applyNow); };

	      // Initial measure after render.
	      setTimeout(schedule, 0);

	      // Resize / keyboard / viewport changes.
	      window.addEventListener('resize', schedule, {passive:true});
	      const vv = window.visualViewport;
	      if (vv){
	        vv.addEventListener('resize', schedule, {passive:true});
	        vv.addEventListener('scroll', schedule, {passive:true});
	      }
	      // Composer height can change as the textarea grows.
	      if (postCommentInput){
	        postCommentInput.addEventListener('input', schedule, {passive:true});
	      }

	      modalComposerCleanup = ()=>{
	        try{ window.removeEventListener('resize', schedule); }catch(_){ }
	        try{
	          const vv2 = window.visualViewport;
	          if (vv2){
	            vv2.removeEventListener('resize', schedule);
	            vv2.removeEventListener('scroll', schedule);
	          }
	        }catch(_){ }
	        try{ postCommentInput && postCommentInput.removeEventListener('input', schedule); }catch(_){ }
	        try{ if (raf) window.cancelAnimationFrame(raf); }catch(_){ }
	        raf = 0;
	        modalComposerCleanup = null;
	      };
	    }

    qsa(document, '[data-iac-viewer-close]').forEach(el=>{
      el.addEventListener('click', (e)=>{ e.preventDefault(); closeViewer(); });
    });

    function openPostModal(pid, pushState, commentId){
      if (!postModal) return;
	      // Ensure previous listeners are removed (SPA re-open safety).
	      try{ if (typeof modalComposerCleanup === 'function') modalComposerCleanup(); }catch(_){ }
      currentOpenPostId = pid;
      try { if (postModal) postModal.setAttribute('data-iac-open-post-id', String(pid)); } catch (ePid) {}
      setModalCommentCount(0);
      setModalFollowState(false);
	      setModalEditButtons(false);
      try { if (postCommentSend) postCommentSend.setAttribute('data-post-id', String(pid)); } catch (e) {}
      postModal.hidden = false;
      document.documentElement.style.overflow = 'hidden';
      // Composer is in-flow (non-fixed) to avoid iOS Safari rendering issues.

      iaConnectApplyPageTitle('Connect');

      if (pushState){
        try{
          lastUrlBeforeModal = location.href;
          const u = new URL(location.href);
          u.searchParams.set('tab','connect');
          u.searchParams.set('ia_post', String(pid));
          if (commentId && parseInt(commentId,10)>0) { u.searchParams.set('ia_comment', String(parseInt(commentId,10))); } else { u.searchParams.delete('ia_comment'); }
          history.pushState({iac_post: pid}, '', u.toString());
          iaConnectRefreshPageTitle(root, postModal);
        }catch(_){ }
      }

      if (postBody) postBody.innerHTML = '<div class="iac-card" style="margin:0">Loading…</div>';
      if (postComms) postComms.innerHTML = '';
      if (postCommentInput) postCommentInput.value = '';

      loadPostForModal(pid);
      iaConnectRefreshPageTitle(root, postModal);
    }

	    function setModalEditButtons(canEdit){
	      currentOpenPostCanEdit = !!canEdit;
	      if (postEditBtn) postEditBtn.hidden = !currentOpenPostCanEdit;
	      if (postDeleteBtn) postDeleteBtn.hidden = !currentOpenPostCanEdit;
	    }

    function closePostModal(popState){
      if (!postModal) return;
	      try{ if (typeof modalComposerCleanup === 'function') modalComposerCleanup(); }catch(_){ }
	      postModal.hidden = true;
	      document.documentElement.style.overflow = '';
      currentOpenPostId = 0;
      try { if (postModal) postModal.removeAttribute('data-iac-open-post-id'); } catch (ePid2) {}
	      setModalEditButtons(false);
	      setModalEditButtons(false);
      if (!popState){
        try{
          if (lastUrlBeforeModal) history.pushState({}, '', lastUrlBeforeModal);
          else {
            const u = new URL(location.href);
            u.searchParams.delete('ia_post');
            history.pushState({}, '', u.toString());
          }
        }catch(_){ }
      }
      iaConnectRefreshPageTitle(root, postModal);
    }

    function openShareModal(pid){
      if (!shareModal) return;
      shareForPostId = pid;
      sharePickedUsers = [];
      if (sharePicked) sharePicked.innerHTML = '';
      if (shareResults) shareResults.innerHTML = '';
      if (shareSearch) shareSearch.value = '';
      if (shareSend) shareSend.disabled = true;
      shareModal.hidden = false;
    }

    function closeShareModal(){
      if (!shareModal) return;
      shareModal.hidden = true;
      shareForPostId = 0;
      sharePickedUsers = [];
    }

    qsa(document,'[data-iac-share-close]').forEach(el=>{
      el.addEventListener('click', (e)=>{ e.preventDefault(); closeShareModal(); });
    });

    function renderPicked(){
      if (!sharePicked) return;
      sharePicked.innerHTML = sharePickedUsers.map(u=>
        '<span class="iac-chip" data-key="' + esc(u.phpbb_user_id||u.wp_user_id||0) + '">' +
          esc(u.display||u.username||'User') +
          '<button type="button" class="iac-chip-x" data-iac-chip-x aria-label="Remove">×</button>' +
        '</span>'
      ).join('');
      // Enable when something is selected; if no valid targets, we'll show an error on send.
      if (shareSend) shareSend.disabled = sharePickedUsers.length === 0;
    }

    if (sharePicked){
      sharePicked.addEventListener('click', (e)=>{
        const x = e.target.closest('[data-iac-chip-x]');
        if (!x) return;
        const chip = e.target.closest('.iac-chip');
        const key = parseInt(chip?.getAttribute('data-key')||'0',10)||0;
        sharePickedUsers = sharePickedUsers.filter(u => ((u.phpbb_user_id||u.wp_user_id||0) !== key));
        renderPicked();
      });
    }

    function addPicked(user){
      const id = user?.phpbb_user_id || user?.wp_user_id || 0;
      if (!id && !user?.username) return;
      if (sharePickedUsers.some(u => ((u.phpbb_user_id||u.wp_user_id||0) === id && id) || (user?.username && (u.username||'') === user.username))) return;
      sharePickedUsers.push(user);
      renderPicked();
    }

    const deb = (fn, ms)=>{ let t=0; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), ms); }; };
    const doShareSearch = deb(async ()=>{
      if (!shareResults) return;
      const q = String(shareSearch?.value||'').trim();
      if (!q){ shareResults.innerHTML=''; return; }
      try{
        const r = await postForm('ia_connect_mention_suggest', {nonce: nonces.mention_suggest||'', q});
        if (!r || !r.success) throw r;
        const rows = r.data.results || [];
        const blankAva = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        shareResults.innerHTML = rows.map(u=>
          '<div class="iac-share-row" data-iac-share-pick data-phpbb="' + esc(u.phpbb_user_id||0) + '" data-wp="' + esc(u.wp_user_id||0) + '" data-username="' + esc(u.username||'') + '" data-display="' + esc(u.display||u.username||'User') + '">' +
            '<img class="iac-share-ava" src="' + esc(u.avatarUrl||blankAva) + '" alt="" />' +
            '<div class="iac-share-name">' + esc(u.display||u.username||'User') + '</div>' +
          '</div>'
        ).join('');
      } catch(_){
        shareResults.innerHTML = '';
      }
    }, 180);

    if (shareSearch){
      shareSearch.addEventListener('input', doShareSearch);
    }

    if (shareResults){
      const pickShareUser = (e)=>{
        const row = e.target.closest('[data-iac-share-pick]');
        if (!row) return;
        e.preventDefault();
        const phpbb = parseInt(row.getAttribute('data-phpbb')||'0',10)||0;
        const wpId = parseInt(row.getAttribute('data-wp')||'0',10)||0;
        const uname = row.getAttribute('data-username') || '';
        const disp = row.getAttribute('data-display') || (uname || 'User');
        addPicked({phpbb_user_id: phpbb, wp_user_id: wpId, username: uname, display: disp});
      };
      // Desktop + mobile
      shareResults.addEventListener('mousedown', pickShareUser);
      shareResults.addEventListener('click', pickShareUser);
      shareResults.addEventListener('touchstart', pickShareUser, {passive:false});
    }

    if (shareSelf){
      shareSelf.addEventListener('click', async ()=>{
        if (!shareForPostId) return;
        try{
          const r = await postForm('ia_connect_post_share', {nonce: nonces.post_share||'', post_id: shareForPostId, share_to_self: 1});
          if (!r || !r.success) throw r;
          showToast('Shared to your wall');
          closeShareModal();
        } catch(e){
          alert(e?.data?.message || 'Share failed');
        }
      });
    }

    if (shareSend){
      shareSend.addEventListener('click', async ()=>{
        if (!shareForPostId) return;
        const targets = sharePickedUsers.map(u=>u.phpbb_user_id||0).filter(Boolean);
        if (!targets.length) { alert('Selected user(s) are not linked yet.'); return; }
        shareSend.disabled = true;
        try{
          const r = await postForm('ia_connect_post_share', {nonce: nonces.post_share||'', post_id: shareForPostId, targets});
          if (!r || !r.success) throw r;
          showToast('Shared to ' + (r.data.created_count||targets.length) + ' wall(s)');
          closeShareModal();
        } catch(e){
          alert(e?.data?.message || 'Share failed');
        } finally {
          shareSend.disabled = false;
        }
      });
    }

    qsa(document,'[data-iac-post-close]').forEach(el=>{
      el.addEventListener('click', (e)=>{ e.preventDefault(); closePostModal(false); });
    });

    async function loadPostForModal(pid){
      try{
        const r = await postForm('ia_connect_post_get', {nonce: nonces.post_get||'', post_id: pid});
        if (!r || !r.success) throw r;
        const p = r.data.post;
        const comments = r.data.comments || [];
		    // Prefer server-side permission flag, but fall back safely.
		    const canEditOpen = (p && typeof p.can_edit !== 'undefined')
		      ? !!p.can_edit
		      : !!(IS_ADMIN || (ME_ID && (parseInt(p?.author_wp_id||0,10)||0) === ME_ID));
		    setModalEditButtons(canEditOpen);
        setModalFollowState(!!(p && p.i_following));
        setModalCommentCount(p && p.comment_count ? p.comment_count : 0);
        if (postBody) {
          postBody.innerHTML = renderCard(p, {mode:'modal'});
          hydrateGalleries(postBody);
          hydrateDiscussEmbeds(postBody);
        }
        if (postComms) postComms.innerHTML = renderCommentThread(comments);
        if (postBody) hydrateGalleries(postBody);
        if (postComms) hydrateMentions(postComms);

        // (header state handled by setModalFollowState + setModalCommentCount)

        // If opened from an email (or deep link), optionally highlight a specific comment.
        try{
          const u = new URL(location.href);
          const cid = parseInt(u.searchParams.get('ia_comment')||'0',10)||0;
          if (cid > 0 && postComms){
            const el = postComms.querySelector('[data-comment-id="' + cid + '"]');
            if (el){
              el.classList.add('iac-comment-highlight');
              // Scroll it into view in the modal.
              setTimeout(()=>{ try{ el.scrollIntoView({block:'center'}); }catch(_){ } }, 50);
            }
          }
        }catch(_){ }
        iaConnectRefreshPageTitle(root, postModal);
      } catch(e){
        if (postBody) postBody.innerHTML = '<div class="iac-card" style="margin:0">Failed to load.</div>';
	        setModalEditButtons(false);
        iaConnectRefreshPageTitle(root, postModal);
      }
    }

	    // Top-right edit/delete actions in fullscreen view
	    if (postEditBtn){
	      postEditBtn.addEventListener('click', (e)=>{
	        e.preventDefault();
	        if (!currentOpenPostId || !postBody) return;
	        const card = postBody.querySelector('.iac-card[data-post-id="' + currentOpenPostId + '"]');
	        if (!card) return;
	        const menu = qs(card, '.iac-menu');
	        if (menu) menu.hidden = false;
	        const btn = card.querySelector('[data-iac-edit]');
	        // If the menu isn't rendered (shouldn't happen when can_edit=true), fail silently.
	        try{ btn && btn.click && btn.click(); }catch(_){ }
	      });
	    }
	    if (postDeleteBtn){
	      postDeleteBtn.addEventListener('click', (e)=>{
	        e.preventDefault();
	        if (!currentOpenPostId || !postBody) return;
	        const card = postBody.querySelector('.iac-card[data-post-id="' + currentOpenPostId + '"]');
	        if (!card) return;
	        const menu = qs(card, '.iac-menu');
	        if (menu) menu.hidden = false;
	        const btn = card.querySelector('[data-iac-delete]');
	        try{ btn && btn.click && btn.click(); }catch(_){ }
	      });
	    }

    if (postJumpCommentsBtn){
      postJumpCommentsBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        // Focus the composer and scroll it into view.
        try{
          if (postCommentInput){
            postCommentInput.focus();
            postCommentInput.scrollIntoView({block:'center'});
          } else if (postComms){
            postComms.scrollIntoView({block:'start'});
          }
        }catch(_){ }
      });
    }

    function renderCommentThread(comments){
      if (!comments || !comments.length) return '<div class="iac-card" style="margin:0">No comments yet.</div>';
      const byParent = {};
      comments.forEach(c=>{
        const pid = String(c.parent_comment_id||0);
        (byParent[pid] = byParent[pid] || []).push(c);
      });
      const icoReply = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 9V5L3 12l7 7v-4.1c5 0 8.5 1.6 11 5.1-1-7-4-11-11-11Z" fill="currentColor"/></svg>';
      const icoEdit  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75Z" fill="currentColor"/></svg>';
      const icoTrash = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 14H7L6 7Zm3-3h6l1 2H8l1-2Zm-4 2h14v2H5V6Z" fill="currentColor"/></svg>';

      const renderLevel = (parentId, depth)=>{
        const list = byParent[String(parentId||0)] || [];
        return list.map(c=>{
          const kids = renderLevel(c.id, depth+1);
          const pad = depth ? (' style="margin-left:' + Math.min(24, depth*12) + 'px"') : '';
          const canEdit = (typeof c.can_edit !== 'undefined')
            ? !!c.can_edit
            : !!(IS_ADMIN || (ME_ID && ME_ID === (parseInt(c.author_wp_id||0,10)||0)));
          const actions =
            '<div class="iac-comment-actions">' +
              '<button type="button" class="iac-cact" data-iac-comment-reply aria-label="Reply" title="Reply">' + icoReply + '</button>' +
              (canEdit ? (
                '<button type="button" class="iac-cact" data-iac-comment-edit aria-label="Edit" title="Edit">' + icoEdit + '</button>' +
                '<button type="button" class="iac-cact" data-iac-comment-delete aria-label="Delete" title="Delete">' + icoTrash + '</button>'
              ) : '') +
            '</div>';
          return (
            '<div class="iac-comment"' + pad + ' data-comment-id="' + esc(c.id) + '" data-parent-id="' + esc(c.parent_comment_id||0) + '" data-post-id="' + esc(currentOpenPostId||0) + '">' +
              '<img class="iac-comment-ava" src="' + esc(c.author_avatar||BLANK_AVA) + '" alt="" />' +
                '<div class="iac-comment-bub">' +
                  '<div class="iac-comment-a">' + userLinkHtml(c.author||'User', c.author_phpbb_id, c.author_username, 'is-comment') + '</div>' +
                  '<div class="iac-comment-t">' + linkMentions(c.body||'') + '</div>' +
                  actions +
                '</div>' +
            '</div>' +
            kids
          );
        }).join('');
      };
      return renderLevel(0,0);
    }

    if (postCopyBtn){
      postCopyBtn.addEventListener('click', async ()=>{
        if (!currentOpenPostId) return;
        try{
          const u = new URL(location.href);
          u.searchParams.set('tab','connect');
          u.searchParams.set('ia_post', String(currentOpenPostId));
          await navigator.clipboard.writeText(u.toString());
        }catch(_){ }
      });
    }

    if (postShareBtn){
      postShareBtn.addEventListener('click', ()=>{ if (currentOpenPostId) openShareModal(currentOpenPostId); });
    }

	    // Opening post edit/delete in fullscreen header.
	    if (postEditBtn){
	      postEditBtn.addEventListener('click', (e)=>{
	        e.preventDefault();
	        if (!currentOpenPostId || !currentOpenPostCanEdit) return;
	        const card = postBody ? postBody.querySelector('.iac-card[data-post-id="' + String(currentOpenPostId) + '"]') : null;
	        if (!card) return;
	        const menu = qs(card, '.iac-menu');
	        if (menu) menu.hidden = false;
	        const btn = card.querySelector('[data-iac-edit]');
	        if (btn && typeof btn.click === 'function') btn.click();
	      });
	    }

	    if (postDeleteBtn){
	      postDeleteBtn.addEventListener('click', (e)=>{
	        e.preventDefault();
	        if (!currentOpenPostId || !currentOpenPostCanEdit) return;
	        const card = postBody ? postBody.querySelector('.iac-card[data-post-id="' + String(currentOpenPostId) + '"]') : null;
	        if (!card) return;
	        const menu = qs(card, '.iac-menu');
	        if (menu) menu.hidden = false;
	        const btn = card.querySelector('[data-iac-delete]');
	        if (btn && typeof btn.click === 'function') btn.click();
	      });
	    }

    if (postJumpCommentsBtn){
      postJumpCommentsBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        // Bring the composer into view and focus it.
        try{
          if (postCommentInput){
            postCommentInput.focus({preventScroll:true});
          }
          const sheet = postJumpCommentsBtn.closest('.iac-modal-sheet');
          if (sheet) sheet.scrollTop = sheet.scrollHeight;
        }catch(_){ }
      });
    }

    if (postFollowBtn){
      postFollowBtn.addEventListener('click', async (e)=>{
        e.preventDefault();
        if (!currentOpenPostId) return;
        const follow = currentOpenPostFollowing ? 0 : 1;
        postFollowBtn.disabled = true;
        try{
          const r = await postForm('ia_connect_follow_toggle', {
            nonce: nonces.follow_toggle||'',
            post_id: currentOpenPostId,
            follow: follow
          });
          if (r && r.success){
            setModalFollowState(!!(r.data && r.data.following));
          }
        }catch(_){
        }finally{
          postFollowBtn.disabled = false;
        }
      });
    }

    function modalFindLastReplyEl(parentCommentEl){
      if (!parentCommentEl) return null;
      const pid = parentCommentEl.getAttribute('data-post-id') || '';
      const parentId = parentCommentEl.getAttribute('data-comment-id') || '';
      let last = parentCommentEl;
      let n = parentCommentEl.nextElementSibling;
      while (n && n.classList && n.classList.contains('iac-comment')){
        if (n.getAttribute('data-post-id') === pid && n.getAttribute('data-parent-id') === parentId){
          last = n;
          n = n.nextElementSibling;
          continue;
        }
        break;
      }
      return last;
    }

    function modalEnsureReplyBox(afterEl, postId, parentCommentId){
      if (!afterEl || !postId || !parentCommentId) return null;
      const existing = afterEl.parentElement?.querySelector('.iac-replybox[data-parent-id="' + parentCommentId + '"]');
      if (existing){
        const ta = existing.querySelector('textarea');
        if (ta) ta.focus();
        return existing;
      }

      const box = document.createElement('div');
      box.className = 'iac-replybox';
      box.setAttribute('data-post-id', String(postId));
      box.setAttribute('data-parent-id', String(parentCommentId));
      box.innerHTML =
        '<textarea class="iac-replybox-input" rows="1" placeholder="Write a reply..."></textarea>' +
        '<button type="button" class="iac-replybox-send">Reply</button>';

      afterEl.insertAdjacentElement('afterend', box);
      const ta = box.querySelector('textarea');
      if (ta) attachMention(ta);
      if (ta) ta.focus();

      box.querySelector('.iac-replybox-send')?.addEventListener('click', async ()=>{
        const txt = String(ta?.value||'').trim();
        if (!txt) return;
        const sendBtn = box.querySelector('.iac-replybox-send');
        if (sendBtn) sendBtn.disabled = true;
        try{
          const r = await postForm('ia_connect_comment_create', {
            nonce: nonces.comment_create||'',
            post_id: postId,
            parent_comment_id: parentCommentId,
            body: txt
          });
          if (!r || !r.success) throw r;
          const c = r.data.comment;
          c.post_id = postId;
          // Insert the reply directly before the reply box (so replies stay expanded).
          box.insertAdjacentHTML('beforebegin', renderMiniComment(c, true));
          if (ta) ta.value = '';
          setModalCommentCount((currentOpenPostCommentCount||0) + 1);
        }catch(err){
          alert(err?.data?.message || 'Reply failed');
        }finally{
          if (sendBtn) sendBtn.disabled = false;
        }
      });

      return box;
    }

    if (postCommentSend){
      postCommentSend.addEventListener('click', async ()=>{
        const pid = currentOpenPostId || (parseInt(postCommentSend.getAttribute('data-post-id')||'0',10)||0);
        if (!pid) return;
        const txt = String(postCommentInput?.value||'').trim();
        if (!txt) return;
        postCommentSend.disabled = true;
        try{
          const r = await postForm('ia_connect_comment_create', {
            nonce: nonces.comment_create||'',
            post_id: pid,
            parent_comment_id: 0,
            body: txt
          });
          if (!r || !r.success) throw r;
          const c = r.data.comment;
          if (postComms){
            // If there was a "No comments" placeholder, replace it.
            if (/No comments yet/i.test(postComms.textContent||'')) postComms.innerHTML = '';
            c.post_id = pid;
            c.parent_comment_id = 0;
            postComms.insertAdjacentHTML('beforeend', renderMiniComment(c, false));
          }
          if (postCommentInput) postCommentInput.value = '';
          // Keep header comment count in sync.
          setModalCommentCount((currentOpenPostCommentCount||0) + 1);
        } catch(e){
          alert(e?.data?.message || 'Comment failed');
        } finally {
          postCommentSend.disabled = false;
        }
      });
    }

    if (postModal){
      postModal.addEventListener('click', async (e)=>{
        // Post menu in fullscreen view
        const modalCard = e.target.closest('.iac-card[data-post-id]');
        if (modalCard && e.target.closest('[data-iac-menu]')){
          e.preventDefault();
          const menu = qs(modalCard, '.iac-menu');
          if (menu) menu.hidden = !menu.hidden;
          return;
        }
        if (modalCard && e.target.closest('[data-iac-edit]')){
          e.preventDefault();
          const menu = qs(modalCard, '.iac-menu'); if (menu) menu.hidden = true;
          const pid = parseInt(modalCard.getAttribute('data-post-id')||'0',10)||0;
          const titleEl = qs(modalCard, '.iac-card-title');
          const textEl = qs(modalCard, '.iac-card-text');
          const curTitle = (titleEl?.textContent || '').trim();
          const curBody = (textEl?.textContent || '').trim();
          const bodyBox = qs(modalCard, '.iac-card-body') || modalCard;
          if (modalCard.__iacEditing) return;
          modalCard.__iacEditing = true;
          if (titleEl) titleEl.style.display = 'none';
          if (textEl) textEl.style.display = 'none';

          const editor = mountInlineEditor(bodyBox, [
            {type:'input', key:'title', value: curTitle, placeholder: 'Title'},
            {type:'textarea', key:'body', value: curBody, placeholder: 'Write your post...'}
          ], async (payload)=>{
            const r = await postForm('ia_connect_post_update', {
              nonce: nonces.post_update||'',
              post_id: pid,
              title: payload.title,
              body: payload.body
            });
            if (r && r.success && r.data && r.data.post){
              const p2 = r.data.post;
              // Update in-place (and keep menu working) by re-rendering the card.
              if (postBody) {
                postBody.innerHTML = renderCard(p2, {mode:'modal'});
                hydrateGalleries(postBody);
                hydrateDiscussEmbeds(postBody);
                hydrateGalleries(postBody);
                hydrateMentions(postBody);
              }
            } else {
              throw r;
            }
          });

          const restore = ()=>{
            modalCard.__iacEditing = false;
            if (titleEl) titleEl.style.display = '';
            if (textEl) textEl.style.display = '';
          };
          if (!editor){ restore(); return; }
          const cancelBtn = qs(editor, '.iac-inline-cancel');
          cancelBtn && cancelBtn.addEventListener('click', ()=>{ restore(); });
          // Restore flag on save cleanup via mutation (best-effort)
          const saveBtn = qs(editor, '.iac-inline-save');
          saveBtn && saveBtn.addEventListener('click', ()=>{ restore(); });
          return;
        }
        if (modalCard && e.target.closest('[data-iac-delete]')){
          e.preventDefault();
          const menu = qs(modalCard, '.iac-menu'); if (menu) menu.hidden = true;
          const pid = parseInt(modalCard.getAttribute('data-post-id')||'0',10)||0;
          const ok = await openConfirm('Delete this post?', 'Delete post', 'Delete', 'Cancel');
          if (!ok) return;
          try{
            const r = await postForm('ia_connect_post_delete', {
              nonce: nonces.post_delete||'',
              post_id: pid
            });
            if (r && r.success){
              // Remove from feed if present
              try{
                document.querySelectorAll('.iac-card[data-post-id="' + pid + '"]').forEach(el=>el.remove());
              }catch(_){ }
              closePostModal(false);
            }
          }catch(err){
            showToast(err?.data?.message || 'Delete failed');
          }
          return;
        }

        // Comment actions in fullscreen view
        const cEl = e.target.closest('.iac-comment[data-comment-id]');
        if (!cEl) return;
        const postId = parseInt(cEl.getAttribute('data-post-id')||String(currentOpenPostId||0),10)||0;
        const cid = parseInt(cEl.getAttribute('data-comment-id')||'0',10)||0;

        if (e.target.closest('[data-iac-comment-reply]')){
          e.preventDefault();
          const last = modalFindLastReplyEl(cEl);
          modalEnsureReplyBox(last || cEl, postId, cid);
          return;
        }
        if (e.target.closest('[data-iac-comment-edit]')){
          e.preventDefault();
          const tEl = qs(cEl, '.iac-comment-t');
          if (!tEl) return;
          if (cEl.__iacEditing) return;
          cEl.__iacEditing = true;
          const cur = (tEl.textContent || '').trim();
          tEl.style.display = 'none';
          const bub = qs(cEl, '.iac-comment-bub') || cEl;
          const actions = qs(cEl, '.iac-comment-actions');
          if (actions) actions.style.display = 'none';

          const editor = mountInlineEditor(bub, [
            {type:'textarea', key:'body', value: cur, placeholder: 'Edit your comment...'}
          ], async (payload)=>{
            const r = await postForm('ia_connect_comment_update', {
              nonce: nonces.comment_update||'',
              comment_id: cid,
              body: payload.body
            });
            if (!r || !r.success) throw r;
            const nextBody = (r.data.comment && r.data.comment.body) ? r.data.comment.body : payload.body;
            tEl.innerHTML = linkMentions(nextBody);
            hydrateMentions(cEl);
          });

          const restore = ()=>{
            cEl.__iacEditing = false;
            tEl.style.display = '';
            if (actions) actions.style.display = '';
          };
          if (!editor){ restore(); return; }
          const cancelBtn = qs(editor, '.iac-inline-cancel');
          cancelBtn && cancelBtn.addEventListener('click', ()=>{ restore(); });
          const saveBtn = qs(editor, '.iac-inline-save');
          saveBtn && saveBtn.addEventListener('click', ()=>{ restore(); });
          try{
            // handled by inline editor
          }catch(err){
            alert(err?.data?.message || 'Update failed');
          }
          return;
        }
        if (e.target.closest('[data-iac-comment-delete]')){
          e.preventDefault();
          const ok = await openConfirm('Delete this comment?', 'Delete comment', 'Delete', 'Cancel');
          if (!ok) return;
          try{
            const r = await postForm('ia_connect_comment_delete', {
              nonce: nonces.comment_delete||'',
              comment_id: cid
            });
            if (!r || !r.success) throw r;
            const tEl = qs(cEl, '.iac-comment-t');
            if (tEl) tEl.innerHTML = '<em>Deleted</em>';
            cEl.classList.add('is-deleted');
            qsa(cEl, '.iac-comment-actions .iac-cact').forEach(b=>b.remove());
          }catch(err){
            alert(err?.data?.message || 'Delete failed');
          }
          return;
        }
      });
    }

    // Mentions: lightweight @username suggestions
    const mentionBox = document.createElement('div');
    mentionBox.className = 'iac-mentionbox';
    mentionBox.hidden = true;
    document.body.appendChild(mentionBox);

    let mentionActiveEl = null;
    let mentionToken = '';
    let mentionItems = [];

    function hideMention(){
      mentionBox.hidden = true;
      mentionBox.innerHTML = '';
      mentionBox.removeAttribute('data-iac-style');
      mentionActiveEl = null;
      mentionToken = '';
      mentionItems = [];
    }

    function syncMentionTheme(el){
      const host = el && el.closest && el.closest('[data-iac-style]');
      const style = host ? String(host.getAttribute('data-iac-style') || '').trim() : '';
      if (style) mentionBox.setAttribute('data-iac-style', style);
      else mentionBox.removeAttribute('data-iac-style');
    }

    async function fetchMentions(q){
      const r = await postForm('ia_connect_mention_suggest', {nonce: nonces.mention_suggest||'', q});
      if (!r || !r.success) return [];
      return r.data.results || [];
    }

    const mentionDebounced = deb(async (q)=>{
      if (!mentionActiveEl) return;
      const items = await fetchMentions(q);
      mentionItems = items;
      if (!items.length){ hideMention(); return; }
      mentionBox.innerHTML = items.map((u, idx)=>
        '<div class="iac-mention-row" data-iac-mention-pick data-idx="' + esc(idx) + '">' +
          '<img class="iac-mention-ava" src="' + esc(u.avatarUrl||'') + '" alt="" />' +
          '<div>' +
            '<div class="iac-mention-name">' + esc(u.display||u.username||'User') + '</div>' +
            '<div class="iac-mention-user">@' + esc(u.username||'user') + '</div>' +
          '</div>' +
        '</div>'
      ).join('');

      // Position under the input
      const rct = mentionActiveEl.getBoundingClientRect();
      const top = Math.min(window.innerHeight - 10, rct.bottom + 6);
      const left = Math.min(window.innerWidth - 10, rct.left);
      mentionBox.style.top = top + 'px';
      mentionBox.style.left = left + 'px';
      mentionBox.hidden = false;
    }, 160);

    function getAtToken(el){
      const v = String(el.value||'');
      const pos = (typeof el.selectionStart === 'number') ? el.selectionStart : v.length;
      const before = v.slice(0,pos);
      const m = before.match(/(^|\s)@([a-zA-Z0-9_\-\.]{1,40})$/);
      if (!m) return '';
      return m[2] || '';
    }

    function replaceAtToken(el, username){
      const v = String(el.value||'');
      const pos = (typeof el.selectionStart === 'number') ? el.selectionStart : v.length;
      const before = v.slice(0,pos);
      const after = v.slice(pos);
      const m = before.match(/(^|\s)@([a-zA-Z0-9_\-\.]{1,40})$/);
      if (!m) return;
      const start = before.length - m[2].length - 1; // include '@'
      const pre = v.slice(0,start);
      const ins = '@' + username + ' ';
      const next = pre + ins + after;
      el.value = next;
      const caret = (pre + ins).length;
      if (el.setSelectionRange) el.setSelectionRange(caret, caret);
    }

    function attachMention(el){
      if (!el) return;
      el.addEventListener('input', ()=>{
        const token = getAtToken(el);
        if (!token){ hideMention(); return; }
        mentionActiveEl = el;
        mentionToken = token;
        syncMentionTheme(el);
        mentionDebounced(token);
      });
      el.addEventListener('blur', ()=>{ setTimeout(()=>hideMention(), 120); });
    }

    mentionBox.addEventListener('mousedown', (e)=>{
      const row = e.target.closest('[data-iac-mention-pick]');
      if (!row) return;
      e.preventDefault();
      const idx = parseInt(row.getAttribute('data-idx')||'0',10)||0;
      const u = mentionItems[idx];
      if (!u || !mentionActiveEl) return;
      replaceAtToken(mentionActiveEl, u.username||'');
      hideMention();
      try{ mentionActiveEl.focus(); }catch(_){ }
    });

    // Popstate: allow browser back to close modal
    window.addEventListener('popstate', ()=>{
      const u = new URL(location.href);
      const pid = parseInt(u.searchParams.get('ia_post')||'0',10)||0;
      const cid = parseInt(u.searchParams.get('ia_comment')||'0',10)||0;
      if (pid > 0){
        if (!postModal || postModal.hidden) openPostModal(pid, false, cid>0 ? cid : null);
      } else {
        if (postModal && !postModal.hidden) closePostModal(true);
      }
      iaConnectRefreshPageTitle(root, postModal);
    });

    // Click-to-view (cover/avatar + attachment media)
    root.addEventListener('click', (e)=>{
      const change = e.target.closest('[data-iac-change]');
      if (change){
        e.preventDefault();
        e.stopPropagation();
        const kind = change.getAttribute('data-iac-change');
        if (kind === 'profile') {
          const pick = qs(document, '[data-iac-filepick-profile]');
          if (pick) pick.click();
        }
        if (kind === 'cover') {
          const pick = qs(document, '[data-iac-filepick-cover]');
          if (pick) pick.click();
        }
        return;
      }

      const view = e.target.closest('[data-iac-view]');
      if (view){
        e.preventDefault();
        const src = view.getAttribute('data-iac-view');
        openViewer(src);
      }
    });

    // File pickers
    function uploadOne(action, nonceKey, file, cb){
      const fd = new FormData();
      fd.append('action', action);
      fd.append('nonce', nonces[nonceKey] || '');
      fd.append('file', file);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.onreadystatechange = ()=>{
        if (xhr.readyState !== 4) return;
        let json = null; try{ json = JSON.parse(xhr.responseText||'{}'); }catch(e){}
        if (xhr.status >= 200 && xhr.status < 300 && json && json.success){
          cb(null, json.data);
        } else {
          cb(json?.data?.message || 'Upload failed');
        }
      };
      xhr.send(fd);
    }

    const profPick = qs(document, '[data-iac-filepick-profile]');
    if (profPick){
      profPick.addEventListener('change', ()=>{
        const f = profPick.files && profPick.files[0];
        if (!f) return;
        uploadOne('ia_connect_upload_profile','profile_photo',f,(err,data)=>{
          profPick.value = '';
          if (err) return alert(err);
          const img = qs(root, '.iac-avatar-img');
          if (img && data.url) img.src = data.url + (data.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
        });
      });
    }

    const covPick = qs(document, '[data-iac-filepick-cover]');
    if (covPick){
      covPick.addEventListener('change', ()=>{
        const f = covPick.files && covPick.files[0];
        if (!f) return;
        uploadOne('ia_connect_upload_cover','cover_photo',f,(err,data)=>{
          covPick.value = '';
          if (err) return alert(err);
          const img = qs(root, '.iac-cover-img');
          if (img && data.url) img.src = data.url + (data.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
          if (!img && data.url){
            // If fallback, inject img
            const cover = qs(root, '.iac-cover');
            if (cover){
              cover.innerHTML = '<img class="iac-cover-img" src="' + esc(data.url) + '" alt="Cover" />' + cover.innerHTML;
            }
          }
        });
      });
    }

    // Tabs
    qsa(root, '[data-iac-tab]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const t = btn.getAttribute('data-iac-tab');
        qsa(root, '[data-iac-tab]').forEach(b=>b.classList.toggle('is-active', b===btn));
        qsa(root, '[data-iac-panel]').forEach(p=>p.classList.toggle('is-active', p.getAttribute('data-iac-panel')===t));

        // Followers tab: lazy load
        if (t === 'followers') {
          try { initFollowers(); } catch(_){ }
        }
      });
    });

    // Followers tab
    let followersInit = false;
    function initFollowers(){
      if (followersInit) return;
      followersInit = true;
      const qIn = qs(root, '[data-iac-followers-q]');
      const list = qs(root, '[data-iac-followers-list]');
      const countEl = qs(root, '[data-iac-followers-count]');
      const targetPhpbb = root ? (parseInt(root.getAttribute('data-wall-phpbb')||'0',10)||0) : 0;
      if (!list) return;

      function row(u){
        const av = u.avatarUrl ? '<img class="iac-f-ava" src="' + esc(u.avatarUrl) + '" alt="" />' : '<div class="iac-f-ava"></div>';
        const name = esc(u.display || u.username || 'User');
        const handle = u.username ? ('@' + esc(u.username)) : '';
        return '<div class="iac-f-row" data-phpbb="' + (u.phpbb_user_id||0) + '">' +
          av +
          '<div class="iac-f-meta"><div class="iac-f-name">' + name + '</div><div class="iac-f-sub">' + handle + '</div></div>' +
        '</div>';
      }

      async function fetchFollowers(action, q){
        const data = await postForm(action, { nonce: nonces.user_search||'', q: q||'', target_phpbb: targetPhpbb||0 });
        if (!data || !data.success) throw new Error((data&&data.data&&data.data.message)||'Request failed');
        return data.data || { total:0, results:[] };
      }

      async function load(q){
        list.innerHTML = '<div class="iac-muted">Loading…</div>';
        try{
          const res = await fetchFollowers(q ? 'ia_connect_followers_search' : 'ia_connect_followers_list', q);
          if (countEl) countEl.textContent = String(res.total||0);
          const items = Array.isArray(res.results) ? res.results : [];
          if (!items.length){ list.innerHTML = '<div class="iac-muted">No followers.</div>'; return; }
          list.innerHTML = items.map(row).join('');
        }catch(e){
          list.innerHTML = '<div class="iac-muted">Could not load followers.</div>';
        }
      }

      let tmr = 0;
      if (qIn){
        qIn.addEventListener('input', ()=>{
          clearTimeout(tmr);
          tmr = setTimeout(()=> load(qIn.value.trim()), 180);
        });
      }
      load('');
    }


    // Profile menu integration (ia-profile-menu dispatches ia_connect:profileMenu)
    window.addEventListener("ia_connect:profileMenu", function(ev){
      const action = ev && ev.detail ? String(ev.detail.action||"") : "";
      if (!action) return;
      function goTab(tab){
        const btn = document.querySelector("[data-iac-tab=\""+tab+"\"]");
        if (btn) btn.click();
      }

      function clickWhenReady(selector, tries){
        let left = typeof tries === "number" ? tries : 20;
        const tick = ()=>{
          const b = document.querySelector(selector);
          if (b){ b.click(); return; }
          left--;
          if (left <= 0) return;
          setTimeout(tick, 120);
        };
        tick();
      }

      if (action === "settings" || action === "privacy"){
        goTab(action);
        return;
      }

      // These live inside Settings, so navigate there first, then trigger.
      function scrollToSection(key){
        try{
          const el = document.querySelector('[data-iac-settings-section="'+key+'"]');
          if (el && typeof el.scrollIntoView === 'function'){
            el.scrollIntoView({ behavior:'smooth', block:'start' });
          }
        }catch(_){ }
      }

      if (action === "export"){
        goTab("settings");
        scrollToSection('export');
        // Optional: auto-click export button once the settings panel is visible.
        clickWhenReady("[data-iac-acct-export]", 30);
        return;
      }
      if (action === "deactivate"){
        goTab("settings");
        scrollToSection('deactivate');
        return;
      }
      if (action === "delete"){
        goTab("settings");
        scrollToSection('delete');
        return;
      }
    });

    // Message button (delegated, survives SPA rerenders)
    document.addEventListener('click', (e)=>{
      const btn = e.target && e.target.closest ? e.target.closest('[data-iac-message]') : null;
      if (!btn) return;
      try { e.preventDefault(); e.stopPropagation(); } catch(_){ }
      const prof = document.querySelector('[data-iac-profile]');
      const toPhpbb = prof ? (parseInt(prof.getAttribute('data-wall-phpbb') || '0', 10) || 0) : 0;
      if (!toPhpbb) return;
      try { window.localStorage && localStorage.setItem('ia_msg_to', String(toPhpbb)); } catch(_){ }
      try { window.__IA_MESSAGE_PENDING_DM_TO = toPhpbb; window.__IA_MESSAGE_DEEPLINK_DONE = false; } catch(_){ }
      try { if (window.IA_ATRIUM && typeof window.IA_ATRIUM.setTab === 'function') { window.IA_ATRIUM.setTab('messages'); return; } } catch(_){ }
      try { if (window.IA_ATRIUM && typeof window.IA_ATRIUM.openTab === 'function') { window.IA_ATRIUM.openTab('messages'); return; } } catch(_){ }
      try { const t = document.querySelector('a[href*="tab=messages"], button[data-tab="messages"], a[data-tab="messages"], [data-ia-tab="messages"], [data-tab-key="messages"]'); if (t) { t.click(); return; } } catch(_){ }
      try { const u = new URL(location.href); u.searchParams.set('tab','messages'); location.href = u.toString(); } catch(_){ }
    }, true);

    
    
    // Relationship modal (blocked / confirm)
    function iacRelModal(opts){
      opts = opts || {};
      // Remove any existing
      try{
        const ex = document.querySelector('.iac-relmodal');
        if (ex) ex.remove();
      }catch(_){}
      const wrap = document.createElement('div');
      wrap.className = 'iac-relmodal';
      wrap.innerHTML =
        '<div class="iac-relmodal-backdrop" data-iac-relmodal-close></div>' +
        '<div class="iac-relmodal-card" role="dialog" aria-modal="true">' +
          '<div class="iac-relmodal-title">' + (opts.title || '') + '</div>' +
          '<div class="iac-relmodal-body">' + (opts.body || '') + '</div>' +
          '<div class="iac-relmodal-actions"></div>' +
        '</div>';
      const acts = wrap.querySelector('.iac-relmodal-actions');
      (opts.actions || [{label:'Close', kind:'close'}]).forEach(a=>{
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'iac-relmodal-btn' + (a.primary ? ' is-primary' : '');
        b.textContent = a.label || 'OK';
        b.addEventListener('click', async (ev)=>{
          ev.preventDefault();
          ev.stopPropagation();
          if (a.onClick){
            try{ await a.onClick(); }catch(_){}
          }
          if (a.kind === 'stay') return;
          try{ wrap.remove(); }catch(_){}
        });
        acts.appendChild(b);
      });
      wrap.addEventListener('click', (ev)=>{
        const c = ev.target && ev.target.closest ? ev.target.closest('[data-iac-relmodal-close]') : null;
        if (c){ try{ wrap.remove(); }catch(_){ } }
      }, true);
      document.body.appendChild(wrap);
      return wrap;
    }

// Follow/Block buttons on profile header
    async function relStatus(targetPhpbb){
      try{
        return await postForm('ia_connect_user_rel_status', { nonce: nonces.user_search||'', target_phpbb: targetPhpbb });
      }catch(e){ return null; }
    }
    // NOTE: there can be multiple follow/block buttons on the page (e.g., avatar cluster + actions cluster).
    // Keep them all in sync.
    function syncRelButtons(st){
      const following = !!(st && st.success && st.data && st.data.following);
      const blockedByMe = !!(st && st.success && st.data && st.data.blocked_by_me);

      const fbtns = document.querySelectorAll('[data-iac-follow-user]');
      const bbtns = document.querySelectorAll('[data-iac-block-user]');

      if (fbtns && fbtns.length){
        fbtns.forEach((fbtn)=>{
          try{
            if (following) fbtn.classList.add('is-on'); else fbtn.classList.remove('is-on');
            fbtn.setAttribute('aria-label', following ? 'Unfollow' : 'Follow');
          }catch(_){ }
        });
      }

      if (bbtns && bbtns.length){
        bbtns.forEach((bbtn)=>{
          try{
            if (blockedByMe) bbtn.classList.add('is-on'); else bbtn.classList.remove('is-on');
            bbtn.setAttribute('aria-label', blockedByMe ? 'Unblock' : 'Block');
          }catch(_){ }
        });
      }
    }
    (async function initRelButtons(){
      const prof = document.querySelector('[data-iac-profile]');
      const targetPhpbb = prof ? (parseInt(prof.getAttribute('data-wall-phpbb')||'0',10)||0) : 0;
      if (!targetPhpbb) return;
      const fbtn = document.querySelector('[data-iac-follow-user]');
      const bbtn = document.querySelector('[data-iac-block-user]');
      if (!fbtn && !bbtn) return;
      const st = await relStatus(targetPhpbb);
      syncRelButtons(st);
      if (st && st.success && st.data && st.data.blocked_any){
        if (st.data.blocked_by_me){
          iacRelModal({ title: 'User blocked', body: 'You have blocked this user. You can unblock them to interact again.', actions: [
            {label:'Cancel', primary:false},
            {label:'Unblock', primary:true, onClick: async ()=>{ const r2 = await postForm('ia_connect_user_block_toggle', { nonce: nonces.user_search||'', target_phpbb: targetPhpbb }); const st2 = await relStatus(targetPhpbb); syncRelButtons(st2); }}
          ]});
        } else {
          iacRelModal({ title: 'You are blocked', body: 'You can\'t interact with this user right now. Messaging and replies are disabled while a block is active.' });
        }
      }
    })();

    document.addEventListener('click', async (e)=>{
      const f = e.target && e.target.closest ? e.target.closest('[data-iac-follow-user]') : null;
      const b = e.target && e.target.closest ? e.target.closest('[data-iac-block-user]') : null;
      if (!f && !b) return;
      try{ e.preventDefault(); e.stopPropagation(); }catch(_){}
      const prof = document.querySelector('[data-iac-profile]');
      const targetPhpbb = prof ? (parseInt(prof.getAttribute('data-wall-phpbb')||'0',10)||0) : 0;
      if (!targetPhpbb) return;

      if (f){
        const r = await postForm('ia_connect_user_follow_toggle', { nonce: nonces.user_search||'', target_phpbb: targetPhpbb });
        if (r && r.success === false && r.data && (r.data.message === 'Blocked' || r.data.message === 'blocked')){
          iacRelModal({ title: 'You are blocked', body: 'You can\'t follow or interact with this user right now because a block is active.' });
          return;
        }
        syncRelButtons(r);
        return;
      }
      if (b){
        const isOn = b.classList.contains('is-on');
        const verb = isOn ? 'unblock' : 'block';
        iacRelModal({
          title: (isOn ? 'Unblock user?' : 'Block user?'),
          body: (isOn ? 'Are you sure you want to unblock this user?' : 'Are you sure you want to block this user? You won\'t be able to see or interact with each other until unblocked.'),
          actions: [
            {label:'Cancel', primary:false},
            {label:(isOn ? 'Unblock' : 'Block'), primary:true, onClick: async ()=>{
              const r = await postForm('ia_connect_user_block_toggle', { nonce: nonces.user_search||'', target_phpbb: targetPhpbb });
              const st2 = await relStatus(targetPhpbb);
              syncRelButtons(st2);
              if (st2 && st2.success && st2.data && st2.data.blocked_any){
                if (st2.data.blocked_by_me){
                  iacRelModal({ title: 'User blocked', body: 'This user is blocked. You can unblock them to interact again.', actions: [
                    {label:'Close', primary:false},
                    {label:'Unblock', primary:true, onClick: async ()=>{ await postForm('ia_connect_user_block_toggle', { nonce: nonces.user_search||'', target_phpbb: targetPhpbb }); const st3 = await relStatus(targetPhpbb); syncRelButtons(st3); }}
                  ]});
                } else {
                  iacRelModal({ title: 'You are blocked', body: 'You can\'t interact with this user right now. Messaging and replies are disabled while a block is active.' });
                }
              }
            }}
          ]
        });
        return;
      }
    }, true);


// Search: users + wall
    const searchInput = qs(document, '.iac-search-input');
    const resultsBox = qs(document, '.iac-search-results');
    let searchTimer = null;

    function hideResults(){ if (resultsBox){ resultsBox.hidden = true; resultsBox.innerHTML = ''; } }

    function renderSearch(users, posts, comments){
      if (!resultsBox) return;
      const blankAva = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
      let html = '';
      if (users && users.length){
        html += '<div class="iac-search-hdr">Users</div>';
        users.forEach(u=>{
          html += '<div class="iac-search-row" data-iac-go-profile="1" data-phpbb="' + (u.phpbb_user_id||0) + '" data-username="' + esc(u.username) + '">' +
            '<img class="iac-search-ava" src="' + esc(u.avatarUrl||blankAva) + '" alt="" />' +
            '<div class="iac-search-txt"><div class="iac-search-name">' + esc(u.display||u.username) + '</div>' +
            '<div class="iac-search-sub">@' + esc(u.username) + '</div></div></div>';
        });
      }

      if (posts && posts.length){
        html += '<div class="iac-search-hdr">Posts</div>';
        posts.forEach(p=>{
          html += '<div class="iac-search-row" data-iac-go-post="' + (p.id||0) + '">' +
            '<img class="iac-search-ava" src="' + esc(p.author_avatar||blankAva) + '" alt="" />' +
            '<div class="iac-search-txt"><div class="iac-search-name">' + esc(p.title || '(status)') + '</div>' +
            '<div class="iac-search-sub">' + esc((p.author||'User') + ' · #' + p.id) + '</div></div></div>';
        });
      }

      if (comments && comments.length){
        html += '<div class="iac-search-hdr">Comments</div>';
        comments.forEach(c=>{
          html += '<div class="iac-search-row" data-iac-go-post="' + (c.post_id||0) + '">' +
            '<img class="iac-search-ava" src="' + esc(c.author_avatar||blankAva) + '" alt="" />' +
            '<div class="iac-search-txt"><div class="iac-search-name">' + esc(c.author||'User') + '</div>' +
            '<div class="iac-search-sub">' + esc(String(c.body||'').slice(0,80)) + '</div></div></div>';
        });
      }

      if (!html) html = '<div class="iac-search-row"><div class="iac-search-txt"><div class="iac-search-name">No results</div></div></div>';
      resultsBox.innerHTML = html;
      resultsBox.hidden = false;
    }

    async function runSearch(q){
      if (!q){ hideResults(); return; }
      try{
        const r1 = await postForm('ia_connect_user_search', {nonce: nonces.user_search||'', q});
        const users = (r1 && r1.success ? (r1.data.results||[]) : []);
        const r2 = await postForm('ia_connect_wall_search', {nonce: nonces.wall_search||'', q});
        const posts = (r2 && r2.success ? (r2.data.posts||[]) : []);
        const comments = (r2 && r2.success ? (r2.data.comments||[]) : []);
        renderSearch(users, posts, comments);
      } catch(e){
        // Silent
      }
    }

    if (searchInput){
      searchInput.addEventListener('input', ()=>{
        clearTimeout(searchTimer);
        const q = String(searchInput.value||'').trim();
        searchTimer = setTimeout(()=>runSearch(q), 200);
      });
      searchInput.addEventListener('focus', ()=>{
        const q = String(searchInput.value||'').trim();
        if (q) runSearch(q);
      });
      document.addEventListener('click', (e)=>{
        if (!resultsBox) return;
        if (e.target.closest('.iac-search')) return;
        hideResults();
      });
    }

    if (resultsBox){
      resultsBox.addEventListener('click', (e)=>{
        const row = e.target.closest('.iac-search-row');
        if (!row) return;
        if (row.getAttribute('data-iac-go-profile')){
          hideResults();
          const uname = row.getAttribute('data-username') || '';
          const pid = parseInt(row.getAttribute('data-phpbb')||'0',10)||0;
          const url = new URL(location.href);
          url.searchParams.set('tab','connect');
          if (pid) url.searchParams.set('ia_profile', String(pid));
          if (uname) url.searchParams.set('ia_profile_name', uname);
          location.href = url.toString();
          return;
        }

        const postId = parseInt(row.getAttribute('data-iac-go-post')||'0',10)||0;
        if (postId){
          hideResults();
          const contentTab = qs(root,'[data-iac-tab="content"]');
          if (contentTab) contentTab.click();
          openPostModal(postId, true);
        }
      });
    }

    // Username links (cards, comments, etc.)
    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('[data-iac-userlink]');
      if (!btn) return;
      e.preventDefault();
      const pid = parseInt(btn.getAttribute('data-phpbb')||'0',10)||0;
      const uname = btn.getAttribute('data-username') || '';
      const url = new URL(location.href);
      url.searchParams.set('tab','connect');
      // Leaving the post modal => remove deep-link param.
      url.searchParams.delete('ia_post');
      if (pid) url.searchParams.set('ia_profile', String(pid));
      if (uname) url.searchParams.set('ia_profile_name', uname);
      // If a fullscreen post modal is open, close it first so the profile is visible.
      try{
        if (postModal && !postModal.hidden) {
          closePostModal(false);
        }
      }catch(_){ }

      // Navigate to profile.
      location.href = url.toString();
    });

    // Feed
    const wallWp = parseInt(root.getAttribute('data-wall-wp')||'0',10)||0;
    const wallPhpbb = parseInt(root.getAttribute('data-wall-phpbb')||'0',10)||0;

    const feedInner = qs(root, '[data-iac-feed-inner]');
    const loadMoreBtn = qs(root, '[data-iac-loadmore]');
    let oldestId = 0;
    let loading = false;

    function findLastReplyEl(parentCommentEl){
      if (!parentCommentEl) return null;
      const pid = parentCommentEl.getAttribute('data-post-id') || '';
      const parentId = parentCommentEl.getAttribute('data-comment-id') || '';
      const box = parentCommentEl.parentElement;
      if (!box) return parentCommentEl;
      // Replies are rendered as sibling .iac-comment elements after the parent.
      let last = parentCommentEl;
      let n = parentCommentEl.nextElementSibling;
      while (n && n.classList && n.classList.contains('iac-comment')){
        if (n.getAttribute('data-post-id') === pid && n.getAttribute('data-parent-id') === parentId){
          last = n;
          n = n.nextElementSibling;
          continue;
        }
        break;
      }
      return last;
    }

    function ensureInlineReplyBox(afterEl, postId, parentCommentId){
      if (!afterEl || !postId || !parentCommentId) return null;
      const existing = afterEl.parentElement?.querySelector('.iac-replybox[data-parent-id="' + parentCommentId + '"]');
      if (existing){
        const ta = existing.querySelector('textarea');
        if (ta) { ta.focus(); }
        return existing;
      }

      const box = document.createElement('div');
      box.className = 'iac-replybox';
      box.setAttribute('data-post-id', String(postId));
      box.setAttribute('data-parent-id', String(parentCommentId));
      box.innerHTML =
        '<textarea class="iac-replybox-input" rows="1" placeholder="Write a reply..."></textarea>' +
        '<button type="button" class="iac-replybox-send">Reply</button>';

      afterEl.insertAdjacentElement('afterend', box);

      const ta = box.querySelector('textarea');
      if (ta) attachMention(ta);
      if (ta) ta.focus();

      box.querySelector('.iac-replybox-send')?.addEventListener('click', async ()=>{
        const txt = String(ta?.value||'').trim();
        if (!txt) return;
        const sendBtn = box.querySelector('.iac-replybox-send');
        if (sendBtn) sendBtn.disabled = true;
        try{
          const r = await postForm('ia_connect_comment_create', {
            nonce: nonces.comment_create||'',
            post_id: postId,
            parent_comment_id: parentCommentId,
            body: txt
          });
          if (!r || !r.success) throw r;
          const c = r.data.comment;
          // Insert after last reply (so replies expand in-order).
          const parentEl = box.previousElementSibling?.classList?.contains('iac-comment') ? box.previousElementSibling : null;
          const parentForLast = parentEl && parseInt(parentEl.getAttribute('data-comment-id')||'0',10) === parentCommentId ? parentEl : document.querySelector('.iac-comment[data-comment-id="' + parentCommentId + '"]');
          const last = findLastReplyEl(parentForLast || afterEl);
          if (last){
            // Ensure fields for renderer.
            c.post_id = postId;
            box.insertAdjacentHTML('beforebegin', renderMiniComment(c, true));
          }
          if (ta) ta.value = '';
        }catch(err){
          alert(err?.data?.message || 'Reply failed');
        }finally{
          if (sendBtn) sendBtn.disabled = false;
        }
      });

      return box;
    }

    function renderAttachmentGallery(atts){
      if (!atts || !atts.length) return '';
      const safe = atts.map(a=>({url:a.url, kind:a.kind, name:a.name}));
      const data = esc(JSON.stringify(safe));
      return (
        '<div class="iac-gallery" data-iac-gallery data-items="' + data + '">' +
          '<div class="iac-g-stage">' +
            '<div class="iac-g-media" data-iac-g-media></div>' +
            '<button type="button" class="iac-g-nav iac-g-prev" data-iac-g-prev aria-label="Prev">‹</button>' +
            '<button type="button" class="iac-g-nav iac-g-next" data-iac-g-next aria-label="Next">›</button>' +
            '<div class="iac-g-count" data-iac-g-count></div>' +
          '</div>' +
        '</div>'
      );
    }

    

function renderMiniComment(c, isReply){
  c = c || {};
  const pad = isReply ? ' iac-comment-reply' : '';
  const cid = parseInt(c.id||0,10)||0;
  const pid = parseInt(c.post_id||0,10)||0;
  const parentId = parseInt(c.parent_comment_id||0,10)||0;
  const canEdit = (typeof c.can_edit !== 'undefined')
    ? !!c.can_edit
    : !!(IS_ADMIN || (ME_ID && ME_ID === (parseInt(c.author_wp_id||0,10)||0)));

  const icoReply = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 9V5L3 12l7 7v-4.1c5 0 8.5 1.6 11 5.1-1-7-4-11-11-11Z" fill="currentColor"/></svg>';
  const icoEdit  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75Z" fill="currentColor"/></svg>';
  const icoTrash = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 14H7L6 7Zm3-3h6l1 2H8l1-2Zm-4 2h14v2H5V6Z" fill="currentColor"/></svg>';

  const actions =
    '<div class="iac-comment-actions">' +
      '<button type="button" class="iac-cact" data-iac-comment-reply aria-label="Reply" title="Reply">' + icoReply + '</button>' +
      (canEdit ? (
        '<button type="button" class="iac-cact" data-iac-comment-edit aria-label="Edit" title="Edit">' + icoEdit + '</button>' +
        '<button type="button" class="iac-cact" data-iac-comment-delete aria-label="Delete" title="Delete">' + icoTrash + '</button>'
      ) : '') +
    '</div>';

  return '<div class="iac-comment' + pad + '" data-comment-id="' + esc(cid) + '" data-parent-id="' + esc(parentId) + '" data-post-id="' + esc(pid) + '">' +
    '<img class="iac-comment-ava" src="' + esc(c.author_avatar||BLANK_AVA) + '" alt="" />' +
    '<div class="iac-comment-bub"><div class="iac-comment-a">' +
      userLinkHtml(c.author||'User', c.author_phpbb_id, c.author_username, 'is-comment') +
    '</div>' +
    '<div class="iac-comment-t">' + linkMentions(c.body||'') + '</div>' +
    actions +
    '</div></div>';
}
function renderCard(p, opts){
      opts = opts || {};
      if (!p || typeof p !== 'object') return '';
      if (!p.id) return '';
      const title = esc(p.title||'');
	  const bodyRaw = (p.body||'');
	  const bodyClean = stripEmbeddableVideoUrls(bodyRaw);
	  const body = linkMentions(bodyClean);
	  const embeds = renderVideoEmbedsFromText(bodyRaw);
      const linkCards = renderLinkCardsFromText(bodyRaw);
      const when = esc(p.created_at||'');
      const isRepost = (p.type === 'repost' || p.type === 'mention') && p.parent_post;

      const isDiscussShare = (String(p.shared_tab||'') === 'discuss') && String(p.shared_ref||'') !== '';

      const discussRef = String(p.shared_ref||'');
      const discussBits = discussRef.split(':');
      const discussTopicId = parseInt(discussBits[0]||'0',10)||0;
      const discussPostId  = parseInt(discussBits[1]||'0',10)||0;
      const discussKind    = String(discussBits[2]||'');

      const discussShareCount = isDiscussShare ? (parseInt(String(discussBits[3]||'0'),10)||0) : 0;

      function buildDiscussUrl(tid, pid){
        try {
          const u = new URL(location.href);
          u.searchParams.set('tab','discuss');
          u.searchParams.set('ia_tab','discuss');
          u.searchParams.set('iad_view','topic');
          u.searchParams.set('iad_topic', String(tid));
          if (pid) u.searchParams.set('iad_post', String(pid));
          else u.searchParams.delete('iad_post');
          return u.toString();
        } catch(_){
          return '#';
        }
      }

      const discussOpenUrl = isDiscussShare ? buildDiscussUrl(discussTopicId, 0) : '#';
      const discussTitleLink = (isDiscussShare && title)
        ? ('<a class="iac-discuss-sharelink" href="' + esc(discussOpenUrl) + '" data-iac-discuss-open data-topic-id="' + esc(discussTopicId) + '"><strong>' + esc(title) + '</strong></a>')
        : '';

      const discussShareHeader = isDiscussShare
        ? (' shared a ' + (discussKind==='topic' ? 'topic' : 'reply') + ' from Discuss' +
           (discussTitleLink ? (' · ' + discussTitleLink) : '') +
           (discussShareCount > 0 ? (' · shared with ' + esc(String(discussShareCount))) : ''))
        : '';

      const discussEmbed = isDiscussShare && discussTopicId
        ? ('<div class="iac-discuss-embed" data-iac-discuss-embed data-topic-id="' + esc(discussTopicId) + '" data-post-id="' + esc(discussPostId) + '">' +
             '<div class="iac-discuss-embed-loading">Loading preview…</div>' +
           '</div>')
        : '';

      // Repost chains can nest repost -> repost -> original post. Resolve to the first
      // non-repost so the nested card shows real content.
      function resolveOriginal(post){
        let cur = post;
        let guard = 0;
        while (cur && (cur.type === 'repost' || cur.type === 'mention') && cur.parent_post && guard < 8){
          cur = cur.parent_post;
          guard++;
        }
        return cur || post;
      }

      const repostLine = (()=>{
        if (!isRepost) return '';
        if (p.type === 'mention') {
          return '<div class="iac-card-text iac-repostline">Mentioned you</div>';
        }
        const sameWall = (parseInt(p.wall_owner_phpbb_id||0,10)||0) === (parseInt(p.author_phpbb_id||0,10)||0);
        return '<div class="iac-card-text iac-repostline">' + (sameWall ? 'Shared a post' : 'Shared a post with you') + '</div>';
      })();

      let nested = '';
      if (isRepost){
        const op = resolveOriginal(p.parent_post);
	    const opBodyRaw = (op.body||'');
	    const opEmbeds = renderVideoEmbedsFromText(opBodyRaw);
	    const opLinkCards = renderLinkCardsFromText(opBodyRaw);
	    const opBodyClean = stripEmbeddableVideoUrls(opBodyRaw);
        nested = '<div class="iac-card" style="margin-top:10px;opacity:.98">' +
          '<div class="iac-card-head"><img class="iac-card-ava" src="' + esc(op.author_avatar||BLANK_AVA) + '" alt="" />' +
          '<div class="iac-card-hmeta"><div class="iac-card-author">' + userLinkHtml(op.author||'User', op.author_phpbb_id, op.author_username) + '</div>' +
          '<div class="iac-card-time">Shared post · #' + esc(op.id) + '</div></div></div>' +
          '<div class="iac-card-body">' +
            (op.title ? '<div class="iac-card-title">' + esc(op.title) + '</div>' : '') +
	        (opBodyClean ? '<div class="iac-card-text">' + linkMentions(opBodyClean) + '</div>' : '') +
            (opEmbeds || '') +
            (opLinkCards || '') +
            renderAttachmentGallery(op.attachments||[]) +
          '</div></div>';
      }

      const gallery = (!isRepost ? renderAttachmentGallery(p.attachments||[]) : '');

      const preview = (p.comments_preview||[]).map(c=>{
        c = c || {};
        c.post_id = p.id;
        c.parent_comment_id = 0;
        return renderMiniComment(c, false);
      }).join('');

      // Prefer server-side permission flag when available. This avoids mismatches
      // if legacy rows have missing author_wp_id.
      const canEditPost = (typeof p.can_edit !== 'undefined')
        ? !!p.can_edit
        : !!(IS_ADMIN || (ME_ID && (parseInt(p.author_wp_id||0,10)||0) === ME_ID));
      const icoDots = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12a2 2 0 1 0 4 0 2 2 0 0 0-4 0Zm5 0a2 2 0 1 0 4 0 2 2 0 0 0-4 0Zm5 0a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z" fill="currentColor"/></svg>';
      const icoEdit = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75Z" fill="currentColor"/></svg>';
      const icoTrash = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 14H7L6 7Zm3-3h6l1 2H8l1-2Zm-4 2h14v2H5V6Z" fill="currentColor"/></svg>';
      const postMenu = canEditPost ? (
        '<button type="button" class="iac-menu-btn" data-iac-menu aria-label="Menu" title="Menu">' + icoDots + '</button>' +
        '<div class="iac-menu" hidden>' +
          '<button type="button" class="iac-menu-item" data-iac-edit>' + icoEdit + ' Edit</button>' +
          '<button type="button" class="iac-menu-item" data-iac-delete>' + icoTrash + ' Delete</button>' +
        '</div>'
      ) : '';

      const inModal = (opts.mode === 'modal');

	      // In feed view, add direct edit/delete action icons for the opening post.
	      // (Users requested these to appear alongside the other card action icons.)
	      const postEditActions = (!inModal && canEditPost) ? (
	        '<button type="button" class="iac-act-ico iac-act-right" data-iac-edit aria-label="Edit" title="Edit">' +
	          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75Z" fill="currentColor"/></svg>' +
	        '</button>' +
	        '<button type="button" class="iac-act-ico" data-iac-delete aria-label="Delete" title="Delete">' +
	          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 14H7L6 7Zm3-3h6l1 2H8l1-2Zm-4 2h14v2H5V6Z" fill="currentColor"/></svg>' +
	        '</button>'
	      ) : '';

      const actions = inModal
        ? ''
        : (
            '<button type="button" class="iac-act-ico" data-iac-comment-open aria-label="Comments" title="Comments">' +
              '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>' +
              '<span class="iac-act-badge">' + esc(p.comment_count||0) + '</span>' +
            '</button>' +
            '<button type="button" class="iac-act-ico" data-iac-share aria-label="Share" title="Share">' +
              '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
              '<path d="M16 6l-4-4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
              '<path d="M12 2v13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
            '</button>' +
            '<button type="button" class="iac-act-ico" data-iac-open aria-label="Open" title="Open">' +
              '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
              '<path d="M10 14L21 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
              '<path d="M21 14v6a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
            '</button>' +
            '<button type="button" class="iac-act-ico' + (p.i_following ? ' is-following' : '') + '" data-iac-follow aria-label="' + (p.i_following ? 'Unfollow' : 'Follow') + '" title="' + (p.i_following ? 'Unfollow' : 'Follow') + '" data-following="' + (p.i_following ? '1' : '0') + '">' +
              '<svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
                '<circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/>' +
                '<path class="iac-follow-plus" d="M19 8v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
                '<path class="iac-follow-plus" d="M16 11h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
                '<path class="iac-follow-minus" d="M16 11h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
              '</svg>' +
            '</button>'
	            + postEditActions
          );

      return (
        '<article class="iac-card" data-post-id="' + esc(p.id) + '" data-author-wp-id="' + esc(p.author_wp_id||0) + '" data-comment-count="' + esc(p.comment_count||0) + '" data-post-type="' + esc(p.type||'') + '" data-parent-post-id="' + esc((p.parent_post && p.parent_post.id) ? p.parent_post.id : (p.parent_post_id||0)) + '" data-shared-tab="' + esc(p.shared_tab||'') + '" data-shared-ref="' + esc(p.shared_ref||'') + '">' +
          '<div class="iac-card-head">' +
            '<img class="iac-card-ava" src="' + esc(p.author_avatar||BLANK_AVA) + '" alt="" />' +
            '<div class="iac-card-hmeta">' +
              '<div class="iac-card-author">' + userLinkHtml(p.author||'User', p.author_phpbb_id, p.author_username) + (isDiscussShare ? discussShareHeader : '') + '</div>' +
              '<div class="iac-card-time">' + when + '</div>' +
            '</div>' +
            postMenu +
          '</div>' +
          '<div class="iac-card-body">' +
            repostLine +
            (isDiscussShare ? '' : (title ? '<div class="iac-card-title">' + title + '</div>' : '')) +
            (body ? '<div class="iac-card-text">' + body + '</div>' : '') +
            (isDiscussShare ? discussEmbed : (embeds || '')) +
            (isDiscussShare ? '' : (linkCards || '')) +
            gallery +
            nested +
          '</div>' +
          (actions ? ('<div class="iac-card-actions">' + actions + '</div>') : '') +
          '<div class="iac-comments" hidden>' +
            '<div class="iac-comments-preview" data-iac-preview-count="' + esc((p.comments_preview||[]).length) + '">' + preview + '</div>' +
            ((inModal || (parseInt(p.comment_count||0,10)||0) <= (p.comments_preview||[]).length) ? '' : ('<button type="button" class="iac-loadmore" data-iac-loadmore data-offset="' + esc((p.comments_preview||[]).length) + '">Load more</button>')) +
            '<div class="iac-comment-form">' +
              '<textarea class="iac-comment-input" rows="1" placeholder="Write a comment..."></textarea>' +
              '<button type="button" class="iac-comment-send">Send</button>' +
            '</div>' +
          '</div>' +
        '</article>'
      );
    }

    async function hydrateDiscussEmbeds(scope){
      // Render Discuss topic-view post HTML inside Connect cards for shares.
      // Uses Discuss's existing public topic endpoint and topic renderer.
      const API = (window.IA_DISCUSS_API && typeof window.IA_DISCUSS_API.post === 'function') ? window.IA_DISCUSS_API : null;
      const R = window.IA_DISCUSS_TOPIC_RENDER;
      if (!API || !R || typeof R.renderPostHTML !== 'function') return;

      const nodes = qsa(scope, '[data-iac-discuss-embed]');
      if (!nodes.length) return;

      // Simple in-memory cache per page load.
      scope.__iacDiscussCache = scope.__iacDiscussCache || {};
      const cache = scope.__iacDiscussCache;

      for (const el of nodes){
        if (el.__iacHydrated) continue;
        el.__iacHydrated = true;
        const tid = parseInt(el.getAttribute('data-topic-id')||'0',10)||0;
        const pid = parseInt(el.getAttribute('data-post-id')||'0',10)||0;
        if (!tid) { el.innerHTML = ''; continue; }

        let topic = cache['t:'+tid];
        if (!topic){
          try {
            const res = await API.post('ia_discuss_topic', { topic_id: tid, offset: 0 });
            if (!res || !res.success || !res.data) throw res;
            topic = res.data;
            cache['t:'+tid] = topic;
          } catch(e){
            el.innerHTML = '<div class="iac-discuss-embed-loading">Preview unavailable.</div>';
            continue;
          }
        }

        const posts = (topic.posts || topic.items || topic.data || []);
        let post = null;
        if (pid > 0){
          for (const p of posts){ if (p && (parseInt(p.post_id||p.id||0,10)||0) === pid){ post = p; break; } }
        }
        if (!post && posts && posts.length) post = posts[0];
        if (!post){ el.innerHTML = '<div class="iac-discuss-embed-loading">Preview unavailable.</div>'; continue; }

        // Render an extract (200 words) rather than the full post body.
        function stripHtmlToText(html){
          try {
            const tmp = document.createElement('div');
            tmp.innerHTML = String(html||'');
            return (tmp.textContent || tmp.innerText || '').replace(/\s+/g,' ').trim();
          } catch(e){
            return String(html||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
          }
        }
        function firstWords(text, n){
          const t = String(text||'').trim();
          if (!t) return '';
          const parts = t.split(/\s+/);
          if (parts.length <= n) return t;
          return parts.slice(0, n).join(' ') + '…';
        }
        const postHtml = (post.content_html != null) ? post.content_html : (post.html != null ? post.html : '');

        // Preserve a single inline video embed (youtube/peertube/iframe/video) in the preview.
        // Topic view renders these nicely; when we build a text excerpt we must not strip them.
        let videoHtml = '';
        let postHtmlWithoutVideo = String(postHtml || '');
        try {
          const tmp = document.createElement('div');
          tmp.innerHTML = String(postHtml || '');
          const iframe = tmp.querySelector('iframe');
          const vid = tmp.querySelector('video');
          const embed = tmp.querySelector('.iad-embed-video, [data-ia-embed="video"]');
          const pick = iframe || vid || embed;
          if (pick) {
            videoHtml = String(pick.outerHTML || '');
            try { pick.remove(); } catch(_) {}
            postHtmlWithoutVideo = String(tmp.innerHTML || '');
          }
        } catch(e){
          videoHtml = '';
          postHtmlWithoutVideo = String(postHtml || '');
        }

        const postText = stripHtmlToText(postHtmlWithoutVideo);
        const excerpt200 = firstWords(postText, 200);
        const excerptHtml = excerpt200 ? ('<p>' + esc(excerpt200) + '</p>') : '';
        const combinedHtml = (videoHtml ? (videoHtml + excerptHtml) : excerptHtml);
        const postForRender = Object.assign({}, post, { content_html: combinedHtml });

        // Render with topic view HTML and hide action buttons via CSS.
        try {
          const html = R.renderPostHTML(postForRender, 0, 0, {});
          el.innerHTML = '<div class="iac-discuss-embed-inner">' + html + '</div>';
        } catch(e){
          el.innerHTML = '<div class="iac-discuss-embed-loading">Preview unavailable.</div>';
          continue;
        }

        // Make the whole embed clickable to open Discuss.
        el.addEventListener('click', (ev)=>{
          const a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
          if (a) return; // allow normal links inside
          try {
            const u = new URL(location.href);
            u.searchParams.set('tab','discuss');
            u.searchParams.set('ia_tab','discuss');
            u.searchParams.set('iad_view','topic');
            u.searchParams.set('iad_topic', String(tid));
            if (pid) u.searchParams.set('iad_post', String(pid));
            location.href = u.toString();
          } catch(_){ }
        });
      }
    }

    function hydrateGalleries(scope){
      qsa(scope, '[data-iac-gallery]').forEach(g=>{
        if (g.__iacHydrated) return;
        g.__iacHydrated = true;
        let items = [];
        try{ items = JSON.parse(g.getAttribute('data-items')||'[]') || []; }catch(e){}
        let idx = 0;
        const media = qs(g, '[data-iac-g-media]');
        const count = qs(g, '[data-iac-g-count]');

        function render(){
          if (!media) return;
          const it = items[idx];
          media.innerHTML = '';
          if (!it) return;
          if (it.kind === 'video'){
            const v = document.createElement('video');
            v.src = it.url;
            v.controls = true;
            v.playsInline = true;
            v.setAttribute('data-iac-view', it.url);
            media.appendChild(v);
          } else if (it.kind === 'image'){
            const img = document.createElement('img');
            img.src = it.url;
            img.alt = '';
            img.setAttribute('data-iac-view', it.url);
            media.appendChild(img);
          } else {
            const a = document.createElement('a');
            a.href = it.url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = it.name || 'Open file';
            a.style.color = 'var(--iac-ac)';
            media.appendChild(a);
          }
          if (count) count.textContent = (idx+1) + ' / ' + items.length;
        }

        function safeNav(fn){
          const se = document.scrollingElement || document.documentElement;
          const st = se ? se.scrollTop : (window.pageYOffset||0);
          try{ fn(); } finally {
            // Prevent browser scroll anchoring/focus adjustments when media swaps.
            const restore = ()=>{ if (se) se.scrollTop = st; else window.scrollTo(0, st); };
            requestAnimationFrame(()=>{
              restore();
              requestAnimationFrame(restore);
              setTimeout(restore, 120);
            });
          }
        }

        function prev(){ idx = (idx - 1 + items.length) % items.length; safeNav(render); }
        function next(){ idx = (idx + 1) % items.length; safeNav(render); }

        const p = qs(g, '[data-iac-g-prev]');
        const n = qs(g, '[data-iac-g-next]');
        const bindNav = (el, fn)=>{
          if (!el) return;
          el.addEventListener('pointerdown', (e)=>{ e.preventDefault(); e.stopPropagation(); }, {passive:false});
          el.addEventListener('mousedown', (e)=>{ e.preventDefault(); e.stopPropagation(); });
          el.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); try{ el.blur && el.blur(); }catch(_){} fn(); });
        };
        bindNav(p, prev);
        bindNav(n, next);

        // Touch swipe
        let sx = 0; let dx = 0;
        g.addEventListener('touchstart', (e)=>{ sx = e.touches[0]?.clientX || 0; dx = 0; }, {passive:true});
        g.addEventListener('touchmove', (e)=>{ dx = (e.touches[0]?.clientX || 0) - sx; }, {passive:true});
        g.addEventListener('touchend', ()=>{
          if (Math.abs(dx) > 40){ dx < 0 ? next() : prev(); }
          sx = 0; dx = 0;
        });

        render();
      });
    }

    async function loadPosts(){
      if (loading) return;
      loading = true;
      if (loadMoreBtn) loadMoreBtn.disabled = true;
      try{
        const r = await postForm('ia_connect_post_list', {
          nonce: nonces.post_list||'',
          wall_wp: wallWp,
          wall_phpbb: wallPhpbb,
          before_id: oldestId||0,
          limit: 10
        });
        if (!r || !r.success) throw r;
        const posts = r.data.posts || [];
        if (posts.length){
          const html = posts.map(renderCard).join('');
          feedInner.insertAdjacentHTML('beforeend', html);
          hydrateGalleries(feedInner);
          hydrateDiscussEmbeds(feedInner);
          // track oldest
          posts.forEach(p=>{ if (p && p.id) oldestId = oldestId ? Math.min(oldestId, p.id) : p.id; });
        } else {
          if (loadMoreBtn) loadMoreBtn.textContent = 'No more';
        }
      } catch(e){
        // silent
      } finally {
        loading = false;
        if (loadMoreBtn) loadMoreBtn.disabled = false;
      }
    }

    if (loadMoreBtn) loadMoreBtn.addEventListener('click', ()=>loadPosts());
    // initial load
    loadPosts();

    // Deep link to a post (optionally scroll/highlight a comment)
    try{
      const u = new URL(location.href);
      const pid = parseInt(u.searchParams.get('ia_post')||'0',10)||0;
      const cid = parseInt(u.searchParams.get('ia_comment')||'0',10)||0;
      if (pid > 0) openPostModal(pid, false, cid>0 ? cid : null);
    }catch(_){ }

    // Composer
    const postBtn = qs(root, '[data-iac-post]');
    const titleEl = qs(root, '[data-iac-title]');
    const bodyEl  = qs(root, '[data-iac-body]');
    const filesEl = qs(root, '[data-iac-files]');
    const metaEl  = qs(root, '[data-iac-files-meta]');
    const prevEl  = qs(root, '[data-iac-preview]');
    let picked = [];

    // Mentions on composer + modal comment box
    attachMention(bodyEl);
    attachMention(postCommentInput);

    // Discuss-like behavior: allow multi-line replies in the fullscreen post view.
    // Enter inserts a newline; sending happens via the Send button.
    if (postCommentInput){
      postCommentInput.addEventListener('keydown', (e)=>{
        if (e.key === 'Enter'){
          // Don't let any outer/global handlers treat Enter as "send".
          e.stopPropagation();
        }
      });
    }

    function refreshPreview(){
      if (!metaEl || !prevEl) return;
      metaEl.textContent = picked.length ? (picked.length + ' file' + (picked.length>1?'s':'') + ' selected') : '';
      prevEl.innerHTML = '';
      if (!picked.length){ prevEl.hidden = true; return; }
      prevEl.hidden = false;
      picked.slice(0,12).forEach(f=>{
        const box = document.createElement('div');
        box.className = 'iac-prev';
        const t = (f.type||'').toLowerCase();
        if (t.startsWith('image/')){
          const img = document.createElement('img');
          img.src = URL.createObjectURL(f);
          box.appendChild(img);
        } else if (t.startsWith('video/')){
          const v = document.createElement('video');
          v.src = URL.createObjectURL(f);
          v.muted = true;
          v.playsInline = true;
          box.appendChild(v);
        } else {
          box.textContent = (f.name||'file').slice(0,12);
        }
        prevEl.appendChild(box);
      });
    }

    if (filesEl){
      filesEl.addEventListener('change', ()=>{
        picked = filesEl.files ? Array.from(filesEl.files) : [];
        refreshPreview();
      });
    }

    if (postBtn){
      postBtn.addEventListener('click', async ()=>{
        const title = String(titleEl?.value||'').trim();
        const body = String(bodyEl?.value||'').trim();
        if (!title && !body && !picked.length) return;

        postBtn.disabled = true;
        try{
          const r = await postForm('ia_connect_post_create', {
            nonce: nonces.post_create||'',
            wall_wp: wallWp,
            wall_phpbb: wallPhpbb,
            title,
            body
          }, picked);
          if (!r || !r.success) throw r;
          const p = r.data.post;
          // prepend
          feedInner.insertAdjacentHTML('afterbegin', renderCard(p));
          hydrateGalleries(feedInner);
          hydrateDiscussEmbeds(feedInner);

          // reset
          if (titleEl) titleEl.value = '';
          if (bodyEl) bodyEl.value = '';
          picked = [];
          if (filesEl) filesEl.value = '';
          refreshPreview();
        } catch(e){
          alert(e?.data?.message || 'Post failed');
        } finally {
          postBtn.disabled = false;
        }
      });
    }

    // Card actions: comment open, share, open
    feedInner.addEventListener('click', async (e)=>{
      const card = e.target.closest('.iac-card[data-post-id]');
      if (!card) return;
      const pid = parseInt(card.getAttribute('data-post-id')||'0',10)||0;

      // Comment-level actions (reply/edit/delete)
      const cEl = e.target.closest('.iac-comment[data-comment-id]');
      if (cEl && e.target.closest('[data-iac-comment-reply]')){
        e.preventDefault();
        const postId = parseInt(cEl.getAttribute('data-post-id')||String(pid),10)||pid;
        const cid = parseInt(cEl.getAttribute('data-comment-id')||'0',10)||0;
        if (postId && cid){
          const last = findLastReplyEl(cEl);
          ensureInlineReplyBox(last || cEl, postId, cid);
        }
        return;
      }
      if (cEl && e.target.closest('[data-iac-comment-edit]')){
        e.preventDefault();
        const cid = parseInt(cEl.getAttribute('data-comment-id')||'0',10)||0;
        const tEl = qs(cEl, '.iac-comment-t');
        if (!tEl) return;
        if (cEl.__iacEditing) return;
        cEl.__iacEditing = true;
        const cur = (tEl.textContent||'').trim();
        tEl.style.display = 'none';
        const bub = qs(cEl, '.iac-comment-bub') || cEl;
        const actions = qs(cEl, '.iac-comment-actions');
        if (actions) actions.style.display = 'none';

        const editor = mountInlineEditor(bub, [
          {type:'textarea', key:'body', value: cur, placeholder: 'Edit your comment...'}
        ], async (payload)=>{
          const r = await postForm('ia_connect_comment_update', {
            nonce: nonces.comment_update||'',
            comment_id: cid,
            body: payload.body
          });
          if (!r || !r.success) throw r;
          const nextBody = (r.data.comment && r.data.comment.body) ? r.data.comment.body : payload.body;
          tEl.innerHTML = linkMentions(nextBody);
          hydrateMentions(cEl);
        });

        const restore = ()=>{
          cEl.__iacEditing = false;
          tEl.style.display = '';
          if (actions) actions.style.display = '';
        };
        if (!editor){ restore(); return; }
        const cancelBtn = qs(editor, '.iac-inline-cancel');
        cancelBtn && cancelBtn.addEventListener('click', ()=>{ restore(); });
        const saveBtn = qs(editor, '.iac-inline-save');
        saveBtn && saveBtn.addEventListener('click', ()=>{ restore(); });
        return;
      }
      if (cEl && e.target.closest('[data-iac-comment-delete]')){
        e.preventDefault();
        const cid = parseInt(cEl.getAttribute('data-comment-id')||'0',10)||0;
        const ok = await openConfirm('Delete this comment?', 'Delete comment', 'Delete', 'Cancel');
        if (!ok) return;
        try{
          const r = await postForm('ia_connect_comment_delete', {
            nonce: nonces.comment_delete||'',
            comment_id: cid
          });
          if (!r || !r.success) throw r;
          const tEl = qs(cEl, '.iac-comment-t');
          if (tEl) tEl.innerHTML = '<em>Deleted</em>';
          cEl.classList.add('is-deleted');
          qsa(cEl, '.iac-comment-actions .iac-cact').forEach(b=>b.remove());
        }catch(err){
          alert(err?.data?.message || 'Delete failed');
        }
        return;
      }


if (e.target.closest('[data-iac-open]')){
  e.preventDefault();
  const ptype = (card.getAttribute('data-post-type')||'');
  if (ptype === 'mention' || ptype === 'repost'){
    const parentId = parseInt(card.getAttribute('data-parent-post-id')||'0',10)||0;
    if (parentId > 0){
      let cid = 0;
      if (ptype === 'mention'){
        const ref = (card.getAttribute('data-shared-ref')||'');
        const m = ref.match(/mention\s*:\s*(\d+)/i);
        if (m) cid = parseInt(m[1],10)||0;
      }
      openPostModal(parentId, true, cid);
      return;
    }
  }
  openPostModal(pid, true);
}

      if (e.target.closest('[data-iac-comment-open]')){
        const comm = qs(card, '.iac-comments');
        if (comm) {
          comm.hidden = !comm.hidden;
          // When opening, replace the simple preview with a threaded view (top+replies).
          if (!comm.hidden && !comm.__iacThreaded){
            comm.__iacThreaded = true;
            try{
              const box = qs(card, '.iac-comments-preview');
              const previewCount = parseInt(box?.getAttribute('data-iac-preview-count')||'0',10)||0;
              if (box && previewCount){
                const r = await postForm('ia_connect_comments_page', {
                  nonce: nonces.comments_page||'',
                  post_id: pid,
                  offset: 0,
                  limit: previewCount
                });
                if (r && r.success){
                  const top = (r.data && r.data.top) ? r.data.top : [];
                  const repliesBy = (r.data && r.data.replies_by_parent) ? r.data.replies_by_parent : {};
                  box.innerHTML = '';
                  top.forEach(c=>{
                    box.insertAdjacentHTML('beforeend', renderMiniComment(c, false));
                    const reps = repliesBy[String(c.id)] || repliesBy[c.id] || [];
                    reps.forEach(rc=> box.insertAdjacentHTML('beforeend', renderMiniComment(rc, true)));
                  });
                  const btn = qs(card, '[data-iac-loadmore]');
                  if (btn && r.data && typeof r.data.next_offset !== 'undefined'){
                    btn.setAttribute('data-offset', String(parseInt(r.data.next_offset,10)||0));
                  }
                }
              }
            }catch(_){ }
          }
        }
      }



if (e.target.closest('[data-iac-follow]')) {
  e.preventDefault();
  const btn = e.target.closest('[data-iac-follow]');
  // Toggle based on state rather than button text (icons don't have text).
  const isFollowing = !!(btn && (btn.getAttribute('data-following') === '1' || btn.classList.contains('is-following')));
  const follow = isFollowing ? 0 : 1;
  try{
    const r = await postForm('ia_connect_follow_toggle', {
      nonce: nonces.follow_toggle||'',
      post_id: pid,
      follow: follow
    });
    if (r && r.success){
      const following = !!(r.data && r.data.following);
      if (btn){
        btn.setAttribute('data-following', following ? '1' : '0');
        btn.setAttribute('aria-label', following ? 'Unfollow' : 'Follow');
        btn.setAttribute('title', following ? 'Unfollow' : 'Follow');
        btn.classList.toggle('is-following', following);
      }
    }
  }catch(_){}
}

if (e.target.closest('[data-iac-loadmore]')) {
  e.preventDefault();
  const btn = e.target.closest('[data-iac-loadmore]');
  if (!btn) return;
  btn.disabled = true;
  const offset = parseInt(btn.getAttribute('data-offset')||'0',10)||0;
  try{
    const r = await postForm('ia_connect_comments_page', {
      nonce: nonces.comments_page||'',
      post_id: pid,
      offset: offset,
      limit: 15
    });
    if (!r || !r.success) throw r;
    const top = (r.data && r.data.top) ? r.data.top : [];
    const repliesBy = (r.data && r.data.replies_by_parent) ? r.data.replies_by_parent : {};
    const box = qs(card, '.iac-comments-preview');
    if (box){
      top.forEach(c=>{
        box.insertAdjacentHTML('beforeend', renderMiniComment(c, false));
        const reps = repliesBy[String(c.id)] || repliesBy[c.id] || [];
        reps.forEach(rc=>{
          box.insertAdjacentHTML('beforeend', renderMiniComment(rc, true));
        });
      });
      const nextOffset = (r.data && r.data.next_offset) ? parseInt(r.data.next_offset,10)||0 : (offset + top.length);
      btn.setAttribute('data-offset', String(nextOffset));
      // Hide if we loaded all (best-effort: compare to comment_count on card)
      const total = parseInt((card.__iac_comment_count || card.getAttribute('data-comment-count') || '0'),10)||0;
      if (total && nextOffset >= total) btn.remove();
    }
  }catch(_){
  }finally{
    try{ btn.disabled = false; }catch(_){}
  }
}

if (e.target.closest('[data-iac-menu]')) {
  e.preventDefault();
  const menu = qs(card, '.iac-menu');
  if (menu) menu.hidden = !menu.hidden;
}

if (e.target.closest('[data-iac-edit]')) {
  e.preventDefault();
  const menu = qs(card, '.iac-menu'); if (menu) menu.hidden = true;
  const titleEl = qs(card, '.iac-card-title');
  const textEl = qs(card, '.iac-card-text');
  const curTitle = (titleEl?.textContent || '').trim();
  const curBody = (textEl?.textContent || '').trim();
  const bodyBox = qs(card, '.iac-card-body') || card;
  if (card.__iacEditing) return;
  card.__iacEditing = true;
  if (titleEl) titleEl.style.display = 'none';
  if (textEl) textEl.style.display = 'none';

  const editor = mountInlineEditor(bodyBox, [
    {type:'input', key:'title', value: curTitle, placeholder: 'Title'},
    {type:'textarea', key:'body', value: curBody, placeholder: 'Write your post...'}
  ], async (payload)=>{
    const r = await postForm('ia_connect_post_update', {
      nonce: nonces.post_update||'',
      post_id: pid,
      title: payload.title,
      body: payload.body
    });
    if (r && r.success && r.data && r.data.post){
      const p2 = r.data.post;
      const html = renderCard(p2, {mode:'feed'});
      card.outerHTML = html;
    } else {
      throw r;
    }
  });

  const restore = ()=>{
    card.__iacEditing = false;
    if (titleEl) titleEl.style.display = '';
    if (textEl) textEl.style.display = '';
  };
  if (!editor){ restore(); return; }
  const cancelBtn = qs(editor, '.iac-inline-cancel');
  cancelBtn && cancelBtn.addEventListener('click', ()=>{ restore(); });
  const saveBtn = qs(editor, '.iac-inline-save');
  saveBtn && saveBtn.addEventListener('click', ()=>{ restore(); });
}

if (e.target.closest('[data-iac-delete]')) {
  e.preventDefault();
  const menu = qs(card, '.iac-menu'); if (menu) menu.hidden = true;
  const ok = await openConfirm('Delete this post?', 'Delete post', 'Delete', 'Cancel');
  if (!ok) return;
  try{
    const r = await postForm('ia_connect_post_delete', {
      nonce: nonces.post_delete||'',
      post_id: pid
    });
    if (r && r.success){
      card.remove();
    }
  }catch(err){
    showToast(err?.data?.message || 'Delete failed');
  }
}

      if (e.target.closest('[data-iac-share]')){
        e.preventDefault();
        openShareModal(pid);
      }

      if (e.target.closest('.iac-comment-send')){
        const input = qs(card, '.iac-comment-input');
        const txt = String(input?.value||'').trim();
        if (!txt) return;
        try{
          const r = await postForm('ia_connect_comment_create', {
            nonce: nonces.comment_create||'',
            post_id: pid,
            parent_comment_id: 0,
            body: txt
          });
          if (!r || !r.success) throw r;
          const c = r.data.comment;
          const box = qs(card, '.iac-comments-preview');
          if (box){
            c.post_id = pid;
            c.parent_comment_id = 0;
            box.insertAdjacentHTML('beforeend', renderMiniComment(c, false));
          }
          if (input) input.value = '';
        } catch(err){
          alert(err?.data?.message || 'Comment failed');
        }
      }

      // If the user clicked the card (not a control/input/media), open the fullscreen post modal.
      if (!e.defaultPrevented &&
          !e.target.closest('button, a, input, textarea, select, [data-iac-view], .iac-mention, [data-iac-userlink]')){
        openPostModal(pid, true);
      }
    });

    feedInner.addEventListener('focusin', (e)=>{
      const el = e.target;
      if (el && el.classList && el.classList.contains('iac-comment-input')) {
        attachMention(el);
      }
    });

    // Discuss-like behavior for inline reply boxes: allow multi-line comments.
    // Enter inserts newline; sending happens via the Send button.
    feedInner.addEventListener('keydown', (e)=>{
      const el = e.target;
      if (!el || !el.classList || !el.classList.contains('iac-comment-input')) return;
      if (e.key === 'Enter'){
        e.stopPropagation();
      }
    });

    // Clicking attachment media should open viewer
    feedInner.addEventListener('click', (e)=>{
      const el = e.target.closest('[data-iac-view]');
      if (!el) return;
      // Prevent opening viewer for cover/avatar handled elsewhere? this is inside feed.
      const src = el.getAttribute('data-iac-view');
      if (!src) return;
      e.preventDefault();
      openViewer(src);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();

// Account settings (Connect → Settings tab)
(function(){
  "use strict";
  function qs(sel){ return document.querySelector(sel); }
  function setStatus(el, msg, isErr){
    if (!el) return;
    el.textContent = String(msg||"");
    el.style.opacity = msg ? "1" : "0";
    el.style.color = isErr ? "var(--iac-red, #ff6b6b)" : "var(--iac-muted, #bdbdbd)";
  }
  async function post(action, data){
    const cfg = window.IA_CONNECT || {};
    const url = cfg.ajaxUrl || (window.ajaxurl || "/wp-admin/admin-ajax.php");
    const fd = new FormData();
    fd.append("action", action);
    Object.keys(data||{}).forEach(k=>fd.append(k, data[k]));
    const res = await fetch(url, { method:"POST", credentials:"same-origin", body:fd });
    const txt = await res.text();
    let json;
    try{ json = JSON.parse(txt); }catch(_){ throw new Error("Non-JSON response"); }
    if (!json || !json.success) throw new Error((json && json.data && json.data.message) ? json.data.message : "Request failed");
    return json.data || {};
  }

  function applyStyle(style){
    const raw = String(style || 'default').trim().toLowerCase();
    const allowed = ['default','black','calm','dawn','earth','flame','leaf','night','sun','twilight','water'];
    const next = allowed.indexOf(raw) !== -1 ? raw : 'default';
    try{ document.body.setAttribute('data-iac-style', next); }catch(_){}
    try{ document.documentElement.setAttribute('data-iac-style', next); }catch(_){}
    const shell = document.getElementById('ia-atrium-shell');
    try{ if (shell) shell.setAttribute('data-iac-style', next); }catch(_){}
    document.querySelectorAll('[data-iac-style]').forEach(function(el){ el.setAttribute('data-iac-style', next); });
    try{ document.dispatchEvent(new CustomEvent('ia:connect-style-changed', { detail:{ style: next } })); }catch(_){}
    return next;
  }

  function init(){
    const cfg = window.IA_CONNECT || {};
    const nonces = (cfg.nonces || {});

    applyStyle((cfg.me && cfg.me.style) ? cfg.me.style : 'default');

    const chkDeact = qs("[data-iac-acct-deactivate-confirm]");
    const btnDeact = qs("[data-iac-acct-deactivate]");
    if (chkDeact && btnDeact){
      chkDeact.addEventListener("change", ()=>{ btnDeact.disabled = !chkDeact.checked; });
    }

    const chkDel = qs("[data-iac-acct-delete-confirm]");
    const btnDel = qs("[data-iac-acct-delete]");
    if (chkDel && btnDel){
      chkDel.addEventListener("change", ()=>{ btnDel.disabled = !chkDel.checked; });
    }

    // Password reset is handled by IA Auth (ia_auth_forgot). Connect simply triggers the existing flow.
    // Display name (user setting)
    const dnInput = qs("[data-iac-displayname-input]");
    const dnSave  = qs("[data-iac-displayname-save]");
    const dnStat  = qs("[data-iac-displayname-status]");
    if (dnInput){
      try{ dnInput.value = String((cfg.me && cfg.me.display) ? cfg.me.display : ((cfg.me && cfg.me.login) ? cfg.me.login : "")).trim(); }catch(_){}
    }

    // Signature (bio)
    const sigInput = qs("[data-iac-signature-input]");
    const sigShow  = qs("[data-iac-signature-show-discuss]");
    if (sigInput){
      try{ sigInput.value = String((cfg.me && cfg.me.signature) ? cfg.me.signature : ""); }catch(_){}
    }
    if (sigShow){
      try{ sigShow.checked = String((cfg.me && cfg.me.signature_show_discuss) ? cfg.me.signature_show_discuss : 0) === "1"; }catch(_){}
    }
    const homeTabInput = qs("[data-iac-home-tab-input]");
    if (homeTabInput){
      try{ homeTabInput.value = String((cfg.me && cfg.me.home_tab) ? cfg.me.home_tab : "connect"); }catch(_){}
    }
    const styleInput = qs("[data-iac-style-input]");
    if (styleInput){
      try{ styleInput.value = String((cfg.me && cfg.me.style) ? cfg.me.style : "default"); }catch(_){}
    }
    // Note: the Atrium SPA can replace this settings panel after init().
    // The save handler is bound via delegated click (see below).

    const btnReset = qs("[data-iac-acct-reset]");
    if (btnReset){
      btnReset.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-reset-status]");
        const me = (cfg.me || {});
        const identifier = (me.email || me.login || "");
        setStatus(st, "Sending reset email…", false);

        // IA Auth provides its own nonce.
        const authNonce = (window.IA_AUTH && window.IA_AUTH.nonce) ? String(window.IA_AUTH.nonce) : "";
        if (!authNonce){
          return setStatus(st, "IA Auth nonce not found on the page.", true);
        }
        if (!identifier){
          return setStatus(st, "Cannot determine your account email.", true);
        }

        try{
          // ia-auth ajax_forgot expects POST field "login".
          // It sets $_POST['user_login'] and calls retrieve_password().
          const r = await post("ia_auth_forgot", { nonce: authNonce, login: identifier, redirect_to: location.href });
          setStatus(st, (r && r.message) ? r.message : "If an account exists, a reset email has been sent.", false);
        }catch(e){
          // IA Auth returns generic success; if we got here, it was likely a nonce or transport issue.
          setStatus(st, e.message || "Request failed.", true);
        }
      });
    }

    const btnExport = qs("[data-iac-acct-export]");
    if (btnExport){
      btnExport.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-export-status]");
        setStatus(st, "Generating export…", false);
        try{
          // Server action is ia_connect_export_data, nonce key is export_data.
          const r = await post("ia_connect_export_data", { nonce: nonces.export_data||"" });
          if (r && r.url){
            setStatus(st, "Export ready. Downloading…", false);
            window.location.href = r.url;
          } else {
            setStatus(st, "Export generated.", false);
          }
        }catch(e){
          setStatus(st, e.message || "Export failed.", true);
        }
      });
    }

    if (btnDeact){
      btnDeact.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-deactivate-status]");
        setStatus(st, "Deactivating…", false);
        try{
          const r = await post("ia_connect_account_deactivate", { nonce: nonces.account_deactivate||"" });
          setStatus(st, r.message || "Deactivated.", false);
          // Force reload to show logged-out / auth modal state
          setTimeout(()=>{ try{ location.reload(); }catch(_){ } }, 800);
        }catch(e){
          setStatus(st, e.message || "Deactivation failed.", true);
        }
      });
    }

    if (btnDel){
      btnDel.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-delete-status]");
        const pass = (qs("[data-iac-acct-delete-pass]")||{}).value || "";
        setStatus(st, "Deleting…", false);
        if (!pass) return setStatus(st, "Enter your current password.", true);
        try{
          const r = await post("ia_connect_account_delete", { nonce: nonces.account_delete||"", current_password: pass, delete_peertube: 0 });
          setStatus(st, r.message || "Deleted.", false);
          setTimeout(()=>{ try{ location.reload(); }catch(_){ } }, 800);
        }catch(e){
          setStatus(st, e.message || "Delete failed.", true);
        }
      });
    }
  }

  // Delegated handler for display name save (SPA-safe; panel may be re-rendered).
  (function bindDisplayNameSave(){
    if (window.__ia_connect_dn_bound) return;
    window.__ia_connect_dn_bound = true;

    document.addEventListener('click', async function(ev){
      const btn = ev.target && ev.target.closest ? ev.target.closest('[data-iac-displayname-save]') : null;
      if (!btn) return;

      ev.preventDefault();
      ev.stopPropagation();

      const root = btn.closest('[data-iac-settings-section="displayname"]') || document;
      const dnInput = root.querySelector('[data-iac-displayname-input]') || document.querySelector('[data-iac-displayname-input]');
      const dnStat  = root.querySelector('[data-iac-displayname-status]') || document.querySelector('[data-iac-displayname-status]');
      if (!dnInput) return;

      const cfg = window.IA_CONNECT || {};
      const nonces = (cfg.nonces || {});
      const display_name = String(dnInput.value || '').trim();

      if (dnStat) dnStat.textContent = 'Saving…';
      try{
        const r = await postForm('ia_connect_display_name_update', { nonce: (nonces.display_name_update||''), display_name });
        if (!r || !r.success) throw r;

        const d = (r.data || {});
        const newDisp = String(d.display || '').trim();
        if (dnStat) dnStat.textContent = 'Saved.';

        if (cfg && cfg.me) cfg.me.display = newDisp || (cfg.me.login||'');
        document.querySelectorAll('[data-iac-me-display]').forEach(el=>{ el.textContent = newDisp || (cfg.me && cfg.me.login ? cfg.me.login : ''); });
      }catch(e){
        const msg = (e && e.data && e.data.message) ? e.data.message : (e && e.message ? e.message : 'Request failed');
        if (dnStat) dnStat.textContent = msg;
      }
    }, true);
  })();

  // Delegated handler for homepage save (SPA-safe)
  (function bindHomeTabSave(){
    if (window.__ia_connect_home_tab_bound) return;
    window.__ia_connect_home_tab_bound = true;

    document.addEventListener('click', async function(ev){
      const btn = ev.target && ev.target.closest ? ev.target.closest('[data-iac-home-tab-save]') : null;
      if (!btn) return;

      ev.preventDefault();
      ev.stopPropagation();

      const root = btn.closest('[data-iac-settings-section="homepage"]') || document;
      const input = root.querySelector('[data-iac-home-tab-input]') || document.querySelector('[data-iac-home-tab-input]');
      const stat  = root.querySelector('[data-iac-home-tab-status]') || document.querySelector('[data-iac-home-tab-status]');
      if (!input) return;

      const cfg = window.IA_CONNECT || {};
      const nonces = (cfg.nonces || {});
      const home_tab = String(input.value || 'connect').trim().toLowerCase();

      if (stat) stat.textContent = 'Saving…';
      try{
        const r = await postForm('ia_connect_home_tab_update', { nonce: (nonces.home_tab_update||''), home_tab });
        if (!r || !r.success) throw r;

        const d = (r.data || {});
        if (cfg && cfg.me){
          cfg.me.home_tab = (d.home_tab != null) ? String(d.home_tab) : home_tab;
        }
        if (stat) stat.textContent = 'Saved. Applies when you enter IndieAgora without a deep link.';
      }catch(e){
        const msg = (e && e.data && e.data.message) ? e.data.message : (e && e.message ? e.message : 'Request failed');
        if (stat) stat.textContent = msg;
      }
    }, true);
  })();

  // Delegated handler for style save (SPA-safe)
  (function bindStyleSave(){
    if (window.__ia_connect_style_bound) return;
    window.__ia_connect_style_bound = true;

    document.addEventListener('click', async function(ev){
      const btn = ev.target && ev.target.closest ? ev.target.closest('[data-iac-style-save]') : null;
      if (!btn) return;

      ev.preventDefault();
      ev.stopPropagation();

      const root = btn.closest('[data-iac-settings-section="style"]') || document;
      const input = root.querySelector('[data-iac-style-input]') || document.querySelector('[data-iac-style-input]');
      const stat  = root.querySelector('[data-iac-style-status]') || document.querySelector('[data-iac-style-status]');
      if (!input) return;

      const cfg = window.IA_CONNECT || {};
      const nonces = (cfg.nonces || {});
      const style = String(input.value || 'default').trim().toLowerCase();

      if (stat) stat.textContent = 'Saving…';
      try{
        const r = await postForm('ia_connect_style_update', { nonce: (nonces.style_update||''), style });
        if (!r || !r.success) throw r;

        const d = (r.data || {});
        const next = (d.style != null) ? String(d.style) : style;
        if (cfg && cfg.me) cfg.me.style = next;
        if (input) input.value = next;
        applyStyle(next);
        if (stat) stat.textContent = 'Saved.';
      }catch(e){
        const msg = (e && e.data && e.data.message) ? e.data.message : (e && e.message ? e.message : 'Request failed');
        if (stat) stat.textContent = msg;
      }
    }, true);
  })();

  // Delegated handler for signature save (SPA-safe)
  (function bindSignatureSave(){
    if (window.__ia_connect_sig_bound) return;
    window.__ia_connect_sig_bound = true;

    document.addEventListener('click', async function(ev){
      const btn = ev.target && ev.target.closest ? ev.target.closest('[data-iac-signature-save]') : null;
      if (!btn) return;

      ev.preventDefault();
      ev.stopPropagation();

      const root = btn.closest('[data-iac-settings-section="signature"]') || document;
      const sigInput = root.querySelector('[data-iac-signature-input]') || document.querySelector('[data-iac-signature-input]');
      const sigShow  = root.querySelector('[data-iac-signature-show-discuss]') || document.querySelector('[data-iac-signature-show-discuss]');
      const sigStat  = root.querySelector('[data-iac-signature-status]') || document.querySelector('[data-iac-signature-status]');
      if (!sigInput) return;

      const cfg = window.IA_CONNECT || {};
      const nonces = (cfg.nonces || {});

      const signature = String(sigInput.value || '').trim().slice(0, 500);
      const show_discuss = (sigShow && sigShow.checked) ? 1 : 0;

      if (sigStat) sigStat.textContent = 'Saving…';
      try{
        const r = await postForm('ia_connect_signature_update', { nonce: (nonces.signature_update||''), signature, show_discuss });
        if (!r || !r.success) throw r;

        const d = (r.data || {});
        if (cfg && cfg.me){
          cfg.me.signature = (d.signature != null) ? String(d.signature) : signature;
          cfg.me.signature_show_discuss = (d.show_discuss != null) ? String(d.show_discuss) : String(show_discuss);
        }

        if (sigStat) sigStat.textContent = 'Saved.';
      }catch(e){
        const msg = (e && e.data && e.data.message) ? e.data.message : (e && e.message ? e.message : 'Request failed');
        if (sigStat) sigStat.textContent = msg;
      }
    }, true);
  })();

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

// Discuss share header link: open Discuss topic (opening post)
document.addEventListener('click', (e)=>{
  const a = e.target && e.target.closest ? e.target.closest('[data-iac-discuss-open]') : null;
  if (!a) return;
  const tid = parseInt(a.getAttribute('data-topic-id')||'0',10)||0;
  if (!tid) return;
  try { e.preventDefault(); } catch(_){}
  try {
    const u = new URL(location.href);
    u.searchParams.set('tab','discuss');
    u.searchParams.set('ia_tab','discuss');
    u.searchParams.set('iad_view','topic');
    u.searchParams.set('iad_topic', String(tid));
    u.searchParams.delete('iad_post');
    location.href = u.toString();
  } catch(_){}
});
