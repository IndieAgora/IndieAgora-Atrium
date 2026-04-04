(function () {
  "use strict";
  const { qs, esc, timeAgo } = window.IA_DISCUSS_CORE;
  const API = window.IA_DISCUSS_API;

  async function loadMeta(forumId) {
    const res = await API.post("ia_discuss_forum_meta", { forum_id: forumId });
    if (!res || !res.success) return null;
    return res.data;
  }

  function isAgoraBBMode(root) {
    try {
      const host = root || qs('[data-ia-discuss-root]');
      return !!host && String(host.getAttribute('data-iad-layout') || '') === 'agorabb';
    } catch (e) {}
    return false;
  }

  function openConnectProfile(payload) {
    const p = payload || {};
    const username = (p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

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

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  function currentInviteId() {
    try {
      const u = new URL(window.location.href);
      return parseInt(String(u.searchParams.get('iad_invite') || '0'), 10) || 0;
    } catch (e) { return 0; }
  }

  function ensureInviteModal() {
    let m = document.querySelector('[data-iad-agora-invite-modal]');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'iad-modal iad-modal--full';
    m.setAttribute('data-iad-agora-invite-modal', '1');
    m.hidden = true;
    m.innerHTML = '<div class="iad-modal-backdrop"></div><div class="iad-modal-sheet" role="dialog" aria-modal="true"><div class="iad-modal-top"><div class="iad-modal-title">Agora invite</div></div><div class="iad-modal-body" data-iad-invite-body></div></div>';
    document.body.appendChild(m);
    return m;
  }

  async function maybeHandleInvite(root, forumId, forumName, initial) {
    const inviteId = currentInviteId();
    if (!inviteId) return false;
    const mount = qs('[data-iad-view]', root);
    const showExpired = function(name){
      if (mount) {
        mount.innerHTML = `<div class="iad-agora"><header class="iad-agora-head"><div class="iad-agora-inner"><div class="iad-agora-title"><div class="iad-agora-name">agora/${esc(name || forumName || '')}</div></div><div class="iad-agora-desc">This invite is no longer valid. Ask a moderator for a fresh invite.</div></div></header></div>`;
      }
      const mx = ensureInviteModal();
      const bx = qs('[data-iad-invite-body]', mx);
      if (bx) bx.innerHTML = `<p>Invite expired.</p><p class="iad-muted">Ask a moderator for a fresh invite.</p><div style="display:flex;gap:10px;justify-content:flex-end;"><button type="button" class="iad-btn iad-btn-primary" data-iad-invite-expired-ok>OK</button></div>`;
      mx.hidden = false;
      const ok = mx.querySelector('[data-iad-invite-expired-ok]');
      if (ok) ok.onclick = function(){ mx.hidden = true; window.dispatchEvent(new CustomEvent('iad:go_agoras')); };
      return true;
    };
    const res = await API.post('ia_discuss_agora_invite_get', { invite_id: inviteId });
    if (!res || !res.success) return showExpired(forumName);
    const d = res.data || {};
    if ((parseInt(String(d.forum_id || '0'), 10) || 0) !== forumId) return showExpired(String(d.forum_name || forumName || ''));
    if (String(d.status || '') !== 'pending') return showExpired(String(d.forum_name || forumName || ''));
    if (mount) {
      mount.innerHTML = `<div class="iad-agora"><header class="iad-agora-head"><div class="iad-agora-inner"><div class="iad-agora-title"><div class="iad-agora-name">agora/${esc(d.forum_name || forumName || '')}</div></div><div class="iad-agora-desc">This Agora is private. Use the invite prompt to accept or decline.</div></div></header></div>`;
    }
    const m = ensureInviteModal();
    const body = qs('[data-iad-invite-body]', m);
    if (body) body.innerHTML = `<p>You have been invited to join agora/${esc(d.forum_name || forumName || '')}.</p><div style="display:flex;gap:10px;justify-content:flex-end;"><button type="button" class="iad-btn" data-iad-invite-decline>Decline</button><button type="button" class="iad-btn iad-btn-primary" data-iad-invite-accept>Accept</button></div>`;
    m.hidden = false;
    const doRespond = async function(decision){
      const rr = await API.post('ia_discuss_agora_invite_respond', { invite_id: inviteId, decision: decision });
      if (!rr || !rr.success) return;
      m.hidden = true;
      try {
        const u = new URL(window.location.href);
        u.searchParams.set('tab', 'discuss');
        u.searchParams.set('ia_tab', 'discuss');
        u.searchParams.set('iad_view', 'agora');
        u.searchParams.set('iad_forum', String(forumId));
        u.searchParams.delete('iad_invite');
        window.history.replaceState(window.history.state || {}, '', u.toString());
      } catch (e) {}
      if (decision === 'accept') renderAgora(root, forumId, forumName, initial);
      else window.dispatchEvent(new CustomEvent('iad:go_agoras'));
    };
    const accept = m.querySelector('[data-iad-invite-accept]');
    const decline = m.querySelector('[data-iad-invite-decline]');
    if (accept) accept.onclick = function(){ doRespond('accept'); };
    if (decline) decline.onclick = function(){ doRespond('decline'); };
    return true;
  }

  function bindPreviewClicks(mount) {
    mount.onclick = function(e) {
      const t = e.target;
      const btn = t && t.closest ? t.closest('[data-iad-agora-preview-open]') : null;
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        const topicId = parseInt(btn.getAttribute('data-topic-id') || '0', 10) || 0;
        const postId = parseInt(btn.getAttribute('data-post-id') || '0', 10) || 0;
        if (!topicId) return;
        if (postId) {
          window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: topicId, scroll_post_id: postId } }));
        } else {
          window.dispatchEvent(new CustomEvent('iad:open_topic_page', { detail: { topic_id: topicId, scroll: '' } }));
        }
        return;
      }

      const profileBtn = t && t.closest ? t.closest('[data-iad-profile-open]') : null;
      if (profileBtn) {
        e.preventDefault();
        e.stopPropagation();
        openConnectProfile({
          username: profileBtn.getAttribute('data-username') || '',
          user_id: profileBtn.getAttribute('data-user-id') || '0'
        });
        return;
      }

      const back = t && t.closest ? t.closest('[data-iad-back-agoras]') : null;
      if (back) {
        e.preventDefault();
        e.stopPropagation();
        window.dispatchEvent(new CustomEvent('iad:go_agoras'));
      }
    };
  }

  function renderAtriumAgora(root, mount, forumId, meta, forumName, cached, init) {
    const name = (meta && meta.forum_name) ? meta.forum_name : (forumName || "");
    const desc = meta ? (meta.forum_desc_html || "") : "";
    const joined = (meta ? (String(meta.joined||'0')==='1') : (cached ? (String(cached.joined||'0')==='1') : (init ? !!init.joined : false)));
    const bell = (meta ? (String(meta.bell||'0')==='1') : (cached ? (String(cached.bell||'0')==='1') : (init ? !!init.bell : false)));
    const banned = meta && String(meta.banned||'0') === '1';
    const cover = meta ? String(meta.cover_url||'') : '';
    const canEditCover = meta && String(meta.can_edit_cover||'0') === '1';
    const rulesHtml = (meta && meta.forum_rules_html) ? String(meta.forum_rules_html) : '';

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
                <button type="button" class="iad-btn iad-rules-open" data-iad-rules-open="${forumId}">Rules</button>
              </div>
            </div>

            ${desc ? `<div class="iad-agora-desc">${desc}</div>` : ""}

            <div class="iad-agora-previews" data-iad-agora-previews></div>
            <div data-iad-rules-src hidden>${rulesHtml}</div>

            ${banned ? `<div class="iad-agora-banned">You have been kicked from this Agora. You can view, but cannot post.</div>` : `<div class="iad-agora-composer" data-iad-agora-composer></div>`}
          </div>
        </header>

        <div class="iad-agora-feed" data-iad-agora-feed></div>
      </div>
    `;

    bindPreviewClicks(mount);

    const composerMount = qs("[data-iad-agora-composer]", mount);
    if (composerMount) {
      window.dispatchEvent(new CustomEvent("iad:mount_composer", {
        detail: { mount: composerMount, mode: "topic", forum_id: forumId }
      }));
    }

    const feedMount = qs("[data-iad-agora-feed]", mount);
    window.dispatchEvent(new CustomEvent("iad:render_feed", {
      detail: { view: "agora", forum_id: forumId, mount: feedMount }
    }));

    const previews = qs('[data-iad-agora-previews]', mount);
    if (previews) {
      previews.innerHTML = `<div class="iad-agora-preview-grid"><div class="iad-agora-preview iad-loading">Loading…</div><div class="iad-agora-preview iad-loading">Loading…</div></div>`;

      const safeAgo = function(ts){
        try { return (timeAgo ? timeAgo(ts) : ''); } catch(e) { return ''; }
      };

      Promise.all([
        API.post('ia_discuss_feed', { tab: 'new_topics', forum_id: forumId, offset: 0, order: 'created' }),
        API.post('ia_discuss_feed', { tab: 'latest_replies', forum_id: forumId, offset: 0 })
      ]).then((all) => {
        const latestTopic = (all && all[0] && all[0].success && all[0].data && all[0].data.items && all[0].data.items[0]) ? all[0].data.items[0] : null;
        const latestReply = (all && all[1] && all[1].success && all[1].data && all[1].data.items && all[1].data.items[0]) ? all[1].data.items[0] : null;

        const renderBox = function(kind, item){
          if (!item) {
            return `<div class="iad-agora-preview"><div class="iad-agora-preview__k">${esc(kind)}</div><div class="iad-agora-preview__empty">No posts yet.</div></div>`;
          }
          const isReply = kind === 'Latest reply';
          const authorName = isReply ? (item.last_poster_display || item.last_poster_username || '') : (item.topic_poster_display || item.topic_poster_username || '');
          const avatar = isReply ? (item.last_poster_avatar_url || '') : (item.topic_poster_avatar_url || '');
          const when = isReply ? safeAgo(item.last_post_time || item.topic_time) : safeAgo(item.topic_time);
          const postId = isReply ? (parseInt(item.last_post_id || '0', 10) || 0) : 0;
          const tid = parseInt(item.topic_id || '0', 10) || 0;
          const title = item.topic_title || '';
          const excerpt = item.excerpt_html || '';

          return `
            <div class="iad-agora-preview">
              <div class="iad-agora-preview__k">${esc(kind)}</div>
              <button type="button" class="iad-agora-preview__open" data-iad-agora-preview-open data-topic-id="${esc(String(tid))}" data-post-id="${esc(String(postId))}">
                <div class="iad-agora-preview__row">
                  <span class="iad-agora-preview__ava" style="${avatar ? `background-image:url(${esc(avatar)})` : ''}"></span>
                  <span class="iad-agora-preview__who">${esc(authorName)}</span>
                  ${when ? `<span class="iad-agora-preview__when">${esc(when)}</span>` : ''}
                </div>
                <div class="iad-agora-preview__title">${esc(title)}</div>
                <div class="iad-agora-preview__excerpt">${excerpt}</div>
              </button>
            </div>
          `;
        };

        previews.innerHTML = `<div class="iad-agora-preview-grid">${renderBox('Latest topic', latestTopic)}${renderBox('Latest reply', latestReply)}</div>`;
      }).catch(() => {
        previews.innerHTML = `<div class="iad-agora-preview-grid"><div class="iad-agora-preview"><div class="iad-agora-preview__k">Latest topic</div><div class="iad-agora-preview__empty">Failed to load.</div></div><div class="iad-agora-preview"><div class="iad-agora-preview__k">Latest reply</div><div class="iad-agora-preview__empty">Failed to load.</div></div></div>`;
      });
    }
  }

  function renderClassicAgora(root, mount, forumId, meta, forumName, cached, init) {
    const name = (meta && meta.forum_name) ? meta.forum_name : (forumName || "");
    const desc = meta ? (meta.forum_desc_html || "") : "";
    const rulesHtml = (meta && meta.forum_rules_html) ? String(meta.forum_rules_html) : '';
    const joined = (meta ? (String(meta.joined||'0')==='1') : (cached ? (String(cached.joined||'0')==='1') : (init ? !!init.joined : false)));
    const bell = (meta ? (String(meta.bell||'0')==='1') : (cached ? (String(cached.bell||'0')==='1') : (init ? !!init.bell : false)));
    const banned = meta && String(meta.banned||'0') === '1';
    const canEditCover = meta && String(meta.can_edit_cover||'0') === '1';
    const topicCount = esc((meta && meta.forum_topics) ? meta.forum_topics : 0);

    const bellSvg = `<svg class="iad-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.2 2.2 0 0 0 2.2-2.2h-4.4A2.2 2.2 0 0 0 12 22Zm7-6.2v-5.2a7 7 0 1 0-14 0v5.2L3.6 17.2c-.6.6-.2 1.6.7 1.6h15.4c.9 0 1.3-1 .7-1.6L19 15.8Z" fill="currentColor"/></svg>`;

    mount.innerHTML = `
      <div class="iad-agorabb-forum">
        <div class="iad-agorabb-headbar">
          <button type="button" class="iad-btn" data-iad-back-agoras>Forum index</button>
          <div class="iad-agorabb-headbar__title">${name ? `agora/${esc(name)}` : `agora/${esc(String(forumId))}`}</div>
          <div class="iad-agorabb-headbar__actions" data-iad-agora-row>
            <button type="button" class="iad-bell ${bell ? 'is-on' : ''}" data-iad-bell="${forumId}" aria-label="Notifications" aria-pressed="${bell ? 'true':'false'}">${bellSvg}</button>
            <button type="button" class="iad-join ${joined ? 'is-joined' : ''}" data-iad-join="${forumId}" ${banned ? 'disabled aria-disabled="true"' : ''}>${banned ? 'Kicked' : (joined ? 'Joined' : 'Join')}</button>
            ${canEditCover ? `<button type="button" class="iad-btn iad-cover-edit" data-iad-cover-edit="${forumId}">Cover</button>` : ''}
            <button type="button" class="iad-btn iad-rules-open" data-iad-rules-open="${forumId}">Rules</button>
          </div>
        </div>

        ${desc ? `<div class="iad-agorabb-forum-desc">${desc}</div>` : ''}
        ${rulesHtml ? `<div class="iad-agorabb-forum-rules"><strong>Forum rules</strong><div>${rulesHtml}</div></div>` : ''}

        <div class="iad-agorabb-tools">
          ${banned ? `<div class="iad-agora-banned">You have been kicked from this Agora. You can view, but cannot post.</div>` : `<div class="iad-agorabb-composer" data-iad-agora-composer></div>`}
          <div class="iad-agorabb-tools__row">
            <div class="iad-agorabb-tools__meta">${topicCount} topics</div>
            <label class="iad-agorabb-sort">Sort
              <select class="iad-select" data-iad-agorabb-sort>
                <option value="activity">Latest post</option>
                <option value="newest">Newest topic</option>
                <option value="most_replies">Most replies</option>
                <option value="least_replies">Least replies</option>
                <option value="oldest">Oldest activity</option>
              </select>
            </label>
          </div>
        </div>

        <div class="iad-agorabb-table">
          <div class="iad-agorabb-table__head">
            <div>Topics</div>
            <div>Replies</div>
            <div>Views</div>
            <div>Last post</div>
          </div>
          <div class="iad-agorabb-table__body" data-iad-agorabb-topic-list><div class="iad-loading">Loading…</div></div>
        </div>
        <div class="iad-agorabb-more" data-iad-agorabb-more></div>
      </div>
    `;

    bindPreviewClicks(mount);

    const composerMount = qs('[data-iad-agora-composer]', mount);
    if (composerMount) {
      window.dispatchEvent(new CustomEvent('iad:mount_composer', {
        detail: { mount: composerMount, mode: 'topic', forum_id: forumId }
      }));
    }

    function topicRowHTML(item) {
      const topicId = parseInt(item.topic_id || '0', 10) || 0;
      const authorId = parseInt(item.topic_poster_id || '0', 10) || 0;
      const authorName = item.topic_poster_display || item.topic_poster_username || ('user#' + authorId);
      const lastId = parseInt(item.last_poster_id || '0', 10) || 0;
      const lastName = item.last_poster_display || item.last_poster_username || ('user#' + lastId);
      const when = item.last_post_time ? esc(timeAgo(item.last_post_time)) : '';
      const postId = parseInt(item.last_post_id || item.first_post_id || '0', 10) || 0;
      return `
        <div class="iad-agorabb-topic" data-topic-id="${topicId}">
          <div class="iad-agorabb-topic__main">
            <button type="button" class="iad-agorabb-topic__title" data-iad-agora-preview-open data-topic-id="${topicId}" data-post-id="0">${esc(item.topic_title || '')}</button>
            <div class="iad-agorabb-topic__meta">
              by <button type="button" class="iad-user-link" data-iad-profile-open data-user-id="${esc(String(authorId))}" data-username="${esc(item.topic_poster_username || '')}">${esc(authorName)}</button>
              ${item.topic_time ? ` • ${esc(timeAgo(item.topic_time))}` : ''}
            </div>
            ${item.excerpt_html ? `<div class="iad-agorabb-topic__excerpt">${item.excerpt_html}</div>` : ''}
          </div>
          <div class="iad-agorabb-topic__count">${esc(String(item.replies || 0))}</div>
          <div class="iad-agorabb-topic__count">${esc(String(item.views || 0))}</div>
          <div class="iad-agorabb-topic__lastpost">
            <button type="button" class="iad-agorabb-topic__lastlink" data-iad-agora-preview-open data-topic-id="${topicId}" data-post-id="${postId}">${esc(item.topic_title || '')}</button>
            <div>by <button type="button" class="iad-user-link" data-iad-profile-open data-user-id="${esc(String(lastId))}" data-username="${esc(item.last_poster_username || '')}">${esc(lastName)}</button></div>
            ${when ? `<div>${when}</div>` : ''}
          </div>
        </div>
      `;
    }

    const list = qs('[data-iad-agorabb-topic-list]', mount);
    const moreWrap = qs('[data-iad-agorabb-more]', mount);
    const sortSel = qs('[data-iad-agorabb-sort]', mount);
    let offset = 0;
    let hasMore = true;
    let loading = false;
    let sort = 'activity';

    function feedOrderValue(mode) {
      if (mode === 'newest') return 'created';
      if (mode === 'most_replies') return 'most_replies';
      if (mode === 'least_replies') return 'least_replies';
      if (mode === 'oldest') return 'oldest';
      return '';
    }

    function renderMore() {
      if (!moreWrap) return;
      if (!hasMore) {
        moreWrap.innerHTML = '';
        return;
      }
      moreWrap.innerHTML = `<button type="button" class="iad-more" data-iad-agorabb-more-btn>${loading ? 'Loading…' : 'Load more topics'}</button>`;
      const btn = moreWrap.querySelector('[data-iad-agorabb-more-btn]');
      if (btn && loading) btn.disabled = true;
      if (btn) btn.onclick = function(){ loadNext(); };
    }

    async function loadNext(reset) {
      if (loading) return;
      loading = true;
      if (reset) {
        offset = 0;
        hasMore = true;
        if (list) list.innerHTML = `<div class="iad-loading">Loading…</div>`;
      }
      renderMore();
      const res = await API.post('ia_discuss_feed', {
        tab: 'new_posts',
        forum_id: forumId,
        offset: offset,
        order: feedOrderValue(sort)
      }).catch(() => null);
      loading = false;
      if (!list) return;
      if (!res || !res.success || !res.data) {
        list.innerHTML = `<div class="iad-empty">Failed to load topics.</div>`;
        hasMore = false;
        renderMore();
        return;
      }
      const items = Array.isArray(res.data.items) ? res.data.items : [];
      hasMore = String(res.data.has_more || '0') === '1';
      offset = parseInt(String(res.data.next_offset || (offset + items.length)), 10) || (offset + items.length);
      if (reset) list.innerHTML = '';
      if (!items.length && reset) {
        list.innerHTML = `<div class="iad-empty">No topics yet.</div>`;
      } else if (items.length) {
        list.insertAdjacentHTML('beforeend', items.map(topicRowHTML).join(''));
      }
      renderMore();
    }

    if (sortSel) {
      sortSel.onchange = function() {
        sort = String(sortSel.value || 'activity');
        loadNext(true);
      };
    }

    loadNext(true);
  }

  function renderAgora(root, forumId, forumName, initial) {
    const mount = qs("[data-iad-view]", root);
    if (!mount) return;

    mount.innerHTML = `<div class="iad-loading">Loading…</div>`;

    let cached = null;
    try {
      const key = 'iad_agora_state_' + String(forumId);
      const raw = window.localStorage ? window.localStorage.getItem(key) : null;
      if (raw) cached = JSON.parse(raw);
    } catch(e) { cached = null; }
    const init = (initial && typeof initial === 'object') ? initial : null;

    maybeHandleInvite(root, forumId, forumName, initial).then((handled) => {
      if (handled) return;
      loadMeta(forumId).then((meta) => {
        if (isAgoraBBMode(root)) {
          renderClassicAgora(root, mount, forumId, meta, forumName, cached, init);
        } else {
          renderAtriumAgora(root, mount, forumId, meta, forumName, cached, init);
        }
      });
    });
  }

  window.IA_DISCUSS_UI_AGORA = { renderAgora };
})();
