(function () {
  "use strict";

  // This file intentionally patches behaviour without rewriting the core modules.
  // It must load before ia-discuss.boot.js runs.

  function ready() {
    return (
      window.IA_DISCUSS_UI_MODERATION &&
      typeof window.IA_DISCUSS_UI_MODERATION.loadMyModeration === 'function'
    );
  }

  function patchModerationReturnShape() {
    try {
      const ui = window.IA_DISCUSS_UI_MODERATION;
      if (!ui || ui.__iad_patched) return;

      const orig = ui.loadMyModeration;
      ui.loadMyModeration = async function (root) {
        const d = await orig.call(ui, root);
        try {
          // Router expects {count, global_admin}.
          if (d && typeof d === 'object') {
            if (typeof d.count === 'undefined') {
              const items = Array.isArray(d.items) ? d.items : [];
              d.count = items.length;
            }
          }
        } catch (e) {}
        return d;
      };

      ui.__iad_patched = true;
    } catch (e) {}
  }

  // Cover editing is now handled in the moderation modal, not via prompt.
  function removeCoverButtons() {
    try {
      document.querySelectorAll('[data-iad-cover-edit]').forEach((b) => b.remove());
    } catch (e) {}
  }

  function patchCoverPrompt() {
    // Capture-phase click blocker: prevents the old prompt-based cover flow.
    document.addEventListener(
      'click',
      function (e) {
        const btn = e.target && e.target.closest ? e.target.closest('[data-iad-cover-edit]') : null;
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        try { btn.remove(); } catch (e2) {}
      },
      true
    );

    // Remove on render/mutations.
    try {
      const obs = new MutationObserver(function () {
        removeCoverButtons();
      });
      obs.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}

    // Initial sweep.
    removeCoverButtons();
  }

  (function init() {
    patchCoverPrompt();

    // Patch moderation after scripts load.
    if (ready()) {
      patchModerationReturnShape();
      return;
    }
    let tries = 0;
    const t = setInterval(function () {
      tries++;
      if (ready()) {
        clearInterval(t);
        patchModerationReturnShape();
        return;
      }
      if (tries > 80) clearInterval(t);
    }, 25);
  })();
})();
