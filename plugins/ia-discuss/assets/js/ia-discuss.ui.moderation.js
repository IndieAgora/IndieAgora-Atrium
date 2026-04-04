(function () {
  "use strict";
  const CORE = window.IA_DISCUSS_CORE || {};
  const qs = CORE.qs || function(sel, root){ try{return (root||document).querySelector(sel);}catch(e){return null;} };
  const qsa = CORE.qsa || function(sel, root){ try{return Array.from((root||document).querySelectorAll(sel));}catch(e){return [];} };
  const esc = CORE.esc || function(s){ return String(s||''); };
  const API = window.IA_DISCUSS_API;

  let cache = { loaded: false, global_admin: 0, items: [] };

  function ensureModerationModal() {
    let m = document.querySelector('[data-iad-mod-modal]');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'iad-modal iad-modal--full';
    m.setAttribute('data-iad-mod-modal', '1');
    m.hidden = true;
    m.innerHTML = `
      <div class="iad-modal-backdrop" data-iad-mod-close></div>
      <div class="iad-modal-sheet iad-modal-sheet--full" role="dialog" aria-modal="true">
        <div class="iad-modal-top">
          <button type="button" class="iad-x" data-iad-mod-close aria-label="Close">✕</button>
          <div class="iad-modal-title" data-iad-mod-title>Agora settings</div>
        </div>
        <div class="iad-modal-body" data-iad-mod-body></div>
      </div>
    `;
    document.body.appendChild(m);

    // The modal is appended to <body>, outside the Discuss root.
    // Delegate all action clicks from the modal itself, otherwise the buttons
    // will not be caught by the root-level event handler and will appear to do nothing.
    m.addEventListener('click', (e) => {
      const t = e.target;
      if (!t || !t.closest) return;

      const saveBtn = t.closest('[data-iad-save-field]');
      if (saveBtn) {
        e.preventDefault();
        const key = saveBtn.getAttribute('data-iad-save-field') || '';
        const form = saveBtn.closest('[data-iad-mod-form]');
        if (form && key) saveSettingsInline(form, key);
        return;
      }

      const setUrlBtn = t.closest('[data-iad-cover-url-set]');
      if (setUrlBtn) {
        e.preventDefault();
        const fid = parseInt(setUrlBtn.getAttribute('data-forum-id') || '0', 10) || 0;
        if (!fid) return;
        const form = qs(`[data-iad-mod-form][data-forum-id="${fid}"]`, m);
        const inp = qs(`[data-iad-cover-url][data-forum-id="${fid}"]`, m);
        const url = inp ? String(inp.value || '') : '';
        if (form) setInlineStatus(form, 'cover_url', 'Saving…', false);
        API.post('ia_discuss_cover_set', { forum_id: fid, cover_url: url }).then((res) => {
          if (!res || !res.success) {
            const msg = res && res.data && res.data.message ? String(res.data.message) : 'Save failed';
            if (form) setInlineStatus(form, 'cover_url', msg, true);
            return;
          }
          if (form) setInlineStatus(form, 'cover_url', 'Saved', false);
          openSettings(fid);
        });
        return;
      }

      const clearUrlBtn = t.closest('[data-iad-cover-url-clear]');
      if (clearUrlBtn) {
        e.preventDefault();
        const fid = parseInt(clearUrlBtn.getAttribute('data-forum-id') || '0', 10) || 0;
        if (!fid) return;
        const form = qs(`[data-iad-mod-form][data-forum-id="${fid}"]`, m);
        if (form) setInlineStatus(form, 'cover_url', 'Saving…', false);
        API.post('ia_discuss_cover_set', { forum_id: fid, cover_url: '' }).then((res) => {
          if (!res || !res.success) {
            const msg = res && res.data && res.data.message ? String(res.data.message) : 'Save failed';
            if (form) setInlineStatus(form, 'cover_url', msg, true);
            return;
          }
          if (form) setInlineStatus(form, 'cover_url', 'Saved', false);
          openSettings(fid);
        });
        return;
      }

      const unban = t.closest('[data-iad-unban]');
      if (unban) {
        e.preventDefault();
        const fid = parseInt(unban.getAttribute('data-forum-id') || '0', 10) || 0;
        const uid = parseInt(unban.getAttribute('data-user-id') || '0', 10) || 0;
        unbanUser(fid, uid, unban);
        return;
      }

      const del = t.closest('[data-iad-delete-agora]');
      if (del) {
        e.preventDefault();
        const fid = parseInt(del.getAttribute('data-forum-id') || '0', 10) || 0;
        if (fid) deleteAgora(fid);
        return;
      }

      const more = t.closest('[data-iad-kicks-more]');
      if (more) {
        e.preventDefault();
        const fid = parseInt(more.getAttribute('data-forum-id') || '0', 10) || 0;
        const form = fid ? qs(`[data-iad-mod-form][data-forum-id="${fid}"]`, m) : null;
        if (form) {
          const expanded = form.getAttribute('data-iad-kicks-expanded') === '1';
          form.setAttribute('data-iad-kicks-expanded', expanded ? '0' : '1');
          applyKickFilter(form);
        }
        return;
      }

      const privacyBtn = t.closest('[data-iad-privacy-toggle]');
      if (privacyBtn) {
        e.preventDefault();
        const fid = parseInt(privacyBtn.getAttribute('data-forum-id') || '0', 10) || 0;
        const next = String(privacyBtn.getAttribute('data-next-private') || '0') === '1' ? 1 : 0;
        API.post('ia_discuss_agora_privacy_set', { forum_id: fid, is_private: next }).then((res) => {
          if (!res || !res.success) return;
          openSettings(fid);
        });
        return;
      }

      const inviteBtn = t.closest('[data-iad-invite-send]');
      if (inviteBtn) {
        e.preventDefault();
        const fid = parseInt(inviteBtn.getAttribute('data-forum-id') || '0', 10) || 0;
        const form = fid ? qs(`[data-iad-mod-form][data-forum-id="${fid}"]`, m) : null;
        const input = fid ? qs(`[data-iad-invite-search][data-forum-id="${fid}"]`, m) : null;
        const picked = form ? qs('[data-iad-invite-picked-id]', form) : null;
        let uid = picked ? (parseInt(String(picked.value || '0'), 10) || 0) : 0;
        if (!uid && form) {
          const first = qs('[data-iad-invite-pick]', form);
          uid = first ? (parseInt(String(first.getAttribute('data-user-id') || '0'), 10) || 0) : 0;
        }
        if (!fid || !uid) {
          if (form) setInlineStatus(form, 'invite', 'Choose a user first', true);
          return;
        }
        if (form) setInlineStatus(form, 'invite', 'Sending…', false);
        API.post('ia_discuss_agora_invite_user', { forum_id: fid, user_id: uid }).then((res) => {
          if (!res || !res.success) {
            const msg = res && res.data && res.data.message ? String(res.data.message) : 'Invite failed';
            if (form) setInlineStatus(form, 'invite', msg, true);
            return;
          }
          openSettings(fid);
        }).catch(() => {
          if (form) setInlineStatus(form, 'invite', 'Invite failed', true);
        });
        return;
      }
    });

    // Live search/filter for kicked users inside the modal.
    m.addEventListener('input', (e) => {
      const t = e.target;
      if (!t || !t.matches) return;
      if (t.matches('[data-iad-kick-search]')) {
        const form = t.closest('[data-iad-mod-form]');
        if (!form) return;
        form.setAttribute('data-iad-kicks-expanded', '0');
        applyKickFilter(form);
        return;
      }
      if (t.matches('[data-iad-invite-search]')) {
        runInviteSearch(t);
      }
    });

    m.addEventListener('click', (e) => {
      const t = e.target;
      if (t && t.closest && t.closest('[data-iad-mod-close]')) {
        hideModal();
      }
    });
    document.addEventListener('keydown', (e) => {
      if (!m.hidden && e.key === 'Escape') hideModal();
    });
    return m;
  }

  function showModal(title, html) {
    const m = ensureModerationModal();
    const ttl = qs('[data-iad-mod-title]', m);
    const body = qs('[data-iad-mod-body]', m);
    if (ttl) ttl.textContent = title || 'Agora settings';
    if (body) body.innerHTML = html || '';
    m.hidden = false;
    document.documentElement.classList.add('iad-modal-open');
  }

  function hideModal() {
    const m = ensureModerationModal();
    m.hidden = true;
    document.documentElement.classList.remove('iad-modal-open');
  }

  async function loadMyModeration(root) {
    // Back-compat: some callers didn't pass root. Resolve it here so we can set
    // data-iad-can-moderate reliably.
    if (!root) {
      try { root = qs('[data-ia-discuss-root]'); } catch (e) {}
    }
    if (!API || !window.IA_DISCUSS || String(window.IA_DISCUSS.loggedIn || '0') !== '1') {
      return { loaded: true, global_admin: 0, items: [], count: 0 };
    }
    if (cache.loaded) {
      if (typeof cache.count === 'undefined') cache.count = (cache.items && cache.items.length) ? cache.items.length : 0;
      return cache;
    }

    const res = await API.post('ia_discuss_my_moderation', {});
    if (!res || !res.success) {
      cache = { loaded: true, global_admin: 0, items: [], count: 0 };
      return cache;
    }
    const d = res.data || {};
    cache = {
      loaded: true,
      global_admin: parseInt(String(d.global_admin || '0'), 10) || 0,
      items: Array.isArray(d.items) ? d.items : []
    };

    // Router expects count in some builds.
    cache.count = (cache.items && cache.items.length) ? cache.items.length : 0;

    try {
      if (root) root.setAttribute('data-iad-can-moderate', (cache.items.length || cache.global_admin) ? '1' : '0');
      if (window.IA_DISCUSS_UI_SHELL && typeof window.IA_DISCUSS_UI_SHELL.setActiveTab === 'function') {
        // Refresh tab row visibility WITHOUT stomping the current logical view.
        // On Agora deep-links / refresh, `iad_view` is often empty, and forcing
        // setActiveTab('new') hides the Moderation pill.
        const u = new URL(window.location.href);
        const forumId = parseInt(String(u.searchParams.get('iad_forum') || '0'), 10) || 0;
        const view = String(u.searchParams.get('iad_view') || '');
        const cur = root ? String(root.getAttribute('data-iad-current-view') || '') : '';

        if (cur === 'agora' || forumId > 0) {
          // Keep the Agoras tab highlighted, but set logical view to 'agora'.
          window.IA_DISCUSS_UI_SHELL.setActiveTab('agoras', 'agora');
        } else {
          window.IA_DISCUSS_UI_SHELL.setActiveTab(view || 'new');
        }
      }
    } catch (e) {}

    return cache;
  }

  function rowHTML(it) {
    const id = parseInt(String(it.forum_id || '0'), 10) || 0;
    const name = String(it.forum_name || '');
    const desc = String(it.forum_desc_html || '');
    const cover = String(it.cover_url || '');
    return `
      <div class="iad-mod-row" data-iad-mod-row data-forum-id="${id}">
        <button type="button" class="iad-mod-row__open">
          <div class="iad-mod-row__thumb" ${cover ? `style="background-image:url(${esc(cover)})"` : ''}></div>
          <div class="iad-mod-row__main">
            <div class="iad-mod-row__name">${esc(name)}</div>
            ${desc ? `<div class="iad-mod-row__desc">${desc}</div>` : ``}
          </div>
        </button>
        <div class="iad-mod-row__actions">
          <button type="button" class="iad-btn" data-iad-mod-edit="${id}">Settings</button>
        </div>
      </div>
    `;
  }

  function renderModerationView(root) {
    const mount = qs('[data-iad-view]', root);
    if (!mount) return;

    const items = cache.items || [];

    mount.innerHTML = `
      <div class="iad-moderation">
        <div class="iad-moderation-head">
          <div class="iad-h1">Moderation</div>
          <div class="iad-muted">Agoras you can manage.</div>
        </div>
        <div class="iad-mod-list">
          ${items.length ? items.map(rowHTML).join('') : `<div class="iad-empty">No agoras to moderate.</div>`}
        </div>
      </div>
    `;
  }

  function settingsFormHTML(d) {
    const fid = parseInt(String(d.forum_id || '0'), 10) || 0;
    const nm = String(d.forum_name || '');
    const desc = String(d.forum_desc || '');
    const rules = String(d.forum_rules || '');
    const cover = String(d.cover_url || '');
    const kicked = Array.isArray(d.kicked) ? d.kicked : [];
    const invites = Array.isArray(d.invites) ? d.invites : [];
    const isPrivate = String(d.is_private || '0') === '1';

    const kickedRowsHTML = kicked.length ? kicked.map((u) => {
      const uid = parseInt(String(u.user_id || '0'), 10) || 0;
      const unRaw = String(u.username || ('user#' + uid));
      const un = esc(unRaw);
      const ukey = esc(unRaw.toLowerCase());
      return `
        <div class="iad-kick-row" data-iad-kick-row data-username="${ukey}">
          <div class="iad-kick-name">${un}</div>
          <button type="button" class="iad-btn" data-iad-unban data-user-id="${uid}" data-forum-id="${fid}">Re-add</button>
        </div>
      `;
    }).join('') : '';

    // Always render the search UI so moderators can quickly filter/unban when there are entries,
    // and so the layout doesn't "change" when the first user is kicked.

    const inviteRowsHTML = invites.length ? invites.map((u) => {
      const name = esc(String(u.display || u.username || ('user#' + String(u.user_id || '0'))));
      const status = esc(String(u.status || 'pending'));
      return `<div class="iad-kick-row"><div class="iad-kick-name">${name}</div><div class="iad-muted">${status}</div></div>`;
    }).join('') : '<div class="iad-empty">No invites yet.</div>';

    const privacyHTML = `
      <div class="iad-field-with-save">
        <button type="button" class="iad-btn iad-btn-primary" data-iad-privacy-toggle data-forum-id="${fid}" data-next-private="${isPrivate ? '0' : '1'}">${isPrivate ? 'Set public' : 'Set private'}</button>
        <span class="iad-save-status">${isPrivate ? 'Private Agora' : 'Public Agora'}</span>
      </div>
      <div class="iad-help">Private Agoras require an invite to view or interact. Moderators always retain access.</div>
    `;

    const inviteHTML = `
      <div class="iad-kicks-tools">
        <input class="iad-input" type="search" inputmode="search" placeholder="Search users to invite…" data-iad-invite-search data-forum-id="${fid}" />
        <input type="hidden" value="0" data-iad-invite-picked-id />
        <button type="button" class="iad-btn iad-btn-primary" data-iad-invite-send data-forum-id="${fid}">Invite</button>
      </div>
      <span class="iad-save-status" data-iad-save-status="invite"></span>
      <div class="iad-kicks" data-iad-invite-results></div>
      <div class="iad-help">The invite appears in Notify and opens this Agora with an accept or decline prompt.</div>
      <div class="iad-kicks">${inviteRowsHTML}</div>
    `;

    const kickedHTML = `
      <div class="iad-kicks-tools">
        <input class="iad-input" type="search" inputmode="search" placeholder="Search kicked users…" data-iad-kick-search />
        <span class="iad-kicks-count" data-iad-kicks-count></span>
      </div>
      <div class="iad-kicks" data-iad-kicks-list>${kickedRowsHTML}</div>
      <div class="iad-empty iad-kicks-empty" data-iad-kicks-empty>${kicked.length ? '' : 'No kicked users.'}</div>
      <div class="iad-kicks-actions" ${kicked.length > 5 ? '' : 'hidden'}>
        <button type="button" class="iad-btn" data-iad-kicks-more data-forum-id="${fid}">Show more</button>
      </div>
    `;

    return `
      <form class="iad-mod-form" data-iad-mod-form data-forum-id="${fid}" onsubmit="return false;">
        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Agora name</label>
          <div class="iad-field-with-save">
            <input class="iad-input" name="forum_name" value="${esc(nm)}" maxlength="120" />
            <button type="button" class="iad-btn iad-btn-primary" data-iad-save-field="forum_name" data-forum-id="${fid}">Save</button>
            <span class="iad-save-status" data-iad-save-status="forum_name"></span>
          </div>
          <div class="iad-help">Updates the name as displayed across the site.</div>
          </div>
        </div>

        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Description</label>
          <div class="iad-field-with-save iad-field-with-save--top">
            <textarea class="iad-textarea" name="forum_desc" rows="5" maxlength="8000">${esc(desc)}</textarea>
            <div class="iad-field-with-save__side">
              <button type="button" class="iad-btn iad-btn-primary" data-iad-save-field="forum_desc" data-forum-id="${fid}">Save</button>
              <span class="iad-save-status" data-iad-save-status="forum_desc"></span>
            </div>
          </div>
          </div>
        </div>

        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Rules (BBCode allowed)</label>
          <div class="iad-field-with-save iad-field-with-save--top">
            <textarea class="iad-textarea" name="forum_rules" rows="7" maxlength="12000">${esc(rules)}</textarea>
            <div class="iad-field-with-save__side">
              <button type="button" class="iad-btn iad-btn-primary" data-iad-save-field="forum_rules" data-forum-id="${fid}">Save</button>
              <span class="iad-save-status" data-iad-save-status="forum_rules"></span>
            </div>
          </div>
          <div class="iad-help">Creates/updates the Rules pill on the Agora page.</div>
          </div>
        </div>

        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Privacy</label>
          ${privacyHTML}
          </div>
        </div>

        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Invite users</label>
          ${inviteHTML}
          </div>
        </div>

        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Cover photo</label>
          <div class="iad-cover-row">
            <div class="iad-cover-thumb" ${cover ? `style="background-image:url(${esc(cover)})"` : ''}></div>
            <div class="iad-cover-inputs">
              <input type="file" accept="image/*" data-iad-cover-file data-forum-id="${fid}" />
              <span class="iad-save-status" data-iad-save-status="cover"></span>
            </div>
          </div>
          <div class="iad-cover-url">
            <input class="iad-input" type="url" inputmode="url" placeholder="https://… (optional)" value="${esc(cover)}" data-iad-cover-url data-forum-id="${fid}" />
            <button type="button" class="iad-btn" data-iad-cover-url-set data-forum-id="${fid}">Set URL</button>
            <button type="button" class="iad-btn" data-iad-cover-url-clear data-forum-id="${fid}">Clear</button>
            <span class="iad-save-status" data-iad-save-status="cover_url"></span>
          </div>
          <div class="iad-help">Upload an image, or set a direct URL. This cover is shown in the Agora banner.</div>
          </div>
        </div>

        <div class="iad-mod-card">
          <div class="iad-form-row">
          <label class="iad-label">Kicked users</label>
          ${kickedHTML}
          </div>
        </div>

        <div class="iad-mod-card iad-mod-card--danger">
          <div class="iad-form-row">
          <label class="iad-label">Danger zone</label>
          <div class="iad-field-with-save">
            <button type="button" class="iad-btn iad-btn-danger" data-iad-delete-agora data-forum-id="${fid}">Delete Agora</button>
            <span class="iad-save-status" data-iad-save-status="delete"></span>
          </div>
          <div class="iad-help">Deletes the Agora and its topics/posts.</div>
          </div>
        </div>
      </form>
    `;
  }

  function applyKickFilter(form) {
    if (!form) return;
    const qEl = qs('[data-iad-kick-search]', form);
    const list = qs('[data-iad-kicks-list]', form);
    const countEl = qs('[data-iad-kicks-count]', form);
    const emptyEl = qs('[data-iad-kicks-empty]', form);
    const moreWrap = qs('.iad-kicks-actions', form);
    const moreBtn = moreWrap ? qs('[data-iad-kicks-more]', moreWrap) : null;
    const q = qEl ? String(qEl.value || '').trim().toLowerCase() : '';
    const expanded = form.getAttribute('data-iad-kicks-expanded') === '1';
    const rows = list ? qsa('[data-iad-kick-row]', list) : [];

    const matches = [];
    rows.forEach((r) => {
      const u = String(r.getAttribute('data-username') || '');
      const ok = !q || u.indexOf(q) !== -1;
      r.hidden = !ok;
      if (ok) matches.push(r);
    });

    // Limit to 5 visible unless expanded.
    const limit = 5;
    let shown = 0;
    matches.forEach((r, i) => {
      const hide = (!expanded && i >= limit);
      if (!r.hidden) r.hidden = hide;
      if (!hide) shown++;
    });

    if (countEl) {
      if (!rows.length) countEl.textContent = '';
      else if (!matches.length) countEl.textContent = 'No matches';
      else if (matches.length <= limit) countEl.textContent = `${matches.length} user${matches.length === 1 ? '' : 's'}`;
      else countEl.textContent = `${shown} of ${matches.length}`;
    }

    // Empty state message lives inline under the tools.
    if (emptyEl) {
      if (!rows.length) {
        emptyEl.textContent = 'No kicked users.';
        emptyEl.hidden = false;
      } else if (!matches.length) {
        emptyEl.textContent = 'No matches.';
        emptyEl.hidden = false;
      } else {
        emptyEl.textContent = '';
        emptyEl.hidden = true;
      }
    }

    if (moreWrap && moreBtn) {
      const needMore = matches.length > limit;
      moreWrap.hidden = !needMore;
      if (needMore) moreBtn.textContent = expanded ? 'Show less' : 'Show more';
    }
  }

  async function openSettings(forumId) {
    const res = await API.post('ia_discuss_agora_settings_get', { forum_id: forumId });
    if (!res || !res.success) {
      showModal('Agora settings', `<div class="iad-empty">Failed to load settings.</div>`);
      return;
    }
    const d = res.data || {};
    showModal('Agora settings', settingsFormHTML(d));
    try {
      const m = ensureModerationModal();
      const form = qs(`[data-iad-mod-form][data-forum-id="${parseInt(String(forumId||0),10)||0}"]`, m);
      if (form) {
        if (!form.getAttribute('data-iad-kicks-expanded')) form.setAttribute('data-iad-kicks-expanded', '0');
        applyKickFilter(form);
      }
    } catch (e) {}
  }

  function setInlineStatus(form, key, txt, isErr) {
    if (!form || !key) return;
    const el = qs(`[data-iad-save-status="${key}"]`, form);
    if (!el) return;
    el.textContent = txt || '';
    el.classList.toggle('is-bad', !!isErr);
    el.classList.toggle('is-ok', !isErr && !!txt);
  }

  async function saveSettingsInline(form, key) {
    const fid = parseInt(form.getAttribute('data-forum-id') || '0', 10) || 0;
    if (!fid) return;

    setInlineStatus(form, key, 'Saving…', false);

    const fd = new FormData(form);
    const v = fd.get(key);
    const payload = {
      forum_id: fid,
      field: key,
      value: (v === null || typeof v === 'undefined') ? '' : String(v)
    };

    const res = await API.post('ia_discuss_agora_setting_save_one', payload);
    if (!res || !res.success) {
      const msg = res && res.data && res.data.message ? String(res.data.message) : 'Save failed';
      setInlineStatus(form, key, msg, true);
      return;
    }

    // refresh cached list entry
    try {
      const d = res.data || {};
      const idx = (cache.items || []).findIndex((x) => parseInt(String(x.forum_id||0),10) === fid);
      if (idx >= 0) {
        cache.items[idx].forum_name = d.forum_name || cache.items[idx].forum_name;
        cache.items[idx].forum_desc_html = d.forum_desc_html || cache.items[idx].forum_desc_html;
        cache.items[idx].has_rules = d.forum_rules_html ? 1 : 0;
      }

      // If we're currently viewing this Agora, update the in-view Rules source
      // so the Rules pill reflects the new text without requiring a hard refresh.
      if (key === 'forum_rules' && typeof d.forum_rules_html !== 'undefined') {
        const root = qs('[data-ia-discuss-root]');
        const mount = root ? qs('[data-iad-view]', root) : null;
        const src = mount ? qs('[data-iad-rules-src]', mount) : null;
        if (src) src.innerHTML = String(d.forum_rules_html || '');
      }
    } catch (e) {}

    setInlineStatus(form, key, 'Saved', false);
  }

  async function deleteAgora(forumId) {
    const ok = window.confirm('Delete this Agora? This will remove its topics and posts.');
    if (!ok) return;
    const res = await API.post('ia_discuss_agora_delete', { forum_id: forumId });
    if (!res || !res.success) {
      alert('Delete failed.');
      return;
    }
    // remove from list
    cache.items = (cache.items || []).filter((x) => parseInt(String(x.forum_id||0),10) !== forumId);
    hideModal();
    // Redirect back to the main Agora list (never show the "Agoras you can edit" moderation list).
    try { window.dispatchEvent(new CustomEvent('iad:go_agoras')); } catch (e) {}
    flash('Agora successfully deleted');
  }

  function flash(message) {
    const root = qs('[data-ia-discuss-root]');
    if (!root) { try { alert(message); } catch (e) {} return; }
    let el = qs('[data-iad-flash]', root);
    if (!el) {
      el = document.createElement('div');
      el.className = 'iad-flash';
      el.setAttribute('data-iad-flash', '1');
      root.appendChild(el);
    }
    el.textContent = String(message || '');
    el.classList.add('is-on');
    try { clearTimeout(el._t); } catch (e) {}
    el._t = setTimeout(() => { try { el.classList.remove('is-on'); } catch (e) {} }, 2200);
  }

  async function unbanUser(forumId, userId, btn) {
    if (!forumId || !userId) return;
    if (btn) { btn.disabled = true; btn.textContent = 'Working…'; }
    const res = await API.post('ia_discuss_agora_unban', { forum_id: forumId, user_id: userId });
    if (!res || !res.success) {
      if (btn) { btn.disabled = false; btn.textContent = 'Re-add'; }
      alert('Failed.');
      return;
    }
    // refresh settings modal
    openSettings(forumId);
  }


  async function runInviteSearch(input) {
    const q = String(input && input.value || '').trim();
    const form = input ? input.closest('[data-iad-mod-form]') : null;
    const out = form ? qs('[data-iad-invite-results]', form) : null;
    const picked = form ? qs('[data-iad-invite-picked-id]', form) : null;
    if (!out || !picked) return;
    picked.value = '0';
    if (form) setInlineStatus(form, 'invite', '', false);
    if (q.length < 2) { out.innerHTML = ''; return; }
    const res = await API.post('ia_discuss_search_suggest', { q: q });
    const users = res && res.success && res.data && Array.isArray(res.data.users) ? res.data.users : [];
    out.innerHTML = users.map((u, idx) => {
      const uid = parseInt(String(u.user_id || '0'), 10) || 0;
      const display = esc(String(u.display || u.username || 'User'));
      const username = esc(String(u.username || ''));
      const avatar = esc(String(u.avatar || u.avatar_url || ''));
      return `<button type="button" class="iad-invite-result-row" data-iad-invite-pick data-user-id="${uid}" ${idx === 0 ? 'data-iad-invite-first="1"' : ''}><span class="iad-invite-result-avatar"${avatar ? ` style="background-image:url(${avatar})"` : ''}></span><span class="iad-invite-result-text"><span class="iad-invite-result-name">${display}</span>${username ? `<span class="iad-invite-result-username">@${username}</span>` : ''}</span></button>`;
    }).join('') || '<div class="iad-empty">No users found.</div>';
    qsa('[data-iad-invite-pick]', out).forEach((btn) => {
      btn.addEventListener('click', function(){
        picked.value = String(btn.getAttribute('data-user-id') || '0');
        const nm = qs('.iad-invite-result-name', btn);
        input.value = nm ? nm.textContent.trim() : btn.textContent.trim();
        out.innerHTML = '';
      }, { once: true });
    });
  }

  async function uploadCover(forumId, file, inputEl) {
    if (!file) return;
    const form = qs(`[data-iad-mod-form][data-forum-id="${forumId}"]`);
    if (form) setInlineStatus(form, 'cover', 'Uploading…', false);
    // Re-use upload module then cover set.
    try {
      const up = await API.upload('ia_discuss_upload', file);
      if (!up || !up.success || !up.data || !up.data.url) throw new Error('upload failed');
      const url = String(up.data.url);
      const res = await API.post('ia_discuss_cover_set', { forum_id: forumId, cover_url: url });
      if (!res || !res.success) throw new Error('cover set failed');
      if (form) setInlineStatus(form, 'cover', 'Saved', false);
      // refresh modal
      openSettings(forumId);
    } catch (e) {
      if (form) setInlineStatus(form, 'cover', 'Upload failed', true);
      try { if (inputEl) inputEl.value = ''; } catch (e2) {}
    }
  }

  function bind(root) {
    // Ensure permissions loaded and button visibility updated.
    loadMyModeration(root);

    // Moderation pill
    root.addEventListener('click', async (e) => {
      const t = e.target;
      const btn = t && t.closest ? t.closest('[data-iad-moderation]') : null;
      if (btn) {
        e.preventDefault();
        try { e.stopPropagation(); } catch (e2) {}
        try { if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); } catch (e3) {}
        // Per requirements: clicking Moderation opens settings ONLY for the
        // Agora currently being viewed (no long moderation list).
        await loadMyModeration(root);

        const u = new URL(window.location.href);
        const fid = parseInt(u.searchParams.get('iad_forum') || '0', 10) || 0;
        if (!fid) return;

        const isGlobal = !!cache.global_admin;
        const isLocal = Array.isArray(cache.items) && cache.items.some((x) => {
          return (parseInt(String(x.forum_id || '0'), 10) || 0) === fid;
        });
        if (!isGlobal && !isLocal) return;

        openSettings(fid);
      }
    });

    // View interactions (root-level). Note: modal actions are delegated from the modal.
    root.addEventListener('click', (e) => {
      const t = e.target;
      const open = t && t.closest ? t.closest('[data-iad-mod-edit]') : null;
      if (open) {
        e.preventDefault();
        const fid = parseInt(open.getAttribute('data-iad-mod-edit') || '0', 10) || 0;
        if (fid) openSettings(fid);
        return;
      }
    });

    // Cover file input
    document.addEventListener('change', (e) => {
      const inp = e.target;
      if (!inp || !inp.matches || !inp.matches('[data-iad-cover-file]')) return;
      const fid = parseInt(inp.getAttribute('data-forum-id') || '0', 10) || 0;
      const file = (inp.files && inp.files[0]) ? inp.files[0] : null;
      uploadCover(fid, file, inp);
    }, true);
  }

  window.IA_DISCUSS_UI_MODERATION = {
    bind,
    renderModerationView,
    loadMyModeration
  };
})();
