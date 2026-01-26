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

  function renderItems(listEl, items, scope){
    if (!Array.isArray(items) || items.length === 0) {
      if (!listEl.hasChildNodes()){
        const empty = document.createElement('div');
        empty.className = 'iac-activity-empty';
        empty.textContent = 'No results.';
        listEl.appendChild(empty);
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

    function setActive(btn){
      tabs.forEach(b=>b.classList.toggle('is-active', b===btn));
      type = btn.getAttribute('data-iac-activity-type');
      page = 1;
      hasMore = true;
      listEl.innerHTML = '';
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
        renderItems(listEl, res.data.items || [], scope);
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