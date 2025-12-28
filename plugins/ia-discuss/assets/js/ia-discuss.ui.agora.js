(function () {
  "use strict";
  const { qs, esc } = window.IA_DISCUSS_CORE;
  const API = window.IA_DISCUSS_API;

  async function loadMeta(forumId) {
    const res = await API.post("ia_discuss_forum_meta", { forum_id: forumId });
    if (!res || !res.success) return null;
    return res.data;
  }

  function renderAgora(root, forumId, forumName) {
    const mount = qs("[data-iad-view]", root);
    if (!mount) return;

    mount.innerHTML = `<div class="iad-loading">Loading…</div>`;

    loadMeta(forumId).then((meta) => {
      // IMPORTANT: show known name immediately, meta only enriches it
      const name = (meta && meta.forum_name) ? meta.forum_name : (forumName || "");
      const desc = meta ? (meta.forum_desc_html || "") : "";

      mount.innerHTML = `
        <div class="iad-agora">
          <header class="iad-agora-head">
            <div class="iad-agora-banner"></div>

            <div class="iad-agora-inner">
              <div class="iad-agora-title">
                <div class="iad-agora-name">${name ? `agora/${esc(name)}` : `agora/${esc(String(forumId))}`}</div>
                <div class="iad-agora-sub">
                  ${esc((meta && meta.forum_topics) ? meta.forum_topics : 0)} topics •
                  ${esc((meta && meta.forum_posts) ? meta.forum_posts : 0)} posts
                </div>
              </div>

              <div style="margin-top:10px; display:flex; gap:10px; align-items:center;">
                <button type="button" class="iad-btn" data-iad-back-agoras>← Back to Agoras</button>
              </div>

              ${desc ? `<div class="iad-agora-desc">${desc}</div>` : ""}

              <div class="iad-agora-composer" data-iad-agora-composer></div>
            </div>
          </header>

          <div class="iad-agora-feed" data-iad-agora-feed></div>
        </div>
      `;

      // Back button => return to Agoras list
      const back = qs("[data-iad-back-agoras]", mount);
      if (back) {
        back.addEventListener("click", () => {
          window.dispatchEvent(new CustomEvent("iad:go_agoras"));
        });
      }

      // Composer in Agora header
      const composerMount = qs("[data-iad-agora-composer]", mount);
      window.dispatchEvent(new CustomEvent("iad:mount_composer", {
        detail: { mount: composerMount, mode: "topic", forum_id: forumId }
      }));

      // Render feed INSIDE Agora feed slot (without changing the top tab)
      const feedMount = qs("[data-iad-agora-feed]", mount);
      window.dispatchEvent(new CustomEvent("iad:render_feed", {
        detail: { view: "new", forum_id: forumId, mount: feedMount }
      }));
    });
  }

  window.IA_DISCUSS_UI_AGORA = { renderAgora };
})();
