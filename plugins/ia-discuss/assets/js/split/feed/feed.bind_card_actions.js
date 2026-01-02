"use strict";
            username: btn.getAttribute("data-username") || "",
            user_id: btn.getAttribute("data-user-id") || "0"
          });
        });
      });

      // Links pill
      mount.querySelectorAll("[data-iad-open-links]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          var urls = [];
          try { urls = JSON.parse(btn.getAttribute("data-links") || "[]"); }
          catch (err) { urls = []; }

          var card = btn.closest("[data-topic-id]");
          var title = card ? (card.querySelector(".iad-card-title")?.textContent || "") : "";
          openLinksModal(urls, title);
        });
      });

      // Video thumbnail -> viewer
      mount.querySelectorAll("[data-iad-open-video]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          var videoUrl = btn.getAttribute("data-video-url") || "";
          var meta = buildVideoMeta(videoUrl);

          var card = btn.closest("[data-topic-id]");
          var title = card ? (card.querySelector(".iad-card-title")?.textContent || "") : "Video";
          openVideoModal(meta, title);
        });
      });

      // Copy link
      mount.querySelectorAll("[data-copy-topic-link]").forEach((btn) => {
        btn.addEventListener("click", async (e) => {
          e.preventDefault();
          e.stopPropagation();

          var card = btn.closest("[data-topic-id]");
          var tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
          if (!tid) return;

          var url = makeTopicUrl(tid);
          var ok = await copyToClipboard(url);

          btn.classList.add("is-pressed");
          setTimeout(() => btn.classList.remove("is-pressed"), 450);

          if (!ok) alert("Could not copy link on this browser.");
        });
      });

      // Share to Connect (event only — Connect will implement)
      mount.querySelectorAll("[data-share-topic]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          var card = btn.closest("[data-topic-id]");
          if (!card) return;
          var tid = parseInt(card.getAttribute("data-topic-id") || "0", 10);
          if (!tid) return;

          var title = card.querySelector(".iad-card-title")?.textContent || "";
          var url = makeTopicUrl(tid);

          window.dispatchEvent(new CustomEvent("ia:share_to_connect", {
            detail: { type: "discuss_topic", topic_id: tid, title, url }
          }));

          btn.classList.add("is-pressed");
          setTimeout(() => btn.classList.remove("is-pressed"), 450);
        });
      });

      // Edit (UI hook only)
      mount.querySelectorAll("[data-edit-topic]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          alert("Edit is wired into UI, but saving edits isn’t implemented yet.");
        });
      });
    });
  }
;
