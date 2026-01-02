"use strict";
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

      // Reinstate / unban user
      if (unbanBtn) {
        var userId  = parseInt(unbanBtn.getAttribute("data-user-id") || "0", 10) || 0;
        var forumId = parseInt(unbanBtn.getAttribute("data-forum-id") || "0", 10) || 0;
        var uname   = unbanBtn.getAttribute("data-username") || "user";
        if (!userId || !forumId) return;

        confirmModal("Reinstate user", `Allow ${uname} to post in this Agora again?`, () => {
          if (!API || typeof API.post !== "function") return;

          API.post("ia_discuss_unban_user", { forum_id: forumId, user_id: userId }).then((res) => {
            if (!res || !res.success) {
              var msg = (res && res.data && res.data.message) ? res.data.message : "Reinstate failed";
              confirmModal("Reinstate failed", msg, null);
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
;
