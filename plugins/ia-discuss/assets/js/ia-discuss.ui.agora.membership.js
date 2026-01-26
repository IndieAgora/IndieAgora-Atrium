(function(){
  'use strict';

  if (typeof window === 'undefined') return;

  const API = window.IA_DISCUSS_API; // stable runtime API wrapper used across Discuss

  function stop(e){ try{ e.preventDefault(); e.stopPropagation(); }catch(_){} }

  function isLoggedIn(){
    try { return (window.IA_DISCUSS && String(window.IA_DISCUSS.loggedIn||'0') === '1'); }
    catch(e){ return false; }
  }

  function setBtnState(btn, on){
    if (!btn) return;
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.classList.toggle('is-on', !!on);
  }

  function setJoinState(root, joined){
    if (!root) return;
    root.classList.toggle('iad-joined', !!joined);
    const btn = root.querySelector('[data-iad-join]');
    if (btn) {
      btn.textContent = joined ? 'Joined' : 'Join';
      btn.classList.toggle('is-joined', !!joined);
    }
  }

  
  function persistState(forumId, root){
    try{
      forumId = parseInt(String(forumId||'0'),10)||0;
      if (!forumId) return;
      const joined = root && root.classList && root.classList.contains('iad-joined');
      const bellBtn = root && root.querySelector ? root.querySelector('[data-iad-bell]') : null;
      const bell = getBellEnabled(bellBtn);
      const key = 'iad_agora_state_' + String(forumId);
      if (window.localStorage) window.localStorage.setItem(key, JSON.stringify({ joined: joined?1:0, bell: bell?1:0 }));
    }catch(e){}
  }
function setBellState(root, enabled){
    if (!root) return;
    const bell = root.querySelector('[data-iad-bell]');
    if (bell) setBtnState(bell, !!enabled);
  }

  function getBellEnabled(bellBtn){
    if (!bellBtn) return false;
    if (bellBtn.classList.contains('is-on')) return true;
    const ap = bellBtn.getAttribute('aria-pressed');
    return String(ap||'false') === 'true';
  }

  async function toggleJoin(forumId, root){
    if (!isLoggedIn()) return;
    if (!API || typeof API.post !== 'function') return;

    const joined = !!(root && root.classList.contains('iad-joined'));
    const route = joined ? 'ia_discuss_agora_leave' : 'ia_discuss_agora_join';

    let res = null;
    try { res = await API.post(route, { forum_id: forumId }); } catch(e){ return; }
    if (!res || !res.success) return;

    setJoinState(root, !joined);
    persistState(forumId, root);
    try{ const row=document.querySelector(`[data-iad-agora-row][data-forum-id="${forumId}"]`)||document.querySelector(`[data-forum-id="${forumId}"]`); if(row){ row.setAttribute("data-joined", (!joined)? "1":"0"); } }catch(e){}
  }

  async function toggleBell(forumId, root){
    if (!isLoggedIn()) return;
    if (!API || typeof API.post !== 'function') return;

    const bellBtn = root && root.querySelector ? root.querySelector('[data-iad-bell]') : null;
    const enabled = getBellEnabled(bellBtn);

    let res = null;
    try { res = await API.post('ia_discuss_agora_notify_set', { forum_id: forumId, enabled: enabled ? 0 : 1 }); } catch(e){ return; }
    if (!res || !res.success) return;

    setBellState(root, !enabled);
    persistState(forumId, root);
    try{ const row=document.querySelector(`[data-forum-id="${forumId}"]`); if(row){ row.setAttribute("data-bell", (!enabled)? "1":"0"); } }catch(e){}
  }

  async function changeCover(forumId, root){
    if (!isLoggedIn()) return;
    if (!API || typeof API.post !== 'function') return;

    const current = (root && root.getAttribute) ? (root.getAttribute('data-iad-cover') || '') : '';
    const url = window.prompt('Cover image URL (leave blank to clear):', current);
    if (url === null) return;

    let res = null;
    try { res = await API.post('ia_discuss_agora_cover_set', { forum_id: forumId, cover_url: url }); } catch(e){ return; }
    if (!res || !res.success) return;

    const cover = (res.data && res.data.cover_url) ? String(res.data.cover_url) : '';
    if (root && root.setAttribute) root.setAttribute('data-iad-cover', cover);

    const banner = document.querySelector('.iad-agora-banner');
    if (banner) {
      banner.style.backgroundImage = cover ? `url(${cover})` : '';
      banner.classList.toggle('has-cover', !!cover);
    }
  }

  function findRootFor(btn){
    if (!btn || !btn.closest) return document.body;
    // In Agora view, the buttons live inside .iad-agora-header-actions__right (which also has data-iad-agora-row).
    // We must bind state to the *header actions container* so joined/bell state stays in sync with the Agora view renderer.
    const header = btn.closest('.iad-agora-header-actions');
    if (header) return header;
    return btn.closest('[data-iad-agora-row]') || btn.closest('.iad-agora-header-actions__right') || document.body;
  }
  function bind(){
    document.addEventListener('click', function(e){
      const joinBtn = e.target && e.target.closest && e.target.closest('[data-iad-join]');
      if (joinBtn) {
        stop(e);
        const forumId = parseInt(joinBtn.getAttribute('data-iad-join') || '0', 10);
        if (forumId > 0) toggleJoin(forumId, findRootFor(joinBtn));
        return;
      }

      const bellBtn = e.target && e.target.closest && e.target.closest('[data-iad-bell]');
      if (bellBtn) {
        stop(e);
        const forumId = parseInt(bellBtn.getAttribute('data-iad-bell') || '0', 10);
        if (forumId > 0) toggleBell(forumId, findRootFor(bellBtn));
        return;
      }

      const coverBtn = e.target && e.target.closest && e.target.closest('[data-iad-cover-edit]');
      if (coverBtn) {
        stop(e);
        const forumId = parseInt(coverBtn.getAttribute('data-iad-cover-edit') || '0', 10);
        const root = document.querySelector('.iad-view-agora') || document.body;
        if (forumId > 0) changeCover(forumId, root);
        return;
      }
    }, true);
  }

  bind();

  window.IA_Discuss = window.IA_Discuss || {};
  window.IA_Discuss = window.IA_Discuss || {};

  window.IA_Discuss.agoraMembership = {
    setJoinState: setJoinState,
    setBellState: setBellState,
  };
})();