(function(){
  "use strict";

  const CFG = window.IA_CONTEXT_MENU_CFG || {};

  function qs(sel, root){ return (root||document).querySelector(sel); }
  function esc(s){ return String(s||""); }

  function inTextInput(el){
    if (!el) return false;
    const t = (el.tagName||"").toLowerCase();
    if (t === 'input' || t === 'textarea' || t === 'select') return true;
    if (el.isContentEditable) return true;
    return false;
  }

  function getSelectionText(){
    try {
      const sel = window.getSelection && window.getSelection();
      if (!sel) return "";
      return String(sel.toString()||"").trim();
    } catch(e){ return ""; }
  }

  // Touch/selection coordination: on mobile, long-press can both begin text selection
  // and fire a contextmenu event. Delay our menu until selection stabilizes so handles
  // remain usable.
  const isTouchLike = (function(){
    try {
      return (('ontouchstart' in window) || (navigator.maxTouchPoints > 0));
    } catch(e){
      return false;
    }
  })();

  let lastSelectionChangeAt = 0;
  let touchSelecting = false;
  let touchActive = false;

  // Mobile selection helpers (touch devices).

  document.addEventListener('selectionchange', () => {
    lastSelectionChangeAt = Date.now();
  }, true);

  document.addEventListener('pointerdown', (e) => {
    if (e && e.pointerType === 'touch') {
      touchActive = true;
      touchSelecting = false;
    }
  }, true);

  document.addEventListener('pointermove', (e) => {
    if (e && e.pointerType === 'touch') {
      // if user is dragging, assume selection handles may be in use
      touchSelecting = true;
    }
  }, true);

  document.addEventListener('pointerup', (e) => {
    if (e && e.pointerType === 'touch') {
      // allow selection handles to settle
      setTimeout(() => {
        touchActive = false;
        touchSelecting = false;
      }, 250);
    }
  }, true);

  function selectionWithinDiscuss(){
    try {
      const sel = window.getSelection && window.getSelection();
      if (!sel || sel.rangeCount === 0) return null;
      const r = sel.getRangeAt(0);

      // NOTE: On mobile, selections often span multiple inline/block nodes.
      // In that case, `commonAncestorContainer` can be the overall post wrapper
      // (e.g. `.iad-post`) rather than the body itself, which would make
      // `closest('.iad-post-body')` fail. So we locate the body using BOTH
      // ends of the selection and fall back to the common ancestor.
      function asElement(n){
        if (!n) return null;
        if (n.nodeType === 3) return n.parentElement;
        return n.nodeType === 1 ? n : null;
      }
      const startEl = asElement(r.startContainer);
      const endEl   = asElement(r.endContainer);
      const commonEl = asElement(r.commonAncestorContainer);

      const body = (
        (startEl && startEl.closest && startEl.closest('.iad-post-body')) ||
        (endEl && endEl.closest && endEl.closest('.iad-post-body')) ||
        (commonEl && commonEl.closest && commonEl.closest('.iad-post-body'))
      );
      if (!body) return null;

      const post = (
        (startEl && startEl.closest && startEl.closest('.iad-post')) ||
        (endEl && endEl.closest && endEl.closest('.iad-post')) ||
        (commonEl && commonEl.closest && commonEl.closest('.iad-post'))
      );

      return { bodyEl: body, postEl: post || null, node: startEl || endEl || commonEl || null };
    } catch(e){ return null; }
  }

  // ---- Mobile selection actions (Discuss only) ----
  // We do NOT try to replace native Android/iOS selection UI.
  // Instead, when the user selects text inside a Discuss post body,
  // we show a small bottom action bar: Quote / Copy / Select all.
  const mobileBar = document.createElement('div');
  mobileBar.className = 'ia-cm-mobilebar';
  mobileBar.style.display = 'none';

  // Mobile bar icons (inline SVG, currentColor)
  function iconQuote(){
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7.2 6C5.4 6 4 7.4 4 9.2V12c0 1.8 1.4 3.2 3.2 3.2H9v2.6c0 .5.6.8 1 .4l3.2-3.2c.2-.2.3-.4.3-.7V9.2C13.5 7.4 12.1 6 10.3 6H7.2zm9.6 0c-1.8 0-3.2 1.4-3.2 3.2V12c0 1.8 1.4 3.2 3.2 3.2H18v2.6c0 .5.6.8 1 .4l3.2-3.2c.2-.2.3-.4.3-.7V9.2C22.5 7.4 21.1 6 19.3 6h-2.5z"/></svg>';
  }
  function iconCopy(){
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 1H6c-1.1 0-2 .9-2 2v10h2V3h10V1zm2 4H10c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H10V7h8v14z"/></svg>';
  }
  function iconSelectAll(){
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 3h8v2H5v6H3V3zm16 0h2v8h-2V5h-6V3h6zM3 13h2v6h6v2H3v-8zm16 6v-6h2v8h-8v-2h6zM8 8h8v8H8V8z"/></svg>';
  }

  const mobileBtnQuote = document.createElement('button');
  mobileBtnQuote.type = 'button';
  mobileBtnQuote.className = 'ia-cm-mobilebar-btn';
  mobileBtnQuote.innerHTML = `${iconQuote()}<span>Quote</span>`;

  const mobileBtnCopy = document.createElement('button');
  mobileBtnCopy.type = 'button';
  mobileBtnCopy.className = 'ia-cm-mobilebar-btn';
  mobileBtnCopy.innerHTML = `${iconCopy()}<span>Copy</span>`;

  const mobileBtnSelectAll = document.createElement('button');
  mobileBtnSelectAll.type = 'button';
  mobileBtnSelectAll.className = 'ia-cm-mobilebar-btn';
  mobileBtnSelectAll.innerHTML = `${iconSelectAll()}<span>Select all</span>`;

  mobileBar.appendChild(mobileBtnQuote);
  mobileBar.appendChild(mobileBtnCopy);
  mobileBar.appendChild(mobileBtnSelectAll);

  let mobileBarVisible = false;
  let mobileSelText = '';
  let mobileSelCtx = null;
  let selDebounce = null;
  let touchActiveForSel = false;

  function ensureMobileBarMounted(){
    try {
      if (!document.body) return;
      if (!mobileBar.isConnected) document.body.appendChild(mobileBar);
    } catch(e) {}
  }

  function hideMobileBar(){
    mobileBarVisible = false;
    mobileBar.style.display = 'none';
    mobileSelText = '';
    mobileSelCtx = null;
  }

  function showMobileBar(){
    ensureMobileBarMounted();
    mobileBar.style.display = 'flex';
    mobileBarVisible = true;
  }

  async function copyToClipboard(text){
    const t = String(text||'');
    if (!t) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(t);
        return true;
      }
    } catch(e) {}
    try {
      const ta = document.createElement('textarea');
      ta.value = t;
      ta.setAttribute('readonly','');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      ta.style.top = '0';
      document.body.appendChild(ta);
      ta.select();
      const ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return !!ok;
    } catch(e) {}
    return false;
  }

  function updateMobileSelectionState(){
    try {
      const txt = getSelectionText();
      const ctx = txt ? selectionWithinDiscuss() : null;
      if (!txt || !ctx){
        hideMobileBar();
        return;
      }
      mobileSelText = txt;
      mobileSelCtx = ctx;
      // Quote only makes sense when Discuss topic modal API exists AND we're in topic view.
      const u = currentUrl();
      const inTopic = !!(u && u.searchParams.get('iad_topic'));
      const canQuote = !!(window.IA_DISCUSS_TOPIC_MODAL && inTopic);
      mobileBtnQuote.style.display = canQuote ? 'inline-flex' : 'none';
      showMobileBar();
    } catch(e){
      hideMobileBar();
    }
  }


  function currentUrl(){
    try { return new URL(window.location.href); } catch(e){ return null; }
  }

  function buildUrlWithParams(pairs){
    const u = currentUrl();
    if (!u) return window.location.href;
    Object.keys(pairs||{}).forEach((k)=>{
      const v = pairs[k];
      if (v === null || v === undefined || v === "") u.searchParams.delete(k);
      else u.searchParams.set(k, String(v));
    });
    return u.toString();
  }

  function normalizeHref(href){
    const raw = String(href||"").trim();
    if (!raw) return null;
    if (raw.startsWith('javascript:')) return null;
    if (raw === '#') return null;
    try {
      const u = new URL(raw, window.location.href);
      return u.toString();
    } catch(e){
      return null;
    }
  }

  function resolveUrlFromTarget(t){
    if (!t) return null;

    // IMPORTANT: Prefer user/agora targets over surrounding card/topic targets.
    // In Discuss feed/topic views, username/agora buttons often sit inside a larger
    // clickable card/post container. If we resolve the container first, the menu
    // will incorrectly open the topic instead of the user profile / agora.

    // A) Discuss user buttons (feed + topic)
    const discussUserFirst = t.closest && t.closest('[data-open-user][data-user-id], .iad-user-link[data-open-user][data-user-id]');
    if (discussUserFirst){
      const uid = parseInt(discussUserFirst.getAttribute('data-user-id') || '0', 10) || 0;
      const uname = String(discussUserFirst.getAttribute('data-username') || discussUserFirst.getAttribute('data-user') || '').trim();
      if (uid || uname){
        return buildUrlWithParams({
          tab: 'connect',
          ia_profile: uid || null,
          ia_profile_name: uname || null,
          iad_topic: null,
          iad_post: null,
          iad_view: null,
          iad_q: null,
          iad_forum: null,
          iad_forum_name: null
        });
      }
    }

    // B) Discuss agora buttons (feed)
    const discussAgora = t.closest && t.closest('[data-open-agora][data-forum-id], .iad-agora-link[data-open-agora][data-forum-id]');
    if (discussAgora){
      const fid = parseInt(discussAgora.getAttribute('data-forum-id') || '0', 10) || 0;
      const fname = String(discussAgora.getAttribute('data-forum-name') || discussAgora.getAttribute('data-forum') || '').trim();
      if (fid){
        return buildUrlWithParams({
          tab: 'discuss',
          iad_forum: fid,
          iad_forum_name: fname || null,
          iad_topic: null,
          iad_post: null,
          iad_view: null,
          iad_q: null
        });
      }
    }

    // 1) Real links
    const a = t.closest && t.closest('a[href]');
    if (a){
      const href = normalizeHref(a.getAttribute('href'));
      if (href) return href;
    }

    // 2) Explicit dataset URLs
    const dsEl = t.closest && t.closest('[data-ia-href],[data-href],[data-url]');
    if (dsEl){
      const href = dsEl.getAttribute('data-ia-href') || dsEl.getAttribute('data-href') || dsEl.getAttribute('data-url');
      const n = normalizeHref(href);
      if (n) return n;
    }

    // 3) Discuss top pills (New/Unread/0 replies/Agoras)
    const pill = t.closest && t.closest('.iad-tab[data-view]');
    if (pill){
      const view = String(pill.getAttribute('data-view') || '').trim();
      if (view){
        return buildUrlWithParams({
          tab: 'discuss',
          iad_view: view,
          iad_topic: null,
          iad_post: null,
          iad_forum: null,
          iad_forum_name: null,
          iad_q: null
        });
      }
    }

    // 4) Discuss topic / reply destinations (common patterns)
    const topicish = t.closest && t.closest('[data-topic-id],[data-iad-sug-topic],[data-iad-sug-reply],[data-iad-open-topic],[data-iad-post-reply]');
    if (topicish){
      const tid = parseInt(topicish.getAttribute('data-topic-id') || topicish.getAttribute('data-iad-open-topic') || "0", 10) || 0;
      if (tid){
        // Keep whatever base path this site uses; just force tab=discuss + topic.
        return buildUrlWithParams({ tab: 'discuss', iad_topic: tid, iad_post: null, iad_view: null, iad_q: null });
      }
      // Reply suggestion has both topic_id and post_id
      const tid2 = parseInt(topicish.getAttribute('data-topic-id') || "0", 10) || 0;
      const pid2 = parseInt(topicish.getAttribute('data-post-id') || "0", 10) || 0;
      if (tid2){
        return buildUrlWithParams({ tab: 'discuss', iad_topic: tid2, iad_post: pid2 || null, iad_view: null, iad_q: null });
      }
    }

    // 5) Discuss post itself
    const post = t.closest && t.closest('.iad-post');
    if (post){
      const pid = parseInt(post.getAttribute('data-post-id') || "0", 10) || 0;
      const u = currentUrl();
      const tid = u ? (parseInt(u.searchParams.get('iad_topic') || '0', 10) || 0) : 0;
      if (tid){
        return buildUrlWithParams({ tab: 'discuss', iad_topic: tid, iad_post: pid || null });
      }
    }

    // 6) (Reserved for future) other profile patterns

    // 7) Connect profile (generic patterns, lower priority)
    const profBtn = t.closest && t.closest('[data-iac-go-profile],[data-iac-userlink],[data-phpbb]');
    if (profBtn){
      const pid = parseInt(profBtn.getAttribute('data-phpbb') || profBtn.getAttribute('data-user-id') || "0", 10) || 0;
      const uname = profBtn.getAttribute('data-username') || profBtn.getAttribute('data-user') || '';
      if (pid || uname){
        return buildUrlWithParams({ tab: 'connect', ia_profile: pid || null, ia_profile_name: uname || null });
      }
    }

    // 8) Tab switching items (anything with data-target=connect/discuss/stream/messages)
    const tabBtn = t.closest && t.closest('[data-target]');
    if (tabBtn){
      const k = (tabBtn.getAttribute('data-target') || '').toLowerCase();
      if (k === 'connect' || k === 'discuss' || k === 'stream' || k === 'messages'){
        return buildUrlWithParams({ tab: k });
      }
    }

    return null;
  }

  function openDiscussQuoteSelection(selText, author){
    const Modal = window.IA_DISCUSS_TOPIC_MODAL || {};
    if (!Modal.openComposerModal || !Modal.getComposerMount) return false;

    const u = currentUrl();
    const tid = u ? (parseInt(u.searchParams.get('iad_topic') || '0', 10) || 0) : 0;
    if (!tid) return false;

    const prefix = author ? (author + " wrote:\n") : "";
    const quote = `[quote]${prefix}${selText}[/quote]\n\n`;

    try { Modal.openComposerModal('Reply'); } catch(e) { return false; }
    const mount = Modal.getComposerMount();
    if (!mount) return false;
    mount.innerHTML = '';

    try {
      window.dispatchEvent(new CustomEvent('iad:mount_composer', {
        detail: {
          mount: mount,
          mode: 'reply',
          topic_id: tid,
          start_open: true,
          in_modal: true,
          prefillBody: quote
        }
      }));
    } catch(e){ return false; }

    // Focus the textarea soon after mount
    setTimeout(()=>{
      try {
        const ta = mount.querySelector('textarea[data-iad-bodytext], textarea[name="body"], textarea[name="message"], .iad-composer textarea, textarea');
        if (ta) ta.focus();
      } catch(e) {}
    }, 0);

    return true;
  }

  function getAuthorFromSelectionContext(ctx){
    try {
      if (!ctx || !ctx.postEl) return '';
      const btn = ctx.postEl.querySelector('.iad-user-link[data-username]');
      if (btn) return String(btn.getAttribute('data-username') || '').trim();
      return '';
    } catch(e){ return ''; }
  }

  // ---- UI ----
  const root = document.createElement('div');
  root.className = 'ia-cm-root';
  root.innerHTML = `
    <button type="button" class="ia-cm-item" data-ia-cm-open-tab>Open Link in New Tab</button>
    <button type="button" class="ia-cm-item" data-ia-cm-open-win>Open Link in New Window</button>
    <button type="button" class="ia-cm-item" data-ia-cm-copy>Copy Link</button>
    <div class="ia-cm-sep" data-ia-cm-sep></div>
    <button type="button" class="ia-cm-item" data-ia-cm-quote>Quote Selection</button>
    <div class="ia-cm-hint" data-ia-cm-hint></div>
  `;
  document.addEventListener('DOMContentLoaded', () => {
    document.body.appendChild(root);
  });

  let state = { url: null, canQuote: false, selText: '', author: '' };

  function setDisabled(btn, disabled){
    if (!btn) return;
    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function showMenu(x, y){
    // Ensure on screen
    root.style.display = 'block';
    root.style.left = '0px';
    root.style.top = '0px';

    const rect = root.getBoundingClientRect();
    const vw = window.innerWidth || document.documentElement.clientWidth || 0;
    const vh = window.innerHeight || document.documentElement.clientHeight || 0;

    let nx = x;
    let ny = y;
    if (nx + rect.width + 8 > vw) nx = Math.max(8, vw - rect.width - 8);
    if (ny + rect.height + 8 > vh) ny = Math.max(8, vh - rect.height - 8);

    root.style.left = nx + 'px';
    root.style.top = ny + 'px';
  }

  function hideMenu(){
    root.style.display = 'none';
  }

  function updateMenu(){
    const openTab = qs('[data-ia-cm-open-tab]', root);
    const openWin = qs('[data-ia-cm-open-win]', root);
    const copy = qs('[data-ia-cm-copy]', root);
    const quote = qs('[data-ia-cm-quote]', root);
    const sep = qs('[data-ia-cm-sep]', root);
    const hint = qs('[data-ia-cm-hint]', root);

    const hasUrl = !!state.url;
    setDisabled(openTab, !hasUrl);
    setDisabled(openWin, !hasUrl);
    setDisabled(copy, !hasUrl);

    // Quote is only meaningful when selection exists in Discuss post body.
    setDisabled(quote, !state.canQuote);
    try {
      sep.style.display = state.canQuote ? 'block' : 'none';
      quote.style.display = state.canQuote ? 'flex' : 'none';
    } catch(e) {}

    const label = state.url ? state.url : 'No link detected';
    hint.textContent = label.length > 70 ? (label.slice(0, 67) + '…') : label;
  }

  function copyToClipboard(text){
    const t = String(text||'');
    if (!t) return;
    if (navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(t).catch(()=>{});
      return;
    }
    try {
      const ta = document.createElement('textarea');
      ta.value = t;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    } catch(e) {}
  }

  function onMenuClick(e){
    const btn = e.target && e.target.closest ? e.target.closest('.ia-cm-item') : null;
    if (!btn) return;
    if (btn.getAttribute('aria-disabled') === 'true') return;

    if (btn.hasAttribute('data-ia-cm-open-tab')){
      if (state.url) window.open(state.url, '_blank', 'noopener');
      hideMenu();
      return;
    }
    if (btn.hasAttribute('data-ia-cm-open-win')){
      if (state.url) window.open(state.url, '_blank', 'noopener,noreferrer');
      hideMenu();
      return;
    }
    if (btn.hasAttribute('data-ia-cm-copy')){
      if (state.url) copyToClipboard(state.url);
      hideMenu();
      return;
    }
    if (btn.hasAttribute('data-ia-cm-quote')){
      if (state.canQuote && state.selText){
        openDiscussQuoteSelection(state.selText, state.author);
      }
      hideMenu();
      return;
    }
  }

  root.addEventListener('click', onMenuClick);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideMenu();
  }, true);

  document.addEventListener('click', (e) => {
    if (root.style.display === 'none') return;
    if (root.contains(e.target)) return;
    hideMenu();
  }, true);

  document.addEventListener('scroll', () => {
    if (root.style.display !== 'none') hideMenu();
    if (mobileBarVisible) hideQuotePill();
  }, true);

  document.addEventListener('contextmenu', (e) => {
    try {
      if (!e || !e.target) return;
      // Keep native menu for text inputs/textarea/select to avoid breaking copy/paste UX.
      if (inTextInput(e.target)) return;

      // If user is using modifiers, keep native.
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

      const target = e.target;

      // Touch devices: inside Discuss post bodies we implement our own selection.
      // Never show the browser menu here.
      if (isTouchLike && target.closest && target.closest('.iad-post-body')) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }

      const url = resolveUrlFromTarget(target);

      const selText = getSelectionText();
      const selCtx = selText ? selectionWithinDiscuss() : null;
      const canQuote = !!(selText && selCtx && window.IA_DISCUSS_TOPIC_MODAL);
      const author = canQuote ? getAuthorFromSelectionContext(selCtx) : '';

      // Touch devices: we don't use native text selection; ignore it.

      // On touch devices, do NOT override native behaviour if no destination URL.
      // (We keep this conservative to avoid interfering with long-press selection.)
      if (isTouchLike && !url) {
        return;
      }

      state = { url: url, canQuote: canQuote, selText: selText, author: author };
      updateMenu();

      e.preventDefault();
      e.stopPropagation();

      // Touch devices: show immediately (only when no selection, url exists).
      showMenu(e.clientX, e.clientY);
    } catch(err){
      // Fail open: if something goes wrong, do nothing so browser native menu appears.
    }
  }, true);

  // Mobile: show the action bar after the user finishes selecting.
  // We debounce selectionchange and also update on pointerup.
  if (isTouchLike){
    // Some mobile browsers/firefox variants do not reliably emit Pointer Events
    // during text selection (selection handles). Add Touch Events as a fallback
    // so we still detect when the user has finished selecting.
    document.addEventListener('touchstart', () => {
      touchActiveForSel = true;
    }, {capture:true, passive:true});
    document.addEventListener('touchend', () => {
      touchActiveForSel = false;
      setTimeout(updateMobileSelectionState, 140);
    }, {capture:true, passive:true});
    document.addEventListener('touchcancel', () => {
      touchActiveForSel = false;
      setTimeout(updateMobileSelectionState, 140);
    }, {capture:true, passive:true});

    document.addEventListener('pointerdown', (e) => {
      try {
        if (e && e.pointerType === 'touch') touchActiveForSel = true;
      } catch(_e){}
    }, true);
    document.addEventListener('pointerup', (e) => {
      try {
        if (e && e.pointerType === 'touch') {
          touchActiveForSel = false;
          // Give the browser a tick to settle selection handles.
          setTimeout(updateMobileSelectionState, 120);
        }
      } catch(_e){}
    }, true);
    document.addEventListener('selectionchange', () => {
      try {
        if (selDebounce) clearTimeout(selDebounce);
        selDebounce = setTimeout(() => {
          // If finger is still down, many browsers are still moving handles.
          // Instead of bailing (which can result in NEVER updating), schedule
          // a follow-up update shortly after.
          if (touchActiveForSel) {
            setTimeout(() => {
              if (!touchActiveForSel) updateMobileSelectionState();
            }, 260);
            return;
          }
          updateMobileSelectionState();
        }, 180);
      } catch(_e){}
    }, true);
    document.addEventListener('scroll', () => {
      if (mobileBarVisible) hideMobileBar();
    }, true);
  }

  mobileBtnQuote.addEventListener('click', (e) => {
    try {
      e.preventDefault();
      e.stopPropagation();
      if (!mobileSelText || !mobileSelCtx || !window.IA_DISCUSS_TOPIC_MODAL) return;
      const author = getAuthorFromSelectionContext(mobileSelCtx);
      openDiscussQuoteSelection(mobileSelText, author);
      // Clear selection and hide bar.
      try { window.getSelection && window.getSelection().removeAllRanges(); } catch(_e){}
      hideMobileBar();
    } catch(_e){}
  }, true);

  mobileBtnCopy.addEventListener('click', async (e) => {
    try {
      e.preventDefault();
      e.stopPropagation();
      if (!mobileSelText) return;
      await copyToClipboard(mobileSelText);
      const old = mobileBtnCopy.textContent;
      mobileBtnCopy.textContent = 'Copied';
      setTimeout(() => { mobileBtnCopy.textContent = old; }, 900);
    } catch(_e){}
  }, true);

  mobileBtnSelectAll.addEventListener('click', (e) => {
    try {
      e.preventDefault();
      e.stopPropagation();
      if (!mobileSelCtx || !mobileSelCtx.bodyEl) return;
      const body = mobileSelCtx.bodyEl;
      const r = document.createRange();
      r.selectNodeContents(body);
      const sel = window.getSelection && window.getSelection();
      if (!sel) return;
      sel.removeAllRanges();
      sel.addRange(r);
      // Update cached text + keep bar visible.
      setTimeout(updateMobileSelectionState, 50);
    } catch(_e){}
  }, true);

})();
