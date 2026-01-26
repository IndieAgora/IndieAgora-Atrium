(function () {
  "use strict";
  const { qs, esc } = window.IA_DISCUSS_CORE;
  const API = window.IA_DISCUSS_API;

  async function loadMeta(forumId) {
    const res = await API.post("ia_discuss_forum_meta", { forum_id: forumId });
    if (!res || !res.success) return null;
    return res.data;
  }

  function renderAgora(root, forumId, forumName, initial) {
    const mount = qs("[data-iad-view]", root);
    if (!mount) return;

    
      try {
        const key = 'iad_agora_state_' + String(forumId);
        const val = { joined: joined ? 1 : 0, bell: bell ? 1 : 0 };
        if (window.localStorage) window.localStorage.setItem(key, JSON.stringify(val));
      } catch(e) {}
mount.innerHTML = `<div class="iad-loading">Loading…</div>`;

    // Hydrate from cached state (list click / previous session) so Agora view matches list immediately.
    let cached = null;
    try {
      const key = 'iad_agora_state_' + String(forumId);
      const raw = window.localStorage ? window.localStorage.getItem(key) : null;
      if (raw) cached = JSON.parse(raw);
    } catch(e) { cached = null; }
    const init = (initial && typeof initial === 'object') ? initial : null;

    loadMeta(forumId).then((meta) => {
      // IMPORTANT: show known name immediately, meta only enriches it
      const name = (meta && meta.forum_name) ? meta.forum_name : (forumName || "");
      const desc = meta ? (meta.forum_desc_html || "") : "";
      const joined = (meta ? (String(meta.joined||'0')==='1') : (cached ? (String(cached.joined||'0')==='1') : (init ? !!init.joined : false)));
      const bell = (meta ? (String(meta.bell||'0')==='1') : (cached ? (String(cached.bell||'0')==='1') : (init ? !!init.bell : false)));
      const banned = meta && String(meta.banned||'0') === '1';
      const cover = meta ? String(meta.cover_url||'') : '';
      const canEditCover = meta && String(meta.can_edit_cover||'0') === '1';

      const bellSvg = `<svg class="iad-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.2 2.2 0 0 0 2.2-2.2h-4.4A2.2 2.2 0 0 0 12 22Zm7-6.2v-5.2a7 7 0 1 0-14 0v5.2L3.6 17.2c-.6.6-.2 1.6.7 1.6h15.4c.9 0 1.3-1 .7-1.6L19 15.8Z" fill="currentColor"/></svg>`;

      mount.innerHTML = `
        <div class="iad-agora">
          <header class="iad-agora-head">
            <div class="iad-agora-banner ${cover ? 'has-cover' : ''}" style="${cover ? `background-image:url(${esc(cover)})` : ''}"></div>

            <div class="iad-agora-inner">
              <div class="iad-agora-title">
                <div class="iad-agora-name">${name ? `agora/${esc(name)}` : `agora/${esc(String(forumId))}`}</div>
                <div class="iad-agora-sub">
                  ${esc((meta && meta.forum_topics) ? meta.forum_topics : 0)} topics •
                  ${esc((meta && meta.forum_posts) ? meta.forum_posts : 0)} posts
                </div>
              </div>

              <div class="iad-agora-header-actions ${joined ? 'iad-joined' : ''} ${banned ? 'iad-banned' : ''}">
                <button type="button" class="iad-btn" data-iad-back-agoras>← Back to Agoras</button>
                <div class="iad-agora-header-actions__right" data-iad-agora-row>
                  <button type="button" class="iad-bell ${bell ? 'is-on' : ''}" data-iad-bell="${forumId}" aria-label="Notifications" aria-pressed="${bell ? 'true':'false'}">${bellSvg}</button>
                  <button type="button" class="iad-join ${joined ? 'is-joined' : ''}" data-iad-join="${forumId}" ${banned ? 'disabled aria-disabled="true"' : ''}>${banned ? 'Kicked' : (joined ? 'Joined' : 'Join')}</button>
                  ${canEditCover ? `<button type="button" class="iad-btn iad-cover-edit" data-iad-cover-edit="${forumId}">Cover</button>` : ''}
                </div>
              </div>

              ${desc ? `<div class="iad-agora-desc">${desc}</div>` : ""}

              ${banned ? `<div class="iad-agora-banned">You have been kicked from this Agora. You can view, but cannot post.</div>` : `<div class="iad-agora-composer" data-iad-agora-composer></div>`}
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
      if (composerMount) {
        window.dispatchEvent(new CustomEvent("iad:mount_composer", {
          detail: { mount: composerMount, mode: "topic", forum_id: forumId }
        }));
      }

      // Render feed INSIDE Agora feed slot (without changing the top tab)
      const feedMount = qs("[data-iad-agora-feed]", mount);
      window.dispatchEvent(new CustomEvent("iad:render_feed", {
        detail: { view: "new", forum_id: forumId, mount: feedMount }
      }));
    });
  }

  window.IA_DISCUSS_UI_AGORA = { renderAgora };
})();
