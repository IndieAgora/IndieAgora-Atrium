"use strict";
      wrap.style.display = "none";
      wrap.setAttribute("aria-hidden", "true");
      wrap.__onok = null;
    }

    wrap.querySelector("[data-iad-confirm-overlay]")?.addEventListener("click", close);
    wrap.querySelector("[data-iad-confirm-close]")?.addEventListener("click", close);
    wrap.querySelector("[data-iad-confirm-cancel]")?.addEventListener("click", close);
    wrap.querySelector("[data-iad-confirm-ok]")?.addEventListener("click", () => {
      var fn = wrap.__onok;
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
    var m = ensureConfirmModal();
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
    var modalMount = Modal.getComposerMount();
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
      var ta = findComposerTextarea(modalMount);
      if (!ta) return;
      try { ta.focus(); } catch (e3) {}
    }, 0);
  }

  function bindTopicActions(mount, topicId) {
    if (!mount || mount.__iadTopicActionsBound) return;
    mount.__iadTopicActionsBound = true;

    mount.addEventListener("click", (e) => {
      var postReplyPill = e.target.closest("[data-iad-post-reply]");
      var quoteBtn = e.target.closest("[data-iad-quote]");
      var replyBtn = e.target.closest("[data-iad-reply]");
      var copyBtn  = e.target.closest("[data-iad-copylink]");
      var editBtn  = e.target.closest("[data-iad-edit]");
      var delBtn   = e.target.closest("[data-iad-del]");
      var kickBtn  = e.target.closest("[data-iad-kick]");
      var unbanBtn = e.target.closest("[data-iad-unban]");

      if (!postReplyPill && !quoteBtn && !replyBtn && !copyBtn && !editBtn && !delBtn && !kickBtn && !unbanBtn) return;

      e.preventDefault();
      e.stopPropagation();

      var API = window.IA_DISCUSS_API;

      // ✅ NEW: Top pill opens expanded composer modal
      if (postReplyPill) {
        openReplyModal(topicId, "");
        return;
      }

      // Copy link
      if (copyBtn) {
        var pid = parseInt(copyBtn.getAttribute("data-post-id") || "0", 10) || 0;
;
