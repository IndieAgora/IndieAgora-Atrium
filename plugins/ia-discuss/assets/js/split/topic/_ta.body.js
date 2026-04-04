  "use strict";

  const U = window.IA_DISCUSS_TOPIC_UTILS || {};
  const Modal = window.IA_DISCUSS_TOPIC_MODAL || {};
  const qs = U.qs;

  function findComposerTextarea(scope) {
    const root = scope || document;
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
    const bodyEl = qs(".iad-post-body", postEl);
    if (!bodyEl) return "";
    const t = String(bodyEl.innerText || "").trim();
    if (t) return t;
    return String(bodyEl.textContent || "").trim();
  }

  function decodeB64Utf8(b64) {
    try { return decodeURIComponent(escape(atob(String(b64 || "")))); } catch (e) { return ""; }
  }

  function copyToClipboard(text) {
    const t = String(text || "");
    if (!t) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).catch(() => {});
      return;
    }
    try {
      const ta = document.createElement("textarea");
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
      const u = new URL(window.location.href);
      u.searchParams.set("iad_topic", String(topicId));
      if (postId) u.searchParams.set("iad_post", String(postId));
      return u.toString();
    } catch (e) {
      return window.location.href;
    }
  }

  // Confirm modal from your current build
  function ensureConfirmModal() {
    let wrap = document.querySelector("[data-iad-confirm-modal]");
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
      wrap.style.display = "none";
      wrap.setAttribute("aria-hidden", "true");
      wrap.__onok = null;
    }

    wrap.querySelector("[data-iad-confirm-overlay]")?.addEventListener("click", close);
    wrap.querySelector("[data-iad-confirm-close]")?.addEventListener("click", close);
    wrap.querySelector("[data-iad-confirm-cancel]")?.addEventListener("click", close);
    wrap.querySelector("[data-iad-confirm-ok]")?.addEventListener("click", () => {
      const fn = wrap.__onok;
      close();
      if (typeof fn === "function") fn();
    });

    wrap.addEventListener("keydown", (e) => {
      if (e.key === "Escape") { e.preventDefault(); close(); }
    });

    wrap.__close = close;
    return wrap;
  }

  function confirmModal(title, text, onOk) {
    const m = ensureConfirmModal();
    m.querySelector("[data-iad-confirm-title]").textContent = String(title || "Confirm");
    m.querySelector("[data-iad-confirm-text]").textContent = String(text || "");
    m.__onok = onOk;

    m.style.display = "block";
    m.setAttribute("aria-hidden", "false");
    try { m.tabIndex = -1; m.focus(); } catch (e) {}
  }

  function openReplyModal(topicId, prefill) {
    if (!Modal.openComposerModal || !Modal.getComposerMount) return;

    Modal.openComposerModal("Reply");
    const modalMount = Modal.getComposerMount();
    if (!modalMount) return;
    modalMount.innerHTML = "";

    try {
      window.dispatchEvent(new CustomEvent("iad:mount_composer", {
        detail: {
          mount: modalMount,
          mode: "reply",
          topic_id: topicId,
          start_open: true,
          in_modal: true,
          prefillBody: prefill || ""
        }
      }));
    } catch (e2) {}

    setTimeout(() => {
      const ta = findComposerTextarea(modalMount);
      if (!ta) return;
      try { ta.focus(); } catch (e3) {}
    }, 0);
  }

