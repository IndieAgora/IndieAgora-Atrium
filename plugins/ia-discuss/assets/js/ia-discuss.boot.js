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
      window.IA_DISCUSS_UI_COMPOSER
    );
  }

  function safeQS(sel, root) {
    try {
      const D = document;
      return (root || D).querySelector(sel);
    } catch (e) {
      return null;
    }
  }

  function getParams() {
    try { return new URL(window.location.href).searchParams; }
    catch (e) { return null; }
  }

  function setParam(key, val) {
    try {
      const url = new URL(window.location.href);
      if (val === null || val === undefined || val === "") url.searchParams.delete(key);
      else url.searchParams.set(key, String(val));
      window.history.pushState({ ia: 1 }, "", url.toString());
    } catch (e) {}
  }

  function mount() {
    const qs = (window.IA_DISCUSS_CORE && window.IA_DISCUSS_CORE.qs) ? window.IA_DISCUSS_CORE.qs : safeQS;
    const root = qs("[data-ia-discuss-root]");
    if (!root) return;

    // Render shell once
    window.IA_DISCUSS_UI_SHELL.shell();

    // Router state
    let inAgora = false;
    let agoraForumId = 0;
    let agoraForumName = "";
    let lastListView = "new"; // what to go back to from topic page (new|unread|agoras/agora)
    let lastForumId = 0;
    let lastForumName = "";

    function render(view, forumId, forumName) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      if (view === "agoras") {
        inAgora = false;
        agoraForumId = 0;
        agoraForumName = "";
        lastListView = "agoras";
        lastForumId = 0;
        lastForumName = "";

        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        setParam("iad_topic", ""); // clear topic deep-link

        mountEl.innerHTML = `<div class="iad-loading">Loading…</div>`;

        window.IA_DISCUSS_API.post("ia_discuss_agoras", { offset: 0, q: "" }).then((res) => {
          if (!res || !res.success) {
            mountEl.innerHTML = `<div class="iad-empty">Failed to load agoras.</div>`;
            return;
          }

          const items = (res.data && res.data.items) ? res.data.items : [];

          mountEl.innerHTML = `
            <div class="iad-agoras">
              ${items.map((f) => `
                <button
                  type="button"
                  class="iad-agora-row"
                  data-forum-id="${f.forum_id}"
                  data-forum-name="${(f.forum_name || "")}">
                  <div class="iad-agora-row-name">agora/${(f.forum_name || "")}</div>
                  <div class="iad-agora-row-sub">${(f.topics || 0)} topics • ${(f.posts || 0)} posts</div>
                </button>
              `).join("")}
            </div>
          `;

          mountEl.querySelectorAll("[data-forum-id]").forEach((b) => {
            b.addEventListener("click", () => {
              const id = parseInt(b.getAttribute("data-forum-id") || "0", 10);
              const nm = b.getAttribute("data-forum-name") || "";
              render("agora", id, nm);
            });
          });
        });

        return;
      }

      if (view === "agora") {
        inAgora = true;
        agoraForumId = forumId || 0;
        agoraForumName = forumName || "";

        lastListView = "agora";
        lastForumId = agoraForumId;
        lastForumName = agoraForumName;

        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        setParam("iad_topic", ""); // clear topic deep-link

        window.IA_DISCUSS_UI_AGORA.renderAgora(root, agoraForumId, agoraForumName);
        return;
      }

      // new | unread => GLOBAL feed views
      inAgora = false;
      agoraForumId = 0;
      agoraForumName = "";

      lastListView = view;
      lastForumId = 0;
      lastForumName = "";

      window.IA_DISCUSS_UI_SHELL.setActiveTab(view);
      setParam("iad_topic", ""); // clear topic deep-link

      window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, 0);
    }

    function openTopicPage(topicId, opts) {
      topicId = parseInt(topicId || "0", 10) || 0;
      if (!topicId) return;

      opts = opts || {};

      // Don’t change the top Discuss tabs visually (keep current tab highlighted)
      // but we ARE now showing a “page” inside the Discuss panel.
      setParam("iad_topic", String(topicId));

      window.IA_DISCUSS_UI_TOPIC.renderInto(root, topicId, opts);
    }

    // Tab clicks
    root.querySelectorAll(".iad-tab").forEach((b) => {
      b.addEventListener("click", () => {
        const v = b.getAttribute("data-view");
        if (!v) return;
        render(v, 0, "");
      });
    });

    // Open topic page event (cards -> topic page)
    window.addEventListener("iad:open_topic_page", (e) => {
      const tid = e.detail && e.detail.topic_id ? e.detail.topic_id : 0;
      if (!tid) return;

      const scroll = e.detail && e.detail.scroll ? String(e.detail.scroll) : "";
      openTopicPage(tid, { scroll });
    });

    // Open Agora event (from feed card)
    window.addEventListener("iad:open_agora", (e) => {
      const fid = e.detail && e.detail.forum_id ? parseInt(e.detail.forum_id, 10) : 0;
      const nm  = (e.detail && e.detail.forum_name) ? String(e.detail.forum_name) : "";
      if (!fid) return;
      render("agora", fid, nm);
    });

    // Back to agoras list
    window.addEventListener("iad:go_agoras", () => {
      render("agoras", 0, "");
    });

    // Topic “Back” button behavior
    window.addEventListener("iad:topic_back", () => {
      setParam("iad_topic", "");
      if (lastListView === "agora" && lastForumId) {
        render("agora", lastForumId, lastForumName || "");
        return;
      }
      if (lastListView === "agoras") {
        render("agoras", 0, "");
        return;
      }
      render(lastListView || "new", 0, "");
    });

    // Render feed request (used by Agora page to render into its feed slot)
    window.addEventListener("iad:render_feed", (e) => {
      const d = e.detail || {};
      const view = d.view || "new";
      const forumId = parseInt(d.forum_id || "0", 10) || 0;
      const mountEl = d.mount || null;

      if (inAgora) {
        window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, forumId);
        return;
      }

      render(view, 0, "");
    });

    // Mount composer event
    window.addEventListener("iad:mount_composer", (e) => {
      const d = e.detail || {};
      if (!d.mount) return;

      const mode = d.mode || "topic";
      d.mount.innerHTML = window.IA_DISCUSS_UI_COMPOSER.composerHTML({ mode });

      window.IA_DISCUSS_UI_COMPOSER.bindComposer(d.mount, {
        mode,
        onSubmit(payload, ui) {
          if (IA_DISCUSS.loggedIn !== "1") {
            window.dispatchEvent(new CustomEvent("ia:login_required"));
            return;
          }

          let body = payload.body || "";
          if (payload.attachments && payload.attachments.length) {
            const json = JSON.stringify(payload.attachments);
            const b64 = btoa(unescape(encodeURIComponent(json)));
            body = body + "\n\n[ia_attachments]" + b64;
          }

          if (mode === "topic") {
            const fid = d.forum_id || 0;
            if (!fid) return alert("Open an Agora to post there.");

            window.IA_DISCUSS_API.post("ia_discuss_new_topic", {
              forum_id: fid,
              title: payload.title || "",
              body
            }).then((res) => {
              if (!res || !res.success) {
                return alert((res && res.data && res.data.message) ? res.data.message : "Post failed");
              }
              ui.clear();
              render("agora", fid, agoraForumName || "");
            });
            return;
          }

          if (mode === "reply") {
            const topicId = d.topic_id || 0;
            window.IA_DISCUSS_API.post("ia_discuss_reply", {
              topic_id: topicId,
              body
            }).then((res) => {
              if (!res || !res.success) {
                return alert((res && res.data && res.data.message) ? res.data.message : "Reply failed");
              }
              ui.clear();
              openTopicPage(topicId);
            });
          }
        }
      });
    });

    // Quote event -> inject quote into topic reply textarea
    window.addEventListener("iad:quote", (e) => {
      const d = e.detail || {};
      const text = (d.text || "").trim();
      const author = (d.author || "").trim();
      const quote = `[quote]${author ? author + " wrote:\n" : ""}${text}[/quote]\n\n`;

      const ta = document.querySelector("textarea[data-iad-bodytext]");
      if (!ta) return;
      ta.value = quote + (ta.value || "");
      ta.focus();
    });

    // Handle back/forward browser buttons
    window.addEventListener("popstate", () => {
      const p = getParams();
      const tid = p ? parseInt(p.get("iad_topic") || "0", 10) : 0;
      if (tid) {
        openTopicPage(tid);
        return;
      }
      // If no topic param, just go to default list
      render("new", 0, "");
    });

    // Initial view: if URL contains iad_topic, open it
    const p = getParams();
    const initialTid = p ? parseInt(p.get("iad_topic") || "0", 10) : 0;
    if (initialTid) {
      // keep default list context as “new” for back
      lastListView = "new";
      openTopicPage(initialTid);
      return;
    }

    render("new", 0, "");
  }

  function bootWhenReady() {
    let tries = 0;
    const maxTries = 180;

    function tick() {
      tries++;
      if (depsReady()) {
        mount();
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
