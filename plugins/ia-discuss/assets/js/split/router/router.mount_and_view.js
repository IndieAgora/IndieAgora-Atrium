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
      window.IA_DISCUSS_UI_SEARCH
    );
  }

  function safeQS(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }

  function setParam(key, val) {
    try {
      var url = new URL(window.location.href);
      if (val === null || val === undefined || val === "") url.searchParams.delete(key);
      else url.searchParams.set(key, String(val));
      window.history.pushState({ ia: 1 }, "", url.toString());
    } catch (e) {}
  }

  function mount() {
    if (!depsReady()) return;

    var qs = (window.IA_DISCUSS_CORE && window.IA_DISCUSS_CORE.qs) ? window.IA_DISCUSS_CORE.qs : safeQS;
    var root = qs("[data-ia-discuss-root]");
    if (!root) return;

    window.IA_DISCUSS_UI_SHELL.shell();

    // bind search UX
    try {
      window.IA_DISCUSS_UI_SEARCH.bindSearchBox(root);
    } catch (e) {}

    var inAgora = false;
    var lastListView = "new";
    var lastForumId = 0;
    var lastForumName = "";

    var inSearch = false;
    var searchPrev = { view: "new", forum_id: 0, forum_name: "" };
    var lastSearchQ = "";

    function render(view, forumId, forumName) {
      var mountEl = qs("[data-iad-view]", root);
      if (!mountEl) return;

      inSearch = false;

      if (view === "agoras") {
        inAgora = false;
        lastListView = "agoras";
        lastForumId = 0;
        lastForumName = "";

        window.IA_DISCUSS_UI_SHELL.setActiveTab("agoras");
        setParam("iad_topic", "");

        mountEl.innerHTML = `<div class="iad-loading">Loading…</div>`;

        window.IA_DISCUSS_API.post("ia_discuss_agoras", { offset: 0, q: "" }).then((res) => {
          if (!res || !res.success) {
            mountEl.innerHTML = `<div class="iad-empty">Failed to load agoras.</div>`;
            return;
          }

          var items = (res.data && res.data.items) ? res.data.items : [];

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
;
