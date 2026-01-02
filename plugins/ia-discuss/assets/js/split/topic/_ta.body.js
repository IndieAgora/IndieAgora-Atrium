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

      if (!postReplyPill && !quoteBtn && !replyBtn && !copyBtn && !editBtn && !delBtn && !kickBtn) return;

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
        copyToClipboard(makePostUrl(topicId, pid));
        return;
      }

      // Delete
      if (delBtn) {
        var pid = parseInt(delBtn.getAttribute("data-iad-del") || "0", 10) || 0;
        if (!pid) return;

        confirmModal("Delete post", "Delete this post?", () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_delete_post", { post_id: pid }).then((res) => {
            if (!res || !res.success) {
              var msg = (res && res.data && res.data.message) ? res.data.message : "Delete failed";
              confirmModal("Delete failed", msg, null);
              return;
            }
            if (window.IA_DISCUSS_UI_TOPIC && typeof window.IA_DISCUSS_UI_TOPIC.renderInto === "function") {
              window.IA_DISCUSS_UI_TOPIC.renderInto(document, topicId, {});
            }
          });
        });
        return;
      }

      // Kick / ban user
      if (kickBtn) {
        var userId  = parseInt(kickBtn.getAttribute("data-user-id") || "0", 10) || 0;
        var forumId = parseInt(kickBtn.getAttribute("data-forum-id") || "0", 10) || 0;
        var uname   = kickBtn.getAttribute("data-username") || "user";
        if (!userId || !forumId) return;

        confirmModal("Block user", `Block ${uname} from posting in this Agora?`, () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_ban_user", { forum_id: forumId, user_id: userId }).then((res) => {
            if (!res || !res.success) {
              var msg = (res && res.data && res.data.message) ? res.data.message : "Block failed";
              confirmModal("Block failed", msg, null);
              return;
            }
            if (window.IA_DISCUSS_UI_TOPIC && typeof window.IA_DISCUSS_UI_TOPIC.renderInto === "function") {
              window.IA_DISCUSS_UI_TOPIC.renderInto(document, topicId, {});
            }
          });
        });
        return;
      }

      // Edit
      if (editBtn) {
        var pid = parseInt(editBtn.getAttribute("data-iad-edit") || "0", 10) || 0;
        if (!pid) return;

        if (!Modal.openComposerModal || !Modal.getComposerMount) return;

        Modal.openComposerModal("Edit");
        var modalMount = Modal.getComposerMount();
        if (!modalMount) return;
        modalMount.innerHTML = "";

        var rawB64 = editBtn.getAttribute("data-iad-edit-raw") || "";
        var prefill = decodeB64Utf8(rawB64);

        try {
          window.dispatchEvent(new CustomEvent("iad:mount_composer", {
            detail: {
              mount: modalMount,
              mode: "reply",
              topic_id: topicId,
              start_open: true,
              in_modal: true,
              edit_post_id: pid,
              prefillBody: prefill,
              submitLabel: "Save"
            }
          }));
        } catch (e2) {}

        setTimeout(() => {
          var ta = findComposerTextarea(modalMount);
          if (!ta) return;
          try { ta.focus(); } catch (e3) {}
        }, 0);

        return;
      }

      // Quote / Reply icons
      if (quoteBtn) {
        var author = quoteBtn.getAttribute("data-quote-author") || "";
        var postEl = quoteBtn.closest(".iad-post");
        var text = extractQuoteTextFromPost(postEl);
        var quote = `[quote]${author ? author + " wrote:\n" : ""}${text}[/quote]\n\n`;
        openReplyModal(topicId, quote);
        return;
      }

      if (replyBtn) {
        openReplyModal(topicId, "");
        return;
      }
    });
  }

  window.IA_DISCUSS_TOPIC_ACTIONS = { bindTopicActions };
