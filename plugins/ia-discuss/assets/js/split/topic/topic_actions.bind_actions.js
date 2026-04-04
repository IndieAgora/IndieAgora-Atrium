  function bindTopicActions(mount, topicId) {
    if (!mount) return;

    // The topic view reuses the same mount element when navigating between topics.
    // Never close over a stale topicId.
    // If already bound, just update the current topic id and exit.
    if (mount.__iadTopicActionsBound) {
      try { mount.setAttribute("data-iad-topic-id", String(topicId || "")); } catch (e) {}
      return;
    }
    mount.__iadTopicActionsBound = true;
    try { mount.setAttribute("data-iad-topic-id", String(topicId || "")); } catch (e) {}

    mount.addEventListener("click", (e) => {
      const postReplyPill = e.target.closest("[data-iad-post-reply]");
      const quoteBtn = e.target.closest("[data-iad-quote]");
      const replyBtn = e.target.closest("[data-iad-reply]");
      const copyBtn  = e.target.closest("[data-iad-copylink]");
      const shareBtn = e.target.closest("[data-iad-share]");
      const editBtn  = e.target.closest("[data-iad-edit]");
      const delBtn   = e.target.closest("[data-iad-del]");
      const kickBtn  = e.target.closest("[data-iad-kick]");
      const unbanBtn = e.target.closest("[data-iad-unban]");

      if (!postReplyPill && !quoteBtn && !replyBtn && !copyBtn && !shareBtn && !editBtn && !delBtn && !kickBtn && !unbanBtn) return;

      e.preventDefault();
      e.stopPropagation();

      const API = window.IA_DISCUSS_API;

      // Resolve current topic id dynamically (prevents cross-topic actions)
      const curTopicId = parseInt(mount.getAttribute("data-iad-topic-id") || "0", 10) || topicId;

      // ✅ Top pill opens expanded composer modal
      if (postReplyPill) {
        openReplyModal(curTopicId, "");
        return;
      }

      // Copy link
      if (copyBtn) {
        const pid = parseInt(copyBtn.getAttribute("data-post-id") || "0", 10) || 0;
        copyToClipboard(makePostUrl(curTopicId, pid));
        return;
      }

      // Share to Connect (reuse feed share modal)
      if (shareBtn) {
        const pid = parseInt(shareBtn.getAttribute("data-post-id") || "0", 10) || 0;
        try {
          if (window.IA_DISCUSS_SHARE && typeof window.IA_DISCUSS_SHARE.openShareModal === 'function') {
            window.IA_DISCUSS_SHARE.openShareModal(curTopicId, pid);
          }
        } catch (e2) {}
        return;
      }

      // Delete
      if (delBtn) {
        const pid = parseInt(delBtn.getAttribute("data-iad-del") || "0", 10) || 0;
        if (!pid) return;

        confirmModal("Delete post", "Delete this post?", () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_delete_post", { post_id: pid }).then((res) => {
            if (!res || !res.success) {
              const msg = (res && res.data && res.data.message) ? res.data.message : "Delete failed";
              confirmModal("Delete failed", msg, null);
              return;
            }
            if (window.IA_DISCUSS_UI_TOPIC && typeof window.IA_DISCUSS_UI_TOPIC.renderInto === "function") {
              // Re-render the currently viewed topic (not the topic that was first bound)
              window.IA_DISCUSS_UI_TOPIC.renderInto(document, curTopicId, {});
            }
          });
        });
        return;
      }

      // Kick / ban user
      if (kickBtn) {
        const userId  = parseInt(kickBtn.getAttribute("data-user-id") || "0", 10) || 0;
        const forumId = parseInt(kickBtn.getAttribute("data-forum-id") || "0", 10) || 0;
        const uname   = kickBtn.getAttribute("data-username") || "user";
        if (!userId || !forumId) return;

        confirmModal("Block user", `Block ${uname} from posting in this Agora?`, () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_ban_user", { forum_id: forumId, user_id: userId }).then((res) => {
            if (!res || !res.success) {
              const msg = (res && res.data && res.data.message) ? res.data.message : "Block failed";
              confirmModal("Block failed", msg, null);
              return;
            }
            if (window.IA_DISCUSS_UI_TOPIC && typeof window.IA_DISCUSS_UI_TOPIC.renderInto === "function") {
              window.IA_DISCUSS_UI_TOPIC.renderInto(document, curTopicId, {});
            }
          });
        });
        return;
      }

      // Reinstate / unban user
      if (unbanBtn) {
        const userId  = parseInt(unbanBtn.getAttribute("data-user-id") || "0", 10) || 0;
        const forumId = parseInt(unbanBtn.getAttribute("data-forum-id") || "0", 10) || 0;
        const uname   = unbanBtn.getAttribute("data-username") || "user";
        if (!userId || !forumId) return;

        confirmModal("Reinstate user", `Allow ${uname} to post in this Agora again?`, () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_unban_user", { forum_id: forumId, user_id: userId }).then((res) => {
            if (!res || !res.success) {
              const msg = (res && res.data && res.data.message) ? res.data.message : "Reinstate failed";
              confirmModal("Reinstate failed", msg, null);
              return;
            }
            if (window.IA_DISCUSS_UI_TOPIC && typeof window.IA_DISCUSS_UI_TOPIC.renderInto === "function") {
              window.IA_DISCUSS_UI_TOPIC.renderInto(document, curTopicId, {});
            }
          });
        });
        return;
      }

      // Unban / reinstate user
      if (unbanBtn) {
        const userId  = parseInt(unbanBtn.getAttribute("data-user-id") || "0", 10) || 0;
        const forumId = parseInt(unbanBtn.getAttribute("data-forum-id") || "0", 10) || 0;
        const uname   = unbanBtn.getAttribute("data-username") || "user";
        if (!userId || !forumId) return;

        confirmModal("Reinstate user", `Allow ${uname} to post in this Agora again?`, () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_unban_user", { forum_id: forumId, user_id: userId }).then((res) => {
            if (!res || !res.success) {
              const msg = (res && res.data && res.data.message) ? res.data.message : "Reinstate failed";
              confirmModal("Reinstate failed", msg, null);
              return;
            }
            if (window.IA_DISCUSS_UI_TOPIC && typeof window.IA_DISCUSS_UI_TOPIC.renderInto === "function") {
              window.IA_DISCUSS_UI_TOPIC.renderInto(document, curTopicId, {});
            }
          });
        });
        return;
      }

      // Edit
      if (editBtn) {
        const pid = parseInt(editBtn.getAttribute("data-iad-edit") || "0", 10) || 0;
        if (!pid) return;

        if (!Modal.openComposerModal || !Modal.getComposerMount) return;

        Modal.openComposerModal("Edit");
        const modalMount = Modal.getComposerMount();
        if (!modalMount) return;
        modalMount.innerHTML = "";

        const rawB64 = editBtn.getAttribute("data-iad-edit-raw") || "";
        const prefill = decodeB64Utf8(rawB64);

        try {
          window.dispatchEvent(new CustomEvent("iad:mount_composer", {
            detail: {
              mount: modalMount,
              mode: "reply",
              topic_id: curTopicId,
              start_open: true,
              in_modal: true,
              edit_post_id: pid,
              prefillBody: prefill,
              submitLabel: "Save"
            }
          }));
        } catch (e2) {}

        setTimeout(() => {
          const ta = findComposerTextarea(modalMount);
          if (!ta) return;
          try { ta.focus(); } catch (e3) {}
        }, 0);

        return;
      }

      // Quote / Reply icons
      if (quoteBtn) {
        const author = quoteBtn.getAttribute("data-quote-author") || "";
        const postEl = quoteBtn.closest(".iad-post");
        const text = extractQuoteTextFromPost(postEl);
        const quote = `[quote]${author ? author + " wrote:\n" : ""}${text}[/quote]\n\n`;
        openReplyModal(curTopicId, quote);
        return;
      }

      if (replyBtn) {
        openReplyModal(curTopicId, "");
        return;
      }
    });
  }
