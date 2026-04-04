(function(){
  "use strict";

  const qs = (sel, root)=> (root||document).querySelector(sel);
  const qsa = (sel, root)=> Array.from((root||document).querySelectorAll(sel));
  const esc = (s)=> String(s??'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c]));

  const deb = (fn, ms)=>{
    let t = null;
    return (...args)=>{
      clearTimeout(t);
      t = setTimeout(()=>fn(...args), ms||200);
    };
  };

  
  function setPendingScroll(kind, data){
    try{
      const obj = {kind, data: data||{}, ts: Date.now()};
      sessionStorage.setItem('ia_post_pending_scroll', JSON.stringify(obj));
    }catch(_){ }
  }
  function getPendingScroll(){
    try{
      const raw = sessionStorage.getItem('ia_post_pending_scroll');
      if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || !obj.ts) return null;
      // expire after 3 minutes
      if ((Date.now() - obj.ts) > 180000) { sessionStorage.removeItem('ia_post_pending_scroll'); return null; }
      return obj;
    }catch(_){ return null; }
  }
  function clearPendingScroll(){
    try{ sessionStorage.removeItem('ia_post_pending_scroll'); }catch(_){ }
  }

  function flashAndScrollTo(el){
    if (!el) return false;
    try{
      el.scrollIntoView({behavior:'smooth', block:'center'});
    }catch(_){
      try{ el.scrollIntoView(true); }catch(__){ }
    }
    try{
      el.classList.add('ia-post-flash');
      setTimeout(()=>{ try{ el.classList.remove('ia-post-flash'); }catch(_){ } }, 2200);
    }catch(_){ }
    return true;
  }

  function maybeScrollToPending(){
    const pending = getPendingScroll();
    if (!pending) return;
    if (pending.kind !== 'connect') return;

    const postId = pending.data && pending.data.post_id ? String(pending.data.post_id) : '';
    const url = pending.data && pending.data.url ? String(pending.data.url) : '';

    let tries = 0;
    // Connect feeds can hydrate after AJAX, so allow longer.
    const maxTries = 30;

    const timer = setInterval(()=>{
      tries++;
      let el = null;

      if (postId){
        const sels = [
          `[data-post-id="${postId}"]`,
          `[data-iac-post-id="${postId}"]`,
          `#ia-connect-post-${postId}`,
          `#iac-post-${postId}`,
          `article[data-id="${postId}"]`,
          `article[id="post-${postId}"]`,
          `a[href*="post=${postId}"]`,
          `a[href*="/post/${postId}"]`,
          `a[href*="#${postId}"]`
        ];
        for (const s of sels){
          el = qs(s);
          if (el) break;
        }

        // If we matched a link, walk up to a card/container.
        if (el && el.tagName === 'A'){
          el = el.closest('article, .ia-card, .ia-connect-card, .ia-connect-post, .iac-card') || el;
        }
      }

      if (!el && url){
        // try find a card that links to the post URL
        const a = qsa('a[href]').find(a=> (a.getAttribute('href')||'').indexOf(url) !== -1);
        if (a) el = a.closest('article, .ia-card, .ia-connect-card, .ia-connect-post') || a;
      }

      if (el){
        clearInterval(timer);
        clearPendingScroll();
        flashAndScrollTo(el);
        return;
      }

      // if we can see feed container but element isn't there yet, keep trying
      if (tries >= maxTries){
        // Fallback: if Connect doesn't expose stable selectors, the new post is usually at the top.
        const feed = qs('[data-iac-feed-inner]') || qs('.iac-feed, .ia-connect-feed, [data-iac-feed]');
        if (feed){
          const topCard = feed.querySelector('article, .ia-card, .ia-connect-card, .ia-connect-post, .iac-card');
          if (topCard){
            clearInterval(timer);
            clearPendingScroll();
            flashAndScrollTo(topCard);
            return;
          }
        }
        clearInterval(timer);
        clearPendingScroll();
      }
    }, 250);
  }

function activeAtriumTab(){
    try{
      const u = new URL(location.href);
      const tab = (u.searchParams.get('tab')||'').trim();
      if (tab) return tab;
    }catch(_){ }
    const btn = qs('.ia-tab.is-active');
    const t = btn ? (btn.getAttribute('data-tab')||'').trim() : '';
    return t || 'connect';
  }

  function postForm(action, payload, files){
    const fd = new FormData();
    fd.append('action', action);
    Object.keys(payload||{}).forEach(k=> fd.append(k, payload[k]));
    if (files && files.length){
      files.forEach(f=> fd.append('files[]', f));
    }
    const url = (window.IA_POST && IA_POST.ajaxUrl) ? IA_POST.ajaxUrl : (window.ajaxurl||'/wp-admin/admin-ajax.php');
    return fetch(url, {method:'POST', credentials:'same-origin', body: fd})
      .then(r=> r.text().then(t=>{ try{ return JSON.parse(t); }catch(e){ return {success:false, data:{message:'Non-JSON response from server', raw:String(t).slice(0,300)}}; } }));
  }

  function ensureMounted(){
    const root = qs('[data-ia-post-root]');
    if (!root) return null;
    return root;
  }

  let currentMode = 'connect';
  let state = {
    connect: {
      picked: [],
      wallWp: 0,
      wallPhpbb: 0,
      wallLabel: 'Your wall',
    },
    discuss: {
      forumId: 0,
      forumName: '',
      forumBanned: 0,
      joinedList: [],
    }
  };

  function renderTop(root){
    const tab = currentMode;
    const dest = (tab === 'connect') ? (state.connect.wallLabel||'') : (state.discuss.forumName ? ('Agora: ' + state.discuss.forumName) : 'Choose an Agora');

    root.insertAdjacentHTML('afterbegin',
      '<div class="ia-post-top">'
        + '<div class="ia-post-switch">'
          + '<button type="button" class="ia-post-pill ' + (tab==='connect'?'is-active':'') + '" data-ia-post-mode="connect">Connect</button>'
          + '<button type="button" class="ia-post-pill ' + (tab==='discuss'?'is-active':'') + '" data-ia-post-mode="discuss">Discuss</button>'
        + '</div>'
        + '<div class="ia-post-dest" title="' + esc(dest) + '">' + esc(dest) + '</div>'
      + '</div>'
    );
  }

  function hydrateTop(root){
    qsa('[data-ia-post-mode]', root).forEach(b=>{
      b.addEventListener('click', ()=>{
        const m = (b.getAttribute('data-ia-post-mode')||'').trim();
        if (!m || m === currentMode) return;
        currentMode = m;
        renderAll(root);
      });
    });
  }

  function renderConnect(root){
    const s = state.connect;
    root.insertAdjacentHTML('beforeend',
      '<div class="ia-post-section" data-ia-post-section="connect">'
        + '<div class="iac-composer" data-ia-post-connect>'
          + '<div class="iac-composer-fields">'
            + '<input class="iac-input" type="text" placeholder="Title" data-ia-post-ctitle />'
            + '<textarea class="iac-textarea" placeholder="What\'s happening? Use @username to tag." data-ia-post-cbody></textarea>'
            + '<div class="iac-attach-row">'
              + '<label class="iac-attach-btn">'
                + '<input type="file" multiple data-ia-post-cfiles accept="image/*,video/*,application/pdf,.doc,.docx,.txt,.zip,.rar" />'
                + 'Attach'
              + '</label>'
              + '<div class="iac-attach-meta" data-ia-post-cmeta></div>'
            + '</div>'
            + '<div class="iac-attach-preview" data-ia-post-cprev hidden></div>'
            + '<div class="iac-composer-actions">'
              + '<button type="button" class="iac-post" data-ia-post-csubmit>Post</button>'
            + '</div>'
          + '</div>'
        + '</div>'
      + '</div>'
    );

    // mention box
    const mentionBox = document.createElement('div');
    mentionBox.className = 'iac-mentionbox';
    mentionBox.hidden = true;
    document.body.appendChild(mentionBox);

    let mentionActiveEl = null;
    let mentionItems = [];

    function hideMention(){
      mentionBox.hidden = true;
      mentionBox.innerHTML = '';
      mentionActiveEl = null;
      mentionItems = [];
    }

    function getAtToken(el){
      const v = String(el.value||'');
      const pos = (typeof el.selectionStart === 'number') ? el.selectionStart : v.length;
      const before = v.slice(0,pos);
      const m = before.match(/(^|\s)@([a-zA-Z0-9_\-\.]{1,40})$/);
      return m ? (m[2]||'') : '';
    }

    function replaceAtToken(el, username){
      const v = String(el.value||'');
      const pos = (typeof el.selectionStart === 'number') ? el.selectionStart : v.length;
      const before = v.slice(0,pos);
      const after = v.slice(pos);
      const m = before.match(/(^|\s)@([a-zA-Z0-9_\-\.]{1,40})$/);
      if (!m) return;
      const start = before.length - m[2].length - 1;
      const pre = v.slice(0,start);
      const ins = '@' + username + ' ';
      el.value = pre + ins + after;
      const caret = (pre + ins).length;
      if (el.setSelectionRange) el.setSelectionRange(caret, caret);
    }

    const mentionDebounced = deb(async (q)=>{
      if (!mentionActiveEl) return;
      if (!window.IA_CONNECT) return;
      const nonces = (IA_CONNECT.nonces||{});
      const r = await postForm('ia_connect_mention_suggest', { nonce: nonces.mention_suggest||'', q });
      if (!r || !r.success) { hideMention(); return; }
      const items = r.data && r.data.results ? r.data.results : [];
      mentionItems = items;
      if (!items.length) { hideMention(); return; }

      mentionBox.innerHTML = items.map((u, idx)=>
        '<div class="iac-mention-row" data-ia-post-mention-pick data-idx="' + esc(idx) + '">' +
          '<img class="iac-mention-ava" src="' + esc(u.avatarUrl||'') + '" alt="" />' +
          '<div>' +
            '<div class="iac-mention-name">' + esc(u.display||u.username||'User') + '</div>' +
            '<div class="iac-mention-user">@' + esc(u.username||'user') + '</div>' +
          '</div>' +
        '</div>'
      ).join('');

      const rct = mentionActiveEl.getBoundingClientRect();
      // Position relative to viewport (we force position:fixed in CSS)
      // and match the textarea width so it doesn't look detached.
      const desiredWidth = Math.max(220, Math.min(360, rct.width));
      mentionBox.style.width = desiredWidth + 'px';

      // First place below, then if it would overflow, place above.
      let top = rct.bottom + 6;
      let left = rct.left;
      // Clamp horizontally.
      left = Math.min(Math.max(10, left), window.innerWidth - desiredWidth - 10);
      mentionBox.style.left = left + 'px';
      mentionBox.style.top = top + 'px';
      mentionBox.hidden = false;

      // After it is visible, measure height and adjust if needed.
      const h = mentionBox.offsetHeight || 0;
      if (h && (top + h + 10) > window.innerHeight){
        top = Math.max(10, rct.top - h - 6);
        mentionBox.style.top = top + 'px';
      }
    }, 160);

    function attachMention(el){
      if (!el) return;
      el.addEventListener('input', ()=>{
        const token = getAtToken(el);
        if (!token) { hideMention(); return; }
        mentionActiveEl = el;
        mentionDebounced(token);
      });
      el.addEventListener('blur', ()=> setTimeout(()=>hideMention(), 120));
    }

    mentionBox.addEventListener('mousedown', (e)=>{
      const row = e.target.closest('[data-ia-post-mention-pick]');
      if (!row) return;
      e.preventDefault();
      const idx = parseInt(row.getAttribute('data-idx')||'0',10)||0;
      const u = mentionItems[idx];
      if (!u || !mentionActiveEl) return;
      replaceAtToken(mentionActiveEl, u.username||'');
      hideMention();
      try{ mentionActiveEl.focus(); }catch(_){ }
    });

    const titleEl = qs('[data-ia-post-ctitle]', root);
    const bodyEl  = qs('[data-ia-post-cbody]', root);
    const filesEl = qs('[data-ia-post-cfiles]', root);
    const metaEl  = qs('[data-ia-post-cmeta]', root);
    const prevEl  = qs('[data-ia-post-cprev]', root);
    const submit  = qs('[data-ia-post-csubmit]', root);

    attachMention(bodyEl);

    function refreshPreview(){
      if (!metaEl || !prevEl) return;
      metaEl.textContent = s.picked.length ? (s.picked.length + ' file' + (s.picked.length>1?'s':'') + ' selected') : '';
      prevEl.innerHTML = '';
      if (!s.picked.length){ prevEl.hidden = true; return; }
      prevEl.hidden = false;
      s.picked.slice(0,12).forEach(f=>{
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
        s.picked = filesEl.files ? Array.from(filesEl.files) : [];
        refreshPreview();
      });
    }

    function renderSimpleCard(p){
      if (!p) return '';
      const atts = (p.attachments||[]).slice(0,4).map(a=>{
        const kind = (a.kind||'');
        if (kind === 'image') return '<img class="iac-att-img" src="' + esc(a.url||'') + '" alt="" />';
        if (kind === 'video') return '<video class="iac-att-vid" src="' + esc(a.url||'') + '" muted playsinline></video>';
        return '<a class="iac-att-file" href="' + esc(a.url||'') + '" target="_blank" rel="noopener">' + esc(a.name||'file') + '</a>';
      }).join('');

      return (
        '<article class="iac-card" data-post-id="' + esc(p.id||0) + '">' +
          '<div class="iac-headrow">' +
            '<img class="iac-ava" src="' + esc(p.author_avatar||'') + '" alt="" />' +
            '<div class="iac-headmeta">' +
              '<div class="iac-author">' + esc(p.author||'User') + '</div>' +
              '<div class="iac-when">just now</div>' +
            '</div>' +
          '</div>' +
          (p.title ? '<div class="iac-title">' + esc(p.title) + '</div>' : '') +
          (p.body ? '<div class="iac-body">' + p.body + '</div>' : '') +
          (atts ? '<div class="iac-atts">' + atts + '</div>' : '') +
        '</article>'
      );
    }

    if (submit){
      submit.addEventListener('click', async ()=>{
        if (!window.IA_CONNECT){ alert('Connect not available'); return; }
        const nonces = (IA_CONNECT.nonces||{});
        const title = String(titleEl?.value||'').trim();
        const body = String(bodyEl?.value||'').trim();
        if (!title && !body && !s.picked.length) return;
        submit.disabled = true;
        try{
          const r = await postForm('ia_connect_post_create', {
            nonce: nonces.post_create||'',
            wall_wp: s.wallWp||0,
            wall_phpbb: s.wallPhpbb||0,
            title,
            body
          }, s.picked);
          if (!r || !r.success) throw r;
          // reset
          if (titleEl) titleEl.value = '';
          if (bodyEl) bodyEl.value = '';
          s.picked = [];
          if (filesEl) filesEl.value = '';
          refreshPreview();

          window.dispatchEvent(new CustomEvent('ia_atrium:closeComposer'));

          // Ensure Connect renders with its canonical renderer (galleries/link cards/etc.).
          // The immediate 'optimistic' card insert was intentionally removed because it produces a raw/ugly preview.
          const feedInner = qs('[data-iac-feed-inner]');
          if (feedInner){
            // Preserve canonical rendering, but scroll to the newly created post after reload (Discuss-style behavior).
            const newId = (r && r.data && (r.data.post_id || r.data.id || r.data.wp_post_id || r.data.post_wp_id)) ? String(r.data.post_id || r.data.id || r.data.wp_post_id || r.data.post_wp_id) : '';
            const newUrl = (r && r.data && (r.data.url || r.data.permalink)) ? String(r.data.url || r.data.permalink) : '';
            if (newId || newUrl){
              setPendingScroll('connect', { post_id: newId, url: newUrl });
            }
            setTimeout(()=>{ try{ window.location.reload(); }catch(_){ } }, 60);
          }
        }catch(e){
          alert((e && e.data && e.data.message) ? e.data.message : 'Post failed');
        }finally{
          submit.disabled = false;
        }
      });
    }
  }

  async function resolveConnectDestination(){
    const me = (window.IA_CONNECT && IA_CONNECT.me && IA_CONNECT.me.id) ? parseInt(IA_CONNECT.me.id,10)||0 : ((window.IA_POST && IA_POST.me && IA_POST.me.wp) ? IA_POST.me.wp : 0);
    let wallWp = me;
    let wallPhpbb = 0;
    let label = 'Your wall';

    const profile = qs('[data-iac-profile]');
    if (profile){
      const wwp = parseInt(profile.getAttribute('data-wall-wp')||'0',10)||0;
      const wph = parseInt(profile.getAttribute('data-wall-phpbb')||'0',10)||0;
      if (wwp) wallWp = wwp;
      if (wph) wallPhpbb = wph;

      const nameEl = qs('.iac-name', profile) || qs('.iac-name');
      const nm = nameEl ? (nameEl.textContent||'').trim() : '';
      if (nm && wallWp !== me) label = nm + "'s wall";
      if (wallWp === me) label = 'Your wall';
    }

    state.connect.wallWp = wallWp;
    state.connect.wallPhpbb = wallPhpbb;
    state.connect.wallLabel = label;
  }

  function renderDiscuss(root){
    root.insertAdjacentHTML('beforeend',
      '<div class="ia-post-section" data-ia-post-section="discuss">'
        + '<div class="ia-post-agora-picker" data-ia-post-agora-picker>'
          + '<div class="ia-post-agora-selected" data-ia-post-agora-selected></div>'
          + '<input class="iad-input" type="text" placeholder="Search Agoras" data-ia-post-agora-q />'
          + '<div class="iad-suggest" data-ia-post-agora-suggest hidden></div>'
          + '<div class="ia-post-agora-joined" data-ia-post-agora-joined><div class="ia-post-agora-joined-head"><span class="ia-post-agora-joined-label">Joined</span><button type="button" class="ia-post-linkbtn" data-ia-post-agora-all>All</button></div><div class="ia-post-agora-chips" data-ia-post-agora-chips></div></div><div class="ia-post-agora-tip" data-ia-post-agora-tip>Tip: search Agoras by name.</div>'
        + '</div>'
        + '<div class="ia-post-mini-modal" data-ia-post-joined-modal hidden>'  + '<div class="ia-post-mini-backdrop" data-ia-post-joined-close></div>'  + '<div class="ia-post-mini-panel">'    + '<div class="ia-post-mini-head"><div class="ia-post-mini-title">Joined Agoras</div><button type="button" class="ia-post-mini-x" data-ia-post-joined-close>×</button></div>'    + '<input type="text" class="iad-input ia-post-mini-q" placeholder="Filter joined Agoras" data-ia-post-joined-q />'    + '<div class="ia-post-mini-list" data-ia-post-joined-list></div>'  + '</div>'+ '</div>'+ '<div data-ia-post-discuss-mount></div>'
      + '</div>'
    );

    const mount = qs('[data-ia-post-discuss-mount]', root);
    if (!mount) return;

    // Mount Discuss composer (modal-style: startOpen)
    if (!window.IA_DISCUSS_UI_COMPOSER){
      mount.innerHTML = '<div class="ia-post-loading">Discuss composer not loaded.</div>';
      return;
    }

    mount.innerHTML = window.IA_DISCUSS_UI_COMPOSER.composerHTML({ mode: 'topic', submitLabel: 'Post' });

    window.IA_DISCUSS_UI_COMPOSER.bindComposer(mount, {
      mode: 'topic',
      startOpen: true,
      onSubmit(payload, ui){
        if (!window.IA_DISCUSS_API){ ui.error('Discuss API missing'); return; }
        const forumId = parseInt(state.discuss.forumId||0,10)||0;
        if (!forumId){ ui.error('Choose an Agora first'); return; }
        if (state.discuss.forumBanned){ ui.error('You cannot post in this Agora'); return; }

        let body = payload.body || '';
        if (payload.attachments && payload.attachments.length){
          try{
            const json = JSON.stringify(payload.attachments);
            const b64 = btoa(unescape(encodeURIComponent(json)));
            body = body + "\n\n[ia_attachments]" + b64;
          }catch(_){ }
        }

        const title = (payload.title||'').trim();
        if (!title){ ui.error('Title required'); return; }
        if (!body.trim()){ ui.error('Body required'); return; }

        window.IA_DISCUSS_API.post('ia_discuss_new_topic', {
          forum_id: forumId,
          title,
          body,
          notify: (payload && payload.notify != null) ? payload.notify : 1
        }).then((res)=>{
          if (!res || !res.success){
            ui.error((res && res.data && res.data.message) ? res.data.message : 'New topic failed');
            return;
          }
          const topicId = res.data && res.data.topic_id ? parseInt(res.data.topic_id,10) : 0;
          ui.clear();
          window.dispatchEvent(new CustomEvent('ia_atrium:closeComposer'));
          if (topicId){
            try{
              window.dispatchEvent(new CustomEvent('ia_atrium:navigate', {
                detail: { tab: 'discuss', params: { iad_view: 'topic', iad_topic: String(topicId) } }
              }));
            }catch(_){ }
            setTimeout(()=>{
              try{ window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: topicId } })); }catch(_){ }
            }, 220);
          }
        });
      }
    });

    hydrateAgoraPicker(root);
  }

  
  function positionAgoraSuggest(picker, input, suggest){
    try{
      if (!picker || !input || !suggest) return;
      const pr = picker.getBoundingClientRect();
      const ir = input.getBoundingClientRect();
      const top = (ir.bottom - pr.top) + 6;
      const left = (ir.left - pr.left);
      suggest.style.top = top + 'px';
      suggest.style.left = left + 'px';
      suggest.style.width = ir.width + 'px';
    }catch(_){ }
  }

function hydrateAgoraPicker(root){
    const picker = qs('[data-ia-post-agora-picker]', root);
    if (!picker) return;
    const selected = qs('[data-ia-post-agora-selected]', picker);
    const q = qs('[data-ia-post-agora-q]', picker);
    const suggest = qs('[data-ia-post-agora-suggest]', picker);
    function renderSelected(){
      if (!selected) return;
      if (!state.discuss.forumId){
        selected.innerHTML = '';
        return;
      }
      selected.innerHTML =
        '<span class="ia-post-agora-chip">' + esc(state.discuss.forumName||('Agora #' + state.discuss.forumId)) + '</span>' +
        '<button type="button" class="ia-post-agora-clear" data-ia-post-agora-clear>Change</button>' +
        (state.discuss.forumBanned ? '<span class="ia-post-agora-chip" style="border-color: rgba(255,80,80,0.4);">Banned</span>' : '');

      const clear = qs('[data-ia-post-agora-clear]', selected);
      if (clear){
        clear.addEventListener('click', ()=>{
          state.discuss.forumId = 0;
          state.discuss.forumName = '';
          state.discuss.forumBanned = 0;
          renderSelected();
          if (q) q.focus();
        });
      }
    }

    renderSelected();


    const reposition = ()=>{ if (suggest && !suggest.hidden) positionAgoraSuggest(picker, q, suggest); };
    window.addEventListener('resize', reposition);
    // modal scroll container
    const modal = qs('#ia-atrium-composer');
    if (modal) modal.addEventListener('scroll', reposition, {passive:true});

    function showSuggest(html){
      if (!suggest) return;
      suggest.innerHTML = html;
      suggest.hidden = !html;
      if (html) positionAgoraSuggest(picker, q, suggest);
    }

    function hideSuggest(){
      if (!suggest) return;
      suggest.hidden = true;
      suggest.innerHTML = '';
    }

    async function fetchAgoras(query){
      if (!window.IA_DISCUSS_API) return { items: [] };
      const res = await IA_DISCUSS_API.post('ia_discuss_agoras', { offset: 0, q: String(query||'') });
      if (!res || !res.success) return { items: [] };
      return { items: (res.data && res.data.items) ? res.data.items : [] };
    }

    function pickAgora(item){
      state.discuss.forumId = parseInt(item.forum_id||0,10)||0;
      state.discuss.forumName = String(item.forum_name||'').trim();
      state.discuss.forumBanned = parseInt(item.banned||0,10)||0;
      hideSuggest();
      if (q) q.value = '';
      renderSelected();
      // Update the top destination label
      const topDest = qs('.ia-post-dest');
      if (topDest){
        const txt = state.discuss.forumName ? ('Agora: ' + state.discuss.forumName) : 'Choose an Agora';
        topDest.textContent = txt;
        topDest.title = txt;
      }
    }

    const renderSuggestRows = (items)=>{
      const clean = (items||[]).slice(0, 10);
      if (!clean.length) return '';
      return clean.map(it=>{
        const banned = parseInt(it.banned||0,10)||0;
        const joined = parseInt(it.joined||0,10)||0;
        const icon = '<span class="iad-sug-ico">#</span>';
        const sub = (banned ? 'You are banned' : (joined ? 'Joined' : 'Not joined'));
        return (
          '<button type="button" class="iad-sug-row" data-ia-post-agora-pick="' + esc(it.forum_id) + '">' +
            icon +
            '<div class="iad-sug-text">' +
              '<div class="iad-sug-main">' + esc(it.forum_name||'Agora') + '</div>' +
              '<div class="iad-sug-sub">' + esc(sub) + '</div>' +
            '</div>' +
            (banned ? '<span class="iad-sug-icon">⛔</span>' : '') +
          '</button>'
        );
      }).join('');
    };

    async function loadJoinedQuick(){
      const res = await fetchAgoras('');
      const items = (res.items||[]).filter(it=> parseInt(it.joined||0,10)===1 && parseInt(it.banned||0,10)===0);
      state.discuss.joinedList = items.slice(0, 200);

      const chipsWrap = qs('[data-ia-post-agora-chips]', root);
      const allBtn = qs('[data-ia-post-agora-all]', root);
      const tip = qs('[data-ia-post-agora-tip]', root);

      const modal = qs('[data-ia-post-joined-modal]', root);
      const modalList = qs('[data-ia-post-joined-list]', root);
      const modalQ = qs('[data-ia-post-joined-q]', root);

      if (tip){
        tip.textContent = items.length ? 'Tip: search Agoras by name, or pick from joined.' : 'Tip: search Agoras by name.';
      }

      function renderJoinedList(filter){
        if (!modalList) return;
        const f = String(filter||'').trim().toLowerCase();
        const view = items.filter(it=>{
          if (!f) return true;
          return String(it.forum_name||'').toLowerCase().includes(f);
        }).slice(0, 200);

        if (!view.length){
          modalList.innerHTML = '<div class="ia-post-mini-empty">No matching Agoras.</div>';
          return;
        }

        modalList.innerHTML = view.map(it=>
          '<button type="button" class="ia-post-mini-row" data-ia-post-agora-pick="' + esc(it.forum_id) + '">' +
            '<div class="ia-post-mini-row-name">' + esc(it.forum_name||'Agora') + '</div>' +
          '</button>'
        ).join('');

        qsa('[data-ia-post-agora-pick]', modalList).forEach(btn=>{
          btn.addEventListener('click', ()=>{
            const fid = parseInt(btn.getAttribute('data-ia-post-agora-pick')||'0',10)||0;
            const it = items.find(x=> parseInt(x.forum_id||0,10)===fid);
            if (it){ pickAgora(it); closeJoinedModal(); }
          });
        });
      }

      function openJoinedModal(){
        if (!modal) return;
        modal.hidden = false;
        renderJoinedList(modalQ ? modalQ.value : '');
        if (modalQ) modalQ.focus();
      }
      function closeJoinedModal(){
        if (!modal) return;
        modal.hidden = true;
      }

      if (chipsWrap){
        if (!items.length){
          chipsWrap.innerHTML = '';
        } else {
          const first = items.slice(0, 8);
          const more = Math.max(0, items.length - first.length);
          const chips = first.map(it=>
            '<button type="button" class="ia-post-agora-chip" data-ia-post-agora-chip="' + esc(it.forum_id) + '">' + esc(it.forum_name||'Agora') + '</button>'
          ).join('') + (more ? '<button type="button" class="ia-post-agora-chip ia-post-agora-more" data-ia-post-agora-all>+' + more + '</button>' : '');
          chipsWrap.innerHTML = chips;

          qsa('[data-ia-post-agora-chip]', chipsWrap).forEach(b=>{
            b.addEventListener('click', ()=>{
              const fid = parseInt(b.getAttribute('data-ia-post-agora-chip')||'0',10)||0;
              const it = items.find(x=> parseInt(x.forum_id||0,10)===fid);
              if (it) pickAgora(it);
            });
          });
          qsa('[data-ia-post-agora-all]', chipsWrap).forEach(b=>{
            b.addEventListener('click', ()=> openJoinedModal());
          });
        }
      }

      if (allBtn){
        allBtn.addEventListener('click', ()=> openJoinedModal());
      }
      if (modal){
        qsa('[data-ia-post-joined-close]', modal).forEach(el=>{
          el.addEventListener('click', ()=> closeJoinedModal());
        });
        // ESC close
        document.addEventListener('keydown', (e)=>{
          if (e.key === 'Escape' && !modal.hidden){ closeJoinedModal(); }
        });
      }
      if (modalQ){
        modalQ.addEventListener('input', deb(()=> renderJoinedList(modalQ.value), 120));
      }
    }

    loadJoinedQuick();


    const doSuggest = deb(async ()=>{
      const query = String(q?.value||'').trim();
      if (query.length < 2){ hideSuggest(); return; }
      const res = await fetchAgoras(query);
      const html = renderSuggestRows(res.items||[]);
      showSuggest(html);
      qsa('[data-ia-post-agora-pick]', suggest).forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const fid = parseInt(btn.getAttribute('data-ia-post-agora-pick')||'0',10)||0;
          const it = (res.items||[]).find(x=> parseInt(x.forum_id||0,10)===fid);
          if (it) pickAgora(it);
        });
      });
    }, 180);

    if (q){
      q.addEventListener('input', doSuggest);
      q.addEventListener('blur', ()=> setTimeout(()=>hideSuggest(), 120));
      q.addEventListener('focus', ()=>{ if (String(q.value||'').trim().length >= 2) doSuggest(); });
    }

    // Preselect current agora if in agora view
    try{
      const u = new URL(location.href);
      const currentForum = parseInt(u.searchParams.get('iad_forum')||'0',10)||0;
      if (currentForum && !state.discuss.forumId && window.IA_DISCUSS_API){
        IA_DISCUSS_API.post('ia_discuss_forum_meta', { forum_id: currentForum }).then(res=>{
          if (!res || !res.success) return;
          const m = res.data || {};
          pickAgora({
            forum_id: currentForum,
            forum_name: m.forum_name||('Agora #' + currentForum),
            banned: m.banned||0,
            joined: m.joined||0,
          });
        });
      }
    }catch(_){ }
  }

  function renderAll(root){
    // Keep connect destination up to date (wall changes as user navigates)
    resolveConnectDestination();

    root.innerHTML = '';
    renderTop(root);

    if (currentMode === 'discuss'){
      renderDiscuss(root);
    } else {
      renderConnect(root);
    }

    hydrateTop(root);
  }

  function openComposer(){
    const root = ensureMounted();
    if (!root) return;

    const tab = activeAtriumTab();
    currentMode = (tab === 'discuss') ? 'discuss' : 'connect';

    renderAll(root);
  }

  // Hook: Atrium opens composer
  window.addEventListener('ia_atrium:openComposer', ()=> openComposer());

  // If composer already open on load (rare), mount anyway
  document.addEventListener('DOMContentLoaded', ()=>{
    const modal = qs('#ia-atrium-composer');
    if (modal && !modal.hidden){
      openComposer();
    }
    // After reload following a post, scroll + highlight the newly created Connect post.
    // We retry briefly because the feed may render asynchronously.
    maybeScrollToPending();
  });

})();
