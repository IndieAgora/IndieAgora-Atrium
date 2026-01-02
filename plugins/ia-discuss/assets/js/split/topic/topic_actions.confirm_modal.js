  "use strict";

  var U = window.IA_DISCUSS_TOPIC_UTILS || {};
  var Modal = window.IA_DISCUSS_TOPIC_MODAL || {};
  var qs = U.qs;

  function findComposerTextarea(scope) {
    var root = scope || document;
    return (
      qs('textarea[data-iad-bodytext]', root) ||
      qs('textarea[name="body"]', root) ||
      qs('textarea[name="message"]', root) ||
      qs(".iad-composer textarea", root) ||
      qs("textarea", root)
    );
  }

  function extractQuoteTextFromPost(postEl) {
    if (!postEl) return "";
    var bodyEl = qs(".iad-post-body", postEl);
    if (!bodyEl) return "";
    var t = String(bodyEl.innerText || "").trim();
    if (t) return t;
    return String(bodyEl.textContent || "").trim();
  }

  function decodeB64Utf8(b64) {
    try { return decodeURIComponent(escape(atob(String(b64 || "")))); } catch (e) { return ""; }
  }

  function copyToClipboard(text) {
    var t = String(text || "");
    if (!t) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).catch(() => {});
      return;
    }
    try {
      var ta = document.createElement("textarea");
      ta.value = t;
      ta.style.position = "fixed";
      ta.style.left = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
    } catch (e) {}
  }

  function makePostUrl(topicId, postId) {
    try {
      var u = new URL(window.location.href);
      u.searchParams.set("iad_topic", String(topicId));
      if (postId) u.searchParams.set("iad_post", String(postId));
      return u.toString();
    } catch (e) {
      return window.location.href;
    }
  }

  // Confirm modal from your current build
  function ensureConfirmModal() {
    var wrap = document.querySelector("[data-iad-confirm-modal]");
    if (wrap) return wrap;

    wrap = document.createElement("div");
    wrap.setAttribute("data-iad-confirm-modal", "1");
    wrap.setAttribute("aria-hidden", "true");
    wrap.style.cssText = "position:fixed;inset:0;z-index:99999;display:none;";

    wrap.innerHTML = `
      <div class="iad-compose-overlay" data-iad-confirm-overlay></div>
      <div role="dialog" aria-modal="true" class="iad-compose-sheet">
        <div class="iad-compose-top">
          <div class="iad-compose-title" data-iad-confirm-title>Confirm</div>
          <button type="button" class="iad-compose-x" data-iad-confirm-close aria-label="Close">✕</button>
        </div>
        <div class="iad-compose-body">
          <div class="iad-confirm-text" data-iad-confirm-text style="margin-bottom:12px;"></div>
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="iad-btn" data-iad-confirm-cancel>Cancel</button>
            <button type="button" class="iad-btn iad-btn-primary" data-iad-confirm-ok>OK</button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(wrap);

    function close() {
;
