    function render(view, forumId, forumName, opts) {
      const mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      opts = opts || {};
      const noHist = !!opts.no_history;

      // Back-compat: older URLs used "unread". Map to the new Replies view.
      if (view === 'unread') view = 'replies';

      inSearch = false;

      // Update moderation visibility flags for the current context.
      // - data-iad-can-moderate-any: user can moderate at least one forum (or is admin)
      // - data-iad-can-moderate-here: user can moderate the *current* forum (admin always)
      async function setModerationContext(curView, curForumId) {
        try {
          const d = await window.IA_DISCUSS_UI_MODERATION.loadMyModeration(root);
          const items = (d && Array.isArray(d.items)) ? d.items : [];
          const isAdmin = !!(d && (parseInt(String(d.global_admin||'0'),10) === 1));
          const canAny = !!(isAdmin || items.length > 0);
          let canHere = canAny;
          if (curView === 'agora') {
            const fid = parseInt(String(curForumId||0),10) || 0;
            canHere = !!(isAdmin || (fid && items.some((x) => (parseInt(String(x.forum_id||0),10) === fid))));
          }
          root.setAttribute('data-iad-can-moderate-any', canAny ? '1' : '0');
          root.setAttribute('data-iad-can-moderate-here', canHere ? '1' : '0');
        } catch (e) {}
      }

      if (view === "moderation") {
        inAgora = false;
        lastListView = "moderation";
        lastForumId = 0;
        lastForumName = "";

        setModerationContext('moderation', 0);
        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        if (!noHist) {
          setParams({
            iad_view: "moderation",
            iad_forum: null,
            iad_forum_name: null,
            iad_topic: null,
            iad_post: null,
            iad_q: null
          });
        }

        mountEl.innerHTML = `<div class="iad-loading">Loading…</div>`;
        try {
          // renderModerationView expects the Discuss root so it can locate [data-iad-view]
          // and manage internal state correctly.
          window.IA_DISCUSS_UI_MODERATION.renderModerationView(root);
        } catch (e) {
          mountEl.innerHTML = `<div class="iad-empty">Moderation failed to load.</div>`;
        }
        return;
      }

