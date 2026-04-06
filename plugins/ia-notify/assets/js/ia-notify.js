(function(){
  "use strict";

  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

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
      method:'POST', credentials:'same-origin', body: fd
    }).then(r=>r.json());
  }

  function timeAgo(mysql){
    const s = String(mysql||'').replace(' ', 'T');
    const d = new Date(s);
    if (isNaN(d.getTime())) return '';
    const diff = Math.max(0, Date.now() - d.getTime());
    const sec = Math.floor(diff/1000);
    if (sec < 60) return sec + 's ago';
    const min = Math.floor(sec/60); if (min < 60) return min + 'm ago';
    const hr = Math.floor(min/60); if (hr < 24) return hr + 'h ago';
    return Math.floor(hr/24) + 'd ago';
  }

  function sourceLabel(source){
    return ({messages:'Messages', connect:'Connect', discuss:'Discuss', stream:'Stream', system:'System'})[String(source||'system')] || 'Notifications';
  }

  function ensureBadge(shell){
    const btn = qs('.ia-bottom-item[data-bottom="notify"]', shell) || qs('[data-bottom="notify"]', shell);
    if (!btn) return null;
    let b = qs('.ia-notify-badge', btn);
    if (!b){ b = document.createElement('span'); b.className='ia-notify-badge'; b.textContent='0'; btn.appendChild(b); }
    return b;
  }

  function safeParseUrl(href){ try{ return new URL(href, window.location.href); }catch(e){ return null; } }

  function routeTo(item){
    try{ const p = qs('.ia-notify-panel'); if (p) p.classList.remove('open'); }catch(_){ }
    const href = (item && item.url) ? String(item.url||'') : '';
    if (!href) return false;
    const u = safeParseUrl(href); if (!u) return false;
    if (u.origin !== window.location.origin) { window.location.href = href; return true; }
    const tab = String(u.searchParams.get('tab') || '').toLowerCase();
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
        if (profName && pid === 0) { window.location.href = cur.toString(); return true; }
        window.history.pushState({}, '', cur.toString());
        window.dispatchEvent(new PopStateEvent('popstate'));
        return true;
      } catch(e2){ window.location.href = href; return true; }
    }
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
      if (inviteId > 0 || reportId > 0 || discussView === 'agora') { window.location.href = href; return true; }
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
      } catch(_e) { window.location.href = href; return true; }
    }
    if (tab === 'messages') {
      const threadId = parseInt(u.searchParams.get('ia_msg_thread')||'0',10)||0;
      const messageId = parseInt(u.searchParams.get('ia_msg_mid')||'0',10)||0;
      try{ window.dispatchEvent(new CustomEvent('ia_atrium:navigate', { detail:{ tab:'messages' } })); }catch(_){ }
      try{
        const cur = new URL(window.location.href);
        cur.searchParams.set('tab','messages');
        if (threadId>0) cur.searchParams.set('ia_msg_thread', String(threadId)); else cur.searchParams.delete('ia_msg_thread');
        if (messageId>0) cur.searchParams.set('ia_msg_mid', String(messageId)); else cur.searchParams.delete('ia_msg_mid');
        window.history.pushState({}, '', cur.toString());
        window.dispatchEvent(new PopStateEvent('popstate'));
      }catch(_e){ }
      if (threadId > 0) { try{ window.dispatchEvent(new CustomEvent('ia_message:open_thread', { detail:{ thread_id: threadId, message_id: messageId } })); }catch(_){ } }
      try{ if (threadId > 0 && !window.IA_MESSAGE) window.location.href = href; }catch(_){ }
      return true;
    }
    if (tab === 'stream') {
      const videoId = String(u.searchParams.get('video') || u.searchParams.get('v') || '').trim();
      const commentId = String(u.searchParams.get('stream_comment') || '').trim();
      const replyId = String(u.searchParams.get('stream_reply') || '').trim();
      const focus = String(u.searchParams.get('focus') || '').trim();
      try{ window.dispatchEvent(new CustomEvent('ia_atrium:navigate', { detail:{ tab:'stream' } })); }catch(_){ }
      try{
        const cur = new URL(window.location.href);
        cur.searchParams.set('tab','stream');
        if (videoId) cur.searchParams.set('video', videoId); else cur.searchParams.delete('video');
        if (focus) cur.searchParams.set('focus', focus); else cur.searchParams.delete('focus');
        if (commentId) cur.searchParams.set('stream_comment', commentId); else cur.searchParams.delete('stream_comment');
        if (replyId) cur.searchParams.set('stream_reply', replyId); else cur.searchParams.delete('stream_reply');
        window.history.pushState({}, '', cur.toString());
        window.dispatchEvent(new PopStateEvent('popstate'));
      }catch(_e){ }
      try {
        const api = window.IA_STREAM;
        if (api && api.ui && api.ui.video && typeof api.ui.video.open === 'function' && videoId) {
          api.ui.video.open(videoId, {
            focus: focus || ((commentId || replyId) ? 'comments' : ''),
            highlightCommentId: replyId || commentId || '',
            commentId: commentId || '',
            replyId: replyId || ''
          });
          return true;
        }
      } catch(_e2){}
      if (videoId) { window.location.href = href; return true; }
    }
    window.location.href = href;
    return true;
  }

  function ensureToast(){ let t=qs('.ia-notify-toast'); if(!t){ t=document.createElement('div'); t.className='ia-notify-toast'; document.body.appendChild(t);} return t; }

  function ensurePanel(){
    let p = qs('.ia-notify-panel'); if (p) return p;
    p = document.createElement('div'); p.className = 'ia-notify-panel';
    p.innerHTML = `
      <div class="backdrop" aria-hidden="true"></div>
      <div class="card" role="dialog" aria-label="Notifications">
        <div class="hdr">
          <div>
            <div class="title">Notifications</div>
            <div class="sub" data-notify-summary>Grouped by person and area</div>
          </div>
          <button type="button" class="close" aria-label="Close">×</button>
        </div>
        <div class="controls">
          <label><input type="checkbox" data-pref="popups"> Pop-up notifications</label>
          <label><input type="checkbox" data-pref="emails"> Email notifications</label>
          <label><input type="checkbox" data-pref="mute_all"> Mute all notifications</label>
          <div class="actions">
            <button type="button" class="btn" data-action="markall">Mark all as read</button>
            <button type="button" class="btn danger" data-action="clear">Clear all</button>
          </div>
        </div>
        <div class="list" data-list></div>
        <div class="footer"><button type="button" class="load" data-action="load">Load more</button></div>
      </div>`;
    document.body.appendChild(p); return p;
  }

  function itemIdentity(item){
    return String(item && item.id !== undefined ? item.id : '');
  }

  function groupKey(item){
    const source = String(item && item.source || 'system');
    const actor = String(item && item.actor_phpbb_id || 0);
    const actorName = String(item && item.actor_name || (item.meta && item.meta.actor_name) || '');
    if (source === 'messages') return source + '|' + (actor || actorName || '0');
    return source + '|' + (actor || actorName || '0');
  }

  function groupItems(items){
    const map = new Map();
    (items||[]).forEach(item=>{
      const key = groupKey(item);
      if (!map.has(key)) map.set(key, { key, items:[], source: String(item.source||'system'), actor_name: String(item.actor_name || (item.meta&&item.meta.actor_name) || ''), actor_avatar: String(item.actor_avatar || (item.meta&&item.meta.actor_avatar) || '') });
      map.get(key).items.push(item);
    });
    const groups = Array.from(map.values());
    groups.forEach(g=>{
      g.items.sort((a,b)=>{
        const ta = new Date(String(a.created_at||'').replace(' ','T')).getTime() || 0;
        const tb = new Date(String(b.created_at||'').replace(' ','T')).getTime() || 0;
        return tb - ta;
      });
      g.latest = g.items[0] || null;
      g.unread_count = g.items.filter(it=>!it.read_at).length;
      g.count = g.items.length;
      g.title = (g.actor_name ? g.actor_name + ' · ' : '') + sourceLabel(g.source);
    });
    groups.sort((a,b)=>{
      const ta = a.latest ? (new Date(String(a.latest.created_at||'').replace(' ','T')).getTime() || 0) : 0;
      const tb = b.latest ? (new Date(String(b.latest.created_at||'').replace(' ','T')).getTime() || 0) : 0;
      return tb - ta;
    });
    return groups;
  }

  function renderChild(item){
    const div = document.createElement('button');
    div.type='button';
    div.className='ia-notify-child' + (item.read_at ? '' : ' unread');
    div.setAttribute('data-id', itemIdentity(item));
    div.setAttribute('data-kind', 'item');
    try{ div.setAttribute('data-payload', JSON.stringify(item)); }catch(_){ }
    const ctx = item.context ? `<div class="ctx">${esc(item.context)}</div>` : '';
    const detail = item.detail ? `<div class="detail">${esc(item.detail)}</div>` : '';
    div.innerHTML = `<div class="top"><span class="label">${esc(item.title || item.text || 'Notification')}</span><span class="time">${esc(timeAgo(item.created_at))}</span></div>${ctx}${detail}`;
    return div;
  }

  function renderGroup(group){
    const div = document.createElement('div');
    div.className = 'ia-notify-group' + (group.unread_count ? ' unread' : '');
    div.setAttribute('data-key', group.key);
    div.innerHTML = `
      <button type="button" class="ia-notify-group-head" data-kind="group-toggle">
        <img alt="" src="${esc(group.actor_avatar || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=')}">
        <div class="body">
          <div class="row1"><span class="title">${esc(group.title)}</span><span class="time">${esc(timeAgo(group.latest && group.latest.created_at || ''))}</span></div>
          <div class="row2">${group.count} notification${group.count===1?'':'s'}${group.unread_count ? ' · ' + group.unread_count + ' unread' : ''}</div>
          ${group.latest && group.latest.detail ? `<div class="detail">${esc(group.latest.detail)}</div>` : (group.latest && group.latest.context ? `<div class="detail">${esc(group.latest.context)}</div>` : '')}
        </div>
        <span class="chev">▾</span>
      </button>
      <div class="ia-notify-group-items"></div>`;
    const itemsWrap = qs('.ia-notify-group-items', div);
    group.items.forEach(item=>itemsWrap.appendChild(renderChild(item)));
    return div;
  }

  function updateBadge(badge, count){
    const n = Math.max(0, parseInt(count||0,10)||0);
    if (!badge) return;
    if (n <= 0){ badge.style.display = 'none'; badge.textContent = '0'; return; }
    badge.textContent = String(n); badge.style.display = 'inline-block';
  }

  function showToastGroup(group, prefs){
    if (!prefs || !prefs.popups || !group) return;
    const toast = ensureToast();
    const el = document.createElement('button');
    el.type='button';
    el.className='ia-notify-toast-item';
    const latest = group.latest || {};
    el.innerHTML = `<img alt="" src="${esc(group.actor_avatar || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=')}"><div class="txt"><div class="ttl">${esc(group.title)}</div><div class="sub">${group.count} new notification${group.count===1?'':'s'}${latest.detail ? ' · ' + esc(latest.detail) : ''}</div></div><span class="x" aria-hidden="true">×</span>`;
    el.addEventListener('click', function(){
      const ids = group.items.map(itemIdentity).filter(Boolean);
      postForm('ia_notify_mark_read', { ids }).then(function(){}).catch(function(){});
      if (latest && latest.url) routeTo(latest);
      el.remove();
    });
    toast.prepend(el);
    setTimeout(function(){ try{ el.remove(); }catch(e){} }, 6000);
  }

  document.addEventListener('DOMContentLoaded', function(){
    const shell = qs('#ia-atrium-shell'); if (!shell) return;
    const badge = ensureBadge(shell); const panel = ensurePanel(); const list = qs('[data-list]', panel); const summary = qs('[data-notify-summary]', panel);
    let offset=0, lastId=0, prefs={popups:true,emails:true,mute_all:false}, polling=null;
    let itemMap = new Map();

    function trackItems(items){
      (items||[]).forEach(it=>{ itemMap.set(itemIdentity(it), it); const n=parseInt(String(it.id).replace(/^pt:/,''),10)||0; if(String(it.id||'').indexOf('pt:')!==0 && n>lastId) lastId=n; });
    }

    function renderItems(items, append){
      trackItems(items);
      const groups = groupItems(Array.from(itemMap.values()));
      if (!append) list.innerHTML = '';
      else list.innerHTML = '';
      groups.forEach(g=>list.appendChild(renderGroup(g)));
      if (summary) summary.textContent = groups.length ? groups.length + ' grouped card' + (groups.length===1?'':'s') : 'No notifications';
    }

    function openPanel(){ panel.classList.add('open'); loadList(true); }
    function closePanel(){ panel.classList.remove('open'); }
    function applyPrefs(p){ prefs = Object.assign(prefs, p||{}); qsa('input[data-pref]', panel).forEach(inp=>{ const k = inp.getAttribute('data-pref'); inp.checked = !!prefs[k]; }); }

    function loadList(reset){
      if (reset){ offset=0; itemMap = new Map(); list.innerHTML=''; }
      postForm('ia_notify_list', { offset, limit: 30 }).then(function(r){
        if (!r || !r.success) return;
        const data = r.data || {}; applyPrefs(data.prefs || {}); updateBadge(badge, data.unread_count);
        const items = Array.isArray(data.items) ? data.items : [];
        if (items.length){ renderItems(items, !reset); offset += items.length; }
      }).catch(function(){});
    }

    function markAllRead(){
      postForm('ia_notify_mark_read', { all:1 }).then(function(r){ if (!r || !r.success) return; updateBadge(badge, r.data && r.data.unread_count || 0); itemMap.forEach(it=>{ it.read_at = it.read_at || it.created_at || '1'; }); renderItems([], true); }).catch(function(){});
    }

    function clearAll(){
      postForm('ia_notify_clear', {}).then(function(r){ if (!r || !r.success) return; updateBadge(badge, 0); itemMap = new Map(); list.innerHTML=''; if(summary) summary.textContent='No notifications'; }).catch(function(){});
    }

    function savePrefs(){ postForm('ia_notify_prefs_save', { popups:prefs.popups?1:0, emails:prefs.emails?1:0, mute_all:prefs.mute_all?1:0 }).then(function(r){ if (r && r.success && r.data && r.data.prefs) applyPrefs(r.data.prefs); }).catch(function(){}); }

    function sync(){
      postForm('ia_notify_sync', { after_id:lastId }).then(function(r){
        if (!r || !r.success) return;
        const data = r.data || {}; updateBadge(badge, data.unread_count);
        const items = Array.isArray(data.items) ? data.items : []; if (!items.length) return;
        const known = new Set(Array.from(itemMap.keys()));
        const fresh = items.filter(it => !known.has(itemIdentity(it)));
        if (!fresh.length) return;
        trackItems(fresh); renderItems([], true);
        const toastGroups = groupItems(fresh); toastGroups.forEach(g=>showToastGroup(g, prefs));
      }).catch(function(){});
    }

    window.addEventListener('ia_atrium:notifications', openPanel);
    qs('.close', panel).addEventListener('click', closePanel);
    qs('.backdrop', panel).addEventListener('click', closePanel);
    qsa('input[data-pref]', panel).forEach(inp=>inp.addEventListener('change', function(){ prefs[inp.getAttribute('data-pref')] = !!inp.checked; savePrefs(); }));
    qs('[data-action="markall"]', panel).addEventListener('click', markAllRead);
    qs('[data-action="clear"]', panel).addEventListener('click', clearAll);
    qs('[data-action="load"]', panel).addEventListener('click', function(){ loadList(false); });

    panel.addEventListener('click', function(ev){
      const toggle = ev.target.closest && ev.target.closest('[data-kind="group-toggle"]');
      if (toggle) { const g = toggle.closest('.ia-notify-group'); if (g) g.classList.toggle('open'); return; }
      const child = ev.target.closest && ev.target.closest('[data-kind="item"]');
      if (!child) return;
      const id = String(child.getAttribute('data-id')||''); if (!id) return;
      const item = itemMap.get(id); if (!item) return;
      if (!item.read_at) {
        postForm('ia_notify_mark_read', { ids:[id] }).then(function(r){ if (r && r.success) updateBadge(badge, r.data && r.data.unread_count || 0); }).catch(function(){});
        item.read_at = item.created_at || '1';
        renderItems([], true);
      }
      if (item.url) routeTo(item);
    });

    setTimeout(function(){ loadList(true); if (polling) clearInterval(polling); polling=setInterval(sync, 8000); document.addEventListener('visibilitychange', function(){ if (!document.hidden) sync(); }); }, 250);
  });
})();
