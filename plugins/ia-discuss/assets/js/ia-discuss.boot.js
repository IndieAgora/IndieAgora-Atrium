(function () {
  "use strict";

  function depsReady() {
    return (
      window.IA_DISCUSS &&
      window.IA_DISCUSS_CORE &&
      window.IA_DISCUSS_API &&
      window.IA_DISCUSS_UI_SHELL &&
      window.IA_DISCUSS_UI_FEED &&
      window.IA_DISCUSS_UI_AGORA &&
      window.IA_DISCUSS_UI_TOPIC &&
      window.IA_DISCUSS_UI_COMPOSER &&
      window.IA_DISCUSS_UI_SEARCH &&   // ✅ NEW
      window.IA_DISCUSS_ROUTER &&
      typeof window.IA_DISCUSS_ROUTER.mount === "function"
    );
  }

  function safeQS(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function bootWhenReady() {
    let tries = 0;
    const maxTries = 180;

    function tick() {
      tries++;

      if (depsReady()) {
        window.IA_DISCUSS_ROUTER.mount();
        return;
      }

      if (tries >= maxTries) {
        const root = safeQS("[data-ia-discuss-root]");
        if (root) {
          root.innerHTML = `
            <div class="iad-empty">
              Discuss failed to start (JS dependencies not loaded).<br/>
              Check script enqueue order in <code>includes/support/assets.php</code>.
            </div>
          `;
        }
        return;
      }

      requestAnimationFrame(tick);
    }

    requestAnimationFrame(tick);
  }

  document.addEventListener("DOMContentLoaded", bootWhenReady);
})();
