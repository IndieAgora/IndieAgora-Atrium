(function(){
  'use strict';

  function q(root, sel){ return (root || document).querySelector(sel); }
  function qa(root, sel){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function getLineHeightPx(el){
    if (!el) return 22;
    const cs = window.getComputedStyle(el);
    const lh = parseFloat(cs.lineHeight);
    if (Number.isFinite(lh)) return lh;
    const fs = parseFloat(cs.fontSize);
    return Number.isFinite(fs) ? Math.round(fs * 1.4) : 22;
  }

  function getMaxHeightPx(textarea){
    const shell = textarea && textarea.closest ? textarea.closest('.ia-msg-shell') : null;
    const viewportH = Math.max(window.innerHeight || 0, 320);
    const line = getLineHeightPx(textarea);
    const minRows = Number(textarea.getAttribute('rows') || 2) || 2;
    const minHeight = Math.max(44, Math.round((line * minRows) + 20));
    const viewportCap = Math.round(viewportH * (viewportH <= 820 ? 0.18 : 0.22));
    const softCap = Math.max(minHeight, Math.min(168, viewportCap || 0));

    if (shell) {
      shell.style.setProperty('--ia-msg-composer-max-h', String(softCap) + 'px');
    }
    return softCap;
  }

  function getMinHeightPx(textarea){
    const line = getLineHeightPx(textarea);
    const minRows = 2;
    const cs = window.getComputedStyle(textarea);
    const pt = parseFloat(cs.paddingTop) || 0;
    const pb = parseFloat(cs.paddingBottom) || 0;
    const bt = parseFloat(cs.borderTopWidth) || 0;
    const bb = parseFloat(cs.borderBottomWidth) || 0;
    return Math.max(44, Math.round((line * minRows) + pt + pb + bt + bb));
  }

  function resizeTextarea(textarea){
    if (!textarea) return;
    const maxH = getMaxHeightPx(textarea);
    const minH = getMinHeightPx(textarea);
    textarea.style.height = 'auto';
    const next = Math.max(textarea.scrollHeight || 0, minH);
    const finalH = next <= minH ? minH : Math.min(next, maxH);
    textarea.style.height = String(finalH) + 'px';
    textarea.style.overflowY = next > maxH ? 'auto' : 'hidden';
  }

  function appendUploadedUrl(textarea, url){
    if (!textarea || !url) return;
    const cur = String(textarea.value || '');
    textarea.value = (cur ? (cur.replace(/\s+$/, '') + '\n') : '') + String(url);
    try {
      textarea.dispatchEvent(new Event('input', { bubbles:true }));
    } catch (_) {
      resizeTextarea(textarea);
    }
  }

  function getProgressEls(textarea){
    const shell = textarea && textarea.closest ? textarea.closest('.ia-msg-shell') : null;
    return {
      prog: shell ? q(shell, '[data-ia-msg-upload-progress]') : null,
      progLabel: shell ? q(shell, '[data-ia-msg-upload-label]') : null,
      progPct: shell ? q(shell, '[data-ia-msg-upload-pct]') : null,
      progFill: shell ? q(shell, '[data-ia-msg-upload-fill]') : null
    };
  }

  function showProgress(els, name){
    if (!els || !els.prog) return;
    els.prog.hidden = false;
    if (els.progLabel) els.progLabel.textContent = 'Uploading ' + (name || '…');
    if (els.progPct) els.progPct.textContent = '0%';
    if (els.progFill) els.progFill.style.width = '0%';
  }

  function setProgress(els, pct){
    if (!els || pct == null) return;
    if (els.progPct) els.progPct.textContent = String(pct) + '%';
    if (els.progFill) els.progFill.style.width = String(pct) + '%';
  }

  function hideProgressSoon(els){
    if (!els || !els.prog) return;
    window.setTimeout(function(){
      try { els.prog.hidden = true; } catch(_) {}
    }, 800);
  }

  function getClipboardFiles(ev){
    const dt = ev && ev.clipboardData ? ev.clipboardData : null;
    const out = [];
    if (!dt || !dt.items || !dt.items.length) return out;
    for (let i = 0; i < dt.items.length; i++) {
      const item = dt.items[i];
      if (!item || item.kind !== 'file' || !item.getAsFile) continue;
      const file = item.getAsFile();
      if (!file) continue;
      out.push(file);
    }
    return out;
  }

  async function uploadPastedFiles(textarea, files){
    if (!textarea || !files || !files.length) return false;
    if (!window.IAMessageApi || typeof window.IAMessageApi.postFileProgress !== 'function') return false;

    const els = getProgressEls(textarea);

    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      showProgress(els, file && file.name ? file.name : 'clipboard item');
      try {
        const res = await window.IAMessageApi.postFileProgress('ia_message_upload', file, {}, function(pct){ setProgress(els, pct); });
        if (res && res.success && res.data && res.data.url) {
          appendUploadedUrl(textarea, String(res.data.url));
          setProgress(els, 100);
        } else {
          throw new Error('Upload failed');
        }
      } catch (e) {
        hideProgressSoon(els);
        throw e;
      }
    }

    hideProgressSoon(els);
    try { textarea.focus(); } catch (_) {}
    return true;
  }

  function bindTextarea(textarea){
    if (!textarea || textarea.getAttribute('data-ia-msg-autosize') === '1') return;
    textarea.setAttribute('data-ia-msg-autosize', '1');

    const onChange = function(){ resizeTextarea(textarea); };
    textarea.addEventListener('input', onChange);
    textarea.addEventListener('change', onChange);
    textarea.addEventListener('focus', onChange);
    textarea.addEventListener('paste', function(ev){
      const files = getClipboardFiles(ev);
      if (!files.length) return;
      ev.preventDefault();
      uploadPastedFiles(textarea, files).catch(function(){
        try { window.alert('Paste upload failed.'); } catch (_) {}
      });
    });

    const form = textarea.form || (textarea.closest ? textarea.closest('form') : null);
    if (form) {
      form.addEventListener('submit', function(){
        window.setTimeout(function(){ resizeTextarea(textarea); }, 0);
        window.setTimeout(function(){ resizeTextarea(textarea); }, 120);
      });
    }

    resizeTextarea(textarea);
  }

  function bindShell(shell){
    if (!shell) return;
    qa(shell, '[data-ia-msg-send-input], [data-ia-msg-new-body], [data-ia-msg-group-body], [data-ia-msg-slot="composer"]').forEach(bindTextarea);
  }

  function bindAll(){
    qa(document, '.ia-msg-shell').forEach(bindShell);
    qa(document, '[data-ia-msg-slot="composer"]').forEach(bindTextarea);
  }

  document.addEventListener('input', function(ev){
    const t = ev.target;
    if (!(t instanceof Element)) return;
    if (t.matches('[data-ia-msg-send-input], [data-ia-msg-new-body], [data-ia-msg-group-body], [data-ia-msg-slot="composer"]')) {
      resizeTextarea(t);
    }
  }, true);

  window.addEventListener('resize', bindAll, { passive:true });
  window.addEventListener('orientationchange', bindAll, { passive:true });

  if (window.MutationObserver) {
    const mo = new MutationObserver(function(muts){
      for (let i = 0; i < muts.length; i++) {
        const m = muts[i];
        if (!m) continue;
        if (m.type === 'childList') {
          m.addedNodes.forEach(function(node){
            if (!(node instanceof Element)) return;
            if (node.matches && (node.matches('.ia-msg-shell') || node.matches('[data-ia-msg-send-input], [data-ia-msg-new-body], [data-ia-msg-group-body], [data-ia-msg-slot="composer"]'))) {
              bindAll();
              return;
            }
            if (node.querySelector && node.querySelector('.ia-msg-shell, [data-ia-msg-send-input], [data-ia-msg-new-body], [data-ia-msg-group-body], [data-ia-msg-slot="composer"]')) {
              bindAll();
            }
          });
        }
      }
    });
    mo.observe(document.documentElement || document.body, { childList:true, subtree:true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAll, { once:true });
  } else {
    bindAll();
  }
})();
