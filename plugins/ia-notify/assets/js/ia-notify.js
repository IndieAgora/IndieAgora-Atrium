(function(){
  "use strict";

  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }

  function postForm(action, data){
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', (window.IA_NOTIFY && IA_NOTIFY.nonce) ? IA_NOTIFY.nonce : '');
    Object.keys(data||{}).forEach(k=>{
      const v = data[k];
      if (Array.isArray(v)) v.forEach(x=>fd.append(k+'[]', x));
      else fd.append(k, v);
    });
    return fetch((window.IA_NOTIFY && IA_NOTIFY.ajaxUrl) ? IA_NOTIFY.ajaxUrl : '/wp-admin/admin-ajax.php', {
      method:'POST',
      credentials:'same-origin',
      body: fd
    }).then(r=>r.json());
  }

  function timeAgo(mysql){
    // mysql: YYYY-MM-DD HH:MM:SS in site tz; approximate.
    const s = (mysql||'').replace(' ', 'T');
    const d = new Date(s);
    if (isNaN(d.getTime())) return '';
    const diff = Math.max(0, Date.now() - d.getTime());
    const sec = Math.floor(diff/1000);
    if (sec < 60) return sec + 's ago';
    const min = Math.floor(sec/60);
    if (min < 60) return min + 'm ago';
    const hr = Math.floor(min/60);
    if (hr < 24) return hr + 'h ago';
    const day = Math.floor(hr/24);
    return day + 'd ago';
  }

  function ensureBadge(shell){
    const btn = qs('.ia-bottom-item[data-bottom="notify"]', shell) || qs('[data-bottom="notify"]', shell);
    if (!btn) return null;
    let b = qs('.ia-notify-badge', btn);
    if (!b){
      b = document.createElement('span');
      b.className = 'ia-notify-badge';
      b.textContent = '0';
      btn.appendChild(b);
    }
    return b;
  }

  function safeParseUrl(href){
    try{ return new URL(href, window.location.href); }catch(e){ return null; }
  }

  function routeTo(item){
    // Always close the notifications overlay before routing.
    try{
      const p = qs('.ia-notify-panel');
      if (p) p.classList.remove('open');
    }catch(_){ }

    const href = (item && item.url) ? String(item.url||'') : '';
    if (!href) return false;

    const u = safeParseUrl(href);
    if (!u) return false;

    // Only attempt in-app routing for same-origin.
    if (u.origin !== window.location.origin) {
      window.location.href = href;
      return true;
    }

    const tab = String(u.searchParams.get('tab') || '').toLowerCase();

    // Connect: open fullscreen post modal (and optionally scroll to comment)
    if (tab === 'connect') {
      const pid = parseInt(u.searchParams.get('ia_post')||'0',10)||0;
      const cid = parseInt(u.searchParams.get('ia_comment')||'0',10)||0;
      const profName = (u.searchParams.get('ia_profile_name')||'').trim();

      try{ window.dispatchEvent(new CustomEvent('ia_atrium:navigate', { detail:{ tab:'connect' } })); }catch(e){}

      try{
        const cur = new URL(window.location.href);
        cur.searchParams.set('tab','connect');
        if (pid>0) cur.searchParams.set('ia_post', String(pid)); else cur.searchParams.delete('ia_post');
        if (cid>0) cur.searchParams.set('ia_comment', String(cid)); else cur.searchParams.delete('ia_comment');
        if (profName) cur.searchParams.set('ia_profile_name', profName); else cur.searchParams.delete('ia_profile_name');

        // Connect's JS router currently only reacts to ia_post/ia_comment (fullscreen modal).
        // Profile context is server-rendered, so for profile navigation we do a hard navigation.
        if (profName && pid === 0) {
          window.location.href = cur.toString();
          return true;
        }

        window.history.pushState({}, '', cur.toString());
        // Connect listens to popstate to open/close the modal.
        window.dispatchEvent(new PopStateEvent('popstate'));
        return true;
      } catch(e2){
        window.location.href = href;
        return true;
      }
    }

    // Discuss: use Discuss router event (Atrium already intercepts link clicks, but notifications aren't <a> tags)
    if (tab === 'discuss') {
      const tid = parseInt(u.searchParams.get('iad_topic')||'0',10)||0;
      const postId = parseInt(u.searchParams.get('iad_post')||'0',10)||0;
      const forumId = parseInt(u.searchParams.get('iad_forum')||'0',10)||0;
      const inviteId = parseInt(u.searchParams.get('iad_invite')||'0',10)||0;
      const reportId = parseInt(u.searchParams.get('iad_report')||'0',10)||0;
      const discussView = String(u.searchParams.get('iad_view')||'').toLowerCase();

      let curTab = '';
      try{ curTab = String((new URL(window.location.href)).searchParams.get('tab')||'').toLowerCase(); }catch(_){ curTab=''; }

      const discussLoaded = !!window.IA_DISCUSS_API;
      const hasTopic = tid > 0;

      // Invite and Agora routes must preserve the original query string so Discuss can
      // show the correct private-Agora accept/decline modal on boot.
      if (inviteId > 0 || reportId > 0 || discussView === 'agora') {
        window.location.href = href;
        return true;
      }

      if (curTab === 'discuss' && discussLoaded && hasTopic) {
        try{ window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail:{ topic_id: tid, scroll_post_id: postId||0 } })); }catch(_){ }
        return true;
      }

      try{
        const dest = new URL(window.location.href);
        dest.searchParams.set('tab', 'discuss');
        if (forumId>0) dest.searchParams.set('iad_forum', String(forumId)); else dest.searchParams.delete('iad_forum');
        if (hasTopic) dest.searchParams.set('iad_topic', String(tid)); else dest.searchParams.delete('iad_topic');
        if (postId>0) dest.searchParams.set('iad_post', String(postId)); else dest.searchParams.delete('iad_post');
        window.location.href = dest.toString();
        return true;
      } catch(_e) {
        window.location.href = href;
        return true;
      }
    }

    // Messages: open Messages tab and deep-link into a thread.
    if (tab === 'messages') {
      const threadId = parseInt(u.searchParams.get('ia_msg_thread')||'0',10)||0;

      // Switch to Messages tab in the shell.
      try{ window.dispatchEvent(new CustomEvent('ia_atrium:navigate', { detail:{ tab:'messages' } })); }catch(_){ }

      // Update URL params and trigger routers without a hard reload.
      try{
        const cur = new URL(window.location.href);
        cur.searchParams.set('tab','messages');
        if (threadId>0) cur.searchParams.set('ia_msg_thread', String(threadId)); else cur.searchParams.delete('ia_msg_thread');
        window.history.pushState({}, '', cur.toString());
        window.dispatchEvent(new PopStateEvent('popstate'));
      }catch(_e){ }

      // Ask ia-message to open the thread (it will activate and load it).
      if (threadId > 0) {
        try{ window.dispatchEvent(new CustomEvent('ia_message:open_thread', { detail:{ thread_id: threadId } })); }catch(_){ }
      }

      // If something unexpected blocks in-app routing, fall back to hard navigation.
      try{
        if (threadId > 0 && !window.IA_MESSAGE) {
          window.location.href = href;
        }
      }catch(_){ }
      return true;
    }

    // Fallback: same-origin navigation
    window.location.href = href;
    return true;
  }

  function ensureToast(){
    let t = qs('.ia-notify-toast');
    if (!t){
      t = document.createElement('div');
      t.className = 'ia-notify-toast';
      document.body.appendChild(t);
    }
    return t;
  }

  function ensurePanel(){
    let p = qs('.ia-notify-panel');
    if (p) return p;

    p = document.createElement('div');
    p.className = 'ia-notify-panel';
    p.innerHTML = `
      <div class="backdrop" aria-hidden="true"></div>
      <div class="card" role="dialog" aria-label="Notifications">
        <div class="hdr">
          <div class="title">Notifications</div>
          <button type="button" class="close" aria-label="Close">×</button>
        </div>
        <div class="controls">
          <label><input type="checkbox" data-pref="popups"> Pop-up notifications</label>
          <label><input type="checkbox" data-pref="emails"> Email notifications</label>
          <label><input type="checkbox" data-pref="mute_all"> Mute all notifications</label>
          <div class="actions">
            <button type="button" class="btn" data-action="markall">Mark all as read</button>
          </div>
        </div>
        <div class="list" data-list></div>
        <div class="footer"><button type="button" class="load" data-action="load">Load more</button></div>
      </div>
    `;

    document.body.appendChild(p);
    return p;
  }

  function renderItem(item){
    const div = document.createElement('div');
    div.className = 'ia-notify-item' + (item.read_at ? '' : ' unread');
    div.setAttribute('data-id', String(item.id||0));
    // Store payload for click routing without needing a second fetch.
    try{
      div.setAttribute('data-payload', JSON.stringify({
        id: item.id||0,
        url: item.url||'',
        type: item.type||'',
        object_type: item.object_type||'',
        object_id: item.object_id||0,
        meta: item.meta||{}
      }));
    }catch(_){ }

    const avatar = (item.meta && item.meta.actor_avatar) ? item.meta.actor_avatar : '';
    const name = (item.meta && item.meta.actor_name) ? item.meta.actor_name : '';

    const img = document.createElement('img');
    img.alt = '';
    img.src = avatar || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

    const body = document.createElement('div');
    body.className = 'body';

    const t = document.createElement('div');
    t.className = 't';
    t.textContent = item.text || (name ? (name + ' sent a notification.') : 'Notification');

    const s = document.createElement('div');
    s.className = 's';
    s.textContent = timeAgo(item.created_at);

    body.appendChild(t);
    body.appendChild(s);

    div.appendChild(img);
    div.appendChild(body);

    return div;
  }

  function updateBadge(badge, count){
    const n = Math.max(0, parseInt(count||0,10)||0);
    if (!badge) return;
    if (n <= 0){ badge.style.display = 'none'; badge.textContent = '0'; return; }
    badge.textContent = String(n);
    badge.style.display = 'inline-block';
  }

  function showToast(item, prefs){
    if (!prefs || !prefs.popups) return;
    const toast = ensureToast();

    const el = document.createElement('div');
    el.className = 'ia-notify-toast-item';

    const img = document.createElement('img');
    img.alt = '';
    img.src = (item.meta && item.meta.actor_avatar) ? item.meta.actor_avatar : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

    const txt = document.createElement('div');
    txt.className = 'txt';
    txt.textContent = item.text || 'Notification';

    const x = document.createElement('button');
    x.type = 'button';
    x.className = 'x';
    x.setAttribute('aria-label','Dismiss');
    x.textContent = '×';
    x.addEventListener('click', function(ev){ ev.stopPropagation(); el.remove(); });

    el.appendChild(img);
    el.appendChild(txt);
    el.appendChild(x);

    el.addEventListener('click', function(){
      // Mark as read (best-effort) then route.
      try{
        const id = parseInt(item && item.id ? item.id : 0, 10) || 0;
        if (id > 0 && !item.read_at) {
          postForm('ia_notify_mark_read', { ids:[id] }).then(function(r){
            // Badge update is handled on next poll; update early if provided.
            if (r && r.success && r.data && typeof r.data.unread_count !== 'undefined') {
              const shell = qs('#ia-atrium-shell');
              const b = shell ? ensureBadge(shell) : null;
              updateBadge(b, r.data.unread_count);
            }
          }).catch(function(){});
        }
      }catch(_){ }

      routeTo(item);
      el.remove();
    });

    toast.prepend(el);

    setTimeout(function(){ try{ el.remove(); }catch(e){} }, 6000);
  }

  document.addEventListener('DOMContentLoaded', function(){
    const shell = qs('#ia-atrium-shell');
    if (!shell) return;

    const badge = ensureBadge(shell);
    const panel = ensurePanel();
    const list = qs('[data-list]', panel);

    let offset = 0;
    let lastId = 0;
    let initialMaxId = 0;
    let lastToastId = 0;
    let prefs = { popups:true, emails:true, mute_all:false };
    let polling = null;

    function openPanel(){
      panel.classList.add('open');
      loadList(true);
    }
    function closePanel(){ panel.classList.remove('open'); }

    function applyPrefs(p){
      prefs = Object.assign(prefs, p||{});
      qsa('input[data-pref]', panel).forEach(inp=>{
        const k = inp.getAttribute('data-pref');
        inp.checked = !!prefs[k];
      });
    }

    function loadList(reset){
      if (reset){ offset = 0; list.innerHTML = ''; }
      postForm('ia_notify_list', { offset: offset, limit: 30 }).then(function(r){
        if (!r || !r.success) return;
        const data = r.data || {};
        applyPrefs(data.prefs || {});
        updateBadge(badge, data.unread_count);

        const items = Array.isArray(data.items) ? data.items : [];
        if (items.length){
          items.forEach(it=>{
            if ((it.id||0) > lastId) lastId = (it.id||0);
            list.appendChild(renderItem(it));
          });
          offset += items.length;
        }

        // Persist the newest ID we've ever seen (prevents "toast replay" after refresh)
        try{
          const storedSeen = parseInt(localStorage.getItem('ia_notify_last_seen_id')||'0',10)||0;
          const nextSeen = Math.max(storedSeen, lastId);
          localStorage.setItem('ia_notify_last_seen_id', String(nextSeen));
        }catch(_){ }

        // On first list load, establish the baseline that should NOT trigger toasts.
        if (!initialMaxId) {
          initialMaxId = lastId;
          try{
            lastToastId = parseInt(localStorage.getItem('ia_notify_last_toast_id')||'0',10)||0;
          }catch(_){ lastToastId = 0; }
          // Never allow toastLastId to be below the initial baseline.
          lastToastId = Math.max(lastToastId, initialMaxId);
          try{ localStorage.setItem('ia_notify_last_toast_id', String(lastToastId)); }catch(_){ }
        }
      }).catch(function(){});
    }

    function markAllRead(){
      postForm('ia_notify_mark_read', { all: 1 }).then(function(r){
        if (!r || !r.success) return;
        updateBadge(badge, (r.data && r.data.unread_count) ? r.data.unread_count : 0);
        // visually clear unread
        qsa('.ia-notify-item.unread', panel).forEach(el=>el.classList.remove('unread'));
      }).catch(function(){});
    }

    function savePrefs(){
      postForm('ia_notify_prefs_save', {
        popups: prefs.popups ? 1 : 0,
        emails: prefs.emails ? 1 : 0,
        mute_all: prefs.mute_all ? 1 : 0
      }).then(function(r){
        if (r && r.success && r.data && r.data.prefs) applyPrefs(r.data.prefs);
      }).catch(function(){});
    }

    function sync(){
      postForm('ia_notify_sync', { after_id: lastId }).then(function(r){
        if (!r || !r.success) return;
        const data = r.data || {};
        updateBadge(badge, data.unread_count);

        const items = Array.isArray(data.items) ? data.items : [];
        if (!items.length) return;

        // Items are newest-first from server; show oldest-first for toasts.
        items.slice().reverse().forEach(function(it){
          if ((it.id||0) > lastId) lastId = (it.id||0);

          // Only toast truly "new" notifications (real-time), never replay on refresh.
          const tid = (it.id||0);
          if (tid > 0 && tid > lastToastId) {
            showToast(it, prefs);
            lastToastId = tid;
            try{ localStorage.setItem('ia_notify_last_toast_id', String(lastToastId)); }catch(_){ }
          }
          // If panel open, prepend to list.
          if (panel.classList.contains('open')){
            list.prepend(renderItem(it));
            offset += 1;
          }
        });
      }).catch(function(){});
    }

    // Bell click handled by Atrium intent; also listen for the event.
    window.addEventListener('ia_atrium:notifications', function(){
      openPanel();
    });

    // Close controls
    qs('.close', panel).addEventListener('click', closePanel);
    qs('.backdrop', panel).addEventListener('click', closePanel);

    // Pref toggles
    qsa('input[data-pref]', panel).forEach(inp=>{
      inp.addEventListener('change', function(){
        const k = inp.getAttribute('data-pref');
        prefs[k] = !!inp.checked;
        savePrefs();
      });
    });

    // Actions
    qs('[data-action="markall"]', panel).addEventListener('click', markAllRead);
    qs('[data-action="load"]', panel).addEventListener('click', function(){ loadList(false); });

    // Mark single read on click
    panel.addEventListener('click', function(ev){
      const item = ev.target.closest && ev.target.closest('.ia-notify-item');
      if (!item) return;
      const id = parseInt(item.getAttribute('data-id')||'0',10)||0;
      if (id<=0) return;

      // Capture payload from the last list render.
      let payload = null;
      try{
        const raw = item.getAttribute('data-payload') || '';
        if (raw) payload = JSON.parse(raw);
      }catch(_){ payload = null; }

      if (item.classList.contains('unread')){
        // Optimistically decrement badge immediately
        try{ const n=parseInt(badge.textContent||'0',10)||0; if(n>0){ badge.textContent=String(n-1); if(n-1<=0) badge.style.display='none'; } }catch(_){ }

        postForm('ia_notify_mark_read', { ids:[id] }).then(function(r){
          if (r && r.success) updateBadge(badge, (r.data&&r.data.unread_count)?r.data.unread_count:0);
        }).catch(function(){});
        item.classList.remove('unread');
      }

      // Navigate to the target (do NOT lose the link just because we marked it read)
      if (payload && payload.url) {
        routeTo(payload);
      }
    });

    // Initial fetch ASAP so badge updates without refresh.
    setTimeout(function(){
      // Seed lastId from persistent storage so we don't "re-toast" after a refresh.
      try{
        const stored = parseInt(localStorage.getItem('ia_notify_last_seen_id')||'0',10)||0;
        if (stored > 0) lastId = stored;
      }catch(_){ }

      loadList(true);
      // After initial list, begin polling.
      if (polling) clearInterval(polling);
      polling = setInterval(sync, 8000);
      // Also sync when tab becomes visible.
      document.addEventListener('visibilitychange', function(){
        if (!document.hidden) sync();
      });
    }, 250);

  });
})();
