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
      window.IA_DISCUSS_UI_COMPOSER &&
      window.IA_DISCUSS_UI_SEARCH &&
      window.IA_DISCUSS_UI_MODERATION &&
      window.IA_DISCUSS_UI_RULES
    );
  }

  function safeQS(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function setParam(key, val) {
    try {
      const url = new URL(window.location.href);
      if (val === null || val === undefined || val === "") url.searchParams.delete(key);
      else url.searchParams.set(key, String(val));
      window.history.pushState({ ia: 1 }, "", url.toString());
    } catch (e) {}
  }

  // Set multiple query params in one push/replace.
  // map: { key: value|null } where null deletes the param.
  function setParams(map, replace) {
    try {
      const url = new URL(window.location.href);
      Object.keys(map || {}).forEach((k) => {
        const v = map[k];
        if (v === null || v === undefined || v === "") url.searchParams.delete(k);
        else url.searchParams.set(k, String(v));
      });
      const st = { ia: 1 };
      if (replace) window.history.replaceState(st, "", url.toString());
      else window.history.pushState(st, "", url.toString());
    } catch (e) {}
  }

  function mount() {
    if (!depsReady()) return;

    const qs = (window.IA_DISCUSS_CORE && window.IA_DISCUSS_CORE.qs) ? window.IA_DISCUSS_CORE.qs : safeQS;
    const root = qs("[data-ia-discuss-root]");
    if (!root) return;

    window.IA_DISCUSS_UI_SHELL.shell();

    let inAgora = false;
    let lastListView = "new";
    let lastForumId = 0;
    let lastForumName = "";

    let inSearch = false;
    let searchPrev = { view: "new", forum_id: 0, forum_name: "" };
    let lastSearchQ = "";

    // bind search UX
    try {
      window.IA_DISCUSS_UI_SEARCH.bindSearchBox(root);
    } catch (e) {}

    // Bind Rules modal click-handlers
    try {
      window.IA_DISCUSS_UI_RULES.bind();
    } catch (e) {}

    // Bind Moderation UX and pre-load permissions
    try {
      // IMPORTANT: bind needs the Discuss root to attach event listeners.
      window.IA_DISCUSS_UI_MODERATION.bind(root);
      // IMPORTANT: pass root so the moderation module can set data-iad-can-moderate.
      // Also compute capability from the returned items length (some builds don't return a count).
      window.IA_DISCUSS_UI_MODERATION.loadMyModeration(root).then((data) => {
        try {
          const itemsLen = data && Array.isArray(data.items) ? data.items.length : (data && data.loaded && data.items ? (data.items.length||0) : 0);
          const isAdmin = (data && String(data.global_admin||'0') === '1');
          const canAny = !!(isAdmin || itemsLen > 0 || parseInt(String(data.count||'0'), 10) > 0);

          // Back-compat (older shell logic)
          root.setAttribute('data-iad-can-moderate', canAny ? '1' : '0');
          // New context flags
          root.setAttribute('data-iad-can-moderate-any', canAny ? '1' : '0');
          // Default "here" to any; it will be refined when entering a specific Agora.
          root.setAttribute('data-iad-can-moderate-here', canAny ? '1' : '0');

          window.IA_DISCUSS_UI_SHELL.setActiveTab(lastListView || 'new');
        } catch (e) {}
      });
    } catch (e) {}

