"use strict";
  }

  // -------------------------------------------------
  // Connect open helper (MUST match feed behaviour)
  // -------------------------------------------------
  function openConnectProfile(payload) {
    var p = payload || {};
    var username = String(p.username || "").trim();
    var user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username,
        user_id,
        ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try {
      window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } }));
    } catch (e) {}

    var tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // ---------------------------
  // Suggestions dropdown
  // ---------------------------
  function ensureSuggestBox(root) {
    var wrap = qs("[data-iad-search-wrap]", root) || (qs(".iad-search", root) || null);
    if (!wrap) return null;

    var box = qs("[data-iad-suggest]", wrap);
    if (box) return box;

    wrap.style.position = "relative";
    box = document.createElement("div");
    box.setAttribute("data-iad-suggest", "1");
    box.className = "iad-suggest";
    box.style.display = "none";
    wrap.appendChild(box);
    return box;
  }

  function hideSuggest(box) {
    if (!box) return;
    box.style.display = "none";
    box.innerHTML = "";
  }

  function showSuggest(box) {
    if (!box) return;
    box.style.display = "block";
  }

  function suggestGroup(title, itemsHTML) {
    return `
      <div class="iad-sg">
        <div class="iad-sg-title">${esc(title)}</div>
        <div class="iad-sg-items">${itemsHTML}</div>
      </div>
    `;
  }

  function bindSearchBox(root) {
    var input = qs("[data-iad-search]", root);
    if (!input || input.__iadBound) return;
    input.__iadBound = true;

    var box = ensureSuggestBox(root);

    var runSuggest = debounce((q) => {
      q = String(q || "").trim();
      if (!q || q.length < 2) return hideSuggest(box);

      API.post("ia_discuss_search_suggest", { q }).then((res) => {
        if (!res || !res.success) return hideSuggest(box);

        var d = res.data || {};
        var users = Array.isArray(d.users) ? d.users : [];
        var agoras = Array.isArray(d.agoras) ? d.agoras : [];
        var topics = Array.isArray(d.topics) ? d.topics : [];
        var replies = Array.isArray(d.replies) ? d.replies : [];

        var parts = [];

        parts.push(`
          <button type="button" class="iad-sug-row is-cta" data-iad-sug-open-search data-q="${esc(q)}">
            Search for <span class="iad-sug-q">“${esc(q)}”</span>
;
