(function(){
  if (!window.IA_CONNECT) return;

  const ajaxUrl = IA_CONNECT.ajaxUrl;
  const nonces = (IA_CONNECT.nonces || {});
  const debounce = (fn, ms) => {
    let t = null;
    return (...args) => {
      if (t) clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  function postForm(action, data){
    const fd = new FormData();
    fd.append('action', action);
    for (const k in data){
      fd.append(k, data[k]);
    }
    return fetch(ajaxUrl, { method:'POST', credentials:'same-origin', body: fd })
      .then(async (r) => {
        const text = await r.text();
        // WP ajax sometimes returns HTML on fatal; try parse anyway.
        try { return JSON.parse(text); } catch(e){
          return { success:false, data:{ message:'Non-JSON response', raw:text.slice(0,400) } };
        }
      });
  }

  function esc(s){
    s = String(s==null?'':s);
    return s.replace(/[&<>"']/g, (c)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c]));
  }

  function renderDiscussAgora(it){
    const fid = parseInt(String(it.forum_id||'0'),10)||0;
    const name = String(it.forum_name||it.title||'');
    const topics = parseInt(String(it.topics||0),10)||0;
    const posts = parseInt(String(it.posts||0),10)||0;
    const joined = String(it.joined||'0') === '1';
    const bell = String(it.bell||'0') === '1';
    const banned = String(it.banned||'0') === '1';
    const cover = String(it.cover_url||'');
    const url = String(it.url||'#');

    const bellSvg = `<svg class="iad-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.2 2.2 0 0 0 2.2-2.2h-4.4A2.2 2.2 0 0 0 12 22Zm7-6.2v-5.2a7 7 0 1 0-14 0v5.2L3.6 17.2c-.6.6-.2 1.6.7 1.6h15.4c.9 0 1.3-1 .7-1.6L19 15.8Z" fill="currentColor"/></svg>`;
    const joinLabel = banned ? 'Kicked' : (joined ? 'Joined' : 'Join');
    const joinDisabled = banned ? 'disabled aria-disabled="true"' : '';

    const row = document.createElement('div');
    row.className = `iad-agora-row ${joined ? 'iad-joined' : ''} ${banned ? 'iad-banned' : ''}`;
    row.setAttribute('data-iad-agora-row','');
    row.setAttribute('data-forum-id', String(fid));
    row.setAttribute('data-forum-name', name);
    row.setAttribute('data-iad-cover', cover);
    row.setAttribute('data-joined', joined ? '1' : '0');
    row.setAttribute('data-bell', bell ? '1' : '0');

    row.innerHTML = `
      <button type="button" class="iad-agora-row__open">
        <div class="iad-agora-row__thumb" ${cover ? `style="background-image:url(${esc(cover)})"` : ''}></div>
        <div class="iad-agora-row__main">
          <div class="iad-agora-row__name">${esc(name)}</div>
          <div class="iad-agora-row__meta">${topics} topics • ${posts} posts</div>
        </div>
      </button>
      <div class="iad-agora-row__actions">
        <button type="button" class="iad-bell ${bell ? 'is-on' : ''}" data-iad-bell="${fid}" aria-label="Notifications" aria-pressed="${bell ? 'true':'false'}">${bellSvg}</button>
        <button type="button" class="iad-join ${joined ? 'is-joined' : ''}" data-iad-join="${fid}" ${joinDisabled}>${joinLabel}</button>
      </div>
    `;

    // Navigate to the Agora in Discuss when clicking the main row.
    const openBtn = row.querySelector('.iad-agora-row__open');
    if (openBtn) {
      openBtn.addEventListener('click', ()=>{ try{ window.location.href = url; }catch(e){} });
    }

    return row;
  }

  function renderDiscussCard(it){
    const kind = String(it.kind||'');
    const title = String(it.title||'');
    const url = String(it.url||'#');
    const forumName = String(it.forum_name||'agora');
    const forumId = parseInt(String(it.forum_id||0),10)||0;
    const author = String(it.author||'');
    const authorId = parseInt(String(it.author_id||0),10)||0;
    const authorAvatar = String(it.author_avatar||'');
    const ts = parseInt(String(it.time||0),10)||0;
    const views = parseInt(String(it.views||0),10)||0;

    const excerpt = String(it.excerpt||'');
    const excerptHtml = it.excerpt_html ? String(it.excerpt_html) : (excerpt ? `<p>${esc(excerpt)}</p>` : '');

    function timeAgo(unix){
      if (!unix) return '';
      const sec = Math.max(0, Math.floor(Date.now()/1000) - unix);
      if (sec < 60) return sec + 's';
      const min = Math.floor(sec/60);
      if (min < 60) return min + 'm';
      const hr = Math.floor(min/60);
      if (hr < 24) return hr + 'h';
      const day = Math.floor(hr/24);
      if (day < 30) return day + 'd';
      const mo = Math.floor(day/30);
      if (mo < 12) return mo + 'mo';
      return Math.floor(mo/12) + 'y';
    }

    // Minimal inline icons (CSS already styles .iad-iconbtn svg via currentColor)
    const icoReply = `<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M10 9V5L3 12l7 7v-4.1c5 0 8.5 1.6 11 5.1-1-7-4-11-11-11Z" fill="currentColor"/></svg>`;
    const icoLink  = `<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M10.6 13.4a1 1 0 0 1 0-1.4l3.4-3.4a1 1 0 1 1 1.4 1.4l-1.2 1.2a3 3 0 0 1-4.2 4.2l-1.2-1.2a1 1 0 0 1 1.4-1.4l1.2 1.2a1 1 0 0 0 1.4 0 1 1 0 0 0 0-1.4l-1.2-1.2Z" fill="currentColor"/><path d="M13.4 10.6a1 1 0 0 1 0 1.4l-3.4 3.4a1 1 0 1 1-1.4-1.4l1.2-1.2a3 3 0 0 1 4.2-4.2l1.2 1.2a1 1 0 1 1-1.4 1.4l-1.2-1.2a1 1 0 0 0-1.4 0 1 1 0 0 0 0 1.4l1.2 1.2Z" fill="currentColor"/></svg>`;
    const icoShare = `<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M18 16a3 3 0 0 0-2.4 1.2l-6.1-3.1a3 3 0 0 0 0-2.2l6.1-3.1A3 3 0 1 0 15 6a3 3 0 0 0 .1.7l-6.1 3.1a3 3 0 1 0 0 4.4l6.1 3.1A3 3 0 1 0 18 16Z" fill="currentColor"/></svg>`;
    const icoLast  = `<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 22a10 10 0 1 1 10-10 10 10 0 0 1-10 10Zm1-10.6 3.2 1.9-.9 1.5-4.3-2.6V6h2v5.4Z" fill="currentColor"/></svg>`;

    const ago = timeAgo(ts);
    const metaLabel = (kind === 'reply') ? 'Reply' : (kind === 'topic' ? 'Topic' : '');

    const art = document.createElement('article');
    art.className = 'iad-card';
    if (it.topic_id) art.setAttribute('data-topic-id', String(it.topic_id));
    if (it.first_post_id) art.setAttribute('data-first-post-id', String(it.first_post_id));
    if (forumId) {
      art.setAttribute('data-forum-id', String(forumId));
      art.setAttribute('data-forum-name', forumName);
    }
    if (authorId) art.setAttribute('data-author-id', String(authorId));

    art.innerHTML = `
      <div class="iad-card-main">
        <div class="iad-card-meta">
          ${authorAvatar ? `<img class="iad-uava" src="${esc(authorAvatar)}" alt="" />` : ``}
          <button type="button" class="iad-sub iad-agora-link" data-open-agora data-forum-id="${forumId}" data-forum-name="${esc(forumName)}" aria-label="Open agora ${esc(forumName)}" title="Open agora">
            agora/${esc(forumName || 'agora')}
          </button>
          <span class="iad-dotsep">•</span>
          <button type="button" class="iad-user-link" data-open-user data-username="${esc(author)}" data-user-id="${authorId}" aria-label="Open profile ${esc(author)}" title="Open profile">
            ${esc(author || metaLabel)}
          </button>
          ${ago ? `<span class="iad-dotsep">•</span><span class="iad-time">${esc(ago)}</span>` : ``}
        </div>

        <h3 class="iad-card-title" data-open-topic-title>${esc(title)}</h3>

        ${excerptHtml ? `<div class="iad-card-excerpt" data-open-topic-excerpt>${excerptHtml}</div>` : ``}

        <div class="iad-card-actions">
          <button type="button" class="iad-iconbtn" data-open-topic-comments title="Open comments" aria-label="Open comments">${icoReply}</button>
          <button type="button" class="iad-iconbtn" data-copy-topic-link title="Copy link" aria-label="Copy link">${icoLink}</button>
          <button type="button" class="iad-iconbtn" data-share-topic title="Share to Connect" aria-label="Share to Connect">${icoShare}</button>
          <button type="button" class="iad-pill is-muted" data-open-topic-lastreply title="Last reply" aria-label="Last reply">${icoLast} <span>Last reply</span></button>
          <span class="iad-muted">${esc(String(views))} views</span>
        </div>
      </div>
    `;

    // Clickable title/excerpt should navigate to the item.
    const go = () => { try { window.location.href = url; } catch(e) {} };
    const tEl = art.querySelector('[data-open-topic-title]');
    const eEl = art.querySelector('[data-open-topic-excerpt]');
    if (tEl) tEl.addEventListener('click', go);
    if (eEl) eEl.addEventListener('click', go);

    // Basic actions.
    const copyBtn = art.querySelector('[data-copy-topic-link]');
    if (copyBtn) copyBtn.addEventListener('click', (ev)=>{
      ev.preventDefault(); ev.stopPropagation();
      try {
        navigator.clipboard.writeText(url);
      } catch(e){}
    });
    const comBtn = art.querySelector('[data-open-topic-comments]');
    if (comBtn) comBtn.addEventListener('click', (ev)=>{ ev.preventDefault(); ev.stopPropagation(); go(); });
    const lastBtn = art.querySelector('[data-open-topic-lastreply]');
    if (lastBtn) lastBtn.addEventListener('click', (ev)=>{ ev.preventDefault(); ev.stopPropagation(); go(); });

    return art;
  }

  function renderItems(listEl, items, scope, type){
    if (!Array.isArray(items) || items.length === 0) {
      if (!listEl.hasChildNodes()){
        const empty = document.createElement('div');
        empty.className = 'iac-activity-empty';
        empty.textContent = 'No results.';
        listEl.appendChild(empty);
      }
      return;
    }

    if (scope === 'discuss'){
      for (const it of items){
        const kind = String(it.kind||'');
        if (kind === 'agora') {
          listEl.appendChild(renderDiscussAgora(it));
        } else {
          listEl.appendChild(renderDiscussCard(it));
        }
      }
      return;
    }

    for (const it of items){
      const row = document.createElement('a');
      row.className = 'iac-activity-item';
      row.href = it.url || '#';
      row.target = (scope === 'stream') ? '_blank' : '_self';
      row.rel = 'noopener';

      const hasThumb = !!(it.thumb);
      row.innerHTML = `
        ${hasThumb ? `<div class="iac-activity-thumb"><img alt="" loading="lazy" /></div>` : ``}
        <div class="iac-activity-body">
          <div class="iac-activity-item-title"></div>
          ${it.excerpt ? `<div class="iac-activity-item-excerpt"></div>` : ``}
        </div>
      `;
      row.querySelector('.iac-activity-item-title').textContent = it.title || '';
      const ex = row.querySelector('.iac-activity-item-excerpt');
      if (ex) ex.textContent = it.excerpt || '';
      if (hasThumb){
        const img = row.querySelector('img');
        if (img){
          const fallbacks = Array.isArray(it.thumb_fallbacks) ? it.thumb_fallbacks.slice(0) : [];
          // Ensure primary thumb is first in the queue.
          if (it.thumb) fallbacks.unshift(it.thumb);
          let idx = 0;
          const tryNext = () => {
            idx++;
            if (idx >= fallbacks.length) {
              img.remove();
              const holder = row.querySelector('.iac-activity-thumb');
              if (holder) holder.remove();
              return;
            }
            img.src = fallbacks[idx];
          };
          img.addEventListener('error', tryNext);
          img.src = fallbacks[0] || it.thumb;
        }
      }
      listEl.appendChild(row);
    }
  }

  function setupActivity(root){
    const scope = root.getAttribute('data-iac-activity-scope');
    const targetWp = root.getAttribute('data-target-wp') || root.dataset.targetWp || '';
    const listEl = root.querySelector('[data-iac-activity-list]');
    const moreBtn = root.querySelector('[data-iac-activity-more]');
    const qEl = root.querySelector('[data-iac-activity-q]');
    const tabs = Array.from(root.querySelectorAll('[data-iac-activity-type]'));

    if (!listEl || !moreBtn || tabs.length === 0) return;

    let type = (tabs.find(b=>b.classList.contains('is-active')) || tabs[0]).getAttribute('data-iac-activity-type');
    let page = 1;
    let hasMore = true;
    let q = '';

    function applyListMode(){
      if (scope !== 'discuss') return;
      // Keep Connect's list container but borrow Discuss list styles.
      listEl.classList.add('iad-feed-list');
      // Agora subtabs render as a list of rows (not feed cards).
      if (type && String(type).indexOf('agoras_') === 0) {
        listEl.classList.add('iad-agoras-list');
      } else {
        listEl.classList.remove('iad-agoras-list');
      }
    }

    function setActive(btn){
      tabs.forEach(b=>b.classList.toggle('is-active', b===btn));
      type = btn.getAttribute('data-iac-activity-type');
      page = 1;
      hasMore = true;
      listEl.innerHTML = '';
      applyListMode();
      load();
    }

    function load(){
      if (!hasMore) return;
      moreBtn.disabled = true;

      const action = scope === 'stream' ? 'ia_connect_stream_activity' : 'ia_connect_discuss_activity';
      const nonceKey = scope === 'stream' ? 'stream_activity' : 'discuss_activity';

      postForm(action, {
        nonce: nonces[nonceKey] || '',
        target_wp: targetWp,
        type,
        q,
        page,
        per_page: 10
      }).then((res)=>{
        if (!res || !res.success){
          listEl.innerHTML = '';
          const err = document.createElement('div');
          err.className = 'iac-activity-error';
          err.textContent = (res && res.data && res.data.message) ? res.data.message : 'Request failed.';
          listEl.appendChild(err);
          hasMore = false;
          moreBtn.hidden = true;
          return;
        }
        renderItems(listEl, res.data.items || [], scope, type);
        hasMore = !!res.data.has_more;
        moreBtn.hidden = !hasMore;
        moreBtn.disabled = false;
        page += 1;
      });
    }

    tabs.forEach(btn => btn.addEventListener('click', ()=> setActive(btn)));
    moreBtn.addEventListener('click', ()=> load());
    if (qEl){
      qEl.addEventListener('input', debounce(()=>{
        q = (qEl.value || '').trim();
        page = 1;
        hasMore = true;
        listEl.innerHTML = '';
        moreBtn.hidden = false;
        load();
      }, 250));
    }

    // initial
    applyListMode();
    load();
  }

  function setupPrivacy(root){
    const targetWp = root.getAttribute('data-target-wp') || '';
    const save = root.querySelector('[data-iac-privacy-save]');
    const status = root.querySelector('[data-iac-privacy-status]');
    const inputs = Array.from(root.querySelectorAll('[data-iac-privacy-key]'));
    if (!save || inputs.length === 0) return;

    save.addEventListener('click', ()=>{
      save.disabled = true;
      if (status) status.textContent = 'Saving…';

      const privacy = {};
      inputs.forEach(i=>{
        privacy[i.getAttribute('data-iac-privacy-key')] = i.checked ? 1 : 0;
      });

      postForm('ia_connect_privacy_update', {
        nonce: nonces.privacy_update || '',
        target_wp: targetWp,
        privacy: JSON.stringify(privacy)
      }).then((res)=>{
        save.disabled = false;
        if (!res || !res.success){
          if (status) status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Save failed.';
          return;
        }
        if (status) status.textContent = 'Saved.';
        setTimeout(()=>{ if(status) status.textContent=''; }, 1500);
      });
    });
  }

  function boot(){
    document.querySelectorAll('[data-iac-activity-root]').forEach(setupActivity);
    document.querySelectorAll('[data-iac-privacy-root]').forEach(setupPrivacy);
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
document.addEventListener('click',function(e){
  if(e.target.classList.contains('ia-load-more-comments')){
    const wrap=e.target.closest('.ia-comments');
    wrap.querySelectorAll('.ia-comment.hidden').forEach(c=>c.classList.remove('hidden'));
    e.target.remove();
  }
});
