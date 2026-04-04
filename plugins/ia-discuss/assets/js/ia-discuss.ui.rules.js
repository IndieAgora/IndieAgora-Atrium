(function () {
  "use strict";
  const CORE = window.IA_DISCUSS_CORE || {};
  const qs = CORE.qs || function(sel, root){ try{return (root||document).querySelector(sel);}catch(e){return null;} };

  let modal = null;

  function ensureModal() {
    if (modal) return modal;
    const el = document.createElement('div');
    el.className = 'iad-modal';
    el.hidden = true;
    el.innerHTML = `
      <div class="iad-modal-backdrop" data-iad-rules-close></div>
      <div class="iad-modal-sheet iad-modal-sheet--full" role="dialog" aria-modal="true" aria-label="Agora rules">
        <div class="iad-modal-top">
          <button type="button" class="iad-x" data-iad-rules-close aria-label="Close">×</button>
          <div class="iad-modal-title" data-iad-rules-title>Rules</div>
        </div>
        <div class="iad-modal-body">
          <div class="iad-rules-body" data-iad-rules-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(el);
    modal = el;
    el.addEventListener('click', (e) => {
      const close = e.target && e.target.closest ? e.target.closest('[data-iad-rules-close]') : null;
      if (close) hide();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal && !modal.hidden) hide();
    });
    return modal;
  }

  function show(title, html) {
    const m = ensureModal();
    const t = qs('[data-iad-rules-title]', m);
    const b = qs('[data-iad-rules-body]', m);
    if (t) t.textContent = title || 'Rules';
    if (b) {
      const s = String(html || '').trim();
      b.innerHTML = s ? s : `<div class="iad-rules-empty">No rules have been set for this Agora.</div>`;
    }
    m.hidden = false;
    try { document.body.classList.add('iad-modal-open'); } catch(e) {}
  }

  function hide() {
    if (!modal) return;
    modal.hidden = true;
    try { document.body.classList.remove('iad-modal-open'); } catch(e) {}
  }

  function bind() {
    document.addEventListener('click', (e) => {
      const btn = e.target && e.target.closest ? e.target.closest('[data-iad-rules-open]') : null;
      if (!btn) return;
      e.preventDefault();
      const root = qs('[data-ia-discuss-root]');
      const mount = root ? qs('[data-iad-view]', root) : null;
      const src = mount ? qs('[data-iad-rules-src]', mount) : null;
      const html = src ? src.innerHTML : '';
      // Title: use current agora name when available.
      const h = mount ? qs('.iad-agora-name', mount) : null;
      const title = h ? h.textContent : 'Rules';
      show(title, html);
    }, true);
  }

  window.IA_DISCUSS_UI_RULES = { bind, show, hide };
})();
