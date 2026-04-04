
      if (view === "agora") {
        inAgora = true;

        lastListView = "agora";
        lastForumId = forumId || 0;
        lastForumName = forumName || "";

        // If arriving via refresh/deep-link, the URL may have only iad_forum.
        // Fetch the agora name once so the header is correct.
        if (!lastForumName && lastForumId) {
          try {
            // NOTE: the canonical meta endpoint is ia_discuss_forum_meta (see includes/modules/forum-meta.php)
            window.IA_DISCUSS_API.post("ia_discuss_forum_meta", { forum_id: String(lastForumId) }).then((r) => {
              if (r && r.success && r.data && r.data.forum_name) {
                lastForumName = String(r.data.forum_name || "");
                // Store in the URL so back/forward and refresh keep the identity.
                setParams({ iad_forum_name: lastForumName }, true);
                try { window.IA_DISCUSS_UI_AGORA.renderAgora(root, lastForumId, lastForumName, opts||null); } catch (e) {}
              }
            });
          } catch (e) {}
        }

        // Update context moderation visibility for this specific forum.
        try {
          root.setAttribute('data-iad-current-forum-id', String(lastForumId||0));
          // Ensure moderation cache is loaded, then decide if user can moderate THIS Agora.
          window.IA_DISCUSS_UI_MODERATION.loadMyModeration(root).then((data) => {
            try {
              const isAdmin = (data && parseInt(String(data.global_admin||'0'),10) === 1);
              const items = (data && Array.isArray(data.items)) ? data.items : [];
              const canHere = isAdmin || items.some((x) => (parseInt(String(x.forum_id||0),10) === (parseInt(String(lastForumId||0),10) || 0)));
              root.setAttribute('data-iad-can-moderate-here', canHere ? '1' : '0');
              const canAny = isAdmin || (items && items.length > 0);
              root.setAttribute('data-iad-can-moderate-any', canAny ? '1' : '0');
              root.setAttribute('data-iad-can-moderate', canAny ? '1' : '0');
              // Refresh tab row visibility (Moderation pill) while keeping Agora context.
              window.IA_DISCUSS_UI_SHELL.setActiveTab('agoras', 'agora');
            } catch (e) {}
          });
        } catch (e) {}

        // Highlight the Agoras tab, but set the logical context to 'agora' so
        // the Moderation pill can be shown/hidden per-forum.
        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras", 'agora');
        // In an Agora view, show moderation pill only if user can moderate THIS forum.
        setModerationContext('agora', lastForumId);
        if (!noHist) {
          setParams({
            iad_view: null,
            iad_forum: String(lastForumId || 0),
            iad_forum_name: String(lastForumName || ""),
            iad_topic: null,
            iad_post: null,
            iad_q: null
          });
        }

        window.IA_DISCUSS_UI_AGORA.renderAgora(root, lastForumId, lastForumName, opts||null);
        return;
      }

      inAgora = false;

      lastListView = view;
      lastForumId = 0;
      lastForumName = "";

      window.IA_DISCUSS_UI_SHELL.setActiveTab(view);
      if (!noHist) {
        setParams({
          iad_view: String(view || "new"),
          iad_forum: null,
          iad_forum_name: null,
            iad_topic: null,
          iad_post: null,
          iad_q: null
        });
      }

      window.IA_DISCUSS_UI_FEED.renderFeedInto(mountEl, view, 0);
    }

    // -----------------------------
    // Feed scroll preservation
    // -----------------------------
    // Atrium can run in "panel scroll" mode (each tab scrolls inside the panel).
    // If so, preserving window.scrollY won't help; we must preserve the panel's
    // scrollTop. We pick the best available scroller in priority order.
