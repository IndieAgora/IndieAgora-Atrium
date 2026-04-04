  function loadResults(mount, q, type, offset) {
    const box = qs("[data-iad-search-results]", mount);
    if (!box) return;

    box.innerHTML = `<div class="iad-loading">Loading…</div>`;

    API.post("ia_discuss_search", { q, type, offset: offset || 0, limit: 25 }).then((res) => {
      if (!res || !res.success) {
        box.innerHTML = `<div class="iad-empty">Search failed.</div>`;
        return;
      }

      const d = res.data || {};
      const items = Array.isArray(d.items) ? d.items : [];
      const hasMore = !!d.has_more;

      box.innerHTML = `
        <div class="iad-sr-list">
          ${items.length ? items.map((it, i) => renderResultRow(type, it, i)).join("") : `<div class="iad-empty">No results.</div>`}
          ${hasMore ? `<button type="button" class="iad-more" data-iad-sr-more>Load more</button>` : ``}
        </div>
      `;

      const more = qs("[data-iad-sr-more]", box);
      if (more) {
        more.addEventListener("click", () => {
          more.disabled = true;
          API.post("ia_discuss_search", { q, type, offset: (offset || 0) + 25, limit: 25 }).then((res2) => {
            if (!res2 || !res2.success) { more.disabled = false; return; }
            const d2 = res2.data || {};
            const items2 = Array.isArray(d2.items) ? d2.items : [];
            const hasMore2 = !!d2.has_more;

            const list = qs(".iad-sr-list", box);
            if (!list) return;

            // current rows count for alternating
            const existing = list.querySelectorAll(".iad-sr-row").length;

            const tmp = document.createElement("div");
            tmp.innerHTML = items2.map((it, i) => renderResultRow(type, it, existing + i)).join("");
            list.insertBefore(tmp, more);

            if (!hasMore2) more.remove();
            else more.disabled = false;

            offset = (offset || 0) + 25;
          });
        });
      }

      bindResultsClicks(mount);
    });
  }

  function renderSearchPageInto(mount, q) {
    if (!mount) return;
    q = String(q || "").trim();
    mount.innerHTML = resultsShellHTML(q);

    const back = qs("[data-iad-search-back]", mount);
    if (back) back.addEventListener("click", () => {
      window.dispatchEvent(new CustomEvent("iad:search_back"));
    });

    let activeType = "topics";

    mount.querySelectorAll(".iad-stab").forEach((b) => {
      b.addEventListener("click", () => {
        const t = b.getAttribute("data-type") || "topics";
        activeType = t;
        setActiveType(mount, activeType);
        loadResults(mount, q, activeType, 0);
      });
    });

    loadResults(mount, q, activeType, 0);
  }
